<?php
/**
 * Manage admin menu
 *
 * @package BfSecretFileDownloader
 */

namespace Breadfish\SecretFileDownloader;

// Security check: prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin class
 * Manage WordPress admin menu and route to each page class
 */
class Admin {

    /**
     * File list page instance
     *
     * @var \Breadfish\SecretFileDownloader\Admin\FileListPage
     */
    private $file_list_page;

    /**
     * Settings page instance
     *
     * @var \Breadfish\SecretFileDownloader\Admin\SettingsPage
     */
    private $settings_page;

    /**
     * Constructor
     */
    public function __construct() {
        // Do not register hooks in constructor
        $this->file_list_page = new \Breadfish\SecretFileDownloader\Admin\FileListPage();
        $this->settings_page = new \Breadfish\SecretFileDownloader\Admin\SettingsPage();
    }

    /**
     * Initialize hooks
     */
    public function init() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

        // Show notices about the secure directory
        // セキュアディレクトリに関する通知を表示する
        add_action( 'admin_notices', array( $this, 'render_directory_notices' ) );

        // Initialize each page
        $this->file_list_page->init();
        $this->settings_page->init();
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Check editor admin privilege setting
        $allow_editor_admin = (bool) get_option( 'bf_sfd_allow_editor_admin', false );

        // If editor admin privilege is disabled and current user is editor, do not show menu
        if ( ! $allow_editor_admin && current_user_can( 'editor' ) && ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Get menu title from settings
        $menu_title = get_option( 'bf_sfd_menu_title', __('BF Secret File Downloader', 'bf-secret-file-downloader' ) );

        // Determine file list capability
        $file_capability = $this->get_file_access_capability();

        // Add main menu page
        add_menu_page(
            $menu_title, // Page title
            $menu_title, // Menu title
            $file_capability, // Capability
            $this->file_list_page::PAGE_SLUG, // Menu slug
            array( $this->file_list_page, 'render' ), // Callback function
            'dashicons-lock', // Icon
            30 // Menu position
        );

        // Add submenu page
        add_submenu_page(
            $this->file_list_page::PAGE_SLUG, // Parent menu slug
            $this->file_list_page->get_page_title(), // Page title
            $this->file_list_page->get_menu_title(), // Menu title
            $file_capability, // Capability
            $this->file_list_page::PAGE_SLUG, // Menu slug (same as main page)
            array( $this->file_list_page, 'render' ) // Callback function
        );

        add_submenu_page(
            $this->file_list_page::PAGE_SLUG, // Parent menu slug
            $this->settings_page->get_page_title(), // Page title
            $this->settings_page->get_menu_title(), // Menu title
            'manage_options', // Capability
            $this->settings_page::PAGE_SLUG, // Menu slug
            array( $this->settings_page, 'render' ) // Callback function
        );
    }

    /**
     * Render notices about the secure directory
     * セキュアディレクトリに関する通知を表示する
     *
     * - A one-time notice after the directory was moved to the hidden directory
     * - A warning on the plugin's screens when files can be downloaded directly
     * - 隠しディレクトリへ移動した後に一度だけ出す通知
     * - ファイルを直接ダウンロードできる状態のとき、プラグインの画面に出す警告
     */
    public function render_directory_notices() {
        // Only administrators can act on these notices
        // 通知に対応できるのは管理者のみ
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // One-time notice: the location for FTP uploads has changed
        // 一度だけの通知: FTP でのアップロード先が変わった
        if ( get_option( DirectoryManager::MOVED_NOTICE_OPTION, false ) ) {
            delete_option( DirectoryManager::MOVED_NOTICE_OPTION );
            ?>
            <div class="notice notice-info is-dismissible">
                <p><strong><?php esc_html_e( 'BF Secret File Downloader', 'bf-secret-file-downloader' ); ?></strong></p>
                <p><?php esc_html_e( 'To improve protection, the secure directory has been moved to a hidden directory (a directory whose name starts with a dot).', 'bf-secret-file-downloader' ); ?></p>
                <p><?php esc_html_e( 'If you upload files via FTP, please use the following directory from now on.', 'bf-secret-file-downloader' ); ?></p>
                <p><code><?php echo esc_html( DirectoryManager::get_secure_directory() ); ?></code></p>
                <p><?php esc_html_e( 'If the directory is not shown in your FTP client, enable the option to show hidden files.', 'bf-secret-file-downloader' ); ?></p>
            </div>
            <?php
        }

        // The protection check sends an HTTP request, so run it only on the plugin's screens
        // 保護状態の確認は HTTP リクエストを伴うため、プラグインの画面でのみ行う
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( ! in_array( $page, array( $this->file_list_page::PAGE_SLUG, $this->settings_page::PAGE_SLUG ), true ) ) {
            return;
        }

        if ( ! DirectoryManager::secure_directory_exists() ) {
            return;
        }

        if ( DirectoryManager::get_protection_status() !== DirectoryManager::STATUS_UNPROTECTED ) {
            return;
        }
        ?>
        <div class="notice notice-warning">
            <p><strong><?php esc_html_e( 'Files in the secure directory can be accessed directly.', 'bf-secret-file-downloader' ); ?></strong></p>
            <p><?php esc_html_e( 'Your web server does not block direct access to the secure directory.', 'bf-secret-file-downloader' ); ?></p>
            <p><?php esc_html_e( 'The directory name is random and not shown on public pages, so it is hard to guess, but anyone who learns the URL can download the files without authentication.', 'bf-secret-file-downloader' ); ?></p>
            <p><?php esc_html_e( 'If you use Nginx, add the following setting to the server configuration, or ask your hosting provider to add it.', 'bf-secret-file-downloader' ); ?></p>
            <p><code><?php echo esc_html( DirectoryManager::get_nginx_deny_rule() ); ?></code></p>
        </div>
        <?php
    }

    /**
     * Get file access capability
     *
     * @return string Capability string
     */
    private function get_file_access_capability() {
        $allow_editor_admin = (bool) get_option( 'bf_sfd_allow_editor_admin', false );

        // If editor admin privilege is enabled, editor or higher, otherwise only admin
        return $allow_editor_admin ? 'edit_posts' : 'manage_options';
    }

    /**
     * Check if current user has file access capability
     *
     * @return bool File access capability
     */
    public function can_access_files() {
        $capability = $this->get_file_access_capability();
        return current_user_can( $capability );
    }
}
