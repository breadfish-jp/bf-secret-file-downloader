# BF Secret File Downloader

A WordPress plugin for securely managing and distributing private files to authenticated users.

## Overview

BF Secret File Downloader is a WordPress plugin that automatically creates a secure directory, manages files placed within it, and provides safe download functionality to authenticated users.

## Key Features

### Security Features
- **Automatic Secure Directory Creation**: Automatically creates a protected directory when the plugin is activated
- **Multi-layer Security**: Path traversal attack protection, file type restrictions, access control
- **Authentication System**: Supports WordPress login authentication and simple password authentication

### File Management
- **File Upload**: Safe file upload to secure directory from the admin interface
- **File List Display**: Display files in the secure directory created by the plugin
- **Download Control**: Only authenticated users can download files from the secure directory

### Administration Features
- **Settings Screen**: Configure authentication methods, menu titles, and access permissions
- **Permission Management**: Grant admin permissions to editor-level users
- **Multi-language Support**: Japanese and English support

## Installation

### Requirements
- WordPress 6.8 or higher
- PHP 7.4 or higher

### Installation Steps
1. Upload the plugin through the WordPress admin interface
2. Activate the plugin
3. A secure directory will be created automatically
4. Check settings from the "BF Secret File Downloader" menu

## Usage

### 1. Initial Setup
1. Open "BF Secret File Downloader" → "Settings" in the WordPress admin
2. Select authentication method (WordPress login or simple password authentication)
3. Configure menu title and access permissions

### 2. File Management
1. Open "BF Secret File Downloader" → "File List"
2. Upload files to the secure directory using the "Select Files" button
3. Uploaded files are saved in the secure directory and displayed in the list

### 3. Sharing Download URLs
- Share generated download URLs with authenticated users
- Users can download files after authentication

## Development Environment

### Requirements
- Node.js 16 or higher
- npm
- Docker
- Composer

### Setup

```bash
# Install dependencies
composer install
npm install

# Start WordPress environment
npm run env:start

# Run tests
npm run phpunit
```

### Available Commands

#### Environment Management
```bash
npm run env:start    # Start environment
npm run env:stop     # Stop environment
npm run env:destroy  # Completely remove environment
```

#### Testing
```bash
npm run phpunit      # Run tests
npm run phpunit:watch # Watch mode
```

#### Code Quality
```bash
composer phpcs       # Code style check
composer phpstan     # Static analysis
composer fix         # Code style fix
```

#### Internationalization
```bash
npm run makepot      # Create translation template
npm run update-po    # Update translation files
npm run compile-mo   # Compile translation files
npm run i18n:prepare # Prepare i18n files
npm run i18n:check   # Check translation files
npm run i18n:compile # Compile MO files
```

### Development Environment Access
- **Development Environment**: http://localhost:9999
  - Username: `admin`
  - Password: `password`
- **Test Environment**: http://localhost:9998
  - Username: `admin`
  - Password: `password`

## Directory Structure

```
bf-secret-file-downloader/
├── bf-secret-file-downloader.php  # Main plugin file
├── inc/                           # Plugin source code
│   ├── Admin/                     # Admin screen classes
│   │   ├── FileListPage.php      # File list page
│   │   └── SettingsPage.php      # Settings page
│   ├── Admin.php                  # Main admin class
│   ├── DirectoryManager.php       # Directory management
│   ├── FrontEnd.php               # Frontend functionality
│   ├── SecurityHelper.php         # Security helper
│   ├── ViewRenderer.php           # View renderer
│   └── views/                     # View files
├── assets/                        # Frontend assets
├── languages/                     # Translation files
├── tests/                         # Test files
├── dist/                          # Distribution files
└── scripts/                       # Build scripts
```

## Security Features

### Implemented Security Measures
- **Automatic Secure Directory Creation**: Automatically creates a protected directory with a unique name when the plugin is activated
- **.htaccess Protection**: .htaccess file to prevent direct access to the secure directory
- **Path Traversal Attack Protection**: Safe path construction and directory restrictions
- **File Type Restrictions**: Blocks upload of dangerous file types (PHP, JS, Python, etc.)
- **Authentication Check**: Requires authentication for all download requests to files in the secure directory
- **Nonce Verification**: Implements nonce verification for all admin screen actions
- **Input Sanitization**: Properly sanitizes all user input

## Multi-language Support

The plugin supports the following languages:
- Japanese (ja)
- English (en_US)

Translation files are located in the `languages/` directory.

## License

This plugin is released under the [GPL v2 or later](https://www.gnu.org/licenses/gpl-2.0.html) license.

## Support

- **Official Website**: https://sfd.breadfish.jp/
- **Developer**: BREADFISH (https://breadfish.jp/)
- **License**: GPL v2 or later

## Changelog

### 1.0.0
- Initial release
- Automatic secure directory creation on plugin activation
- File management functionality within secure directory
- Authenticated download functionality
- Multi-language support (Japanese/English)
- Multi-layer security features

## Notes

- Currently does not support multisite
- Upload of program code files (PHP, JavaScript, Python, etc.) is blocked for security reasons
- A secure directory is automatically created when the plugin is activated, and file management is performed within it