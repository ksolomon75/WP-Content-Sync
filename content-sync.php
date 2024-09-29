<?php
/**
 * Plugin Name: Content Sync
 * Description: A plugin to sync content between WordPress installations using the WordPress REST API.
 * Version: 0.4
 * Author: Keith Solomon
 * Author URI: https://keithsolomon.net
 * Plugin URI: https://github.com/ksolomon75/content-sync
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) { exit; } // Exit if accessed directly.

define('CONTENT_SYNC_PATH', plugin_dir_path(__FILE__));
define('CONTENT_SYNC_URL', plugins_url('', __FILE__));

require_once CONTENT_SYNC_PATH . 'includes/content-sync-options.php';
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
