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

        // Handle the "dismiss notice" and "check again" links in the notices
        // 通知内の「通知を閉じる」「再確認」リンクを処理する
        add_action( 'admin_init', array( $this, 'handle_directory_notice_actions' ) );

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
     * Action name of the "dismiss the directory moved notice" link
     * 「ディレクトリ移動の通知を閉じる」リンクのアクション名
     */
    const ACTION_DISMISS_MOVED_NOTICE = 'bf_sfd_dismiss_moved_notice';

    /**
     * Action name of the "check the protection status again" link
     * 「保護状態を再確認する」リンクのアクション名
     */
    const ACTION_RECHECK_PROTECTION = 'bf_sfd_recheck_protection';

    /**
     * Get the URL of a notice action link (with a nonce)
     * 通知の操作リンクの URL を取得する（nonce 付き）
     *
     * The link always points to one of the plugin's own screens instead of the current URL,
     * so that the redirect after the action never lands on a screen that needs its own nonce
     * (e.g. update.php).
     * リンク先は現在の URL ではなく常にプラグイン自身の画面にする。
     * 処理後のリダイレクト先が、独自の nonce を必要とする画面（update.php など）にならないようにするため。
     *
     * @param string $action    one of the ACTION_* constants / ACTION_* 定数のいずれか
     * @param string $page_slug slug of the plugin screen to return to / 戻り先のプラグイン画面のスラッグ
     * @return string the URL / URL
     */
    public static function get_notice_action_url( $action, $page_slug ) {
        $url = add_query_arg(
            array(
                'page'          => $page_slug,
                'bf_sfd_action' => $action,
            ),
            admin_url( 'admin.php' )
        );
        return wp_nonce_url( $url, $action );
    }

    /**
     * Get the plugin screen to return to after a notice action
     * 通知の操作後に戻るプラグイン画面を取得する
     *
     * @param string $page_slug the requested page slug / リクエストされたページのスラッグ
     * @return string a slug of the plugin's screens (the file list if invalid) / プラグイン画面のスラッグ（不正ならファイル一覧）
     */
    public static function get_return_page_slug( $page_slug ) {
        $allowed = array(
            \Breadfish\SecretFileDownloader\Admin\FileListPage::PAGE_SLUG,
            \Breadfish\SecretFileDownloader\Admin\SettingsPage::PAGE_SLUG,
        );
        return in_array( $page_slug, $allowed, true ) ? $page_slug : \Breadfish\SecretFileDownloader\Admin\FileListPage::PAGE_SLUG;
    }

    /**
     * Handle the notice action links
     * 通知の操作リンクを処理する
     *
     * Redirects back to the same screen without the query parameters after the action.
     * 処理後はクエリパラメータを除いた同じ画面へリダイレクトする。
     */
    public function handle_directory_notice_actions() {
        $action = isset( $_GET['bf_sfd_action'] ) ? sanitize_key( wp_unslash( $_GET['bf_sfd_action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( ! in_array( $action, array( self::ACTION_DISMISS_MOVED_NOTICE, self::ACTION_RECHECK_PROTECTION ), true ) ) {
            return;
        }

        // Only administrators can perform the actions, and the nonce must be valid
        // 操作できるのは管理者のみで、nonce が有効である必要がある
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        check_admin_referer( $action );

        if ( $action === self::ACTION_DISMISS_MOVED_NOTICE ) {
            // Stop showing the "directory moved" notice
            // 「ディレクトリを移動した」通知の表示をやめる
            delete_option( DirectoryManager::MOVED_NOTICE_OPTION );
        } else {
            // Check the protection status again, ignoring the cache
            // キャッシュを無視して保護状態を再確認する
            DirectoryManager::get_protection_status( true );
        }

        // Return to the plugin's screen (never to the current URL, which may need its own nonce)
        // プラグインの画面に戻る（独自の nonce が必要な場合がある現在の URL には戻らない）
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the nonce is verified above / nonce は上で検証済み
        wp_safe_redirect( admin_url( 'admin.php?page=' . self::get_return_page_slug( $page ) ) );
        exit;
    }

    /**
     * Render notices about the secure directory
     * セキュアディレクトリに関する通知を表示する
     *
     * - A notice after the directory was moved to the hidden directory (shown until dismissed)
     * - A warning on the plugin's screens when files can be downloaded directly
     * - 隠しディレクトリへ移動した後の通知（閉じるまで表示する）
     * - ファイルを直接ダウンロードできる状態のとき、プラグインの画面に出す警告
     */
    public function render_directory_notices() {
        // Only administrators can act on these notices
        // 通知に対応できるのは管理者のみ
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Notice: the location for FTP uploads has changed.
        // Kept until the administrator dismisses it, because it may be missed on screens
        // that do not show classic notices (e.g. the block editor).
        // 通知: FTP でのアップロード先が変わった。
        // ブロックエディターなど従来の通知が表示されない画面で見逃されないよう、
        // 管理者が閉じるまで表示し続ける。
        if ( get_option( DirectoryManager::MOVED_NOTICE_OPTION, false ) ) {
            ?>
            <div class="notice notice-info">
                <p><strong><?php esc_html_e( 'BF Secret File Downloader', 'bf-secret-file-downloader' ); ?></strong></p>
                <p><?php esc_html_e( 'To improve protection, the secure directory has been moved to a hidden directory (a directory whose name starts with a dot).', 'bf-secret-file-downloader' ); ?></p>
                <p><?php esc_html_e( 'If you upload files via FTP, please use the following directory from now on.', 'bf-secret-file-downloader' ); ?></p>
                <p><code><?php echo esc_html( DirectoryManager::get_secure_directory() ); ?></code></p>
                <p><?php esc_html_e( 'If the directory is not shown in your FTP client, enable the option to show hidden files.', 'bf-secret-file-downloader' ); ?></p>
                <p><a href="<?php echo esc_url( self::get_notice_action_url( self::ACTION_DISMISS_MOVED_NOTICE, $this->file_list_page::PAGE_SLUG ) ); ?>" class="button"><?php esc_html_e( 'Dismiss this notice', 'bf-secret-file-downloader' ); ?></a></p>
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
            <p><a href="<?php echo esc_url( self::get_notice_action_url( self::ACTION_RECHECK_PROTECTION, $page ) ); ?>" class="button"><?php esc_html_e( 'Check again', 'bf-secret-file-downloader' ); ?></a></p>
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
