<?php
/**
 * 管理画面メニューを管理するクラス
 *
 * @package BfBasicGuard
 */

namespace Breadfish\SecretFileDownloader;

// セキュリティチェック：直接アクセスを防ぐ
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin クラス
 * WordPressの管理画面メニューを管理し、各ページクラスにルーティングします
 */
class Admin {

    /**
     * ファイルリストページインスタンス
     *
     * @var \Breadfish\BasicGuard\Admin\FileListPage
     */
    private $file_list_page;

    /**
     * 設定ページインスタンス
     *
     * @var \Breadfish\BasicGuard\Admin\SettingsPage
     */
    private $settings_page;

    /**
     * コンストラクタ
     */
    public function __construct() {
        // コンストラクタではフックを登録しない
        $this->file_list_page = new \Breadfish\SecretFileDownloader\Admin\FileListPage();
        $this->settings_page = new \Breadfish\SecretFileDownloader\Admin\SettingsPage();
    }

    /**
     * フックを初期化します
     */
    public function init() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

        // 各ページの初期化も実行
        $this->file_list_page->init();
        $this->settings_page->init();
    }

    /**
     * 管理画面メニューを追加します
     */
    public function add_admin_menu() {
        // 編集者管理権限設定をチェック
        $allow_editor_admin = (bool) get_option( 'bf_sfd_allow_editor_admin', false );
        
        // 編集者管理権限が無効で、現在のユーザーが編集者の場合はメニューを表示しない
        if ( ! $allow_editor_admin && current_user_can( 'editor' ) && ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        // 設定からメニュータイトルを取得
        $menu_title = get_option( 'bf_sfd_menu_title', __( 'BF Secret File Downloader', 'bf-secret-file-downloader' ) );
        
        // ファイルリスト用の権限を決定
        $file_capability = $this->get_file_access_capability();
        
        // メインメニューページを追加
        add_menu_page(
            $menu_title, // ページタイトル
            $menu_title, // メニュータイトル
            $file_capability, // 権限
            $this->file_list_page::PAGE_SLUG, // メニュースラッグ
            array( $this->file_list_page, 'render' ), // コールバック関数
            'dashicons-lock', // アイコン
            30 // メニューの位置
        );

        // サブメニューページを追加
        add_submenu_page(
            $this->file_list_page::PAGE_SLUG, // 親メニューのスラッグ
            $this->file_list_page->get_page_title(), // ページタイトル
            $this->file_list_page->get_menu_title(), // メニュータイトル
            $file_capability, // 権限
            $this->file_list_page::PAGE_SLUG, // メニュースラッグ（メインページと同じ）
            array( $this->file_list_page, 'render' ) // コールバック関数
        );

        add_submenu_page(
            $this->file_list_page::PAGE_SLUG, // 親メニューのスラッグ
            $this->settings_page->get_page_title(), // ページタイトル
            $this->settings_page->get_menu_title(), // メニュータイトル
            'manage_options', // 権限
            $this->settings_page::PAGE_SLUG, // メニュースラッグ
            array( $this->settings_page, 'render' ) // コールバック関数
        );
    }

    /**
     * ファイルアクセス用の権限を取得します
     *
     * @return string 権限文字列
     */
    private function get_file_access_capability() {
        $allow_editor_admin = (bool) get_option( 'bf_sfd_allow_editor_admin', false );
        
        // 編集者管理権限が有効の場合は編集者以上、無効の場合は管理者のみ
        return $allow_editor_admin ? 'edit_posts' : 'manage_options';
    }

    /**
     * 現在のユーザーがファイルアクセス権限を持っているかチェック
     *
     * @return bool アクセス権限の有無
     */
    public function can_access_files() {
        $capability = $this->get_file_access_capability();
        return current_user_can( $capability );
    }
}
