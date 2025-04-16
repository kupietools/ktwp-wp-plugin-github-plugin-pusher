<?php
/*
Plugin Name: KupieTools GitHub Plugin Pusher
Description: Adds a "Push to GitHub" button to the plugin editor page for repositories
Version: 1.0
Author: Michael Kupietz
Author URI: https://michaelkupietz.com
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class KTWP_GitHub_Plugin_Pusher {
    /**
     * Constructor
     */
    public function __construct() {
        // Add our button to the plugin editor
        add_action('admin_footer-plugin-editor.php', array($this, 'add_push_button'));
        
        // Handle the AJAX request for pushing to GitHub
        add_action('wp_ajax_ktwp_push_to_github', array($this, 'push_to_github'));
    }
    
    /**
     * Add the push button to the plugin editor
     */
    public function add_push_button() {
        // Get the plugin being edited
        $plugin_file = isset($_REQUEST['file']) ? $_REQUEST['file'] : '';
        $plugin = isset($_REQUEST['plugin']) ? $_REQUEST['plugin'] : '';
        
        if (empty($plugin)) {
            return;
        }
        
        // Get the plugin directory path
        $plugin_dir = WP_PLUGIN_DIR . '/' . dirname($plugin);
        
        // Only proceed if this is a git repository
        if (!is_dir($plugin_dir . '/.git')) {
            return;
        }
        
        // Output the HTML for our button and form
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Add the push form after the editor
            $('#template').append(`
                <div id="ktwp-github-pusher" style="margin-top: 20px; padding: 15px; background: #f8f8f8; border: 1px solid #ddd; border-radius: 4px;">
                    <h3 style="margin-top: 0;">Push to GitHub</h3>
                    <div id="push-response" style="display: none; margin-bottom: 15px; padding: 10px; border-radius: 3px;"></div>
                    <div style="margin-bottom: 10px;">
                        <label for="commit-message"><strong>Commit Message:</strong></label>
                        <textarea id="commit-message" style="width: 100%; margin-top: 5px; min-height: 60px;"></textarea>
                    </div>
                    <button id="push-to-github" class="button button-primary">
                        Push Changes to GitHub
                    </button>
                    <span class="spinner" style="float: none; margin-top: 0; margin-left: 10px; visibility: hidden;"></span>
                </div>
            `);
            
            // Handle the push button click
            $('#push-to-github').on('click', function(e) {
                e.preventDefault();
                
                const commitMessage = $('#commit-message').val().trim();
                if (!commitMessage) {
                    $('#push-response')
                        .html('<strong>Error:</strong> Please enter a commit message.')
                        .css('background-color', '#ffebe8')
                        .css('border', '1px solid #c00')
                        .show();
                    return;
                }
                
                // Show spinner
                $(this).prop('disabled', true);
                $(this).next('.spinner').css('visibility', 'visible');
                
                // Clear previous response
                $('#push-response').hide();
                
                // Send AJAX request
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ktwp_push_to_github',
                        plugin: '<?php echo esc_js($plugin); ?>',
                        commit_message: commitMessage,
                        nonce: '<?php echo wp_create_nonce('ktwp_push_to_github'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#push-response')
                                .html('<strong>Success:</strong> ' + response.data.message)
                                .css('background-color', '#ecf7ed')
                                .css('border', '1px solid #46b450')
                                .show();
                                
                            // Clear the commit message field
                            $('#commit-message').val('');
                        } else {
                            $('#push-response')
                                .html('<strong>Error:</strong> ' + response.data.message)
                                .css('background-color', '#ffebe8')
                                .css('border', '1px solid #c00')
                                .show();
                        }
                    },
                    error: function() {
                        $('#push-response')
                            .html('<strong>Error:</strong> Connection error occurred.')
                            .css('background-color', '#ffebe8')
                            .css('border', '1px solid #c00')
                            .show();
                    },
                    complete: function() {
                        // Hide spinner and re-enable button
                        $('#push-to-github').prop('disabled', false);
                        $('.spinner').css('visibility', 'hidden');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Process the GitHub push request
     */
    public function push_to_github() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ktwp_push_to_github')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
            return;
        }
        
        // Get the plugin path
        $plugin = isset($_POST['plugin']) ? sanitize_text_field($_POST['plugin']) : '';
        if (empty($plugin)) {
            wp_send_json_error(array('message' => 'No plugin specified.'));
            return;
        }
        
        // Get the commit message
        $commit_message = isset($_POST['commit_message']) ? sanitize_textarea_field($_POST['commit_message']) : '';
        if (empty($commit_message)) {
            wp_send_json_error(array('message' => 'Please enter a commit message.'));
            return;
        }
        
        // Get plugin directory
        $plugin_dir = WP_PLUGIN_DIR . '/' . dirname($plugin);
        
        // Verify it's a git repository
        if (!is_dir($plugin_dir . '/.git')) {
            wp_send_json_error(array('message' => 'This plugin is not a Git repository.'));
            return;
        }
        
        // Change to plugin directory
        $current_dir = getcwd();
        chdir($plugin_dir);
        
        // Check for changes
        exec('git status --porcelain', $status_output, $status_return);
        if (empty($status_output)) {
            chdir($current_dir);
            wp_send_json_success(array('message' => 'No changes to commit.'));
            return;
        }
        
        // Add all changes
        exec('git add .', $add_output, $add_return);
        if ($add_return !== 0) {
            chdir($current_dir);
            wp_send_json_error(array('message' => 'Failed to stage changes.'));
            return;
        }
        
        // Commit changes
        $safe_commit_message = str_replace('"', '\"', $commit_message);
        exec('git commit -m "' . $safe_commit_message . '"', $commit_output, $commit_return);
        if ($commit_return !== 0) {
            chdir($current_dir);
            wp_send_json_error(array('message' => 'Failed to commit changes.'));
            return;
        }
        
        // Push changes
        exec('git push', $push_output, $push_return);
        if ($push_return !== 0) {
            chdir($current_dir);
            wp_send_json_error(array('message' => 'Failed to push changes to GitHub.'));
            return;
        }
        
        // Restore original directory
        chdir($current_dir);
        
        // Send success response
        wp_send_json_success(array(
            'message' => 'Changes successfully pushed to GitHub.',
            'details' => implode("\n", array_merge($commit_output, $push_output))
        ));
    }
}

// Initialize the plugin
$ktwp_github_plugin_pusher = new KTWP_GitHub_Plugin_Pusher();