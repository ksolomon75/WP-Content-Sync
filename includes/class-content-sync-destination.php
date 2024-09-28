<?php

class ContentSyncDestination {
  protected static $instance = null;

  public static function instance() {
    if (null === self::$instance) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  private function __construct() {
    add_action('rest_api_init', [$this, 'registerRoutes']);
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

      foreach ($item['attachments'] as $attachment) {
        $this->syncAttachment($attachment, $postId);
      }
    }

    error_log('Content synced successfully.');
    return new WP_REST_Response(['message' => 'Content synced successfully'], 200);
  }

  private function syncAttachment($attachment, $postId) {
    $file_array = [
      'name' => basename($attachment['url']),
      'tmp_name' => download_url($attachment['url']),
    ];

    if (is_wp_error($file_array['tmp_name'])) {
      error_log('Error downloading attachment: ' . $file_array['tmp_name']->get_error_message());
      return;
    }

    $attachment_id = media_handle_sideload($file_array, $postId, $attachment['title'], [
      'post_title' => $attachment['title'],
      'post_content' => $attachment['description'],
      'post_excerpt' => $attachment['caption'],
    ]);

    if (is_wp_error($attachment_id)) {
      error_log('Error uploading attachment: ' . $attachment_id->get_error_message());
      return;
    }

    update_post_meta($attachment_id, '_wp_attachment_image_alt', $attachment['alt']);
  }

  private function validateJsonData($data) {
    foreach ($data as $item) {
      if (!isset($item['postType']) || !isset($item['postTitle']) || !isset($item['postContent'])) {
        return false;
      }
    }
    return true;
  }
}
