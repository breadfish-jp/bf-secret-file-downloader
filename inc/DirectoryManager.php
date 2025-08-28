<?php
/**
 * Provides secure directory management functionality
 *
 * @package BfSecretFileDownloader
 */

namespace Breadfish\SecretFileDownloader;

// Security check: prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * DirectoryManager class
 * Provides functionality for creating, retrieving, and managing secure directories
 */
class DirectoryManager {

    /**
     * Create a secure directory
     *
     * @param bool $force_create Whether to force the creation of a new directory even if an existing directory exists
     * @return bool true on successful creation
     */
    public static function create_secure_directory( $force_create = false ) {
        // Skip if the directory ID is already set and force_create is false
        if ( ! $force_create && get_option( 'bf_sfd_secure_directory_id' ) ) {
            return true;
        }

        // Generate a random string (32 characters of alphanumeric)
        $random_id = bin2hex( random_bytes( 16 ) );

        // Create a directory under wp-content/uploads
        $uploads_dir = wp_upload_dir();
        $secure_base_dir = $uploads_dir['basedir'] . '/bf-secret-file-downloader';
        $secure_dir = $secure_base_dir . '/' . $random_id;

        // Create a directory
        if ( ! wp_mkdir_p( $secure_dir ) ) {
            return false;
        }

        // Create an .htaccess file to completely block access
        $htaccess_content = "# Deny all access\nDeny from all\n";
        file_put_contents( $secure_dir . '/.htaccess', $htaccess_content );

        // Create an index.php file to provide further protection
        $index_content = "<?php\n// Silence is golden.\nexit;";
        file_put_contents( $secure_dir . '/index.php', $index_content );

        // Update existing settings (using update_option instead of add_option)
        update_option( 'bf_sfd_secure_directory_id', $random_id );
        update_option( 'bf_sfd_target_directory', $secure_dir );

        return true;
    }

    /**
     * Get the path to the secure directory
     *
     * @return string the path to the secure directory (empty if it does not exist)
     */
    public static function get_secure_directory() {
        $secure_id = get_option( 'bf_sfd_secure_directory_id', '' );
        if ( empty( $secure_id ) ) {
            return '';
        }

        $uploads_dir = wp_upload_dir();
        return $uploads_dir['basedir'] . '/bf-secret-file-downloader/' . $secure_id;
    }

    /**
     * Check if the secure directory exists
     *
     * @return bool true if it exists
     */
    public static function secure_directory_exists() {
        $secure_dir = self::get_secure_directory();
        return ! empty( $secure_dir ) && is_dir( $secure_dir );
    }

    /**
     * Check if the secure directory's protection files are properly set
     *
     * @return bool true if the protection files are properly set
     */
    public static function is_secure_directory_protected() {
        $secure_dir = self::get_secure_directory();
        if ( empty( $secure_dir ) || ! is_dir( $secure_dir ) ) {
            return false;
        }

        // Check if the .htaccess file exists
        $htaccess_path = $secure_dir . '/.htaccess';
        if ( ! file_exists( $htaccess_path ) ) {
            return false;
        }

        // Check the content of the .htaccess file
        $htaccess_content = file_get_contents( $htaccess_path );
        if ( strpos( $htaccess_content, 'Deny from all' ) === false ) {
            return false;
        }

        // Check if the index.php file exists
        $index_path = $secure_dir . '/index.php';
        if ( ! file_exists( $index_path ) ) {
            return false;
        }

        return true;
    }

    /**
     * Repair the secure directory's protection files
     *
     * @return bool true if the repair is successful
     */
    public static function repair_secure_directory_protection() {
        $secure_dir = self::get_secure_directory();
        if ( empty( $secure_dir ) || ! is_dir( $secure_dir ) ) {
            return false;
        }

        // Re-create the .htaccess file
        $htaccess_content = "# Deny all access\nDeny from all\n";
        if ( file_put_contents( $secure_dir . '/.htaccess', $htaccess_content ) === false ) {
            return false;
        }

        // Re-create the index.php file
        $index_content = "<?php\n// Silence is golden.\nexit;";
        if ( file_put_contents( $secure_dir . '/index.php', $index_content ) === false ) {
            return false;
        }

        return true;
    }

    /**
     * Get the ID of the secure directory
     *
     * @return string the ID of the secure directory
     */
    public static function get_secure_directory_id() {
        return get_option( 'bf_sfd_secure_directory_id', '' );
    }

    /**
     * Remove the secure directory and its settings
     *
     * @param bool $delete_files Whether to delete the files (default: true)
     * @return bool true if the removal is successful
     */
    public static function remove_secure_directory( $delete_files = true ) {
        $secure_dir = self::get_secure_directory();

        if ( $delete_files && ! empty( $secure_dir ) && is_dir( $secure_dir ) ) {
            // Delete files in the directory
            $files = scandir( $secure_dir );
            foreach ( $files as $file ) {
                if ( $file !== '.' && $file !== '..' ) {
                    $file_path = $secure_dir . '/' . $file;
                    if ( is_file( $file_path ) ) {
                        wp_delete_file( $file_path );
                    }
                }
            }

            // Delete the directory
            global $wp_filesystem;
            if ( empty( $wp_filesystem ) ) {
                require_once ABSPATH . '/wp-admin/includes/file.php';
                WP_Filesystem();
            }
            $wp_filesystem->rmdir( $secure_dir );
        }

        // Delete the options
        delete_option( 'bf_sfd_secure_directory_id' );
        delete_option( 'bf_sfd_target_directory' );

        return true;
    }

    /**
     * Delete user files only in the secure directory (keep the protection files)
     *
     * @return bool true if the deletion is successful
     */
    public static function clear_user_files() {
        $secure_dir = self::get_secure_directory();

        if ( empty( $secure_dir ) || ! is_dir( $secure_dir ) ) {
            return false;
        }

        $files = scandir( $secure_dir );
        $protected_files = array( '.', '..', '.htaccess', 'index.php' );

        foreach ( $files as $file ) {
            if ( ! in_array( $file, $protected_files ) ) {
                $file_path = $secure_dir . '/' . $file;
                if ( is_file( $file_path ) ) {
                    wp_delete_file( $file_path );
                }
            }
        }

        return true;
    }
}