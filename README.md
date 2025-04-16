# KupieTools GitHub Plugin Pusher

A specialized WordPress plugin that adds a "Push to GitHub" button directly to the plugin editor page, allowing for seamless integration between WordPress development and GitHub repositories. It can even automatically create GitHub repositories for new plugins.

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