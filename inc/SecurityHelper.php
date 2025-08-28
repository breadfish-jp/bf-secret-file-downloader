<?php
/**
 * SecurityHelper class
 * Provides security-related helper functions
 *
 * @package BfSecretFileDownloader
 */

namespace Breadfish\SecretFileDownloader;

// Security check: prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * SecurityHelper class
 * Provides directory traversal prevention and file access control functionality
 */
class SecurityHelper {

    /**
     * Build a safe path (prevent directory traversal)
     *
     * @param string $base_directory base directory
     * @param string $relative_path relative path
     * @return string safe path
     */
    public static function build_safe_path( $base_directory, $relative_path ) {

        // If the relative path is empty, return the base directory
        if ( empty( $relative_path ) ) {
            return $base_directory;
        }

        // Check for dangerous strings
        if ( strpos( $relative_path, '..' ) !== false || strpos( $relative_path, '//' ) !== false ) {
            return $base_directory;
        }

        // Build the path
        $full_path = $base_directory . DIRECTORY_SEPARATOR . ltrim( $relative_path, DIRECTORY_SEPARATOR );

        // Normalize the path
        $normalized_path = realpath( $full_path );

        // If the normalization fails or the path is outside the base directory, return the base directory
        $real_base = realpath( $base_directory );

        if ( $normalized_path === false || strpos( $normalized_path, $real_base ) !== 0 ) {
            return $base_directory;
        }

        return $normalized_path;
    }

    /**
     * Check if directory access is allowed
     *
     * @param string $path the path to check
     * @return bool true if allowed
     */
    public static function is_allowed_directory( $path ) {

        $real_path = realpath( $path );

        if ( $real_path === false ) {
            return false;
        }

        // If it is a symbolic link, reject it
        if ( is_link( $path ) ) {
             return false;
        }

        // Get the target directory
        $target_directory = \Breadfish\SecretFileDownloader\DirectoryManager::get_secure_directory();

        if ( empty( $target_directory ) ) {
            return false;
        }

        $real_target_directory = realpath( $target_directory );

        if ( $real_target_directory === false ) {
            return false;
        }

        // Allow only directories within the secure directory and that exist
        $within_target = strpos( $real_path, $real_target_directory ) === 0;
        $is_dir_check = is_dir( $real_path );

        $result = $within_target && $is_dir_check;

        return $result;
    }

    /**
     * Check the security of file upload
     *
     * @param string $filename file name
     * @param string $target_path target path
     * @return array check result
     */
    public static function check_file_upload_security( $filename, $target_path ) {
        // Check if the directory is allowed
        // If target_path is a directory, use it as is, otherwise use dirname()
        $check_dir = is_dir( $target_path ) ? $target_path : dirname( $target_path );

        if ( ! self::is_allowed_directory( $check_dir ) ) {
            return array( 'allowed' => false, 'error_message' => 'アップロード先ディレクトリへのアクセスが許可されていません。' );
        }

        // Basic file name check (program code files are checked separately)
        if ( empty( $filename ) || strpos( $filename, '..' ) !== false ) {
            return array( 'allowed' => false, 'error_message' => '無効なファイル名です。' );
        }

        return array( 'allowed' => true, 'error_message' => '' );
    }

    /**
     * Check the security of directory creation
     *
     * @param string $parent_path parent directory path
     * @param string $directory_name directory name to create
     * @return array check result
     */
    public static function check_ajax_create_directory_security( $parent_path, $directory_name ) {
        // Check if the parent directory is allowed
        if ( ! self::is_allowed_directory( $parent_path ) ) {
            return array( 'allowed' => false, 'error_message' => 'ディレクトリ作成が許可されていません。' );
        }

        // Basic directory name check
        if ( empty( $directory_name ) || strpos( $directory_name, '..' ) !== false || strpos( $directory_name, '/' ) !== false ) {
            return array( 'allowed' => false, 'error_message' => '無効なディレクトリ名です。' );
        }

        return array( 'allowed' => true, 'error_message' => '' );
    }

    /**
     * Check if the file name is a program code file
     *
     * @param string $filename file name
     * @return bool true if it is a program code file
     */
    public static function is_program_code_file( $filename ) {
        $program_extensions = array(
            'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phps',
            'js', 'jsx', 'ts', 'tsx', 'vue',
            'py', 'rb', 'pl', 'sh', 'bash', 'zsh', 'fish',
            'java', 'class', 'jar',
            'c', 'cpp', 'cc', 'cxx', 'h', 'hpp',
            'cs', 'vb', 'asp', 'aspx',
            'go', 'rs', 'swift', 'kt', 'scala',
            'sql', 'db', 'sqlite', 'sqlite3',
            'xml', 'xsl', 'xslt',
            'htaccess', 'htpasswd',
            'config', 'conf', 'cfg', 'ini', 'env',
            'yml', 'yaml', 'toml'
        );

        $extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

        if ( in_array( $extension, $program_extensions ) ) {
            return true;
        }

        // Check for common file name patterns in configuration files
        $dangerous_patterns = array(
            'wp-config', 'config', 'configuration', 'settings',
            '.env', '.htaccess', '.htpasswd', 'composer', 'package',
            'makefile', 'dockerfile', 'vagrantfile'
        );

        $filename_lower = strtolower( $filename );
        foreach ( $dangerous_patterns as $pattern ) {
            if ( strpos( $filename_lower, $pattern ) !== false ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the path contains a null byte
     *
     * @param string $path the path to check
     * @return bool true if it contains a null byte
     */
    public static function contains_null_byte( $path ) {
        return strpos( $path, "\0" ) !== false;
    }

    /**
     * Check if the path is an absolute path
     *
     * @param string $path the path to check
     * @return bool true if it is an absolute path
     */
    public static function is_absolute_path( $path ) {
        // Unix-like absolute path
        if ( substr( $path, 0, 1 ) === '/' ) {
            return true;
        }

        // Windows absolute path (e.g. C:\)
        if ( preg_match( '/^[a-zA-Z]:[\\\\\/]/', $path ) ) {
            return true;
        }

        return false;
    }
}