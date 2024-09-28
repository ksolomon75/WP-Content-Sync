<?php
require_once ABSPATH . "wp-admin" . '/includes/image.php';
require_once ABSPATH . "wp-admin" . '/includes/file.php';
require_once ABSPATH . "wp-admin" . '/includes/media.php';

class ContentSyncDestination {
  protected static $instance = null;
  protected $syncSuccess = false;
  protected $syncError = false;

  public static function instance() {
    if (null === self::$instance) {
      self::$instance = new self();
    }
    return self::$instance;
  }

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

  public function registerRoutes() {
    register_rest_route('content-sync/v1', '/sync', [
      'methods' => 'POST',
      'callback' => [$this, 'syncContent'],
      'permission_callback' => function () {
        return current_user_can('manage_options');
      },
    ]);
  }

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

  private function validateJsonData($data) {
    foreach ($data as $item) {
      if (!isset($item['postType']) || !isset($item['postTitle']) || !isset($item['postContent'])) {
        return false;
      }
    }
    return true;
  }

  public function addAdminMenu() {
    add_options_page(
      'Content Sync Settings',
      'Content Sync',
      'manage_options',
      'content-sync-settings',
      [$this, 'displayAdminPage']
    );
  }

  public function displayAdminPage() {
    ?>
    <div class="wrap">
      <h1>Content Sync Settings</h1>
      <form method="post" action="options.php">
        <?php
        settings_fields('content_sync_options');
        do_settings_sections('content-sync-settings');
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

  public function registerSettings() {
    register_setting('content_sync_options', 'content_sync_allow_local_sync');
  }

  public function allowLocalSync($is, $host) {
    if (strpos($host, '.test') !== false || strpos($host, '.local') !== false) {
      $is = true;
    }
    return $is;
  }

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
