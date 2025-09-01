<?php
/**
 * Manage file list page
 *
 * @package BfSecretFileDownloader
 */

namespace Breadfish\SecretFileDownloader\Admin;

use Breadfish\SecretFileDownloader\SecurityHelper;
use Breadfish\SecretFileDownloader\DirectoryManager;


// Security check: prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * FileListPage class
 * Manage file list functionality
 */
class FileListPage {

    /**
     * Page slug
     */
    const PAGE_SLUG = 'bf-secret-file-downloader';

    /**
     * Number of files per page
     */
    const FILES_PER_PAGE = 20;

    /**
     * Constructor
     */
    public function __construct() {
        // Do not register hooks in constructor
    }

    /**
     * Initialize hooks
     */
    public function init() {
        add_action( 'wp_ajax_bf_sfd_browse_files', array( $this, 'ajax_browse_files' ) );
        add_action( 'wp_ajax_bf_sfd_upload_file', array( $this, 'ajax_upload_file' ) );
        add_action( 'wp_ajax_bf_sfd_create_directory', array( $this, 'ajax_create_directory' ) );
        add_action( 'wp_ajax_bf_sfd_delete_file', array( $this, 'ajax_delete_file' ) );
        add_action( 'wp_ajax_bf_sfd_bulk_delete', array( $this, 'ajax_bulk_delete' ) );
        add_action( 'wp_ajax_bf_sfd_download_file', array( $this, 'ajax_download_file' ) );
        add_action( 'wp_ajax_bf_sfd_set_directory_auth', array( $this, 'ajax_set_directory_auth' ) );
        add_action( 'wp_ajax_bf_sfd_get_directory_auth', array( $this, 'ajax_get_directory_auth' ) );
        add_action( 'wp_ajax_bf_sfd_get_global_auth', array( $this, 'ajax_get_global_auth' ) );
        add_action( 'wp_ajax_bf_sfd_recreate_secure_directory', array( $this, 'ajax_recreate_secure_directory' ) );

        add_action( 'admin_post_nopriv_bf_sfd_file_download', array( $this, 'handle_file_download' ) );
        add_action( 'admin_post_bf_sfd_file_download', array( $this, 'handle_file_download' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts( $hook ) {
        // Check if current page is the appropriate page
        if ( $hook !== 'toplevel_page_bf-secret-file-downloader' ) {
            return;
        }

        // Ensure Dashicons is loaded
        wp_enqueue_style( 'dashicons' );

        // Load jQuery
        wp_enqueue_script( 'jquery' );

        // Load admin CSS
        $css_file_path = plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'assets/css/file-list-admin.css';
        if ( file_exists( $css_file_path ) ) {
            wp_enqueue_style(
                'bf-secret-file-downloader-admin',
                plugin_dir_url( dirname( dirname( __FILE__ ) ) ) . 'assets/css/file-list-admin.css',
                array( 'dashicons' ),
                filemtime( $css_file_path )
            );
        }

        // Enqueue external JS (placeholder; logic will be added later)
        $js_file_path = plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'assets/js/file-list-admin.js';
        wp_enqueue_script(
            'bf-sfd-admin-file-list',
            plugin_dir_url( dirname( dirname( __FILE__ ) ) ) . 'assets/js/file-list-admin.js',
            array( 'jquery' ),
            file_exists( $js_file_path ) ? filemtime( $js_file_path ) : '1.0.0',
            true
        );

        // Pass initial data to JavaScript
        $initial_data = $this->prepare_data();

        wp_localize_script( 'jquery', 'bfFileListData', array(
            'initialData' => array(
                'items' => $initial_data['files'],
                'current_path' => $initial_data['current_path'],
                'current_path_display' => $initial_data['current_path_display'],
                'total_items' => $initial_data['total_files'],
                'current_page' => $initial_data['page'],
                'total_pages' => $initial_data['total_pages'],
                'current_directory_has_auth' => $initial_data['current_directory_has_auth'] ?? false,
                'current_user_can_delete' => $initial_data['current_user_can_delete'] ?? false,
                'current_user_can_upload' => $initial_data['current_user_can_upload'] ?? false,
                'current_user_can_create_dir' => $initial_data['current_user_can_create_dir'] ?? false,
                'current_user_can_manage_auth' => $initial_data['current_user_can_manage_auth'] ?? false,
            ),
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce' => $initial_data['nonce'],
            'strings' => array(
                'loading' => __('Loading...', 'bf-secret-file-downloader' ),
                'currentDirectory' => __('Current directory:', 'bf-secret-file-downloader' ),
                'rootDirectory' => __('Root directory', 'bf-secret-file-downloader' ),
                'goUp' => __('Go to parent directory', 'bf-secret-file-downloader' ),
                'authSettings' => __('Authentication settings', 'bf-secret-file-downloader' ),
                'open' => __('Open', 'bf-secret-file-downloader' ),
                'download' => __('Download', 'bf-secret-file-downloader' ),
                'copyUrl' => __('Copy URL', 'bf-secret-file-downloader' ),
                'delete' => __('Delete', 'bf-secret-file-downloader' ),
                'directory' => __('Directory', 'bf-secret-file-downloader' ),
                'file' => __('File', 'bf-secret-file-downloader' ),
                'accessDenied' => __('Access denied', 'bf-secret-file-downloader' ),
                'noFilesFound' => __('No files or directories found.', 'bf-secret-file-downloader' ),
                /* translators: %d: number of items found */
                'itemsFound' => __('%d items found.', 'bf-secret-file-downloader' ),
                'noItemsFound' => __('No items found.', 'bf-secret-file-downloader' ),
            ),
        ));
    }

    /**
     * AJAX handler for file browsing
     */
    public function ajax_browse_files() {
        // Check permissions
        if ( ! $this->can_access_files() ) {
            wp_die( 'Unauthorized' );
        }

        check_ajax_referer( 'bf_sfd_file_list_nonce', 'nonce' );


        $relative_path = sanitize_text_field( wp_unslash( $_POST['path'] ?? '' ) );
        $page = intval( $_POST['page'] ?? 1 );
        $sort_by = sanitize_text_field( wp_unslash( $_POST['sort_by'] ?? 'name' ) );
        $sort_order = sanitize_text_field( wp_unslash( $_POST['sort_order'] ?? 'asc' ) );

        // Get base directory
        $base_directory = DirectoryManager::get_secure_directory();
        if ( empty( $base_directory ) ) {
            wp_send_json_error( __('Target directory is not configured.', 'bf-secret-file-downloader' ) );
        }

        // Build full path
        $full_path = SecurityHelper::build_safe_path( $base_directory, $relative_path );

        // Security check
        if ( ! SecurityHelper::is_allowed_directory( $full_path ) ) {
            wp_send_json_error( __('Access to this directory is not allowed.', 'bf-secret-file-downloader' ) );
        }

        // Check if directory exists
        if ( ! is_dir( $full_path ) || ! is_readable( $full_path ) ) {
            wp_send_json_error( __('Cannot access directory.', 'bf-secret-file-downloader' ) );
        }

        try {
            $files_data = $this->get_directory_contents( $full_path, $relative_path, $page, $sort_by, $sort_order );
            wp_send_json_success( $files_data );
        } catch ( \Exception $e ) {
            wp_send_json_error( __('Failed to retrieve file list.', 'bf-secret-file-downloader' ) );
        }
    }

    /**
     * AJAX handler for file upload
     */
    public function ajax_upload_file() {
        // Check permissions
        if ( ! $this->can_access_files() || ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        check_ajax_referer( 'bf_sfd_file_list_nonce', 'nonce' );

        $relative_path = sanitize_text_field( wp_unslash( $_POST['target_path'] ?? '' ) );

        // Get base directory
        $base_directory = DirectoryManager::get_secure_directory();
        if ( empty( $base_directory ) ) {
            wp_send_json_error( __('Target directory is not configured.', 'bf-secret-file-downloader' ) );
        }

        // Build full path
        $target_path = SecurityHelper::build_safe_path( $base_directory, $relative_path );

        global $wp_filesystem;
        if ( empty( $wp_filesystem ) ) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }

        if ( ! is_dir( $target_path ) || ! $wp_filesystem->is_writable( $target_path ) ) {
            wp_send_json_error( __('No write permission for upload destination directory.', 'bf-secret-file-downloader' ) );
        }

        // Check if file is uploaded
        if ( ! isset( $_FILES['file'] ) || ! isset( $_FILES['file']['error'] ) || $_FILES['file']['error'] !== UPLOAD_ERR_OK ) {
            wp_send_json_error( __('File upload failed.', 'bf-secret-file-downloader' ) );
        }

        $uploaded_file = array_map( 'sanitize_text_field', $_FILES['file'] );

        // Sanitize file name
        $filename = sanitize_file_name( $uploaded_file['name'] );
        if ( empty( $filename ) ) {
            wp_send_json_error( __('Invalid filename.', 'bf-secret-file-downloader' ) );
        }

        // Security check
        $security_check = SecurityHelper::check_file_upload_security( $filename, $target_path );
        if ( ! $security_check['allowed'] ) {
            wp_send_json_error( $security_check['error_message'] );
        }

        // Prevent uploading program code files
        if ( SecurityHelper::is_program_code_file( $filename ) ) {
            wp_send_json_error( __('Cannot upload for security reasons', 'bf-secret-file-downloader' ) );
        }

        // Check file size
        $max_size = get_option( 'bf_sfd_max_file_size', 10 ) * 1024 * 1024; // MB to bytes
        if ( $uploaded_file['size'] > $max_size ) {
            wp_send_json_error( sprintf(
                /* translators: %s: maximum file size in MB */
                __('File size exceeds limit. (Maximum: %sMB)', 'bf-secret-file-downloader' ),
                get_option( 'bf_sfd_max_file_size', 10 )
            ));
        }

        // Target file path
        $target_file_path = $target_path . DIRECTORY_SEPARATOR . $filename;

        // Check if file exists
        if ( file_exists( $target_file_path ) ) {
            // Add counter to filename
            $file_info = pathinfo( $filename );
            $counter = 1;
            do {
                $new_filename = $file_info['filename'] . '_' . $counter;
                if ( isset( $file_info['extension'] ) ) {
                    $new_filename .= '.' . $file_info['extension'];
                }
                $target_file_path = $target_path . DIRECTORY_SEPARATOR . $new_filename;
                $counter++;
            } while ( file_exists( $target_file_path ) );
            $filename = $new_filename;
        }

        // Move file
        if ( $wp_filesystem->put_contents( $target_file_path, $wp_filesystem->get_contents( $uploaded_file['tmp_name'] ) ) ) {
            // Upload success
            wp_send_json_success( array(
                /* translators: %s: uploaded filename */
                'message' => sprintf( __('Uploaded %s.', 'bf-secret-file-downloader' ), $filename ),
                'filename' => $filename,
                'relative_path' => $relative_path
            ));
        } else {
            wp_send_json_error( __('Failed to save file.', 'bf-secret-file-downloader' ) );
        }
    }

    /**
     * AJAX handler for directory creation
     */
    public function ajax_create_directory() {
        // Security check
        if ( ! $this->can_access_files() || ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        check_ajax_referer( 'bf_sfd_file_list_nonce', 'nonce' );

        // Initialize WordPress Filesystem API
        global $wp_filesystem;
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();

        $relative_path = sanitize_text_field( wp_unslash( $_POST['parent_path'] ?? '' ) );
        $directory_name = sanitize_text_field( wp_unslash( $_POST['directory_name'] ?? '' ) );

        // Check input values
        if ( empty( $directory_name ) ) {
            wp_send_json_error( __('Directory name is not specified.', 'bf-secret-file-downloader' ) );
        }

        // Get base directory
        $base_directory = DirectoryManager::get_secure_directory();
        if ( empty( $base_directory ) ) {
            wp_send_json_error( __('Target directory is not configured.', 'bf-secret-file-downloader' ) );
        }

        // Build full path
        $parent_path = SecurityHelper::build_safe_path( $base_directory, $relative_path );

        // Security check
        $security_check = SecurityHelper::check_ajax_create_directory_security( $parent_path, $directory_name );
        if ( ! $security_check['allowed'] ) {
            wp_send_json_error( $security_check['error_message'] );
        }

        // Prevent creating directories starting with a dot
        if ( strpos( $directory_name, '.' ) === 0 ) {
            wp_send_json_error( __('Cannot create directory names starting with a dot.', 'bf-secret-file-downloader' ) );
        }

        // Check write permission
        if ( ! $wp_filesystem->is_writable( $parent_path ) ) {
            wp_send_json_error( __('No write permission for parent directory.', 'bf-secret-file-downloader' ) );
        }

        // New directory path
        $new_directory_path = $parent_path . DIRECTORY_SEPARATOR . $directory_name;

        // Create directory
        if ( wp_mkdir_p( $new_directory_path ) ) {
            wp_send_json_success( array(
                /* translators: %s: directory name */
                'message' => sprintf( __('Created directory \'%s\'.', 'bf-secret-file-downloader' ), $directory_name ),
                'new_directory' => $new_directory_path,
                'parent_path' => $parent_path
            ));
        } else {
            wp_send_json_error( __('Failed to create directory.', 'bf-secret-file-downloader' ) );
        }
    }

    /**
     * AJAX handler for file deletion
     */
    public function ajax_delete_file() {
        // Check permissions
        if ( ! $this->can_access_files() || ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        check_ajax_referer( 'bf_sfd_file_list_nonce', 'nonce' );

        $relative_path = sanitize_text_field( wp_unslash( $_POST['file_path'] ?? '' ) );

        // Check input values
        if ( empty( $relative_path ) ) {
            wp_send_json_error( __('File path is not specified.', 'bf-secret-file-downloader' ) );
        }

        // Get base directory
        $base_directory = DirectoryManager::get_secure_directory();
        if ( empty( $base_directory ) ) {
            wp_send_json_error( __('Target directory is not configured.', 'bf-secret-file-downloader' ) );
        }

        // Build full path
        $full_path = SecurityHelper::build_safe_path( $base_directory, $relative_path );

        // Security check: only allowed directories
        if ( ! SecurityHelper::is_allowed_directory( dirname( $full_path ) ) ) {
            wp_send_json_error( __('Deletion of this file is not allowed.', 'bf-secret-file-downloader' ) );
        }

        // Check if file exists
        if ( ! file_exists( $full_path ) ) {
            wp_send_json_error( __('The specified file was not found.', 'bf-secret-file-downloader' ) );
        }

        // Initialize WordPress Filesystem API
        global $wp_filesystem;
        if ( empty( $wp_filesystem ) ) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }

        // Check delete permission
        $parent_dir = dirname( $full_path );
        if ( ! $wp_filesystem->is_writable( $parent_dir ) ) {
            wp_send_json_error( __('You do not have permission to delete this file.', 'bf-secret-file-downloader' ) );
        }

        $filename = basename( $full_path );
        $is_directory = is_dir( $full_path );

        // Execute deletion
        if ( $is_directory ) {
            // Delete directory
            if ( $this->delete_directory_recursive( $full_path ) ) {
                // Get parent directory relative path
                $parent_relative_path = dirname( $relative_path );
                if ( $parent_relative_path === '.' ) {
                    $parent_relative_path = '';
                }

                wp_send_json_success( array(
                    /* translators: %s: directory name */
                    'message' => sprintf( __('Deleted directory \'%s\'.', 'bf-secret-file-downloader' ), $filename ),
                    'deleted_path' => $relative_path,
                    'parent_path' => $parent_relative_path,
                    'type' => 'directory'
                ));
            } else {
                wp_send_json_error( __('Failed to delete directory.', 'bf-secret-file-downloader' ) );
            }
        } else {
            // Delete file
            if ( wp_delete_file( $full_path ) ) {
                // Get parent directory relative path
                $parent_relative_path = dirname( $relative_path );
                if ( $parent_relative_path === '.' ) {
                    $parent_relative_path = '';
                }

                wp_send_json_success( array(
                    /* translators: %s: filename */
                    'message' => sprintf( __('Deleted file \'%s\'.', 'bf-secret-file-downloader' ), $filename ),
                    'deleted_path' => $relative_path,
                    'parent_path' => $parent_relative_path,
                    'type' => 'file'
                ));
            } else {
                wp_send_json_error( __('Failed to delete file.', 'bf-secret-file-downloader' ) );
            }
        }
    }

    /**
     * AJAX handler for bulk deletion
     */
    public function ajax_bulk_delete() {
        // Check permissions
        if ( ! $this->can_access_files() || ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        check_ajax_referer( 'bf_sfd_file_list_nonce', 'nonce' );

        $file_paths = array_map( 'sanitize_text_field', wp_unslash( $_POST['file_paths'] ?? array() ) );

        // Check input values
        if ( empty( $file_paths ) || ! is_array( $file_paths ) ) {
            wp_send_json_error( __('No files selected for deletion.', 'bf-secret-file-downloader' ) );
        }

        // Get base directory
        $base_directory = DirectoryManager::get_secure_directory();
        if ( empty( $base_directory ) ) {
            wp_send_json_error( __('Target directory is not configured.', 'bf-secret-file-downloader' ) );
        }

        // Initialize WordPress Filesystem API
        global $wp_filesystem;
        if ( empty( $wp_filesystem ) ) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }

        $deleted_files = array();
        $failed_files = array();
        $current_path_deleted = false;
        $redirect_path = '';

        foreach ( $file_paths as $relative_path ) {
            $relative_path = sanitize_text_field( $relative_path );

            if ( empty( $relative_path ) ) {
                continue;
            }

            // Build full path
            $full_path = SecurityHelper::build_safe_path( $base_directory, $relative_path );

            // Security check: only allowed directories
            if ( ! SecurityHelper::is_allowed_directory( dirname( $full_path ) ) ) {
                $failed_files[] = array(
                    'path' => $relative_path,
                    'error' => __('Deletion of this file is not allowed.', 'bf-secret-file-downloader' )
                );
                continue;
            }

            // Check if file exists
            if ( ! file_exists( $full_path ) ) {
                $failed_files[] = array(
                    'path' => $relative_path,
                    'error' => __('File not found.', 'bf-secret-file-downloader' )
                );
                continue;
            }

            // Check delete permission
            $parent_dir = dirname( $full_path );
            if ( ! $wp_filesystem->is_writable( $parent_dir ) ) {
                $failed_files[] = array(
                    'path' => $relative_path,
                    'error' => __('You do not have permission to delete this file.', 'bf-secret-file-downloader' )
                );
                continue;
            }

            $filename = basename( $full_path );
            $is_directory = is_dir( $full_path );

            // Execute deletion
            $delete_success = false;
            if ( $is_directory ) {
                $delete_success = $this->delete_directory_recursive( $full_path );
            } else {
                $delete_success = wp_delete_file( $full_path );
            }

            if ( $delete_success ) {
                $deleted_files[] = array(
                    'path' => $relative_path,
                    'name' => $filename,
                    'type' => $is_directory ? 'directory' : 'file'
                );

                // Check if current path was deleted
                if ( $is_directory ) {
                    $current_path = sanitize_text_field( wp_unslash( $_POST['current_path'] ?? '' ) );
                    if ( $current_path === $relative_path ||
                         ( $current_path && $relative_path && strpos( $current_path, $relative_path . '/' ) === 0 ) ) {
                        $current_path_deleted = true;
                        if ( empty( $redirect_path ) ) {
                            $redirect_path = dirname( $relative_path );
                            if ( $redirect_path === '.' ) {
                                $redirect_path = '';
                            }
                        }
                    }
                }
            } else {
                $failed_files[] = array(
                    'path' => $relative_path,
                    'error' => $is_directory ? __('Failed to delete directory.', 'bf-secret-file-downloader' ) : __('Failed to delete file.', 'bf-secret-file-downloader' )
                );
            }
        }

        // Summarize results
        $response_data = array(
            'deleted_files' => $deleted_files,
            'failed_files' => $failed_files,
            'deleted_count' => count( $deleted_files ),
            'failed_count' => count( $failed_files ),
            'current_path_deleted' => $current_path_deleted,
            'redirect_path' => $redirect_path
        );

        if ( count( $deleted_files ) > 0 && count( $failed_files ) === 0 ) {
            // All successful
            $message = sprintf(
                /* translators: %d: number of deleted items */
                _n('Deleted %d item.', 'Deleted %d items.',
                    count( $deleted_files ),
                    'bf-secret-file-downloader'
                ),
                count( $deleted_files )
            );
            $response_data['message'] = $message;
            wp_send_json_success( $response_data );
        } elseif ( count( $deleted_files ) > 0 && count( $failed_files ) > 0 ) {
            // Some successful
            $message = sprintf(
                /* translators: 1: number of deleted items, 2: number of failed items */
                __('Deleted %1$d items. Failed to delete %2$d items.', 'bf-secret-file-downloader' ),
                count( $deleted_files ),
                count( $failed_files )
            );
            $response_data['message'] = $message;
            wp_send_json_success( $response_data );
        } else {
            // All failed
            wp_send_json_error( __('Failed to delete selected items.', 'bf-secret-file-downloader' ) );
        }
    }

    /**
     * AJAX handler for file download
     */
    public function ajax_download_file() {
        // Check security
        if ( ! current_user_can( 'read' ) ) {
            wp_die( 'Unauthorized' );
        }

        check_ajax_referer( 'bf_sfd_file_list_nonce', 'nonce' );

        $relative_path = sanitize_text_field( wp_unslash( $_POST['file_path'] ?? '' ) );

        // Check input values
        if ( empty( $relative_path ) ) {
            wp_send_json_error( __('File path is not specified.', 'bf-secret-file-downloader' ) );
        }

        // Get base directory
        $base_directory = DirectoryManager::get_secure_directory();
        if ( empty( $base_directory ) ) {
            wp_send_json_error( __('Target directory is not configured.', 'bf-secret-file-downloader' ) );
        }

        // Build full path
        $full_path = SecurityHelper::build_safe_path( $base_directory, $relative_path );

        // Security check: only allowed directories
        if ( ! SecurityHelper::is_allowed_directory( dirname( $full_path ) ) ) {
            wp_send_json_error( __('Download of this file is not allowed.', 'bf-secret-file-downloader' ) );
        }

        // Check if file exists
        if ( ! file_exists( $full_path ) || ! is_file( $full_path ) ) {
            wp_send_json_error( __('The specified file was not found.', 'bf-secret-file-downloader' ) );
        }

        // Check read permission
        if ( ! is_readable( $full_path ) ) {
            wp_send_json_error( __('You do not have permission to read this file.', 'bf-secret-file-downloader' ) );
        }

        // Generate temporary token for download
        $download_token = wp_generate_password( 32, false );
        $token_data = array(
            'file_path' => $relative_path,
            'user_id' => get_current_user_id(),
            'expires' => time() + 300, // 5 minutes valid
        );

        // Save token as transient
        set_transient( 'bf_sfd_download_' . $download_token, $token_data, 300 );

        // Generate download URL
        $download_url = add_query_arg( array(
            'action' => 'bf_sfd_file_download',
            'bf_download' => $download_token
        ), admin_url( 'admin-post.php' ) );

        wp_send_json_success( array(
            'download_url' => $download_url,
            'filename' => basename( $full_path )
        ));
    }

    /**
     * File download processing
     */
    public function handle_file_download() {
        $download_token = sanitize_text_field( wp_unslash( $_GET['bf_download'] ?? '' ) );

        if ( empty( $download_token ) ) {
            /* translators: Error message for invalid download token */
            wp_die( esc_html( __('Invalid download token.', 'bf-secret-file-downloader' ) ), 400 );
        }

        // Check token
        $token_data = get_transient( 'bf_sfd_download_' . $download_token );
        if ( $token_data === false ) {
            /* translators: Error message for invalid or expired download token */
            wp_die( esc_html( __('Download token is invalid or expired.', 'bf-secret-file-downloader' ) ), 400 );
        }

        // Delete token (one-time use)
        delete_transient( 'bf_sfd_download_' . $download_token );

        // Check token expiration
        if ( time() > $token_data['expires'] ) {
            /* translators: Error message for expired download token */
            wp_die( esc_html( __('Download token has expired.', 'bf-secret-file-downloader' ) ), 400 );
        }

        // Check user permissions
        if ( ! current_user_can( 'read' ) ) {
            /* translators: Error message for insufficient download permissions */
            wp_die( esc_html( __('You do not have permission to download the file.', 'bf-secret-file-downloader' ) ), 403 );
        }

        // Get base directory
        $base_directory = DirectoryManager::get_secure_directory();
        if ( empty( $base_directory ) ) {
            /* translators: Error message for missing target directory */
            wp_die( esc_html( __('Target directory is not configured.', 'bf-secret-file-downloader' ) ), 500 );
        }

        // Build full path
        $relative_path = $token_data['file_path'];
        $full_path = SecurityHelper::build_safe_path( $base_directory, $relative_path );

        // Check security
        if ( ! SecurityHelper::is_allowed_directory( dirname( $full_path ) ) ) {
            /* translators: Error message for unauthorized file download */
            wp_die( esc_html( __('Download of this file is not allowed.', 'bf-secret-file-downloader' ) ), 403 );
        }

        // Check if file exists
        if ( ! file_exists( $full_path ) || ! is_file( $full_path ) ) {
            wp_die( esc_html( __('The specified file was not found.', 'bf-secret-file-downloader' ) ), 404 );
        }

        // Check read permission
        if ( ! is_readable( $full_path ) ) {
            wp_die( esc_html( __('You do not have permission to read this file.', 'bf-secret-file-downloader' ) ), 403 );
        }

        // Get file information
        $filename = basename( $full_path );
        $filesize = filesize( $full_path );
        $mime_type = wp_check_filetype( $filename )['type'] ?? 'application/octet-stream';

        // Set headers for download
        if ( ! headers_sent() ) {
            header( 'Content-Type: ' . $mime_type );
            header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
            header( 'Content-Length: ' . $filesize );
            header( 'Cache-Control: no-cache, must-revalidate' );
            header( 'Pragma: no-cache' );
            header( 'Expires: 0' );

            // Output file
            global $wp_filesystem;
            if ( empty( $wp_filesystem ) ) {
                require_once ABSPATH . '/wp-admin/includes/file.php';
                WP_Filesystem();
            }
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary file content output
            echo $wp_filesystem->get_contents( $full_path );
        }

        exit;
    }

    /**
     * Render page
     */
    public function render() {
        // Check permissions
        if ( ! $this->can_access_files() ) {
            wp_die( esc_html( __('You do not have permission to delete this file.', 'bf-secret-file-downloader' ) ), 403 );
        }

        // Prepare data for view
        $import = $this->prepare_data();

        // Render view using ViewRenderer
        \Breadfish\SecretFileDownloader\ViewRenderer::admin( 'file-list.php', $import );
    }

    /**
     * Get current path (relative path)
     *
     * @return string Current relative path
     */
    private function get_current_path() {
        return sanitize_text_field( wp_unslash( $_GET['path'] ?? '' ) );
    }

    /**
     * Sort items
     *
     * @param array $items Items to sort
     * @param string $sort_by Sort field
     * @param string $sort_order Sort order
     * @return array Sorted items array
     */
    private function sort_items( $items, $sort_by, $sort_order ) {
        usort( $items, function( $a, $b ) use ( $sort_by, $sort_order ) {
            $result = 0;

            switch ( $sort_by ) {
                case 'name':
                    $result = strcmp( strtolower( $a['name'] ), strtolower( $b['name'] ) );
                    break;
                case 'size':
                    // Directory size is not compared
                    if ( $a['size'] === '-' && $b['size'] === '-' ) {
                        $result = strcmp( strtolower( $a['name'] ), strtolower( $b['name'] ) );
                    } elseif ( $a['size'] === '-' ) {
                        $result = -1;
                    } elseif ( $b['size'] === '-' ) {
                        $result = 1;
                    } else {
                        $result = $a['size'] - $b['size'];
                    }
                    break;
                case 'modified':
                    $result = $a['modified'] - $b['modified'];
                    break;
                default:
                    $result = strcmp( strtolower( $a['name'] ), strtolower( $b['name'] ) );
                    break;
            }

            return $sort_order === 'desc' ? -$result : $result;
        });

        return $items;
    }

    /**
     * Get CSS class for file type
     *
     * @param string $filename File name
     * @return string CSS class
     */
    private function get_file_type_class( $filename ) {
        $extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

        // Image files
        $image_extensions = array( 'jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp', 'ico' );
        if ( in_array( $extension, $image_extensions ) ) {
            return 'image-file';
        }

        // Document files
        $document_extensions = array( 'pdf', 'doc', 'docx', 'txt', 'rtf', 'odt', 'pages' );
        if ( in_array( $extension, $document_extensions ) ) {
            return 'document-file';
        }

        // Archive files
        $archive_extensions = array( 'zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz' );
        if ( in_array( $extension, $archive_extensions ) ) {
            return 'archive-file';
        }

        return '';
    }

    /**
     * Prepare data for view
     *
     * @return array Data for view
     */
    private function prepare_data() {

        $relative_path = $this->get_current_path();
        $page = $this->get_current_page();
        $sort_by = $this->get_current_sort_by();
        $sort_order = $this->get_current_sort_order();

        // Get base directory
        $base_directory = DirectoryManager::get_secure_directory();
        if ( empty( $base_directory ) || ! is_dir( $base_directory ) ) {
            return array(
                'files' => array(),
                'total_files' => 0,
                'upload_limit' => $this->get_upload_limit(),
                'current_user_can_upload' => current_user_can( 'manage_options' ),
                'current_user_can_delete' => current_user_can( 'manage_options' ),
                'current_user_can_create_dir' => current_user_can( 'manage_options' ),
                'current_user_can_manage_auth' => current_user_can( 'manage_options' ),
                'current_path' => '',
                'current_path_display' => '',
                'page' => $page,
                'total_pages' => 0,
                'files_per_page' => self::FILES_PER_PAGE,
                'nonce' => wp_create_nonce( 'bf_sfd_file_list_nonce' ),
                'target_directory_set' => false,
                'secure_directory_exists' => false,
                'secure_directory_path' => $base_directory,
                'pagination_html' => '',
                'current_path_writable' => false,
                'max_file_size_mb' => get_option( 'bf_sfd_max_file_size', 10 ),
            );
        }

        $full_path = SecurityHelper::build_safe_path( $base_directory, $relative_path );
        $files = $this->get_files( $full_path, $relative_path, $page, $sort_by, $sort_order );
        $total_pages = $this->get_total_pages( $full_path );

        // Initialize WP_Filesystem
        global $wp_filesystem;
        if ( empty( $wp_filesystem ) ) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }

        // Add formatted size and type class to file data
        $formatted_files = array();
        foreach ( $files as $file ) {
            $formatted_file = $file;
            if ( $file['size'] !== '-' ) {
                $formatted_file['formatted_size'] = $this->format_file_size( $file['size'] );
            } else {
                $formatted_file['formatted_size'] = '-';
            }

            // Add file type class
            if ( $file['type'] === 'file' ) {
                $formatted_file['type_class'] = $this->get_file_type_class( $file['name'] );
            } else {
                $formatted_file['type_class'] = '';
            }

            // Add delete permission information
            $formatted_file['can_delete'] = current_user_can( 'manage_options' );

            $formatted_files[] = $formatted_file;
        }

        return array(
            'files' => $formatted_files,
            'total_files' => $this->get_total_files( $full_path ),
            'upload_limit' => $this->get_upload_limit(),
            'current_user_can_upload' => current_user_can( 'manage_options' ),
            'current_user_can_delete' => current_user_can( 'manage_options' ),
            'current_user_can_create_dir' => current_user_can( 'manage_options' ),
            'current_user_can_manage_auth' => current_user_can( 'manage_options' ),
            'current_path' => $relative_path,
            'current_path_display' => empty( $relative_path ) ? __('Root directory', 'bf-secret-file-downloader' ) : $relative_path,
            'page' => $page,
            'total_pages' => $total_pages,
            'files_per_page' => self::FILES_PER_PAGE,
            'nonce' => wp_create_nonce( 'bf_sfd_file_list_nonce' ),
            'target_directory_set' => true,
            'secure_directory_exists' => true,
            'pagination_html' => $this->render_pagination( $page, $total_pages, $relative_path, $sort_by, $sort_order ),
            'current_path_writable' => ! empty( $full_path ) && $wp_filesystem->is_writable( $full_path ),
            'max_file_size_mb' => get_option( 'bf_sfd_max_file_size', 10 ),
            'sort_by' => $sort_by,
            'sort_order' => $sort_order,
            'current_directory_has_auth' => $this->has_directory_auth( $relative_path ),
            'current_directory_has_password' => $this->has_directory_password( $relative_path ),
        );
    }

    /**
     * Get file list
     *
     * @param string $full_path Full path
     * @param string $relative_path Relative path
     * @param int $page Page number
     * @param string $sort_by Sort field
     * @param string $sort_order Sort order
     * @return array File list
     */
    private function get_files( $full_path = '', $relative_path = '', $page = 1, $sort_by = 'name', $sort_order = 'asc' ) {
        if ( empty( $full_path ) ) {
            return array();
        }

        if ( ! SecurityHelper::is_allowed_directory( $full_path ) || ! is_dir( $full_path ) || ! is_readable( $full_path ) ) {
            return array();
        }

        try {
            $files_data = $this->get_directory_contents( $full_path, $relative_path, $page, $sort_by, $sort_order );
            return $files_data['items'] ?? array();
        } catch ( \Exception $e ) {
            return array();
        }
    }

    /**
     * Get directory contents
     *
     * @param string $full_path Full path
     * @param string $relative_path Relative path
     * @param int $page Page number
     * @param string $sort_by Sort field
     * @param string $sort_order Sort order
     * @return array Directory contents
     */
    private function get_directory_contents( $full_path, $relative_path = '', $page = 1, $sort_by = 'name', $sort_order = 'asc' ) {
        $directories = array();
        $files = array();
        $base_directory = DirectoryManager::get_secure_directory();

        $items = scandir( $full_path );
        foreach ( $items as $item ) {
            if ( $item === '.' || $item === '..' ) {
                continue;
            }

            // Exclude hidden files (files starting with a dot)
            if ( strpos( $item, '.' ) === 0 ) {
                continue;
            }

            // Exclude program code files
            if ( SecurityHelper::is_program_code_file( $item ) ) {
                continue;
            }

            // Exclude protected files in secure directory
            if ( $item === 'index.php' ) {
                continue;
            }

            $full_item_path = $full_path . DIRECTORY_SEPARATOR . $item;

            // Build relative path
            $item_relative_path = empty( $relative_path )
                ? $item
                : $relative_path . DIRECTORY_SEPARATOR . $item;

            if ( is_dir( $full_item_path ) ) {
                $directories[] = array(
                    'name' => $item,
                    'path' => $item_relative_path,
                    'type' => 'directory',
                    'size' => '-',
                    'modified' => filemtime( $full_item_path ),
                    'readable' => is_readable( $full_item_path ),
                    'can_delete' => current_user_can( 'manage_options' ),
                );
            } else {
                $files[] = array(
                    'name' => $item,
                    'path' => $item_relative_path,
                    'type' => 'file',
                    'size' => filesize( $full_item_path ),
                    'modified' => filemtime( $full_item_path ),
                    'readable' => is_readable( $full_item_path ),
                    'type_class' => $this->get_file_type_class( $item ),
                    'can_delete' => current_user_can( 'manage_options' ),
                );
            }
        }

        // Sort processing
        $directories = $this->sort_items( $directories, $sort_by, $sort_order );
        $files = $this->sort_items( $files, $sort_by, $sort_order );

        // Combine all items (directories first)
        $all_items = array_merge( $directories, $files );
        $total_items = count( $all_items );

        // Paging processing
        $offset = ( $page - 1 ) * self::FILES_PER_PAGE;
        $paged_items = array_slice( $all_items, $offset, self::FILES_PER_PAGE );

        return array(
            'current_path' => $relative_path,
            'parent_path' => $this->get_parent_relative_path( $relative_path ),
            'items' => $paged_items,
            'total_items' => $total_items,
            'total_pages' => ceil( $total_items / self::FILES_PER_PAGE ),
            'current_page' => $page,
            'has_parent' => $this->can_navigate_to_parent( $relative_path ),
            'current_directory_has_auth' => $this->has_directory_auth( $relative_path ),
        );
    }

    /**
     * Get parent directory relative path
     *
     * @param string $relative_path Current relative path
     * @return string Parent directory relative path
     */
    private function get_parent_relative_path( $relative_path ) {
        if ( empty( $relative_path ) ) {
            return '';
        }

        $parts = explode( DIRECTORY_SEPARATOR, trim( $relative_path, DIRECTORY_SEPARATOR ) );
        array_pop( $parts );

        return implode( DIRECTORY_SEPARATOR, $parts );
    }

    /**
     * Check if navigation to parent directory is possible
     *
     * @param string $relative_path Current relative path
     * @return bool Navigation possible flag
     */
    private function can_navigate_to_parent( $relative_path ) {
        // If it is the root directory, return false
        return ! empty( $relative_path );
    }

    /**
     * Get page title
     *
     * @return string Page title
     */
    public function get_page_title() {
        return __('File list', 'bf-secret-file-downloader' );
    }

    /**
     * Generate paging HTML
     *
     * @param int $current_page Current page
     * @param int $total_pages Total pages
     * @param string $current_path Current path
     * @param string $sort_by Sort field
     * @param string $sort_order Sort order
     * @return string Paging HTML
     */
    public function render_pagination( $current_page, $total_pages, $current_path, $sort_by = 'name', $sort_order = 'asc' ) {
        if ( $total_pages <= 1 ) {
            return '';
        }

        $html = '<span class="pagination-links">';

        // Previous page
        if ( $current_page > 1 ) {
            $prev_url = add_query_arg( array(
                'page' => 'bf-secret-file-downloader',
                'path' => urlencode( $current_path ),
                'paged' => $current_page - 1,
                'sort_by' => $sort_by,
                'sort_order' => $sort_order
            ), admin_url( 'admin.php' ) );
            $html .= '<a href="' . esc_url( $prev_url ) . '">&laquo; ' . __('Previous', 'bf-secret-file-downloader' ) . '</a>';
        }

        // Page number
        $start_page = max( 1, $current_page - 2 );
        $end_page = min( $total_pages, $current_page + 2 );

        for ( $i = $start_page; $i <= $end_page; $i++ ) {
            if ( $i == $current_page ) {
                $html .= '<span class="current">' . $i . '</span>';
            } else {
                $page_url = add_query_arg( array(
                    'page' => 'bf-secret-file-downloader',
                    'path' => urlencode( $current_path ),
                    'paged' => $i,
                    'sort_by' => $sort_by,
                    'sort_order' => $sort_order
                ), admin_url( 'admin.php' ) );
                $html .= '<a href="' . esc_url( $page_url ) . '">' . $i . '</a>';
            }
        }

        // Next page
        if ( $current_page < $total_pages ) {
            $next_url = add_query_arg( array(
                'page' => 'bf-secret-file-downloader',
                'path' => urlencode( $current_path ),
                'paged' => $current_page + 1,
                'sort_by' => $sort_by,
                'sort_order' => $sort_order
            ), admin_url( 'admin.php' ) );
            $html .= '<a href="' . esc_url( $next_url ) . '">' . __('Next', 'bf-secret-file-downloader' ) . ' &raquo;</a>';
        }

        $html .= '</span>';
        return $html;
    }

    /**
     * Format file size
     *
     * @param int $bytes Bytes
     * @return string Formatted file size
     */
    public function format_file_size( $bytes ) {
        if ( $bytes == 0 ) {
            return '0 B';
        }

        $k = 1024;
        $sizes = array( 'B', 'KB', 'MB', 'GB', 'TB' );
        $i = floor( log( $bytes ) / log( $k ) );

        return round( $bytes / pow( $k, $i ), 2 ) . ' ' . $sizes[ $i ];
    }

    /**
     * Get menu title
     *
     * @return string Menu title
     */
    public function get_menu_title() {
        return __('File list', 'bf-secret-file-downloader' );
    }

    /**
     * Check if the current user has file access permission
     *
     * @return bool File access permission flag
     */
    private function can_access_files() {
        $allow_editor_admin = (bool) get_option( 'bf_sfd_allow_editor_admin', false );
        $capability = $allow_editor_admin ? 'edit_posts' : 'manage_options';
        return current_user_can( $capability );
    }

    /**
     * Get total number of files
     *
     * @param string $path Directory path
     * @return int Total number of files
     */
    private function get_total_files( $path = '' ) {
        if ( empty( $path ) ) {
            return 0;
        }

        if ( ! SecurityHelper::is_allowed_directory( $path ) || ! is_dir( $path ) || ! is_readable( $path ) ) {
            return 0;
        }

        try {
            $items = scandir( $path );
            $count = 0;
            foreach ( $items as $item ) {
                if ( $item !== '.' && $item !== '..' ) {
                    // Exclude hidden files (files starting with a dot)
                    if ( strpos( $item, '.' ) === 0 ) {
                        continue;
                    }
                    // Exclude program code files
                    if ( SecurityHelper::is_program_code_file( $item ) ) {
                        continue;
                    }
                    // Exclude protected files in secure directory
                    if ( $item === 'index.php' ) {
                        continue;
                    }
                    $count++;
                }
            }
            return $count;
        } catch ( \Exception $e ) {
            return 0;
        }
    }

    /**
     * Get total number of pages
     *
     * @param string $path Directory path
     * @return int Total number of pages
     */
    private function get_total_pages( $path = '' ) {
        $total_files = $this->get_total_files( $path );
        return ceil( $total_files / self::FILES_PER_PAGE );
    }


    /**
     * Get upload limit
     *
     * @return string Upload limit
     */
    private function get_upload_limit() {
        $max_size = get_option( 'bf_sfd_max_file_size', 10 );
        return $max_size . 'MB';
    }

    /**
     * Get current page number
     *
     * @return int Current page number
     */
    private function get_current_page() {
        return max( 1, intval( $_GET['paged'] ?? 1 ) );
    }

    /**
     * Get current sort field
     *
     * @return string Current sort field
     */
    private function get_current_sort_by() {
        $sort_by = sanitize_text_field( wp_unslash( $_GET['sort_by'] ?? 'name' ) );
        $allowed_sorts = array( 'name', 'size', 'modified' );
        return in_array( $sort_by, $allowed_sorts ) ? $sort_by : 'name';
    }

    /**
     * Get current sort order
     *
     * @return string Current sort order
     */
    private function get_current_sort_order() {
        $sort_order = sanitize_text_field( wp_unslash( $_GET['sort_order'] ?? 'asc' ) );
        return in_array( $sort_order, array( 'asc', 'desc' ) ) ? $sort_order : 'asc';
    }

    /**
     * Delete directory recursively
     *
     * @param string $directory_path Directory path to delete
     * @return bool Delete success flag
     */
    private function delete_directory_recursive( $directory_path ) {
        if ( ! is_dir( $directory_path ) ) {
            return false;
        }

        // Initialize WP_Filesystem
        global $wp_filesystem;
        if ( empty( $wp_filesystem ) ) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }

        $items = scandir( $directory_path );
        foreach ( $items as $item ) {
            if ( $item === '.' || $item === '..' ) {
                continue;
            }

            $item_path = $directory_path . DIRECTORY_SEPARATOR . $item;

            if ( is_dir( $item_path ) ) {
                // Delete subdirectories recursively
                if ( ! $this->delete_directory_recursive( $item_path ) ) {
                    return false;
                }
            } else {
                // Delete file
                if ( ! wp_delete_file( $item_path ) ) {
                    return false;
                }
            }
        }

        // Delete empty directory
        return $wp_filesystem->rmdir( $directory_path );
    }

    /**
     * Directory authentication settings AJAX handler
     */
    public function ajax_set_directory_auth() {
        // Security check
        if ( ! $this->can_access_files() || ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        check_ajax_referer( 'bf_sfd_file_list_nonce', 'nonce' );

        $relative_path = sanitize_text_field( wp_unslash( $_POST['path'] ?? '' ) );
        $auth_methods = array_map( 'sanitize_text_field', wp_unslash( $_POST['auth_methods'] ?? array() ) );
        $allowed_roles = array_map( 'sanitize_text_field', wp_unslash( $_POST['allowed_roles'] ?? array() ) );
        $simple_auth_password = sanitize_text_field( wp_unslash( $_POST['simple_auth_password'] ?? '' ) );
        $action_type = sanitize_text_field( wp_unslash( $_POST['action_type'] ?? 'set' ) ); // set, remove

        // Get base directory
        $base_directory = DirectoryManager::get_secure_directory();
        if ( empty( $base_directory ) ) {
            wp_send_json_error( __('Target directory is not configured.', 'bf-secret-file-downloader' ) );
        }

        // Build full path
        $full_path = SecurityHelper::build_safe_path( $base_directory, $relative_path );

        // Security check
        if ( ! SecurityHelper::is_allowed_directory( $full_path ) ) {
            wp_send_json_error( __('Access to this directory is not allowed.', 'bf-secret-file-downloader' ) );
        }

        // Check if directory exists
        if ( ! is_dir( $full_path ) ) {
            wp_send_json_error( __('Directory does not exist.', 'bf-secret-file-downloader' ) );
        }

        if ( $action_type === 'remove' ) {
            // Delete authentication settings
            $this->remove_directory_auth( $relative_path );
            wp_send_json_success( array(
                'message' => __('Directory authentication settings have been deleted.', 'bf-secret-file-downloader' ),
                'has_auth' => false
            ));
        } else {
            // Save authentication settings
            if ( empty( $auth_methods ) || ! is_array( $auth_methods ) ) {
                wp_send_json_error( __('Please select an authentication method.', 'bf-secret-file-downloader' ) );
            }

            // If simple authentication is selected, a password is required
            if ( in_array( 'simple_auth', $auth_methods ) && empty( $simple_auth_password ) ) {
                wp_send_json_error( __('If you select simple authentication, please set a password.', 'bf-secret-file-downloader' ) );
            }

            $this->set_directory_auth( $relative_path, $auth_methods, $allowed_roles, $simple_auth_password );
            wp_send_json_success( array(
                'message' => __('Directory authentication settings have been saved.', 'bf-secret-file-downloader' ),
                'has_auth' => true
            ));
        }
    }

    /**
     * Directory authentication settings retrieval AJAX handler
     */
    public function ajax_get_directory_auth() {
        // Security check
        if ( ! $this->can_access_files() || ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        check_ajax_referer( 'bf_sfd_file_list_nonce', 'nonce' );

        $relative_path = sanitize_text_field( wp_unslash( $_POST['path'] ?? '' ) );

        // Get base directory
        $base_directory = DirectoryManager::get_secure_directory();
        if ( empty( $base_directory ) ) {
            wp_send_json_error( __('Target directory is not configured.', 'bf-secret-file-downloader' ) );
        }

        // Build full path
        $full_path = SecurityHelper::build_safe_path( $base_directory, $relative_path );

        // Security check
        if ( ! SecurityHelper::is_allowed_directory( $full_path ) ) {
            wp_send_json_error( __('Access to this directory is not allowed.', 'bf-secret-file-downloader' ) );
        }

        // Get authentication settings
        $auth_settings = $this->get_directory_auth( $relative_path );

        if ( $auth_settings !== false ) {
            wp_send_json_success( $auth_settings );
        } else {
            wp_send_json_error( __('Could not retrieve authentication settings.', 'bf-secret-file-downloader' ) );
        }
    }

    /**
     * Save directory authentication settings
     *
     * @param string $relative_path Relative path
     * @param array $auth_methods Authentication methods array
     * @param array $allowed_roles Allowed user roles array
     * @param string $simple_auth_password Simple authentication password
     */
    private function set_directory_auth( $relative_path, $auth_methods, $allowed_roles, $simple_auth_password ) {
        $directory_auths = get_option( 'bf_sfd_directory_auths', array() );

        $auth_data = array(
            'auth_methods' => $auth_methods,
            'allowed_roles' => $allowed_roles,
        );

        // If simple authentication password is set
        if ( ! empty( $simple_auth_password ) ) {
            $auth_data['simple_auth_hash'] = wp_hash_password( $simple_auth_password );
            $auth_data['simple_auth_encrypted'] = $this->encrypt_password( $simple_auth_password );
        }

        $directory_auths[ $relative_path ] = $auth_data;
        update_option( 'bf_sfd_directory_auths', $directory_auths );

        // Update authentication settings change timestamp (invalidate all users' authentication)
        update_option( 'bf_sfd_auth_settings_changed', time() );
        error_log( 'Directory auth settings updated. Timestamp: ' . time() );
    }

    /**
     * Delete directory authentication settings
     *
     * @param string $relative_path Relative path
     */
    private function remove_directory_auth( $relative_path ) {
        $directory_auths = get_option( 'bf_sfd_directory_auths', array() );

        if ( isset( $directory_auths[ $relative_path ] ) ) {
            unset( $directory_auths[ $relative_path ] );
            update_option( 'bf_sfd_directory_auths', $directory_auths );

            // Update authentication settings change timestamp (invalidate all users' authentication)
            update_option( 'bf_sfd_auth_settings_changed', time() );
            error_log( 'Directory auth settings removed. Timestamp: ' . time() );
        }
    }

    /**
     * Check if directory has authentication settings
     *
     * @param string $relative_path Relative path
     * @return bool Authentication settings flag
     */
    private function has_directory_auth( $relative_path ) {
        $directory_auths = get_option( 'bf_sfd_directory_auths', array() );

        if ( ! isset( $directory_auths[ $relative_path ] ) ) {
            return false;
        }

        $auth_data = $directory_auths[ $relative_path ];
        return is_array( $auth_data ) && ! empty( $auth_data['auth_methods'] );
    }

    /**
     * Check if directory has password
     *
     * @param string $relative_path Relative path
     * @return bool Password setting flag
     */
    private function has_directory_password( $relative_path ) {
        $directory_passwords = get_option( 'bf_sfd_directory_passwords', array() );

        if ( ! isset( $directory_passwords[ $relative_path ] ) ) {
            return false;
        }

        // Check new array format
        if ( is_array( $directory_passwords[ $relative_path ] ) ) {
            return ! empty( $directory_passwords[ $relative_path ]['hash'] );
        }

        // Check old string format (backward compatibility)
        return ! empty( $directory_passwords[ $relative_path ] );
    }

    /**
     * Get directory authentication settings
     *
     * @param string $relative_path Relative path
     * @return array|false Authentication settings, or false on failure
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

        // Decrypt simple authentication password
        if ( isset( $auth_data['simple_auth_encrypted'] ) ) {
            $result['simple_auth_password'] = $this->decrypt_password( $auth_data['simple_auth_encrypted'] );
        }

        return $result;
    }

    /**
     * Encrypt password
     *
     * @param string $password Plain text password
     * @return string Encrypted password
     */
    private function encrypt_password( $password ) {
        if ( ! function_exists( 'openssl_encrypt' ) ) {
            return base64_encode( $password ); // フォールバック
        }

        $key = $this->get_encryption_key();
        $iv = openssl_random_pseudo_bytes( 16 );
        $encrypted = openssl_encrypt( $password, 'AES-256-CBC', $key, 0, $iv );

        return base64_encode( $iv . $encrypted );
    }

    /**
     * Decrypt password
     *
     * @param string $encrypted_password Encrypted password
     * @return string|false Decrypted password, or false on failure
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
     * Get encryption key
     *
     * @return string Encryption key
     */
    private function get_encryption_key() {
        // Use WordPress salt to generate key
        $salt_keys = array( AUTH_KEY, SECURE_AUTH_KEY, LOGGED_IN_KEY, NONCE_KEY );
        return hash( 'sha256', implode( '', $salt_keys ) );
    }

    /**
     * Common authentication settings retrieval AJAX handler
     */
    public function ajax_get_global_auth() {
        // Security check
        if ( ! $this->can_access_files() || ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        check_ajax_referer( 'bf_sfd_file_list_nonce', 'nonce' );

        // Get common settings
        $auth_methods = get_option( 'bf_sfd_auth_methods', array( 'logged_in' ) );
        $allowed_roles = get_option( 'bf_sfd_allowed_roles', array( 'administrator' ) );
        $simple_auth_password = get_option( 'bf_sfd_simple_auth_password', '' );

        $global_auth = array(
            'auth_methods' => $auth_methods,
            'allowed_roles' => $allowed_roles,
            'simple_auth_password' => $simple_auth_password
        );

        wp_send_json_success( $global_auth );
    }

    /**
     * Secure directory recreation AJAX handler
     */
    public function ajax_recreate_secure_directory() {
        // Security check
        if ( ! $this->can_access_files() || ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        check_ajax_referer( 'bf_sfd_file_list_nonce', 'nonce' );

        // Force create new secure directory
        $result = DirectoryManager::create_secure_directory( true );

        if ( $result ) {
            $new_directory = DirectoryManager::get_secure_directory();
            wp_send_json_success( array(
                'message' => __('New secure directory has been created.', 'bf-secret-file-downloader' ),
                'directory' => $new_directory
            ));
        } else {
            wp_send_json_error( __('Failed to create directory.', 'bf-secret-file-downloader' ) );
        }
    }

}
