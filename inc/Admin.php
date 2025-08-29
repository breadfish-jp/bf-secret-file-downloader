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
