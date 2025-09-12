<?php
/**
 * Plugin Name: BF Secret File Downloader
 * Plugin URI: https://sfd.breadfish.jp/
 * Description: A plugin for securely managing and distributing private files to authenticated users.
 * Version: 1.0.1
 * Author: BREADFISH
 * Author URI: https://breadfish.jp/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bf-secret-file-downloader
 * Domain Path: /languages
 * Requires at least: 6.8
 * Tested up to: 6.8
 * Requires PHP: 7.4
 *
 * @package BfSecretFileDownloader
 */

// Security check: prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'BF_SECRET_FILE_DOWNLOADER_VERSION', '1.0.1' );
define( 'BF_SECRET_FILE_DOWNLOADER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BF_SECRET_FILE_DOWNLOADER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Autoloader function
 * Loads class files based on the namespace
 *
 * @param string $class_name class name (fully qualified name)
 */
function bf_secret_file_downloader_autoloader( $class_name ) {
    // Check the namespace prefix
    $prefix = 'Breadfish\\SecretFileDownloader\\';
    $len = strlen( $prefix );

    if ( strncmp( $prefix, $class_name, $len ) !== 0 ) {
        return;
    }

    // Remove the namespace prefix from the class name
    $relative_class = substr( $class_name, $len );

    // Build the file path
    $file = BF_SECRET_FILE_DOWNLOADER_PLUGIN_DIR . 'inc/' . str_replace( '\\', '/', $relative_class ) . '.php';

    // If the file exists, load it
    if ( file_exists( $file ) ) {
        require $file;
    }
}

// Register the autoloader
spl_autoload_register( 'bf_secret_file_downloader_autoloader' );

/**
 * Load the text domain
 * Note: WordPress automatically loads translations from the languages directory
 * when the text domain is properly set in the plugin header.
 */
function bf_secret_file_downloader_load_textdomain() {
    // WordPress automatically loads translations from languages/ directory
    // when the text domain is properly configured in the plugin header.
    // No manual loading is required for WordPress.org hosted plugins.
}

/**
 * Initialize the plugin
 */
function bf_secret_file_downloader_init() {

    // Load the text domain
    bf_secret_file_downloader_load_textdomain();

    // Execute only in the admin panel
    if ( is_admin() ) {
        $admin = new \Breadfish\SecretFileDownloader\Admin();
        $admin->init(); // Explicitly initialize the hooks
    }

    // Initialize the frontend functionality
    $frontend = new \Breadfish\SecretFileDownloader\FrontEnd();
    $frontend->init();

}

add_action( 'init', 'bf_secret_file_downloader_init' );

/**
 * Process when the plugin is activated
 */
function bf_secret_file_downloader_activate() {
    // Create a secure directory
    \Breadfish\SecretFileDownloader\DirectoryManager::create_secure_directory();

}

register_activation_hook( __FILE__, 'bf_secret_file_downloader_activate' );
