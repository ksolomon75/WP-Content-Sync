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
      $postId = $this->createPost($item);
      $this->setPostCategories($postId, $item['postCategories']);
      $this->setPostTags($postId, $item['postTags']);
      $this->setPostMeta($postId, $item['postMeta']);
      $this->setFeaturedImage($postId, $item['featuredImage']);
      $this->updatePostContentWithAttachments($postId, $item['postContent'], $item['attachments']);
    }

    error_log('Content synced successfully.');
    return new WP_REST_Response(['message' => 'Content synced successfully'], 200);
  }

  /**
   * Creates a new post or updates an existing post with the given data.
   *
   * Given an array of post data, this method will create a new post or update an existing post
   * with the given post type, title, content, date, modified date, status, and excerpt.
   *
   * @since 1.0
   *
   * @param array $item The post data to use for creating the post.
   * @return int The ID of the post that was created or updated.
   */
  private function createPost($item) {
    return wp_insert_post([
      'post_type' => $item['postType'],
      'post_title' => sanitize_text_field($item['postTitle']),
      'post_content' => wp_kses_post($item['postContent']),
      'post_date' => $item['postDate'],
      'post_modified' => $item['postModified'],
      'post_status' => $item['postStatus'],
      'post_excerpt' => sanitize_text_field($item['postExcerpt']),
    ]);
  }

  /**
   * Sets the post categories for a post.
   *
   * @param int $postId The post ID to set the categories for.
   * @param array $categories The categories to set. Should be an array of strings.
   * @return void
   */
  private function setPostCategories($postId, $categories) {
    if (!empty($categories)) {
      $category_ids = [];

      foreach ($categories as $category_name) {
        $category = get_category_by_slug(sanitize_title($category_name));

        if (!$category) {
          $category_id = wp_create_category($category_name);
        } else {
          $category_id = $category->term_id;
        }

        $category_ids[] = $category_id;
      }

      wp_set_post_categories($postId, $category_ids);
    }
  }

  /**
   * Sets the post tags for a post.
   *
   * @param int $postId The post ID to set the tags for.
   * @param array $tags The tags to set. Should be an array of strings.
   * @return void
   */
  private function setPostTags($postId, $tags) {
    if (!empty($tags)) {
      foreach ($tags as $tag_name) {
        $tag = get_term_by('name', $tag_name, 'post_tag');

        if (!$tag) {
          $tag = wp_insert_term($tag_name, 'post_tag');

          if (is_wp_error($tag)) {
            continue;
          }

          $tag_id = $tag['term_id'];
        } else {
          $tag_id = $tag->term_id;
        }

        $tag_ids[] = $tag_id;
      }

      wp_set_post_tags($postId, $tag_ids);
    }
  }

  /**
   * Sets the post meta for a post.
   *
   * @param int $postId The post ID to set the meta for.
   * @param array $meta The meta data to set. Should be an associative array with meta keys as the
   *        keys and the values as arrays of strings.
   * @return void
   */
  private function setPostMeta($postId, $meta) {
    foreach ($meta as $key => $values) {
      foreach ($values as $value) {
        add_post_meta($postId, sanitize_text_field($key), sanitize_text_field($value));
      }
    }
  }

  /**
   * Sets the featured image for a post.
   *
   * @param int $postId The post ID to set the featured image for.
   * @param array $featuredImage The featured image data.
   * @return void
   */
  private function setFeaturedImage($postId, $featuredImage) {
    if (!empty($featuredImage)) {
      $featured_image_id = $this->syncAttachment($featuredImage, $postId, true);
      if (!is_wp_error($featured_image_id)) {
        set_post_thumbnail($postId, $featured_image_id);
      }
    }
  }

  /**
   * Replaces URLs of synced attachments in the post content with the new URLs.
   *
   * @param int $postId The ID of the post to update.
   * @param string $postContent The post content to update.
   * @param array $attachments The attachments to sync.
   */
  private function updatePostContentWithAttachments($postId, $postContent, $attachments) {
    $attachment_urls = [];
    foreach ($attachments as $attachment) {
      $new_attachment_id = $this->syncAttachment($attachment, $postId);
      if (!is_wp_error($new_attachment_id)) {
        $attachment_urls[$attachment['url']] = wp_get_attachment_url($new_attachment_id);
      }
    }

    foreach ($attachment_urls as $old_url => $new_url) {
      $postContent = str_replace($old_url, $new_url, $postContent);
    }

    wp_update_post([
      'ID' => $postId,
      'post_content' => $postContent,
    ]);
  }

  /**
   * Syncs an attachment to the destination site.
   *
   * Downloads an attachment from a URL and uploads it to the destination site.
   * The attachment is associated with the given post ID.
   *
   * If the attachment is a featured image, set the post_meta for the featured image.
   *
   * If there is an error uploading the attachment, an error message is logged and the
   * error object is returned.
   *
   * @since 1.0
   *
   * @param array  $attachment The attachment to be synced. Should contain the 'url', 'title', 'description', 'caption', and 'alt' keys.
   * @param int    $postId     The ID of the post that the attachment will be associated with.
   * @param bool   $feat       Whether the attachment is a featured image or not. Defaults to `false`.
   *
   * @return int|WP_Error The ID of the attachment on success, or a WP_Error object on failure.
   */
  private function syncAttachment($attachment, $postId, $feat = false) {
    $url = esc_url_raw($attachment['url']);
    $fileName = basename($url); // Extract the file name
    error_log('Attempting to sync attachment: ' . $fileName);

    // Step 1: Search for existing media by file name
    $existingAttachment = $this->getExistingMediaByFileName($fileName);

    // If an existing attachment is found
    if ($existingAttachment) {
      error_log('Found existing attachment with ID: ' . $existingAttachment->ID);

      // Step 2: If this is a featured image request, check if it's already set
      if ($feat) {
        $currentThumbnailId = get_post_thumbnail_id($postId);

        if ($currentThumbnailId != $existingAttachment->ID) {
          // Set the found attachment as the featured image
          set_post_thumbnail($postId, $existingAttachment->ID);
        }
      }

      // Return the existing attachment ID without downloading a new one
      return $existingAttachment->ID;
    }

    // Step 3: Download and upload the media item if it doesn't exist
    error_log('Downloading attachment from URL: ' . $url);

    $file_array = [
      'name' => $fileName,
      'tmp_name' => $this->downloadURL($url),
    ];

    if ($feat) {
      $attMeta = [
        'post_title' => $attachment['title'],
      ];
    } else {
      $attMeta = [
        'post_title' => $attachment['title'],
        'post_content' => $attachment['description'],
        'post_excerpt' => $attachment['caption'],
      ];
    }

    if (is_wp_error($file_array['tmp_name'])) {
      error_log('Error downloading attachment: ' . $file_array['tmp_name']->get_error_message());
      return $file_array['tmp_name'];
    }

    $attachment_id = media_handle_sideload($file_array, $postId, $attachment['title'], $attMeta);

    if (is_wp_error($attachment_id)) {
      error_log('Error uploading attachment: ' . $attachment_id->get_error_message());
      return $attachment_id;
    }

    update_post_meta($attachment_id, '_wp_attachment_image_alt', $attachment['alt']);

    // Set as featured image if requested
    if ($feat) {
      set_post_thumbnail($postId, $attachment_id);
    }

    return $attachment_id;
  }

  /**
   * Search the media library for an attachment with the same file name.
   *
   * @param string $fileName The file name to search for.
   *
   * @return WP_Post|false The matching attachment post object if found, or false.
   */
  private function getExistingMediaByFileName($fileName) {
    // Search the media library for an attachment with the same file name
    $args = [
      'post_type' => 'attachment',
      'post_status' => 'inherit',
      'meta_query' => [
        [
          'key' => '_wp_attached_file',
          'value' => $fileName,
          'compare' => 'LIKE', // Searches for the file name within the meta value
        ],
      ],
      'posts_per_page' => 1, // We only need one result
    ];

    $existingAttachments = get_posts($args);

    // If a matching attachment is found, return it
    if ($existingAttachments) {
      return $existingAttachments[0];
    }

    // No attachment found
    return false;
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
      if (!isset($item['postType']) || !isset($item['postTitle']) || !isset($item['postContent']) ||
          !isset($item['postDate']) || !isset($item['postModified']) || !isset($item['postStatus']) ||
          !isset($item['postExcerpt']) || !isset($item['postCategories']) || !isset($item['postTags']) ||
          !isset($item['postMeta']) || !isset($item['featuredImage']) || !isset($item['attachments'])) {
        return false;
      }
    }
    return true;
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
