<?php
/**
 * Plugin Name: BF Secret File Downloader
 * Plugin URI: https://sfd.breadfish.jp/
 * Description: A plugin for securely managing and distributing private files to authenticated users.
 * Version: 1.0.2
 * Author: BREADFISH
 * Author URI: https://breadfish.jp/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bf-secret-file-downloader
 * Domain Path: /languages
 * Requires at least: 6.8
 * Tested up to: 7.0
 * Requires PHP: 7.4
 *
 * @package BfSecretFileDownloader
 */

// Security check: prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'BF_SECRET_FILE_DOWNLOADER_VERSION', '1.0.2' );
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
 * テキストドメインを読み込む
 *
 * The bundled translation (languages/) is loaded explicitly and takes precedence.
 * WordPress core only loads translations from wp-content/languages/plugins/ automatically,
 * so without this, the language pack from translate.wordpress.org is used and strings that
 * are not translated there (e.g. strings added in a new version) are shown in English.
 * Locales without a bundled file fall back to the language pack as before.
 *
 * 同梱の翻訳（languages/）を明示的に読み込み、優先して使う。
 * WordPress コアが自動で読み込むのは wp-content/languages/plugins/ のみのため、
 * これが無いと translate.wordpress.org の言語パックが使われ、そこで未翻訳の文字列
 * （新しいバージョンで追加した文字列など）が英語のまま表示される。
 * 同梱ファイルが無いロケールでは、従来どおり言語パックが使われる。
 */
function bf_secret_file_downloader_load_textdomain() {
    $domain = 'bf-secret-file-downloader';
    $locale = determine_locale();
    $mofile = BF_SECRET_FILE_DOWNLOADER_PLUGIN_DIR . 'languages/' . $domain . '-' . $locale . '.mo';

    // Load the bundled file only if it exists for the current locale
    // 現在のロケールの同梱ファイルがある場合のみ読み込む
    if ( is_readable( $mofile ) ) {
        load_textdomain( $domain, $mofile, $locale );
    }
}

// Load the translation before anything else on init so that the language pack is not loaded first
// 言語パックが先に読み込まれないよう、init の最初で翻訳を読み込む
add_action( 'init', 'bf_secret_file_downloader_load_textdomain', 0 );

/**
 * Initialize the plugin
 */
function bf_secret_file_downloader_init() {

    // Move the secure directory created by older versions to the hidden directory
    // 旧バージョンで作成したセキュアディレクトリを隠しディレクトリへ移行する
    \Breadfish\SecretFileDownloader\DirectoryManager::maybe_migrate_to_hidden_directory();

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
