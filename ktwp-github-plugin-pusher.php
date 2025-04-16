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
                    <div>
                        <button id="push-to-github" class="button button-primary">
                            Push Changes to GitHub
                        </button>
                        <span class="spinner" style="float: none; margin-top: 0; margin-left: 10px; visibility: hidden;"></span>
                    </div>
                </div>
            `);
            
            // Handle push button click
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
                $('#push-to-github').prop('disabled', true);
                $('.spinner').css('visibility', 'visible');
                
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
        
        // Extract plugin name for possible GitHub repo creation
        $plugin_name = basename($plugin_dir);
        $repo_name = "ktwp-wp-plugin-" . str_replace('ktwp-', '', $plugin_name);
        
        // Check if it's a git repository
        $is_git_repo = is_dir($plugin_dir . '/.git');
        
        // If not a git repo, initialize it
        if (!$is_git_repo) {
            // Initialize git repo with main branch
            exec('cd "' . $plugin_dir . '" && git init -b main 2>&1', $init_output, $init_return);
            
            if ($init_return !== 0) {
                wp_send_json_error(array('message' => 'Failed to initialize Git repository.'));
                return;
            }
            
            // Git was just initialized
            $was_initialized = true;
        }
        
        // Make directory safe for git
        exec('git config --global --add safe.directory "' . $plugin_dir . '"');
        
        // Simple, straightforward git commands - the standard workflow
        $current_dir = getcwd();
        chdir($plugin_dir);
        
        // Check what branch we're on
        exec('git branch 2>&1', $branch_output, $branch_return);
        $branch_list = implode("\n", $branch_output);
        
        // Get current branch
        exec('git rev-parse --abbrev-ref HEAD 2>&1', $current_branch_output, $current_branch_return);
        $current_branch = ($current_branch_return === 0 && !empty($current_branch_output)) ? $current_branch_output[0] : '';
        
        // Extract plugin name for possible GitHub repo creation
        $plugin_name = basename($plugin_dir);
        $repo_name = "ktwp-wp-plugin-" . str_replace('ktwp-', '', $plugin_name);
        
        // If we're on master branch, rename it to main
        if ($current_branch === 'master') {
            // Rename local master to main
            exec('git branch -m master main 2>&1', $rename_output, $rename_return);
            $current_branch = 'main';
        }
        
        // 1. Add all files
        exec('git add -A 2>&1', $add_output, $add_return);
        
        // 2. Commit the changes
        $safe_commit_message = str_replace('"', '\"', $commit_message);
        exec('git commit -m "' . $safe_commit_message . '" 2>&1', $commit_output, $commit_return);
        
        // 3. Check if we need to create GitHub repo and set up remote
        $has_remote = false;
        exec('git remote -v 2>&1', $remote_output, $remote_return);
        if (!empty($remote_output)) {
            $has_remote = true;
        }
        
        if (!$has_remote) {
            // No remote - we need to create the GitHub repo
            // First add basic files if missing
            if (!file_exists($plugin_dir . '/LICENSE')) {
                file_put_contents($plugin_dir . '/LICENSE', file_get_contents('https://www.gnu.org/licenses/gpl-3.0.txt'));
                exec('git add ' . $plugin_dir . '/LICENSE 2>&1', $license_output, $license_return);
                exec('git commit -m "Add LICENSE file" 2>&1', $license_commit_output, $license_commit_return);
            }
            
            if (!file_exists($plugin_dir . '/README.md')) {
                file_put_contents($plugin_dir . '/README.md', "# {$repo_name}\n\nWordPress plugin: {$plugin_name}");
                exec('git add ' . $plugin_dir . '/README.md 2>&1', $readme_output, $readme_return);
                exec('git commit -m "Add README.md" 2>&1', $readme_commit_output, $readme_commit_return);
            }
            
            // Create GitHub repo using GitHub CLI
            $desc = "WordPress plugin: " . $plugin_name;
            exec('gh auth status 2>&1', $auth_output, $auth_return);
            
            if ($auth_return === 0) {
                // GitHub CLI is authenticated, try to create repo
                exec('gh repo create "' . $repo_name . '" --public --description "' . $desc . '" 2>&1', $create_output, $create_return);
                
                // Get username from GitHub CLI
                exec('gh api user | grep -o \'"login":"[^"]*"\' | sed \'s/"login":"//;s/"//g\' 2>&1', $username_output, $username_return);
                $username = !empty($username_output) ? $username_output[0] : '';
                
                if (!empty($username)) {
                    // Add remote
                    exec('git remote add origin "https://github.com/' . $username . '/' . $repo_name . '" 2>&1', $remote_add_output, $remote_add_return);
                }
            } else {
                // GitHub CLI not authenticated - add to error message later
                $auth_error = true;
            }
        }
        
        // Push using the current branch (should be main)
        exec('git push -u origin ' . $current_branch . ' 2>&1', $push_output, $push_return);
        
        // Return to original directory
        chdir($current_dir);
        
        // Get the remote URL for creating a link
        exec('git -C ' . $plugin_dir . ' remote get-url origin 2>&1', $remote_url_output, $remote_url_return);
        $remote_url = ($remote_url_return === 0 && !empty($remote_url_output)) ? $remote_url_output[0] : '';
        
        // Format URL for display
        $repo_web_url = $remote_url;
        if (preg_match('#https://github.com/([^/]+)/([^/.]+)\.git#', $remote_url, $matches)) {
            $repo_web_url = "https://github.com/{$matches[1]}/{$matches[2]}";
        }
        
        // Create response message
        $response_message = '';
        
        // Indicate if we initialized the repo
        if (isset($was_initialized) && $was_initialized) {
            $response_message .= 'Initialized Git repository. ';
        }
        
        // Indicate if we created a new GitHub repo
        if (isset($create_return) && $create_return === 0) {
            $response_message .= 'Created new GitHub repository. ';
        } else if (isset($auth_error) && $auth_error) {
            $response_message .= 'Note: GitHub CLI not authenticated. Could not create repository. ';
        }
        
        // Success response
        if ($commit_return === 0 && $push_return === 0) {
            $response_message .= 'Changes pushed to GitHub. <a href="' . esc_url($repo_web_url) . '" target="_blank">View Repository</a>';
            wp_send_json_success(array(
                'message' => $response_message,
                'output' => implode("\n", $push_output)
            ));
        } else if (strpos(implode("\n", $commit_output), 'nothing to commit') !== false) {
            // Nothing to commit
            wp_send_json_error(array('message' => 'No changes to commit.'));
        } else if ($commit_return !== 0) {
            // Commit failed
            wp_send_json_error(array('message' => 'Failed to commit changes: ' . implode("\n", $commit_output)));
        } else {
            // Push failed
            wp_send_json_error(array('message' => 'Failed to push changes: ' . implode("\n", $push_output)));
        }
    }
}

// Initialize the plugin
$ktwp_github_plugin_pusher = new KTWP_GitHub_Plugin_Pusher();