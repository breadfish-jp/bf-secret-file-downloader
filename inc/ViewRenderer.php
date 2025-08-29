<?php
/**
 * ViewRenderer class
 *
 * @package BfSecretFileDownloader
 */

namespace Breadfish\SecretFileDownloader;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ViewRenderer class
 * Manages the rendering of view files and variable scope
 */
class ViewRenderer {

    /**
     * Render a view file
     *
     * @param string $view_file the path to the view file (relative path)
     * @param array  $import    the array of variables to pass to the view
     * @param string $view_type the type of view (Admin, Frontend, Blocks, etc.)
     */
    public static function render( $view_file, $import = array(), $view_type = 'Admin' ) {
        // Build the full path to the view file
        $view_path = BF_SECRET_FILE_DOWNLOADER_PLUGIN_DIR . 'inc/views/' . $view_type . '/' . $view_file;

        // Check if the file exists
        if ( ! file_exists( $view_path ) ) {
            wp_die(
                sprintf(
                    /* translators: %s: ビューファイルのパス */
                    esc_html__('View file not found: %s', 'bf-secret-file-downloader' ),
                    esc_html( $view_type . '/' . $view_file )
                )
            );
        }

        // Include the view file safely
        // Pass variables explicitly (do not use extract)
        // Make variables available in the global scope
        foreach ( $import as $key => $value ) {
            $$key = $value;
        }
        include $view_path;
    }

    /**
     * Render the view for the admin panel (shortcut)
     *
     * @param string $view_file the path to the view file (relative path)
     * @param array  $import    the array of variables to pass to the view
     */
    public static function admin( $view_file, $import = array() ) {
        self::render( $view_file, $import, 'Admin' );
    }

    /**
     * Render the view for the frontend (shortcut)
     *
     * @param string $view_file the path to the view file (relative path)
     * @param array  $import    the array of variables to pass to the view
     */
    public static function frontend( $view_file, $import = array() ) {
        self::render( $view_file, $import, 'Frontend' );
    }
}