<?php
/**
 * Plugin Name: Content Sync
 * Description: A plugin to sync content between WordPress installations using the WordPress REST API.
 * Version: 0.1
 * Author: Vincent Design Inc.
 */

if (!defined('ABSPATH')) { exit; } // Exit if accessed directly.

define('CONTENT_SYNC_PATH', plugin_dir_path(__FILE__));
define('CONTENT_SYNC_URL', plugins_url('', __FILE__));

require_once CONTENT_SYNC_PATH . 'includes/class-content-sync-source.php';
require_once CONTENT_SYNC_PATH . 'includes/class-content-sync-destination.php';

// Initialize the plugin
add_action('plugins_loaded', 'contentSyncInit');

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

function contentSyncAddAdminMenu() {
  add_options_page(
    'Content Sync Settings',
    'Content Sync',
    'manage_options',
    'content-sync-settings',
    'contentSyncDisplayAdminPage'
  );
}

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
        <tr valign="top">
          <th scope="row">Mode</th>
          <td>
            <select name="content_sync_mode">
              <option value="source" <?php selected(get_option('content_sync_mode'), 'source'); ?>>Source</option>
              <option value="destination" <?php selected(get_option('content_sync_mode'), 'destination'); ?>>Destination</option>
            </select>
          </td>
        </tr>
      </table>

      <?php submit_button(); ?>
    </form>
  </div>
<?php
}

add_action('admin_init', 'contentSyncRegisterSettings');

function contentSyncRegisterSettings() { register_setting('content_sync_options', 'content_sync_mode'); }
