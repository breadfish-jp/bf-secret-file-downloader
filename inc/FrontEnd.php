<?php
/**
 * Manages the file downloader on the frontend
 *
 * @package BfSecretFileDownloader
 */

namespace Breadfish\SecretFileDownloader;

use Breadfish\SecretFileDownloader\SecurityHelper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * FrontEnd class
 * Manages the file downloader on the frontend
 */
class FrontEnd {

    /**
     * Constructor
     */
    public function __construct() {
        // Do not register hooks in the constructor
    }

    /**
     * Initialize hooks
     */
    public function init() {
        // Start session
        if ( ! session_id() ) {
            session_start();
        }

        // Hook the file downloader on the frontend
        add_action( 'template_redirect', array( $this, 'handle_file_download' ) );
    }

    /**
     * Handle file download on the frontend
     */
    public function handle_file_download() {
        // Check if the path parameter exists (check for download request)
        $file_path = wp_unslash( $_GET['path'] ?? '' );
        // Basic sanitization of the path (remove null bytes and control characters)
        $file_path = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $file_path );
        // Additional sanitization (remove HTML entities and special characters)
        $file_path = htmlspecialchars_decode( $file_path, ENT_QUOTES | ENT_HTML5 );
        $file_path = wp_strip_all_tags( $file_path );
        if ( empty( $file_path ) ) {
            return; // If it is not a download request, end processing
        }

        // Get the download flag (default is download)
        $download_flag = sanitize_text_field( wp_unslash( $_GET['dflag'] ?? 'download' ) );

        // Get the base directory
        $base_directory = \Breadfish\SecretFileDownloader\DirectoryManager::get_secure_directory();
        if ( empty( $base_directory ) ) {
            wp_die( esc_html( __('Target directory is not configured.', 'bf-secret-file-downloader' ) ), 500 );
        }

        // Build the full path
        $full_path = SecurityHelper::build_safe_path( $base_directory, $file_path );

        // Security check: only allowed directories
        // If the file exists, check the directory of the file
        // If the file does not exist, check the directory returned by build_safe_path
        $check_directory = is_file( $full_path ) ? dirname( $full_path ) : $full_path;
        $directory_check = SecurityHelper::is_allowed_directory( $check_directory );


        if ( ! $directory_check ) {
            wp_die( esc_html( __('Access to this file is not allowed.', 'bf-secret-file-downloader' ) ), 403 );
        }

        // Check if the file exists
        if ( ! file_exists( $full_path ) || ! is_file( $full_path ) ) {
            wp_die( esc_html( __('The specified file was not found.', 'bf-secret-file-downloader' ) ), 404 );
        }

        // Check for access to dangerous files
        $filename = basename( $full_path );
        if ( SecurityHelper::is_program_code_file( $filename ) ) {
            wp_die( esc_html( __('Access to this file is not allowed.', 'bf-secret-file-downloader' ) ), 403 );
        }

        // Check for read permission
        if ( ! is_readable( $full_path ) ) {
            wp_die( esc_html( __('You do not have permission to read this file.', 'bf-secret-file-downloader' ) ), 403 );
        }

        // Check authentication
        $auth_result = $this->check_authentication();

        if ( ! $auth_result ) {
            $this->show_authentication_form();
            exit;
        }

        // Get file information
        $filename = basename( $full_path );
        $filesize = filesize( $full_path );
        $mime_type = wp_check_filetype( $filename )['type'] ?? 'application/octet-stream';

        // Record download log (if enabled)
        if ( get_option( 'bf_sfd_log_downloads', false ) ) {
            $this->log_download( $file_path, $filename );
        }

        // Set headers
        if ( ! headers_sent() ) {
            // Cache control
            header( 'Cache-Control: no-cache, must-revalidate' );
            header( 'Pragma: no-cache' );
            header( 'Expires: 0' );

            if ( $download_flag === 'display' ) {
                // Display on the spot
                header( 'Content-Type: ' . $mime_type );
                header( 'Content-Length: ' . $filesize );
            } else {
                // Download
                header( 'Content-Type: ' . $mime_type );
                header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
                header( 'Content-Length: ' . $filesize );
            }

            // Output the file
            global $wp_filesystem;
            if ( empty( $wp_filesystem ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                WP_Filesystem();
            }

            // Get the file content and output it
            $file_content = $wp_filesystem->get_contents( $full_path );
            if ( $file_content !== false ) {
                echo $file_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
        }

        exit;
    }

    /**
     * Record download log
     *
     * @param string $file_path the file path
     * @param string $filename the file name
     */
    private function log_download( $file_path, $filename ) {
        $log_entry = array(
            'timestamp' => current_time( 'mysql' ),
            'file_path' => $file_path,
            'filename' => $filename,
            'user_id' => get_current_user_id(),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) )
        );

