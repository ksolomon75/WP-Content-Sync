<?php
class ContentSyncSource {
  protected static $instance = null;

  public static function instance() {
    if (null === self::$instance) { self::$instance = new self(); }

    return self::$instance;
  }

  private function __construct() {
    add_action('admin_menu', [$this, 'addAdminMenu']);
    add_action('admin_init', [$this, 'handleSyncRequest']);
  }

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

  public function displayAdminPage() {
?>
    <div class="wrap">
      <h1>Content Sync Source</h1>

      <form method="post">
        <?php wp_nonce_field('contentSyncSourceNonce', 'contentSyncSourceNonceField'); ?>

        <input type="hidden" name="action" value="syncContent">

        <?php submit_button('Sync Content'); ?>
      </form>
    </div>
<?php
  }

  public function handleSyncRequest() {
    if (isset($_POST['action']) && $_POST['action'] === 'syncContent') {
      if (!isset($_POST['contentSyncSourceNonceField']) || !wp_verify_nonce($_POST['contentSyncSourceNonceField'], 'contentSyncSourceNonce')) {
        add_action('admin_notices', [$this, 'showErrorNotice']);

        return;
      }

      $this->syncContent();
    }
  }

  private function syncContent() {
    $posts = get_posts([
      'post_type' => 'post',
      'numberposts' => -1,
    ]);

    $data = [];

    foreach ($posts as $post) {
      $data[] = [
        'postTitle' => $post->post_title,
        'postContent' => $post->post_content,
        'postMeta' => get_post_meta($post->ID),
      ];
    }

    $response = wp_remote_post('https://destination-site.com/wp-json/content-sync/v1/sync', [
      'headers' => [
        'Authorization' => 'Basic ' . base64_encode('application_username:application_password'), // Use Application Passwords
        'Content-Type' => 'application/json',
      ],
      'body' => json_encode($data),
    ]);

    if (is_wp_error($response)) {
      add_action('admin_notices', [$this, 'showErrorNotice']);
    } else {
      add_action('admin_notices', [$this, 'showSuccessNotice']);
    }
  }

  public function showSuccessNotice() {
?>
    <div class="notice notice-success is-dismissible"><p>Content successfully synced!</p></div>
<?php
  }

  public function showErrorNotice() {
?>
    <div class="notice notice-error is-dismissible"><p>There was an error syncing the content. Please try again.</p></div>
<?php
  }
}
