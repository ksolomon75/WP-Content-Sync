<?php
/**
 * Plugin Name: Content Sync
 * Description: A plugin to sync content between WordPress installations using the WordPress REST API.
 * Version: 1.0
 * Author: Vincent Design Inc.
 */

if (!defined('ABSPATH')) { exit; } // Exit if accessed directly.

define('CONTENT_SYNC_PATH', plugin_dir_path(__FILE__));
define('CONTENT_SYNC_URL', plugins_url('', __FILE__));

require_once CONTENT_SYNC_PATH . 'includes/class-content-sync-source.php';
require_once CONTENT_SYNC_PATH . 'includes/class-content-sync-destination.php';

// Initialize the plugin
add_action('plugins_loaded', 'contentSyncInit');

/**
 * Initialize the Content Sync plugin.
 *
 * Checks the 'content_sync_mode' option to determine whether to load the source or
 * destination class.
 *
 * @since 1.0
 */
function contentSyncInit() {
  $mode = get_option('content_sync_mode', 'source'); // Default to 'source'

  if ($mode === 'source') {
    ContentSyncSource::instance();
  } elseif ($mode === 'destination') {
    ContentSyncDestination::instance();
  }
}

// Add settings page to switch between source and destination
add_action('admin_menu', 'contentSyncAddAdminMenu');

/**
 * Adds the Content Sync settings page to the WordPress admin menu.
 *
 * @since 1.0
 */
function contentSyncAddAdminMenu() {
  add_options_page(
    'Content Sync Settings',
    'Content Sync',
    'manage_options',
    'content-sync-settings',
    'contentSyncDisplayAdminPage'
  );
}

/**
 * Displays the Content Sync settings page.
 *
 * @since 1.0
 */
function contentSyncDisplayAdminPage() {
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
          <th scope="row">Mode</th>
          <td>
            <select name="content_sync_mode">
              <option value="source" <?php selected(get_option('content_sync_mode'), 'source'); ?>>Source</option>
              <option value="destination" <?php selected(get_option('content_sync_mode'), 'destination'); ?>>Destination</option>
            </select>
          </td>
        </tr>
        <tr>
          <th scope="row">Destination URL</th>
          <td>
            <input type="text" name="content_sync_destination_url" value="<?php echo esc_attr(get_option('content_sync_destination_url')); ?>" />
          </td>
        </tr>
        <tr>
          <th scope="row">Username</th>
          <td>
            <input type="text" name="content_sync_username" value="<?php echo esc_attr(get_option('content_sync_username')); ?>" />
          </td>
        </tr>
        <tr>
          <th scope="row">Application Password</th>
          <td>
            <input type="password" name="content_sync_app_password" value="<?php echo esc_attr(get_option('content_sync_app_password')); ?>" />
          </td>
        </tr>
      </table>

      <?php submit_button(); ?>
    </form>
  </div>
  <?php
}

add_action('admin_init', 'contentSyncRegisterSettings');

/**
 * Registers the settings for the Content Sync plugin.
 *
 * @since 1.0
 */
function contentSyncRegisterSettings() {
  register_setting('content_sync_options', 'content_sync_mode');
  register_setting('content_sync_options', 'content_sync_destination_url');
  register_setting('content_sync_options', 'content_sync_username');
  register_setting('content_sync_options', 'content_sync_app_password');
}