        // Save the log to the database (simplified version)
        $download_logs = get_option( 'bf_sfd_download_logs', array() );
        $download_logs[] = $log_entry;

        // Limit the number of logs (latest 1000 items)
        if ( count( $download_logs ) > 1000 ) {
            $download_logs = array_slice( $download_logs, -1000 );
        }

        update_option( 'bf_sfd_download_logs', $download_logs );
    }

    /**
     * Get the client's IP address
     *
     * @return string IP address
     */
    private function get_client_ip() {
        $ip_keys = array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );

        foreach ( $ip_keys as $key ) {
            if ( array_key_exists( $key, $_SERVER ) === true ) {
                foreach ( explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) ) as $ip ) {
                    $ip = trim( $ip );
                    if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) !== false ) {
                        return $ip;
                    }
                }
            }
        }

        return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' ) );
    }

        /**
     * Check authentication
     *
     * @return bool true if authentication is successful
     */
    private function check_authentication() {

        // Nonce verification (for POST requests)
        if ( isset( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] === 'POST' && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'bf_sfd_auth' ) ) {
            return false;
        }

        // Get the directory path from the current file path
        $file_path = wp_unslash( $_GET['path'] ?? '' );
        // Basic sanitization of the path (remove null bytes and control characters)
        $file_path = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $file_path );
        // Additional sanitization (remove HTML entities and special characters)
        $file_path = htmlspecialchars_decode( $file_path, ENT_QUOTES | ENT_HTML5 );
        $file_path = wp_strip_all_tags( $file_path );
        $directory_path = dirname( $file_path );
        if ( $directory_path === '.' ) {
            $directory_path = '';
        }


        // Check directory-specific authentication settings
        $directory_auth = $this->get_directory_auth( $directory_path );

        if ( $directory_auth !== false ) {
            // Directory-specific authentication settings take precedence
            $result = $this->check_directory_auth( $directory_auth );
            return $result;
        }

        // Check common authentication settings
        $auth_methods = get_option( 'bf_sfd_auth_methods', array( 'logged_in' ) );

        // If no authentication method is set, deny access
        if ( empty( $auth_methods ) ) {
            return false;
        }

        // Check logged-in user authentication
        if ( in_array( 'logged_in', $auth_methods ) ) {
            if ( is_user_logged_in() ) {
                // Check user role
                if ( $this->check_user_role() ) {
                    // Record timestamp even for logged-in users
                    if ( ! isset( $_SESSION['bf_auth_timestamp'] ) ) {
                        $_SESSION['bf_auth_timestamp'] = time();
                    }
                    return true;
                }
            }
        }

        // Check simple authentication
        if ( in_array( 'simple_auth', $auth_methods ) ) {
            $simple_auth_result = $this->check_simple_auth();
            if ( $simple_auth_result ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check user role
     *
     * @return bool true if the role is allowed
     */
    private function check_user_role() {
        $allowed_roles = get_option( 'bf_sfd_allowed_roles', array( 'administrator' ) );

        if ( empty( $allowed_roles ) ) {
            return false; // ロールが選択されていない場合はアクセス拒否
        }

        $user = wp_get_current_user();
        if ( ! $user->exists() ) {
            return false;
        }

        foreach ( $allowed_roles as $role ) {
            if ( in_array( $role, $user->roles ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check directory-specific authentication settings
     *
     * @param array $directory_auth the directory authentication settings
     * @return bool true if authentication is successful
     */
    private function check_directory_auth( $directory_auth ) {
        $auth_methods = $directory_auth['auth_methods'] ?? array();
        $allowed_roles = $directory_auth['allowed_roles'] ?? array();
        $simple_auth_password = $directory_auth['simple_auth_password'] ?? '';

        // Check logged-in user authentication
        if ( in_array( 'logged_in', $auth_methods ) ) {
            if ( is_user_logged_in() ) {
                // Check user role
                if ( $this->check_user_role_for_directory( $allowed_roles ) ) {
                    // Record timestamp even for logged-in users
                    if ( ! isset( $_SESSION['bf_auth_timestamp'] ) ) {
                        $_SESSION['bf_auth_timestamp'] = time();
                    }
                    return true;
                }
            }
        }

        // Check simple authentication
        if ( in_array( 'simple_auth', $auth_methods ) ) {
            if ( $this->check_simple_auth_for_directory( $simple_auth_password ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check directory-specific user role
     *
     * @param array $allowed_roles the array of allowed user roles
     * @return bool true if the role is allowed
     */
    private function check_user_role_for_directory( $allowed_roles ) {
        if ( empty( $allowed_roles ) ) {
            return false; // If no role is selected, deny access
        }

        $user = wp_get_current_user();
        if ( ! $user->exists() ) {
            return false;
        }

        foreach ( $allowed_roles as $role ) {
            if ( in_array( $role, $user->roles ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check directory-specific simple authentication
     *
     * @param string $directory_password the directory simple authentication password
     * @return bool true if simple authentication is successful
     */
    private function check_simple_auth_for_directory( $directory_password ) {
        // Check if simple authentication is verified from the session
        if ( isset( $_SESSION['bf_directory_simple_auth_verified'] ) && $_SESSION['bf_directory_simple_auth_verified'] === true ) {
            // Check if the session has timed out
            if ( $this->is_session_timeout() ) {
                $this->clear_auth_sessions();
                return false;
            }
            return true;
        }

        // If a simple authentication password is sent via POST
        if ( isset( $_POST['simple_auth_password'] ) ) {
            $submitted_password = sanitize_text_field( wp_unslash( $_POST['simple_auth_password'] ) );

            if ( ! empty( $directory_password ) && $submitted_password === $directory_password ) {
                // Save authentication information to the session
                $_SESSION['bf_directory_simple_auth_verified'] = true;
                $_SESSION['bf_auth_timestamp'] = time();
                return true;
            }
        }

        return false;
    }

    /**
     * Get the directory authentication settings
     *
     * @param string $relative_path the relative path
     * @return array|false the authentication settings, or false if it fails
     */
    private function get_directory_auth( $relative_path ) {
        $directory_auths = get_option( 'bf_sfd_directory_auths', array() );

        if ( ! isset( $directory_auths[ $relative_path ] ) ) {
            return false;
        }

        $auth_data = $directory_auths[ $relative_path ];
        if ( ! is_array( $auth_data ) ) {
            return false;
        }

        $result = array(
            'auth_methods' => $auth_data['auth_methods'] ?? array(),
            'allowed_roles' => $auth_data['allowed_roles'] ?? array(),
        );

        // Decrypt the simple authentication password
        if ( isset( $auth_data['simple_auth_encrypted'] ) ) {
            $result['simple_auth_password'] = $this->decrypt_password( $auth_data['simple_auth_encrypted'] );
        }

        return $result;
    }

    /**
     * Check simple authentication
     *
     * @return bool true if simple authentication is successful
     */
    private function check_simple_auth() {

        // Check if simple authentication is verified from the session
        $session_verified = isset( $_SESSION['bf_simple_auth_verified'] ) && $_SESSION['bf_simple_auth_verified'] === true;

        if ( $session_verified ) {
            // Check if the session has timed out
            if ( $this->is_session_timeout() ) {
                $this->clear_auth_sessions();
                return false;
            }
            return true;
        }

        // If a simple authentication password is sent via POST
        $password_posted = isset( $_POST['simple_auth_password'] );

        if ( $password_posted ) {
            $submitted_password = sanitize_text_field( wp_unslash( $_POST['simple_auth_password'] ) );
            $stored_password = get_option( 'bf_sfd_simple_auth_password', '' );
            $password_match = ! empty( $stored_password ) && $submitted_password === $stored_password;

            if ( $password_match ) {
                // Save authentication information to the session
                $_SESSION['bf_simple_auth_verified'] = true;
                $_SESSION['bf_auth_timestamp'] = time();
                return true;
            }
        }

        return false;
    }

    /**
     * Decrypt the password
     *
     * @param string $encrypted_password the encrypted password
     * @return string|false the decrypted password, or false if it fails
     */
    private function decrypt_password( $encrypted_password ) {
        if ( ! function_exists( 'openssl_decrypt' ) ) {
            return base64_decode( $encrypted_password ); // フォールバック
        }

        $data = base64_decode( $encrypted_password );
        if ( $data === false || strlen( $data ) < 16 ) {
            return false;
        }

        $key = $this->get_encryption_key();
        $iv = substr( $data, 0, 16 );
        $encrypted = substr( $data, 16 );

        return openssl_decrypt( $encrypted, 'AES-256-CBC', $key, 0, $iv );
    }

    /**
     * Get the encryption key
     *
     * @return string the encryption key
     */
    private function get_encryption_key() {
        // Use WordPress's salt to generate the key
        $salt_keys = array( AUTH_KEY, SECURE_AUTH_KEY, LOGGED_IN_KEY, NONCE_KEY );
        return hash( 'sha256', implode( '', $salt_keys ) );
    }

    /**
     * Show the authentication form
     */
    private function show_authentication_form() {
        // Build the current URL safely (be careful not to remove slashes from REQUEST_URI)
        $https = isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';

        // Basic sanitization of REQUEST_URI (remove dangerous characters only)
        $request_uri = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $request_uri );

        // Additional sanitization (remove HTML entities and special characters)
        $request_uri = htmlspecialchars_decode( $request_uri, ENT_QUOTES | ENT_HTML5 );
        $request_uri = wp_strip_all_tags( $request_uri );
        $current_url = $https . '://' . $host . $request_uri;

        // Get the directory path from the current file path
        $file_path = wp_unslash( $_GET['path'] ?? '' );

        // Basic sanitization of the path (remove null bytes and control characters)
        $file_path = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $file_path );

        // Additional sanitization (remove HTML entities and special characters)
        $file_path = htmlspecialchars_decode( $file_path, ENT_QUOTES | ENT_HTML5 );
        $file_path = wp_strip_all_tags( $file_path );
        $directory_path = dirname( $file_path );
        if ( $directory_path === '.' ) {
            $directory_path = '';
        }

        // Get the directory-specific authentication settings
        $directory_auth = $this->get_directory_auth( $directory_path );
        if ( $directory_auth !== false ) {
            $auth_methods = $directory_auth['auth_methods'] ?? array();
        } else {
            $auth_methods = get_option( 'bf_sfd_auth_methods', array( 'logged_in' ) );
        }

        // Error display flag
        $show_error = isset( $_POST['simple_auth_password'] );

        // Show the form using ViewRenderer
        ViewRenderer::render( 'authentication-form.php', array(
            'current_url' => $current_url,
            'auth_methods' => $auth_methods,
            'show_error' => $show_error
        ), 'FrontEnd' );
    }

    /**
     * Check if the session has timed out
     *
     * @return bool true if the session has timed out
     */
    private function is_session_timeout() {
        // Get the timeout setting (in seconds)
        $timeout_minutes = get_option( 'bf_sfd_auth_timeout', 30 );
        $timeout_seconds = $timeout_minutes * 60;

        // Check if the authentication timestamp is recorded in the session
        if ( ! isset( $_SESSION['bf_auth_timestamp'] ) ) {
            return true;
        }

        // Check if the settings have been changed (forced re-authentication when the administrator changes the settings)
        $settings_changed_time = get_option( 'bf_sfd_auth_settings_changed', 0 );
        $auth_timestamp = intval( $_SESSION['bf_auth_timestamp'] );
        if ( $settings_changed_time > 0 && $settings_changed_time > $auth_timestamp ) {
            return true;
        }

        // Calculate the difference between the current time and the authentication time
        $elapsed_time = time() - $auth_timestamp;

        return $elapsed_time > $timeout_seconds;
    }

    /**
     * Clear the authentication session
     */
    private function clear_auth_sessions() {
        unset( $_SESSION['bf_simple_auth_verified'] );
        unset( $_SESSION['bf_directory_simple_auth_verified'] );
        unset( $_SESSION['bf_auth_timestamp'] );

    }

}