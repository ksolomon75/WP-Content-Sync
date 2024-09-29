<?php
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


/**
 * Displays the Content Sync settings page.
 *
 * Handles the form submission for setting the plugin mode (source/destination) and destination settings.
 *
 * @since 1.0
 */
function contentSyncSettingsPage() {
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
            <select id="content_sync_mode" name="content_sync_mode">
              <option value="source" <?php selected(get_option('content_sync_mode'), 'source'); ?>>Source</option>
              <option value="destination" <?php selected(get_option('content_sync_mode'), 'destination'); ?>>Destination</option>
            </select>
          </td>
        </tr>
      </table>

      <!-- Destination settings -->
      <div id="destination-settings">
        <table class="form-table">
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
      </div>

      <?php submit_button(); ?>
    </form>
  </div>

  <!-- JavaScript to show/hide destination settings -->
  <script type="text/javascript">
    (function($) {
      function toggleDestinationSettings() {
        var mode = $('#content_sync_mode').val();
        if (mode === 'destination') {
          $('#destination-settings').hide();
        } else {
          $('#destination-settings').show();
        }
      }

      // On page load
      $(document).ready(function() {
        toggleDestinationSettings();

        // On mode change
        $('#content_sync_mode').change(function() {
          toggleDestinationSettings();
        });
      });
    })(jQuery);
  </script>
  <?php
}

add_action('admin_menu', function () {
  add_options_page('Content Sync', 'Content Sync', 'manage_options', 'content-sync-settings', 'contentSyncSettingsPage');
});
