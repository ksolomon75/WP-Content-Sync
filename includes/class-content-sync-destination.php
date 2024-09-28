<?php

class ContentSyncDestination {
  protected static $instance = null;

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
   * Private constructor to prevent instantiation.
   *
   * Hooks into the necessary actions to register the Content Sync REST API
   * routes.
   *
   * @since 1.0
   */
  private function __construct() {
    add_action('rest_api_init', [$this, 'registerRoutes']);
  }

  /**
   * Registers the Content Sync REST API routes.
   *
   * Registers the /wp-json/content-sync/v1/sync route, which accepts POST
   * requests and calls the syncContent method to sync the content.
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
   * Syncs content received via the Content Sync REST API.
   *
   * Called when a POST request is made to the /wp-json/content-sync/v1/sync
   * route. Validates the data structure and inserts the content into the
   * database.
   *
   * @param WP_REST_Request $request The request object.
   *
   * @return WP_REST_Response|WP_Error The response object on success, or an
   * error object on failure.
   *
   * @since 1.0
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
    }

    error_log('Content synced successfully.');
    return new WP_REST_Response(['message' => 'Content synced successfully'], 200);
  }

  /**
   * Validates the JSON data structure for the Content Sync API.
   *
   * Ensures the data is an array of objects, each containing the postType,
   * postTitle, and postContent keys.
   *
   * @param array $data The JSON data to validate.
   *
   * @return bool True if the data is valid, or false if it is not.
   *
   * @since 1.0
   */
  private function validateJsonData($data) {
    foreach ($data as $item) {
      if (!isset($item['postType']) || !isset($item['postTitle']) || !isset($item['postContent'])) {
        return false;
      }
    }
    return true;
  }
}
