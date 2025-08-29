<?php
/**
 * 設定ページを管理するクラス
 *
 * @package BfSecretFileDownloader
 */

namespace Breadfish\SecretFileDownloader\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * SettingsPage class
 * Manage the settings page
 */
class SettingsPage {

    /**
     * Page slug
     */
    const PAGE_SLUG = 'bf-secret-file-downloader-settings';

    /**
     * Constructor
     */
    public function __construct() {
        // Constructor does not register hooks
    }

    /**
     * Initialize hooks
     */
    public function init() {
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'wp_ajax_bf_sfd_reset_settings', array( $this, 'ajax_reset_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

        // Clear session on setting change
        add_action( 'update_option_bf_sfd_auth_methods', array( $this, 'clear_sessions_on_auth_change' ) );
        add_action( 'update_option_bf_sfd_simple_auth_password', array( $this, 'clear_sessions_on_auth_change' ) );
        add_action( 'update_option_bf_sfd_allowed_roles', array( $this, 'clear_sessions_on_auth_change' ) );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting( 'bf_sfd_settings', 'bf_sfd_max_file_size', array(
            'type' => 'integer',
            'default' => 10,
            'sanitize_callback' => array( $this, 'sanitize_file_size' )
        ) );

        // Add authentication settings
        register_setting( 'bf_sfd_settings', 'bf_sfd_auth_methods', array(
            'type' => 'array',
            'default' => array(),
            'sanitize_callback' => array( $this, 'sanitize_auth_methods' )
        ) );

        // Allowed roles
        register_setting( 'bf_sfd_settings', 'bf_sfd_allowed_roles', array(
            'type' => 'array',
            'default' => array(),
            'sanitize_callback' => array( $this, 'sanitize_roles' )
        ) );

        // Password for simple authentication
        register_setting( 'bf_sfd_settings', 'bf_sfd_simple_auth_password', array(
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => array( $this, 'sanitize_password' )
        ) );

        // Add authentication timeout settings
        register_setting( 'bf_sfd_settings', 'bf_sfd_auth_timeout', array(
            'type' => 'integer',
            'default' => 30,
            'sanitize_callback' => array( $this, 'sanitize_auth_timeout' )
        ) );

        // Menu title settings
        register_setting( 'bf_sfd_settings', 'bf_sfd_menu_title', array(
            'type' => 'string',
            'default' => __('BF Secret File Downloader', 'bf-secret-file-downloader' ),
            'sanitize_callback' => array( $this, 'sanitize_menu_title' )
        ) );

        // Editor admin permission settings
        register_setting( 'bf_sfd_settings', 'bf_sfd_allow_editor_admin', array(
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => array( $this, 'sanitize_boolean' )
        ) );
    }

    /**
     * Reset settings
     */
    public function ajax_reset_settings() {
        // Security check
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        check_ajax_referer( 'bf_sfd_browse_nonce', 'nonce' );

        // Check file deletion option
        $delete_files = isset( $_POST['delete_files'] ) && $_POST['delete_files'] === 'true';

        // Process directory and files
        if ( $delete_files ) {
            // Delete all files and settings
            \Breadfish\SecretFileDownloader\DirectoryManager::remove_secure_directory( true );
            $message = 'すべての設定とファイルがリセットされました。新しいセキュアディレクトリが作成されました。';
        } else {
            // Keep old directory and create new secure directory
            $message = '設定がリセットされました。新しいセキュアディレクトリが作成され、旧ディレクトリのファイルは保持されています。';
        }

        // Create new secure directory
        \Breadfish\SecretFileDownloader\DirectoryManager::create_secure_directory( true );

        // Delete other settings
        delete_option( 'bf_sfd_max_file_size' );
        delete_option( 'bf_sfd_auth_methods' );
        delete_option( 'bf_sfd_allowed_roles' );
        delete_option( 'bf_sfd_simple_auth_password' );
        delete_option( 'bf_sfd_allow_editor_admin' );
        delete_option( 'bf_sfd_auth_timeout' );
        delete_option( 'bf_sfd_auth_settings_changed' );

        // Clear authentication sessions
        $this->clear_all_auth_sessions();

        // Clear directory passwords
        $this->clear_all_directory_passwords();

        wp_send_json_success( array( 'message' => $message ) );
    }

    /**
     * Render the page
     */
        public function render() {
        // Prepare data for the view
        $import = $this->prepare_data();

        // Render the view using ViewRenderer
        \Breadfish\SecretFileDownloader\ViewRenderer::admin( 'settings.php', $import );
    }

    /**
     * Prepare data for the view
     *
     * @return array Data for the view
     */
    private function prepare_data() {
        return array(
            'enable_auth' => $this->get_enable_auth(),
            'max_file_size' => $this->get_max_file_size(),
            'log_downloads' => $this->get_log_downloads(),
            'security_level' => $this->get_security_level(),
            'target_directory' => $this->get_target_directory(),
            'auth_methods' => $this->get_auth_methods(),
            'allowed_roles' => $this->get_allowed_roles(),
            'simple_auth_password' => $this->get_simple_auth_password(),
            'menu_title' => $this->get_plugin_menu_title(),
            'allow_editor_admin' => $this->get_allow_editor_admin(),
            'auth_timeout' => $this->get_auth_timeout(),

            'nonce' => wp_create_nonce( 'bf_sfd_browse_nonce' ),
        );
    }

    /**
     * Get BASIC authentication settings
     *
     * @return bool BASIC authentication enabled flag
     */
    private function get_enable_auth() {
        return (bool) get_option( 'bf_sfd_enable_auth', false );
    }

    /**
     * Get maximum file size settings
     *
     * @return int Maximum file size (MB)
     */
    private function get_max_file_size() {
        return (int) get_option( 'bf_sfd_max_file_size', 10 );
    }

    /**
     * Get download log settings
     *
     * @return bool Download log enabled flag
     */
    private function get_log_downloads() {
        return (bool) get_option( 'bf_sfd_log_downloads', true );
    }

    /**
     * Get security level settings
     *
     * @return string Security level
     */
    private function get_security_level() {
        return get_option( 'bf_sfd_security_level', 'medium' );
    }

    /**
     * Get target directory settings
     *
     * @return string Target directory
     */
    private function get_target_directory() {
        return \Breadfish\SecretFileDownloader\DirectoryManager::get_secure_directory();
    }

    /**
     * Get authentication method settings
     *
     * @return array Authentication method array
     */
    private function get_auth_methods() {
        return get_option( 'bf_sfd_auth_methods', array() );
    }

    /**
     * Get allowed user roles settings
     *
     * @return array Allowed user roles array
     */
    private function get_allowed_roles() {
        return get_option( 'bf_sfd_allowed_roles', array() );
    }

    /**
     * Get simple authentication password settings
     *
     * @return string Simple authentication password
     */
    private function get_simple_auth_password() {
        return get_option( 'bf_sfd_simple_auth_password', '' );
    }

    /**
     * Get plugin menu title settings
     *
     * @return string Plugin menu title
     */
    private function get_plugin_menu_title() {
        return get_option( 'bf_sfd_menu_title', __('BF Secret File Downloader', 'bf-secret-file-downloader' ) );
    }

    /**
     * Get editor admin permission settings
     *
     * @return bool Editor admin permission enabled flag
     */
    private function get_allow_editor_admin() {
        return (bool) get_option( 'bf_sfd_allow_editor_admin', false );
    }

    /**
     * Get authentication timeout settings
     *
     * @return int Authentication timeout (minutes)
     */
    private function get_auth_timeout() {
        return (int) get_option( 'bf_sfd_auth_timeout', 30 );
    }

    /**
     * Sanitize boolean value
     *
     * @param mixed $value Value
     * @return bool Sanitized boolean value
     */
    public function sanitize_boolean( $value ) {
        return (bool) $value;
    }

    /**
     * Sanitize menu title
     *
     * @param string $value Menu title
     * @return string Sanitized menu title
     */
    public function sanitize_menu_title( $value ) {
        $sanitized = sanitize_text_field( trim( $value ) );

        // 空の場合はデフォルト値を返す
        if ( empty( $sanitized ) ) {
            return __('BF Secret File Downloader', 'bf-secret-file-downloader' );
        }

        // 最大文字数制限（50文字まで）
        return mb_substr( $sanitized, 0, 50 );
    }

    /**
     * Sanitize simple authentication password
     *
     * @param string $value Password
     * @return string Sanitized password
     */
    public function sanitize_password( $value ) {
        $sanitized_value = sanitize_text_field( $value );

        // Nonce check
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'bf_sfd_settings-options' ) ) {
            return $sanitized_value;
        }

