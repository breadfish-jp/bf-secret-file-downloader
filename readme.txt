=== BF Secret File Downloader ===
Contributors: breadfish
Tags: download, file manager, security
Requires at least: 6.8
Tested up to: 7.1.1
Stable tag: 1.0.3
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Donate Link: https://square.link/u/Kl16kA0b

Manage and provide download functionality for files in secure, auto-generated directories.

== Description ==

BF Secret File Downloader is a WordPress plugin that automatically creates secure directories and allows you to manage files within them. The plugin creates protected storage areas automatically and provides comprehensive file management, directory management, and download functionality with advanced access control.

= Features =

* **Automatic Secure Directory Creation**: System automatically creates protected directories with unique names
* **File Management**: Browse, upload, and manage files in secure directories
* **Download Control**: Secure download functionality with access control
* **Access Control**: Multiple authentication methods including WordPress login and simple password
* **Directory Management**: Organize files in automatically created protected directories
* **i18n Ready**: Translation ready with Japanese and English support

= Authentication Methods =

* WordPress user login (with role-based access)
* Simple password protection

= Use Cases =

* Private document distribution
* Member-only file downloads
* Protected resource sharing

== Installation ==

1. Activate the plugin through the 'Plugins' screen in WordPress.
2. The plugin automatically creates a secure directory on activation.
3. Use the BF Secret File Downloader->Settings screen to configure authentication methods.
4. Access the File List page to start uploading and managing files in the secure directory.
5. Share the generated download URL with users who need access to the files.

== Frequently Asked Questions ==

= What file types are supported? =

The plugin supports most common file types including documents, images, archives, and media files. For security reasons, program code files are blocked, including PHP, JavaScript, Python, shell scripts, and other executable file types.

= How secure is the download functionality? =

The plugin implements multiple security layers including nonce verification, user authentication, and sanitized file paths to prevent unauthorized access.

= How does the automatic directory creation work? =

The plugin automatically creates secure directories with unique names when activated. These directories are protected with .htaccess and index.php files to prevent direct access and have unique names for additional security.

= Does it work on Nginx? =

Yes. The secure directory is a hidden directory (its name starts with a dot) under wp-content/uploads. Most Nginx configurations for WordPress deny access to hidden files and directories (`location ~ /\. { deny all; }`), so direct access is blocked without any additional configuration.

The plugin checks whether direct access is actually blocked and shows a warning on its admin screens if it is not. In that case, add the following to your Nginx configuration, or ask your hosting provider to add it:

`location ^~ /wp-content/uploads/bf-secret-file-downloader/ { deny all; }`

If you upload files via FTP, enable the option to show hidden files in your FTP client.

= Is it compatible with multisite? =

Currently, the plugin is designed for single-site installations.

== Screenshots ==

1. Admin file list page showing protected files
2. Settings page with authentication options
3. Frontend download interface

== Changelog ==

= 1.0.3 =
* [ Spec Change ] Move the secure directory to a hidden (dot-prefixed) directory so that direct access is blocked on most Nginx servers, and migrate existing directories automatically
* [ Spec Change ] Check whether direct access to the secure directory is actually blocked and show a warning with an Nginx configuration example if it is not
* [ Bug Fix ] Add protection files to the base directory so that the secure directory name is not exposed by directory listing

= 1.0.2 =
* [ Bug Fix ] Fix intermittent 30-second stalls on plugin activation and block editor screens caused by unconditional session_start() on every request

= 1.0.1 =
* Fix: Removed dangerous htmlspecialchars_decode() usage for improved security
* Fix: Removed unnecessary inline script tag from admin interface
* Fix: Added proper translation support for directory name validation messages
* Improvement: Updated PHPUnit tests to match current implementation
* Maintenance: Removed temporary .bak files from distribution

= 1.0.0 =
* Initial release
* Automatic secure directory creation
* File management functionality in protected directories
* Upload and download control with authentication
* Multiple authentication methods (WordPress login, simple password)
* i18n support for Japanese and English

== Upgrade Notice ==

= 1.0.3 =
Improves protection on Nginx servers. The secure directory is automatically moved to a hidden directory. If you upload files via FTP, use the new directory shown in the admin notice.

= 1.0.1 =
Security improvements and bug fixes. Recommended update for all users.

= 1.0.0 =
Initial release of BF Secret File Downloader.

== Security ==

This plugin implements several security measures:

* Automatic secure directory creation with unique names
* Protected directories with .htaccess and index.php files to prevent direct access
* Nonce verification for all admin actions
* Input sanitization and validation
* Path traversal protection
* Access control verification
* Direct file access prevention
* Program code file upload blocking (PHP, JS, Python, etc.)
* Hidden file and dangerous file pattern filtering
* Secure file upload and download handling

== Support ==

For support and feature requests, please visit the plugin's support forum.

== Donate ==

If you find this plugin useful, please consider making a donation to support its development.

[Donate via Square](https://square.link/u/Kl16kA0b)
