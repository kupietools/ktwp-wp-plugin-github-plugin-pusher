<?php
/*
Plugin Name: KupieTools GitHub Plugin Pusher
Description: Adds a "Push to GitHub" button to the plugin editor page for repositories
Version: 1.0
Author: Michael Kupietz
Author URI: https://michaelkupietz.com

Notes: This may be hinky. WordPress isn't a Git client. Make sure you have things backed up elsewhere for safety. Try to always make changes in either WordPress or on Github, *not* both, or bad things will happen. Also, this is not "plug and play", it has not been productized and may still contain settings hard-coded to the author's setup. If you want to play with this you will need to use your brain. 

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
        
        // Add notice if GitHub has changes that need to be pulled
        add_action('admin_notices', array($this, 'check_for_github_changes'));
        
        // Handle the AJAX request for pushing to GitHub
        add_action('wp_ajax_ktwp_push_to_github', array($this, 'push_to_github'));
        
        // Handle the AJAX request for pulling from GitHub
        add_action('wp_ajax_ktwp_pull_from_github', array($this, 'pull_from_github'));
    }
    
    /**
     * Check if GitHub has changes that need to be pulled
     */
    public function check_for_github_changes() {
        // Only run on plugin editor page
        global $pagenow;
        if ($pagenow !== 'plugin-editor.php') {
            return;
        }
        
        // Get the plugin being edited
        $plugin = isset($_REQUEST['plugin']) ? sanitize_text_field($_REQUEST['plugin']) : '';
        if (empty($plugin)) {
            return;
        }
        
        // Get plugin directory
        $plugin_dir = WP_PLUGIN_DIR . '/' . dirname($plugin);
        
        // Check if this is a KTWP plugin by name
        $plugin_name = basename($plugin_dir);
        if (strpos($plugin_name, 'ktwp-') !== 0) {
            return;
        }
        
        // Is this already a git repository?
        if (!is_dir($plugin_dir . '/.git')) {
            return;
        }
        
        // Try to detect if GitHub has changes to pull
        $current_dir = getcwd();
        chdir($plugin_dir);
        
        // Make directory safe for git
        exec('git config --global --add safe.directory "' . $plugin_dir . '"');
        
        // Try to fetch (may fail due to auth)
        exec('git fetch origin 2>&1', $fetch_output, $fetch_return);
        
        // If fetch succeeded, check if behind
        if ($fetch_return === 0) {
            // Get current branch
            exec('git rev-parse --abbrev-ref HEAD 2>&1', $branch_output, $branch_return);
            $branch = ($branch_return === 0 && !empty($branch_output)) ? $branch_output[0] : 'main';
            
            // Check if behind remote
            exec('git rev-list HEAD..origin/' . $branch . ' --count 2>&1', $behind_output, $behind_return);
            $commits_behind = ($behind_return === 0 && !empty($behind_output)) ? (int)$behind_output[0] : 0;
            
            // Return to original directory
            chdir($current_dir);
            
            // If behind, show notice
            if ($commits_behind > 0) {
                // Get remote URL for web link
                exec('git -C ' . $plugin_dir . ' remote get-url origin 2>&1', $remote_url_output, $remote_url_return);
                $remote_url = ($remote_url_return === 0 && !empty($remote_url_output)) ? $remote_url_output[0] : '';
                
                // Format URL for web display
                $repo_web_url = $remote_url;
                if (preg_match('#https://github.com/([^/]+)/([^/.]+)\.git#', $remote_url, $matches)) {
                    $repo_web_url = "https://github.com/{$matches[1]}/{$matches[2]}";
                }
                
                // Show pull notice
                ?>
                <div class="notice notice-warning">
                    <p>
                        <strong>GitHub Changes Available:</strong> 
                        The GitHub repository for this plugin has <?php echo $commits_behind; ?> new commit<?php echo $commits_behind > 1 ? 's' : ''; ?> 
                        that are not in your local version.
                        <a href="<?php echo esc_url($repo_web_url); ?>/commits/<?php echo esc_attr($branch); ?>" target="_blank">View Commits</a>
                        
                        <button id="pull-github-changes" class="button button-primary" style="margin-left: 10px;" 
                                data-plugin="<?php echo esc_attr($plugin); ?>" 
                                data-branch="<?php echo esc_attr($branch); ?>">
                            Pull Changes Now
                        </button>
                        <span class="spinner" style="float: none; margin-top: 0; margin-left: 5px; visibility: hidden;"></span>
                    </p>
                    <p id="github-pull-result" style="display: none; margin-top: 10px; padding: 10px; background: #f7f7f7; border-radius: 4px;"></p>
                </div>
                <script>
                jQuery(document).ready(function($) {
                    $('#pull-github-changes').on('click', function(e) {
                        e.preventDefault();
                        
                        // Show spinner
                        $(this).prop('disabled', true);
                        $(this).next('.spinner').css('visibility', 'visible');
                        
                        // Send AJAX request to pull changes
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'ktwp_pull_from_github',
                                plugin: $(this).data('plugin'),
                                branch: $(this).data('branch'),
                                nonce: '<?php echo wp_create_nonce('ktwp_pull_from_github'); ?>'
                            },
                            success: function(response) {
                                if (response.success) {
                                    $('#github-pull-result')
                                        .html('<strong>Success:</strong> ' + response.data.message)
                                        .css('background-color', '#ecf7ed')
                                        .css('border-left', '4px solid #46b450')
                                        .show();
                                        
                                    // Reload page after 2 seconds to show updated content
                                    setTimeout(function() {
                                        window.location.reload();
                                    }, 2000);
                                } else {
                                    $('#github-pull-result')
                                        .html('<strong>Error:</strong> ' + response.data.message)
                                        .css('background-color', '#ffebe8')
                                        .css('border-left', '4px solid #c00')
                                        .show();
                                        
                                    // Re-enable the button
                                    $('#pull-github-changes').prop('disabled', false);
                                }
                            },
                            error: function() {
                                $('#github-pull-result')
                                    .html('<strong>Error:</strong> Connection error occurred.')
                                    .css('background-color', '#ffebe8')
                                    .css('border-left', '4px solid #c00')
                                    .show();
                                    
                                // Re-enable the button
                                $('#pull-github-changes').prop('disabled', false);
                            },
                            complete: function() {
                                // Hide spinner
                                $('.spinner').css('visibility', 'hidden');
                            }
                        });
                    });
                });
                </script>
                <?php
            }
        } else {
            // Return to original directory if fetch failed
            chdir($current_dir);
        }
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
        
        // Check if this is a KTWP plugin by name
        $plugin_name = basename($plugin_dir);
        if (strpos($plugin_name, 'ktwp-') !== 0) {
            // Not a KTWP plugin - don't show button
            return;
        }
        
        // Is this already a git repository?
        $is_git_repo = is_dir($plugin_dir . '/.git');
        
        // Output the HTML for our button and form
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Add the push form after the editor with appropriate messaging
            const isGitRepo = <?php echo $is_git_repo ? 'true' : 'false'; ?>;
            
            $('#template').append(`
                <div id="ktwp-github-pusher" style="margin-top: 20px; padding: 15px; background: #f8f8f8; border: 1px solid #ddd; border-radius: 4px;">
                    <h3 style="margin-top: 0;">${isGitRepo ? 'Push to GitHub' : 'Create GitHub Repository'}</h3>
                    ${!isGitRepo ? '<p style="color: #666;">This plugin is not a Git repository yet. Clicking the button below will initialize it and create a GitHub repository.</p>' : ''}
                    <div id="push-response" style="display: none; margin-bottom: 15px; padding: 10px; border-radius: 3px;"></div>
                    <div style="margin-bottom: 10px;">
                        <label for="commit-message"><strong>Commit Message:</strong></label>
                        <textarea id="commit-message" style="width: 100%; margin-top: 5px; min-height: 60px;">${!isGitRepo ? 'Initial commit' : ''}</textarea>
                    </div>
                    <div>
                        <button id="push-to-github" class="button button-primary">
                            ${isGitRepo ? 'Push Changes to GitHub' : 'Initialize and Push to GitHub'}
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
        
          // Check if this is a force push request                             
         $force_push = isset($_POST['force_push']) && $_POST['force_push'] === 'true';  
        if ($force_push) {   
        // Force push - will overwrite GitHub changes     
        exec('git push origin main --force 2>&1', $push_output, $push_return);                                                                    
   } else {                                                           
           // Normal push - safer for local changes                       
          exec('git push origin main 2>&1', $push_output, $push_return)   ;                                                                        
       }                                                                  
          
        // If normal push fails due to GitHub having changes, offer options
        if ($push_return !== 0 && (
            strpos(implode("\n", $push_output), 'fetch first') !== false || 
            strpos(implode("\n", $push_output), 'rejected') !== false ||
            strpos(implode("\n", $push_output), 'would be overwritten by merge') !== false
        )) {
            $pull_needed = true;
            $can_force_push = true;
        }
        
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
        } else if (isset($pull_needed) && $pull_needed) {
            // Need to pull first - but also offer force push option
            $terminal_command = 'cd ' . $plugin_dir . ' && git pull origin main';
            
            // Create a force push button if we can
            $force_push_button = '';
            if (isset($can_force_push) && $can_force_push) {
                $nonce = wp_create_nonce('ktwp_force_push');
                $force_push_button = '
                <div style="margin-top: 10px; padding: 10px; background-color: #f8d7da; border-left: 4px solid #842029;">
                    <p><strong>Warning:</strong> You can force push to overwrite GitHub changes, but this will LOSE any changes on GitHub that are not in your local copy.</p>
                    <form id="force-push-form" method="post">
                        <input type="hidden" name="action" value="ktwp_push_to_github">
                        <input type="hidden" name="plugin" value="' . esc_attr($plugin) . '">
                        <input type="hidden" name="commit_message" value="' . esc_attr($commit_message) . '">
                        <input type="hidden" name="force_push" value="true">
                        <input type="hidden" name="nonce" value="' . $nonce . '">
                        <button type="button" id="force-push-button" class="button" style="background-color: #dc3545; color: white; border-color: #dc3545;">
                            Force Push (Overwrite GitHub)
                        </button>
                    </form>
                    <script>
                    jQuery(document).ready(function($) {
                        $("#force-push-button").on("click", function() {
                            if (confirm("Are you sure? This will OVERWRITE all changes on GitHub with your local version.")) {
                                var formData = $("#force-push-form").serialize();
                                $.post(ajaxurl, formData, function(response) {
                                    if (response.success) {
                                        $("#push-response")
                                            .html("<strong>Success:</strong> " + response.data.message)
                                            .css("background-color", "#ecf7ed")
                                            .css("border", "1px solid #46b450")
                                            .show();
                                            
                                        $("#commit-message").val("");
                                    } else {
                                        $("#push-response")
                                            .html("<strong>Error:</strong> " + response.data.message)
                                            .css("background-color", "#ffebe8")
                                            .css("border", "1px solid #c00")
                                            .show();
                                    }
                                });
                            }
                        });
                    });
                    </script>
                </div>';
            }
            
            wp_send_json_error(array(
                'message' => 'GitHub has changes you need to merge first. Please use the "Pull Changes Now" button that appears at the top of the page, then try pushing again.' . $force_push_button,
                'details' => implode("\n", $push_output)
            ));
        } else {
            // Other push failures
            wp_send_json_error(array('message' => 'Failed to push changes: ' . implode("\n", $push_output)));
        }
    }
    
    /**
     * Process the GitHub pull request
     */
    public function pull_from_github() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ktwp_pull_from_github')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
            return;
        }
        
        // Get the plugin path
        $plugin = isset($_POST['plugin']) ? sanitize_text_field($_POST['plugin']) : '';
        if (empty($plugin)) {
            wp_send_json_error(array('message' => 'No plugin specified.'));
            return;
        }
        
        // Get the branch
        $branch = isset($_POST['branch']) ? sanitize_text_field($_POST['branch']) : 'main';
        
        // Get plugin directory
        $plugin_dir = WP_PLUGIN_DIR . '/' . dirname($plugin);
        
        // Verify it's a git repository
        if (!is_dir($plugin_dir . '/.git')) {
            wp_send_json_error(array('message' => 'This plugin is not a Git repository.'));
            return;
        }
        
        // Make directory safe for git
        exec('git config --global --add safe.directory "' . $plugin_dir . '"');
        
        // Check if there are uncommitted changes first
        $current_dir = getcwd();
        chdir($plugin_dir);
        
        exec('git status --porcelain 2>&1', $status_output, $status_return);
        $has_uncommitted = !empty($status_output);
        
        if ($has_uncommitted) {
            // Uncommitted changes - warn the user
            chdir($current_dir);
            wp_send_json_error(array(
                'message' => 'You have uncommitted changes that might be lost. Please commit your changes first, then pull.',
                'details' => implode("\n", $status_output)
            ));
            return;
        }
        
        // No uncommitted changes, safe to pull
        exec('git pull origin ' . $branch . ' 2>&1', $pull_output, $pull_return);
        
        // Return to original directory
        chdir($current_dir);
        
        // Return response
        if ($pull_return === 0) {
            wp_send_json_success(array(
                'message' => 'Successfully pulled changes from GitHub. The page will reload to show the updated files.',
                'output' => implode("\n", $pull_output)
            ));
        } else {
            wp_send_json_error(array(
                'message' => 'Failed to pull changes: ' . implode("\n", $pull_output)
            ));
        }
    }
}

// Initialize the plugin
$ktwp_github_plugin_pusher = new KTWP_GitHub_Plugin_Pusher();