        // Check if simple authentication is enabled
        $auth_methods = array_map( 'sanitize_text_field', wp_unslash( $_POST['bf_sfd_auth_methods'] ?? array() ) );
        if ( is_array( $auth_methods ) && in_array( 'simple_auth', $auth_methods ) ) {
            // If simple authentication is enabled and password is empty
            if ( empty( $sanitized_value ) ) {
                add_settings_error(
                    'bf_sfd_simple_auth_password',
                    'password_required',
                    __('A password is required when enabling simple authentication.', 'bf-secret-file-downloader' ),
                    'error'
                );
                // Keep current password (do not empty)
                return get_option( 'bf_sfd_simple_auth_password', '' );
            }
        }

        return $sanitized_value;
    }

    /**
     * Sanitize file size
     *
     * @param mixed $value File size
     * @return int Sanitized file size
     */
    public function sanitize_file_size( $value ) {
        $size = (int) $value;
        return max( 1, min( 100, $size ) ); // 1-100MB range
    }

    /**
     * Sanitize authentication methods
     *
     * @param array $value Authentication methods array
     * @return array Sanitized authentication methods array
     */
    public function sanitize_auth_methods( $value ) {
        $allowed_methods = array( 'logged_in', 'simple_auth' );

        // If $value is null or not an array, return empty array
        if ( ! is_array( $value ) ) {
            return array();
        }

        // Sanitize
        $sanitized_value = array_map( 'sanitize_text_field', $value );

        // Keep input order and return only allowed methods
        $result = array();
        foreach ( $sanitized_value as $method ) {
            if ( in_array( $method, $allowed_methods ) ) {
                $result[] = $method;
            }
        }

        return array_values( array_unique( $result ) );
    }

    /**
     * Sanitize authentication timeout
     *
     * @param mixed $value Authentication timeout (minutes)
     * @return int Sanitized authentication timeout
     */
    public function sanitize_auth_timeout( $value ) {
        $timeout = (int) $value;
        return max( 1, min( 1440, $timeout ) ); // 1分-24時間の範囲に制限
    }

    /**
     * Sanitize allowed user roles
     *
     * @param array $value User roles array
     * @return array Sanitized user roles array
     */
    public function sanitize_roles( $value ) {
        $allowed_roles = array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' );

        // $valueがnullまたは配列でない場合は空配列を返す
        if ( ! is_array( $value ) ) {
            return array();
        }

        // Sanitize
        $sanitized_value = array_map( 'sanitize_text_field', $value );

        // Keep input order and return only allowed roles
        $result = array();
        foreach ( $sanitized_value as $role ) {
            if ( in_array( $role, $allowed_roles ) ) {
                $result[] = $role;
            }
        }

        return array_values( array_unique( $result ) );
    }

    /**
     * Clear all directory passwords
     */
    private function clear_all_directory_passwords() {
        delete_option( 'bf_sfd_directory_passwords' );
    }

    /**
     * Clear all authentication sessions
     */
    private function clear_all_auth_sessions() {
        // Test environment check (multiple constants)
        $is_test_env = defined( 'PHPUNIT_COMPOSER_INSTALL' ) ||
                       defined( 'WP_TESTS_CONFIG_FILE_PATH' ) ||
                       ( defined( 'WP_RUN_CORE_TESTS' ) && WP_RUN_CORE_TESTS );

        if ( ! $is_test_env ) {
            // If session is not started, start it
            if ( ! session_id() ) {
                @session_start();
            }
        }

        // Clear authentication related session variables
        if ( isset( $_SESSION ) ) {
            unset( $_SESSION['bf_simple_auth_verified'] );
            unset( $_SESSION['bf_directory_simple_auth_verified'] );
            unset( $_SESSION['bf_auth_timestamp'] );
        }

      }

    /**
     * Update timestamp when authentication settings change
     */
    public function clear_sessions_on_auth_change() {
        // Record the time of setting change (to invalidate all users' authentication)
        update_option( 'bf_sfd_auth_settings_changed', time() );
    }

    /**
     * Get page title
     *
     * @return string Page title
     */
    public function get_page_title() {
        return __('Settings', 'bf-secret-file-downloader' );
    }

    /**
     * Get menu title
     *
     * @return string Menu title
     */
    public function get_menu_title() {
        return __('Settings', 'bf-secret-file-downloader' );
    }

    /**
     * Enqueue admin assets (CSS/JS)
     *
     * @param string $hook_suffix Current admin screen hook suffix
     */
    public function enqueue_admin_assets( $hook_suffix ) {
        // Load assets only on settings page
        if ( strpos( $hook_suffix, self::PAGE_SLUG ) === false ) {
            return;
        }

        // Enqueue CSS file
        wp_enqueue_style(
            'bf-sfd-admin-settings',
            plugin_dir_url( dirname( dirname( __FILE__ ) ) ) . 'assets/css/admin-settings.css',
            array(),
            '1.0.0'
        );
    }

}