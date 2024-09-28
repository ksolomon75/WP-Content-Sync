<?php

class ContentSyncSource {
  protected static $instance = null;
  protected $syncSuccess = false;
  protected $syncError = false;

  /**
   * Retrieves the single instance of the class.
   *
   * @return ContentSyncSource The single instance of the class.
   * @since 1.0
   */
  public static function instance() {
    if (null === self::$instance) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  /**
   * Private constructor to prevent instantiation.
   *
   * Hooks into the necessary actions to render the settings page and handle the
   * sync request.
   *
   * @since 1.0
   */
  private function __construct() {
    add_action('admin_menu', [$this, 'addAdminMenu']);
    add_action('admin_init', [$this, 'handleSyncRequest']);
    add_action('admin_notices', [$this, 'showNotices']);
  }

  /**
   * Adds the Content Sync Source page to the WordPress admin menu.
   *
   * @since 1.0
   */
  public function addAdminMenu() {
    add_menu_page(
      'Content Sync Source',
      'Content Sync Source',
      'manage_options',
      'content-sync-source',
      [$this, 'displayAdminPage'],
      'dashicons-update',
      6
    );
  }

  /**
   * Renders the Content Sync Source page in the WordPress admin area.
   *
   * Displays a table of all posts and pages with checkboxes to select which
   * content to sync. Also handles the sync request and shows success or error
   * notices.
   *
   * @since 1.0
   */
  public function displayAdminPage() {
    if (isset($_POST['sync_selected_content'])) {
      $this->syncSelectedContent($_POST['selected_content']);
    }

    $posts = get_posts([
      'post_type' => ['post', 'page'],
      'numberposts' => -1,
    ]);
    ?>
    <div class="wrap">
      <h1>Content Sync Source</h1>
      <form method="post">
        <?php wp_nonce_field('contentSyncSourceNonce', 'contentSyncSourceNonceField'); ?>
        <table class="widefat">
          <thead>
            <tr>
              <th><input type="checkbox" id="select-all" /></th>
              <th>Title</th>
              <th>Type</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($posts as $post) : ?>
              <tr>
                <td><input type="checkbox" name="selected_content[]" value="<?php echo esc_attr($post->ID); ?>" class="select-content" /></td>
                <td><?php echo esc_html($post->post_title); ?></td>
                <td><?php echo esc_html($post->post_type); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <input type="hidden" name="sync_selected_content" value="1">
        <?php submit_button('Sync Selected Content'); ?>
      </form>
    </div>
    <script type="text/javascript">
      document.getElementById('select-all').addEventListener('change', function() {
        var checkboxes = document.querySelectorAll('.select-content');
        checkboxes.forEach(function(checkbox) {
          checkbox.checked = this.checked;
        }, this);
      });
    </script>
    <?php
  }

  /**
   * Handles the sync request and syncs the content if the nonce is valid.
   *
   * Checks if the nonce is valid and if the action is to sync the content. If
   * the nonce is invalid, sets the syncError flag to true and returns. If the
   * nonce is valid, calls the syncContent method to sync the content.
   *
   * @since 1.0
   */
  public function handleSyncRequest() {
    if (isset($_POST['action']) && $_POST['action'] === 'syncContent') {
      if (!isset($_POST['contentSyncSourceNonceField']) || !wp_verify_nonce($_POST['contentSyncSourceNonceField'], 'contentSyncSourceNonce')) {
        $this->syncError = true;
        return;
      }

      $this->syncContent();
    }
  }

  /**
   * Syncs the selected content to the destination site.
   *
   * Loops over the selected IDs and fetches the post data. Then, sends a POST
   * request to the destination site with the post data. Logs the response code
   * and body, and sets the syncSuccess or syncError flags accordingly.
   *
   * @param array $selectedIds The IDs of the selected posts to sync.
   *
   * @since 1.0
   */
  private function syncSelectedContent($selectedIds) {
    $data = [];
    foreach ($selectedIds as $postId) {
      $post = get_post($postId);
      $data[] = [
        'postType' => $post->post_type,
        'postTitle' => $post->post_title,
        'postContent' => $post->post_content,
        'postMeta' => get_post_meta($post->ID),
      ];
    }

    error_log('Syncing selected content: ' . print_r($data, true));

    $destinationUrl = get_option('content_sync_destination_url');
    $username = get_option('content_sync_username');
    $appPassword = get_option('content_sync_app_password');

    $destinationUrl = $destinationUrl . '/wp-json/content-sync/v1/sync';

    $response = wp_remote_post($destinationUrl, [
      'headers' => [
        'Authorization' => 'Basic ' . base64_encode($username . ':' . $appPassword), // Use Application Passwords
        'Content-Type' => 'application/json',
      ],
      'body' => json_encode($data),
    ]);

    if (is_wp_error($response)) {
      error_log('Sync request failed: ' . $response->get_error_message());
      $this->syncError = true;
    } else {
      $response_code = wp_remote_retrieve_response_code($response);
      $response_body = wp_remote_retrieve_body($response);

      error_log('Sync request response code: ' . $response_code);
      error_log('Sync request response body: ' . $response_body);

      if ($response_code === 200) {
        $this->syncSuccess = true;
      } else {
        error_log('Sync request failed with response code: ' . $response_code);
        $this->syncError = true;
      }
    }
  }

  /**
   * Displays admin notices based on the sync status.
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
