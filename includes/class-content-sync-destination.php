<?php
require_once ABSPATH . "wp-admin" . '/includes/image.php';
require_once ABSPATH . "wp-admin" . '/includes/file.php';
require_once ABSPATH . "wp-admin" . '/includes/media.php';

class ContentSyncDestination {
  protected static $instance = null;
  protected $syncSuccess = false;
  protected $syncError = false;

  /**
   * Retrieves the single instance of the class.
   *
   * @return ContentSyncDestination The single instance of the class.
   * @since 1.0
   */
  public static function instance() {
    if (null === self::$instance) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  /**
   * Class constructor.
   *
   * Hooks into the `rest_api_init`, `admin_menu`, `admin_init`, and `admin_notices` actions to add the Content Sync Destination
   * settings page, register the REST API routes, handle the form submission for syncing content, and display a success or error notice.
   *
   * Additionally, if the `content_sync_allow_local_sync` option is enabled, the `allowLocalSync` filter is added to the `http_request_host_is_external` filter
   * to allow the API to make requests to the same host.
   *
   * @since 1.0
   */
  private function __construct() {
    add_action('rest_api_init', [$this, 'registerRoutes']);
    add_action('admin_menu', [$this, 'addAdminMenu']);
    add_action('admin_init', [$this, 'registerSettings']);
    add_action('admin_notices', [$this, 'showNotices']);

    // Apply the filter if the option is enabled
    if (get_option('content_sync_allow_local_sync', false)) {
      add_filter('http_request_host_is_external', [$this, 'allowLocalSync'], 10, 3);
    }
  }

  /**
   * Registers the REST API route for syncing content.
   *
   * The route is `/wp-json/content-sync/v1/sync` and accepts a POST request with a JSON payload
   * containing the content to be synced.
   *
   * The `syncContent` method is called when the route is accessed, and is responsible for validating
   * the JSON payload and creating the content on the site.
   *
   * The `permission_callback` is used to restrict access to the route to only users who have the
   * `manage_options` capability.
   *
   * @since 1.0
   */
  public function registerRoutes() {
    register_rest_route('content-sync/v1', '/sync', [
      'methods' => 'POST',
      'callback' => [$this, 'syncContent'],
      'permission_callback' => function () {
        return current_user_can('manage_options');
      },
    ]);
  }

  /**
   * Handles the sync request and performs the actual content sync.
   *
   * This method is called when the `/wp-json/content-sync/v1/sync` route is accessed.
   *
   * The method expects a JSON payload containing the content to be synced,
   * which is validated using the `validateJsonData` method.
   *
   * The method then iterates over the validated data and creates the content on the site
   * using the `wp_insert_post` and `add_post_meta` functions.
   *
   * The method also syncs the attachments for each post using the `syncAttachment` method.
   *
   * If the sync is successful, the method returns a 200 response with a message indicating
   * that the content was synced successfully.
   *
   * If the sync fails, the method returns a 400 response with an error message.
   *
   * @since 1.0
   *
   * @param WP_REST_Request $request The request object containing the JSON payload.
   *
   * @return WP_REST_Response The response object containing the result of the sync.
   */
  public function syncContent(WP_REST_Request $request) {
    $data = $request->get_json_params();

    error_log('Received data: ' . print_r($data, true));

    // Validate the data structure
    if (!$this->validateJsonData($data)) {
      error_log('Invalid data structure.');
      return new WP_Error('invalid_data', 'Invalid data structure.', ['status' => 400]);
    }

    foreach ($data as $item) {
      $postId = wp_insert_post([
        'post_type' => $item['postType'],
        'post_title' => sanitize_text_field($item['postTitle']),
        'post_content' => wp_kses_post($item['postContent']),
        'post_status' => 'publish',
      ]);

      foreach ($item['postMeta'] as $key => $values) {
        foreach ($values as $value) {
          add_post_meta($postId, sanitize_text_field($key), sanitize_text_field($value));
        }
      }

      $attachment_urls = [];
      foreach ($item['attachments'] as $attachment) {
        $new_attachment_id = $this->syncAttachment($attachment, $postId);
        if (!is_wp_error($new_attachment_id)) {
          $attachment_urls[$attachment['url']] = wp_get_attachment_url($new_attachment_id);
        }
      }

      // Update post content with new attachment URLs
      $post_content = $item['postContent'];
      foreach ($attachment_urls as $old_url => $new_url) {
        $post_content = str_replace($old_url, $new_url, $post_content);
      }
      wp_update_post([
        'ID' => $postId,
        'post_content' => $post_content,
      ]);
    }

    error_log('Content synced successfully.');
    return new WP_REST_Response(['message' => 'Content synced successfully'], 200);
  }

  /**
   * Syncs a single attachment from the source site to the destination site.
   *
   * Downloads the attachment from the source site, and then uploads it to the destination site.
   * Sets the title, description, and caption of the attachment based on the data from the source site.
   * Sets the alt text of the attachment based on the data from the source site.
   *
   * @param array $attachment The data from the source site for the attachment.
   * @param int $postId The ID of the post the attachment is associated with.
   *
   * @return int|WP_Error The ID of the new attachment, or a WP_Error on failure.
   */
  private function syncAttachment($attachment, $postId) {
    $url = esc_url_raw($attachment['url']);
    error_log('Downloading attachment from URL: ' . $url);

    $file_array = [
      'name' => basename($url),
      'tmp_name' => $this->downloadURL($url),
    ];

    if (is_wp_error($file_array['tmp_name'])) {
      error_log('Error downloading attachment: ' . $file_array['tmp_name']->get_error_message());
      return $file_array['tmp_name'];
    }

    $attachment_id = media_handle_sideload($file_array, $postId, $attachment['title'], [
      'post_title' => $attachment['title'],
      'post_content' => $attachment['description'],
      'post_excerpt' => $attachment['caption'],
    ]);

    if (is_wp_error($attachment_id)) {
      error_log('Error uploading attachment: ' . $attachment_id->get_error_message());
      return $attachment_id;
    }

    update_post_meta($attachment_id, '_wp_attachment_image_alt', $attachment['alt']);

    return $attachment_id;
  }

  /**
   * Downloads a file from the given URL and saves it to the uploads directory.
   *
   * If the download fails, the method returns a WP_Error object.
   * If the copy fails, the method returns a WP_Error object.
   *
   * @param string $url The URL of the file to download.
   *
   * @return string|WP_Error The path to the downloaded file, or a WP_Error on failure.
   */
  private function downloadURL($url) {
    $upload_dir = wp_upload_dir();
    $temp_file = download_url($url);

    if (is_wp_error($temp_file)) {
      return $temp_file;
    }

    $file_name = basename($url);
    $file_path = $upload_dir['path'] . '/' . $file_name;

    if (!copy($temp_file, $file_path)) {
      unlink($temp_file);
      return new WP_Error('copy_failed', 'Failed to copy the downloaded file.');
    }

    unlink($temp_file);
    return $file_path;
  }

  /**
   * Validates the given JSON data to make sure it has the required structure.
   *
   * The required structure is an array of objects, each containing the following properties:
   * - postType: The post type of the content to be synced.
   * - postTitle: The title of the content to be synced.
   * - postContent: The content of the content to be synced.
   *
   * @param array $data The JSON data to be validated.
   * @return boolean True if the data is valid, false otherwise.
   */
  private function validateJsonData($data) {
    foreach ($data as $item) {
      if (!isset($item['postType']) || !isset($item['postTitle']) || !isset($item['postContent'])) {
        return false;
      }
    }
    return true;
  }

  /**
   * Adds the Content Sync settings page to the WordPress admin menu.
   *
   * Adds a link to the Content Sync settings page to the Settings menu.
   *
   * @since 1.0
   */
  public function addAdminMenu() {
    add_options_page(
      'Content Sync Destination Settings',
      'Content Sync Destination',
      'manage_options',
      'content-sync-destination',
      [$this, 'displayAdminPage']
    );
  }

  /**
   * Displays the Content Sync settings page.
   *
   * Shows a form with a single checkbox to enable syncing from local or test sites.
   *
   * @since 1.0
   */
  public function displayAdminPage() {
    ?>
    <div class="wrap">
      <h1>Content Sync Destination Settings</h1>
      <form method="post" action="options.php">
        <?php
        settings_fields('content_sync_options');
        do_settings_sections('content-sync-destination');
        ?>
        <table class="form-table">
          <tr>
            <th scope="row">Allow Local Sync</th>
            <td>
              <input type="checkbox" name="content_sync_allow_local_sync" value="1" <?php checked(get_option('content_sync_allow_local_sync'), 1); ?> />
              <p class="description">Enable syncing from local or test sites.</p>
            </td>
          </tr>
        </table>
        <?php submit_button(); ?>
      </form>
    </div>
    <?php
  }

  /**
   * Registers the Content Sync settings.
   *
   * @since 1.0
   */
  public function registerSettings() {
    register_setting('content_sync_options', 'content_sync_allow_local_sync');
  }

  /**
   * Checks if the given host is a local or test site, and if so, returns true.
   *
   * @since 1.0
   *
   * @param bool  $is   The current value of the allow local sync setting.
   * @param mixed $host The host to check.
   *
   * @return bool The updated value of the allow local sync setting.
   */
  public function allowLocalSync($is, $host) {
    if (strpos($host, '.test') !== false || strpos($host, '.local') !== false) {
      $is = true;
    }
    return $is;
  }

  /**
   * Show admin notices after a sync request.
   *
   * Shows a success notice if the sync was successful, or an error notice if
   * there was an error.
   *
   * @since 1.0
   */
  public function showNotices() {
    if ($this->syncSuccess) {
      ?>
      <div class="notice notice-success is-dismissible">
        <p>Content successfully synced!</p>
      </div>
      <?php
      $this->syncSuccess = false; // Reset the flag
    }

    if ($this->syncError) {
      ?>
      <div class="notice notice-error is-dismissible">
        <p>There was an error syncing the content. Please try again.</p>
      </div>
      <?php
      $this->syncError = false; // Reset the flag
    }
  }
}
