# KupieTools GitHub Plugin Pusher

A specialized WordPress plugin that adds a "Push to GitHub" (and "Pull From Github", if there are remote changes that haven't been made locally) button directly to the plugin editor page, allowing for seamless integration between WordPress development and GitHub repositories. It can even automatically create GitHub repositories for new plugins.

# Caution

This currently works with the author's setup for the author's use, but it may be hinky. WordPress isn't a Git client. This plugin could ruin your whole day. Make sure you always have your plugins backed up elsewhere for safety. Try to always make changes in either WordPress or on Github, *not* both, or bad things may happen. 

Also, this is not "plug and play", it has not been productized and may still contain settings hard-coded to the author's setup. If you want to play with this you will need to use your brain. 

For instance, as of this writing, I believe I still have it hard-coded to only work for plugins with the "ktwp-" prefix, for starters... I haven't even looked lately. As this suits my personal use, I may change it at some point, or I may not. Things like this are where your brain will come in. 

Obviously, and I don't see why this should happen, but if someone wants to use this and spends some time making it more flexible and customizable, I'd be willing to consider merging such positive changes into this repo. If you want to add settable user options in the WP Admin area, the big thing to look at would be other repos of mine, such as https://github.com/kupietools/ktwp-wp-plugin-caching-toolkit or https://github.com/kupietools/ktwp-wp-plugin-editor-developerlog, which add their settings to a "Kupietools" settings page, and copy that technique. That's how that sort of thing will be implemented for this plugin if get around to it. 

## Features

- Adds a "Push to GitHub" button at the bottom of the WordPress plugin editor
- Only appears for plugins that are already Git repositories
- Automatically creates GitHub repositories for plugins that don't have one
- Shows helpful warnings if GitHub CLI isn't installed or authenticated
- Simple, intuitive interface for committing and pushing changes
- Real-time feedback with success/error messages
- Handles the entire Git workflow (add, commit, push) with a single click
- Secure implementation with WordPress nonces
- Clean, WordPress-style UI that integrates with the admin interface
- Minimal setup required - works out-of-the-box
- Handles "dubious ownership" Git errors automatically

## How It Works

1. The plugin automatically detects if the plugin you're editing is a Git repository
2. If it is, a "Push to GitHub" section appears at the bottom of the editor
3. Enter a commit message describing your changes
4. Click the button to instantly add, commit, and push your changes to GitHub
5. If the plugin has no GitHub remote configured, it automatically creates a new GitHub repository using GitHub CLI
6. Receive immediate feedback on the success or failure of the operation
7. If any issues occur, detailed error messages guide you through fixing them

## Technical Implementation

- Uses WordPress AJAX for asynchronous processing without page reloads
- Leverages native Git commands through PHP's exec() function
- Implements WordPress nonces for security
- Clean JavaScript implementation with jQuery
- Provides detailed error messages for troubleshooting
- Sanitizes all inputs to prevent command injection

## Requirements

- WordPress 5.0 or higher
- Git must be installed on the server
- The web server user must have permission to execute Git commands
- GitHub CLI (gh) for automatic repository creation (optional but recommended)
- The plugin directory must already be a Git repository (remote configuration is optional)

## Installation

1. Upload the `ktwp-github-plugin-pusher` directory to your `/wp-content/plugins/` directory
2. Activate the plugin through the WordPress admin interface
3. Navigate to the plugin editor (Tools > Plugin Editor) and select a plugin that is a Git repository
4. You'll see the "Push to GitHub" section at the bottom of the editor

## Use Cases

- Streamline your WordPress plugin development workflow
- Make quick fixes and push them directly to GitHub
- Eliminate the need to switch between WordPress and command line
- Keep your GitHub repositories in sync with your live development
- Perfect for developers who manage their WordPress plugins with Git

## License

This project is licensed under the GNU General Public License v3.0 - see the [LICENSE](LICENSE) file for details.