<?php
/**
 * ファイルリストページを管理するクラス
 *
 * @package BfSecretFileDownloader
 */

namespace Breadfish\SecretFileDownloader\Admin;

use Breadfish\SecretFileDownloader\SecurityHelper;
use Breadfish\SecretFileDownloader\DirectoryManager;


// セキュリティチェック：直接アクセスを防ぐ
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * FileListPage クラス
 * ファイルリスト機能を管理します
 */
class FileListPage {

    /**
     * ページスラッグ
     */
    const PAGE_SLUG = 'bf-secret-file-downloader';

    /**
     * 1ページあたりのファイル表示数
     */
    const FILES_PER_PAGE = 20;

    /**
     * コンストラクタ
     */
    public function __construct() {
        // コンストラクタではフックを登録しない
    }

    /**
     * フックを初期化します
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
     * 管理画面用のスクリプトとスタイルをエンキューします
     */
    public function enqueue_admin_scripts( $hook ) {
        // 現在のページが該当するページかチェック
        if ( $hook !== 'toplevel_page_bf-secret-file-downloader' ) {
            return;
        }

        // Dashiconsを確実に読み込む
        wp_enqueue_style( 'dashicons' );

        // jQueryを読み込み
        wp_enqueue_script( 'jquery' );

        // 管理画面用CSSを読み込み
        $css_file_path = plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'assets/css/file-list-admin.css';
        if ( file_exists( $css_file_path ) ) {
            wp_enqueue_style(
                'bf-secret-file-downloader-admin',
                plugin_dir_url( dirname( dirname( __FILE__ ) ) ) . 'assets/css/file-list-admin.css',
                array( 'dashicons' ),
                filemtime( $css_file_path )
            );
        }

        // 初期データをJavaScriptに渡す
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
            ),
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce' => $initial_data['nonce'],
            'strings' => array(
                'loading' => __( '読み込み中...', 'bf-secret-file-downloader' ),
                'currentDirectory' => __( '現在のディレクトリ:', 'bf-secret-file-downloader' ),
                'rootDirectory' => __( 'ルートディレクトリ', 'bf-secret-file-downloader' ),
                'goUp' => __( '上の階層へ', 'bf-secret-file-downloader' ),
                'authSettings' => __( '認証設定', 'bf-secret-file-downloader' ),
                'open' => __( '開く', 'bf-secret-file-downloader' ),
                'download' => __( 'ダウンロード', 'bf-secret-file-downloader' ),
                'copyUrl' => __( 'URLをコピー', 'bf-secret-file-downloader' ),
                'delete' => __( '削除', 'bf-secret-file-downloader' ),
                'directory' => __( 'ディレクトリ', 'bf-secret-file-downloader' ),
                'file' => __( 'ファイル', 'bf-secret-file-downloader' ),
                'accessDenied' => __( 'アクセス不可', 'bf-secret-file-downloader' ),
                'noFilesFound' => __( 'ファイルまたはディレクトリが見つかりませんでした。', 'bf-secret-file-downloader' ),
                /* translators: %d: number of items found */
                'itemsFound' => __( '%d個のアイテムが見つかりました。', 'bf-secret-file-downloader' ),
                'noItemsFound' => __( 'アイテムが見つかりませんでした。', 'bf-secret-file-downloader' ),
            ),
        ));
    }

    /**
     * ファイルブラウズのAJAXハンドラ
     */
    public function ajax_browse_files() {
        // 権限チェック
        if ( ! $this->can_access_files() ) {
            wp_die( 'Unauthorized' );
        }

        check_ajax_referer( 'bf_sfd_file_list_nonce', 'nonce' );


        $relative_path = sanitize_text_field( wp_unslash( $_POST['path'] ?? '' ) );
        $page = intval( $_POST['page'] ?? 1 );
        $sort_by = sanitize_text_field( wp_unslash( $_POST['sort_by'] ?? 'name' ) );
        $sort_order = sanitize_text_field( wp_unslash( $_POST['sort_order'] ?? 'asc' ) );

        // ベースディレクトリを取得
        $base_directory = DirectoryManager::get_secure_directory();
        if ( empty( $base_directory ) ) {
            wp_send_json_error( __( '対象ディレクトリが設定されていません。', 'bf-secret-file-downloader' ) );
        }

        // フルパスを構築
        $full_path = SecurityHelper::build_safe_path( $base_directory, $relative_path );

        // セキュリティチェック
        if ( ! SecurityHelper::is_allowed_directory( $full_path ) ) {
            wp_send_json_error( __( 'このディレクトリへのアクセスは許可されていません。', 'bf-secret-file-downloader' ) );
        }

        // ディレクトリの存在チェック
        if ( ! is_dir( $full_path ) || ! is_readable( $full_path ) ) {
            wp_send_json_error( __( 'ディレクトリにアクセスできません。', 'bf-secret-file-downloader' ) );
        }

        try {
            $files_data = $this->get_directory_contents( $full_path, $relative_path, $page, $sort_by, $sort_order );
            wp_send_json_success( $files_data );
        } catch ( \Exception $e ) {
            wp_send_json_error( __( 'ファイルリストの取得に失敗しました。', 'bf-secret-file-downloader' ) );
        }
    }

    /**
     * ファイルアップロードのAJAXハンドラ
     */
    public function ajax_upload_file() {
        // 権限チェック
        if ( ! $this->can_access_files() || ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        check_ajax_referer( 'bf_sfd_file_list_nonce', 'nonce' );

        $relative_path = sanitize_text_field( wp_unslash( $_POST['target_path'] ?? '' ) );

        // ベースディレクトリを取得
        $base_directory = DirectoryManager::get_secure_directory();
        if ( empty( $base_directory ) ) {
            wp_send_json_error( __( '対象ディレクトリが設定されていません。', 'bf-secret-file-downloader' ) );
        }

        // フルパスを構築
        $target_path = SecurityHelper::build_safe_path( $base_directory, $relative_path );

        global $wp_filesystem;
        if ( empty( $wp_filesystem ) ) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }

        if ( ! is_dir( $target_path ) || ! $wp_filesystem->is_writable( $target_path ) ) {
            wp_send_json_error( __( 'アップロード先ディレクトリに書き込み権限がありません。', 'bf-secret-file-downloader' ) );
        }

        // ファイルがアップロードされているかチェック
        if ( ! isset( $_FILES['file'] ) || ! isset( $_FILES['file']['error'] ) || $_FILES['file']['error'] !== UPLOAD_ERR_OK ) {
            wp_send_json_error( __( 'ファイルのアップロードに失敗しました。', 'bf-secret-file-downloader' ) );
        }

        $uploaded_file = array_map( 'sanitize_text_field', $_FILES['file'] );

        // ファイル名のサニタイゼーション
        $filename = sanitize_file_name( $uploaded_file['name'] );
        if ( empty( $filename ) ) {
            wp_send_json_error( __( '無効なファイル名です。', 'bf-secret-file-downloader' ) );
        }

        // セキュリティチェック
        $security_check = SecurityHelper::check_file_upload_security( $filename, $target_path );
        if ( ! $security_check['allowed'] ) {
            wp_send_json_error( $security_check['error_message'] );
        }

        // プログラムコードファイルのアップロード禁止
        if ( SecurityHelper::is_program_code_file( $filename ) ) {
            wp_send_json_error( __( 'セキュリティ上の理由により、プログラムコードファイルはアップロードできません。', 'bf-secret-file-downloader' ) );
        }

        // ファイルサイズチェック
        $max_size = get_option( 'bf_sfd_max_file_size', 10 ) * 1024 * 1024; // MB to bytes
        if ( $uploaded_file['size'] > $max_size ) {
            wp_send_json_error( sprintf(
                /* translators: %s: maximum file size in MB */
                __( 'ファイルサイズが制限を超えています。（最大: %sMB）', 'bf-secret-file-downloader' ),
                get_option( 'bf_sfd_max_file_size', 10 )
            ));
        }

        // アップロード先のファイルパス
        $target_file_path = $target_path . DIRECTORY_SEPARATOR . $filename;

        // 既存ファイルの確認
        if ( file_exists( $target_file_path ) ) {
            // ファイル名に連番を追加
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

        // ファイルを移動
        if ( $wp_filesystem->put_contents( $target_file_path, $wp_filesystem->get_contents( $uploaded_file['tmp_name'] ) ) ) {
            // アップロード成功
            wp_send_json_success( array(
                /* translators: %s: uploaded filename */
                'message' => sprintf( __( '%s をアップロードしました。', 'bf-secret-file-downloader' ), $filename ),
                'filename' => $filename,
                'relative_path' => $relative_path
            ));
        } else {
            wp_send_json_error( __( 'ファイルの保存に失敗しました。', 'bf-secret-file-downloader' ) );
        }
    }

    /**
     * ディレクトリ作成のAJAXハンドラ
     */
    public function ajax_create_directory() {
        // セキュリティチェック
        if ( ! $this->can_access_files() || ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        check_ajax_referer( 'bf_sfd_file_list_nonce', 'nonce' );

        // WordPress Filesystem APIを初期化
        global $wp_filesystem;
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();

        $relative_path = sanitize_text_field( wp_unslash( $_POST['parent_path'] ?? '' ) );
        $directory_name = sanitize_text_field( wp_unslash( $_POST['directory_name'] ?? '' ) );

        // 入力値チェック
        if ( empty( $directory_name ) ) {
            wp_send_json_error( __( 'ディレクトリ名が指定されていません。', 'bf-secret-file-downloader' ) );
        }

        // ベースディレクトリを取得
        $base_directory = DirectoryManager::get_secure_directory();
        if ( empty( $base_directory ) ) {
            wp_send_json_error( __( '対象ディレクトリが設定されていません。', 'bf-secret-file-downloader' ) );
        }

        // フルパスを構築
        $parent_path = SecurityHelper::build_safe_path( $base_directory, $relative_path );

        // セキュリティチェック
        $security_check = SecurityHelper::check_ajax_create_directory_security( $parent_path, $directory_name );
        if ( ! $security_check['allowed'] ) {
            wp_send_json_error( $security_check['error_message'] );
        }

        // ドットで始まるディレクトリ名を禁止
        if ( strpos( $directory_name, '.' ) === 0 ) {
            wp_send_json_error( __( 'ドットで始まるディレクトリ名は作成できません。', 'bf-secret-file-downloader' ) );
        }

        // 書き込み権限チェック
        if ( ! $wp_filesystem->is_writable( $parent_path ) ) {
            wp_send_json_error( __( '親ディレクトリに書き込み権限がありません。', 'bf-secret-file-downloader' ) );
        }

        // 新しいディレクトリのパス
        $new_directory_path = $parent_path . DIRECTORY_SEPARATOR . $directory_name;

        // ディレクトリ作成
        if ( wp_mkdir_p( $new_directory_path ) ) {
            wp_send_json_success( array(
                /* translators: %s: directory name */
                'message' => sprintf( __( 'ディレクトリ「%s」を作成しました。', 'bf-secret-file-downloader' ), $directory_name ),
                'new_directory' => $new_directory_path,
                'parent_path' => $parent_path
            ));
        } else {
            wp_send_json_error( __( 'ディレクトリの作成に失敗しました。', 'bf-secret-file-downloader' ) );
        }
    }

    /**
     * ファイル削除のAJAXハンドラ
     */
    public function ajax_delete_file() {
        // 権限チェック
        if ( ! $this->can_access_files() || ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        check_ajax_referer( 'bf_sfd_file_list_nonce', 'nonce' );

        $relative_path = sanitize_text_field( wp_unslash( $_POST['file_path'] ?? '' ) );

        // 入力値チェック
        if ( empty( $relative_path ) ) {
            wp_send_json_error( __( 'ファイルパスが指定されていません。', 'bf-secret-file-downloader' ) );
        }

        // ベースディレクトリを取得
        $base_directory = DirectoryManager::get_secure_directory();
        if ( empty( $base_directory ) ) {
            wp_send_json_error( __( '対象ディレクトリが設定されていません。', 'bf-secret-file-downloader' ) );
        }

        // フルパスを構築
        $full_path = SecurityHelper::build_safe_path( $base_directory, $relative_path );

        // セキュリティチェック：許可されたディレクトリのみ
        if ( ! SecurityHelper::is_allowed_directory( dirname( $full_path ) ) ) {
            wp_send_json_error( __( 'このファイルの削除は許可されていません。', 'bf-secret-file-downloader' ) );
        }

        // ファイル存在チェック
        if ( ! file_exists( $full_path ) ) {
            wp_send_json_error( __( '指定されたファイルが見つかりません。', 'bf-secret-file-downloader' ) );
        }

        // WordPress Filesystem APIを初期化
        global $wp_filesystem;
        if ( empty( $wp_filesystem ) ) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }

        // 削除権限チェック
        $parent_dir = dirname( $full_path );
        if ( ! $wp_filesystem->is_writable( $parent_dir ) ) {
            wp_send_json_error( __( 'このファイルを削除する権限がありません。', 'bf-secret-file-downloader' ) );
        }

        $filename = basename( $full_path );
        $is_directory = is_dir( $full_path );

        // 削除実行
        if ( $is_directory ) {
            // ディレクトリの削除
            if ( $this->delete_directory_recursive( $full_path ) ) {
                // 親ディレクトリの相対パスを取得
                $parent_relative_path = dirname( $relative_path );
                if ( $parent_relative_path === '.' ) {
                    $parent_relative_path = '';
                }

                wp_send_json_success( array(
                    /* translators: %s: directory name */
                    'message' => sprintf( __( 'ディレクトリ「%s」を削除しました。', 'bf-secret-file-downloader' ), $filename ),
                    'deleted_path' => $relative_path,
                    'parent_path' => $parent_relative_path,
                    'type' => 'directory'
                ));
            } else {
                wp_send_json_error( __( 'ディレクトリの削除に失敗しました。', 'bf-secret-file-downloader' ) );
            }
        } else {
            // ファイルの削除
            if ( wp_delete_file( $full_path ) ) {
                // 親ディレクトリの相対パスを取得
                $parent_relative_path = dirname( $relative_path );
                if ( $parent_relative_path === '.' ) {
                    $parent_relative_path = '';
                }

                wp_send_json_success( array(
                    /* translators: %s: filename */
                    'message' => sprintf( __( 'ファイル「%s」を削除しました。', 'bf-secret-file-downloader' ), $filename ),
                    'deleted_path' => $relative_path,
                    'parent_path' => $parent_relative_path,
                    'type' => 'file'
                ));
            } else {
                wp_send_json_error( __( 'ファイルの削除に失敗しました。', 'bf-secret-file-downloader' ) );
            }
        }
    }

    /**
     * 一括削除のAJAXハンドラ
     */
    public function ajax_bulk_delete() {
        // 権限チェック
        if ( ! $this->can_access_files() || ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        check_ajax_referer( 'bf_sfd_file_list_nonce', 'nonce' );

        $file_paths = array_map( 'sanitize_text_field', wp_unslash( $_POST['file_paths'] ?? array() ) );

        // 入力値チェック
        if ( empty( $file_paths ) || ! is_array( $file_paths ) ) {
            wp_send_json_error( __( '削除するファイルが選択されていません。', 'bf-secret-file-downloader' ) );
        }

        // ベースディレクトリを取得
        $base_directory = DirectoryManager::get_secure_directory();
        if ( empty( $base_directory ) ) {
            wp_send_json_error( __( '対象ディレクトリが設定されていません。', 'bf-secret-file-downloader' ) );
        }

        // WordPress Filesystem APIを初期化
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

            // フルパスを構築
            $full_path = SecurityHelper::build_safe_path( $base_directory, $relative_path );

            // セキュリティチェック：許可されたディレクトリのみ
            if ( ! SecurityHelper::is_allowed_directory( dirname( $full_path ) ) ) {
                $failed_files[] = array(
                    'path' => $relative_path,
                    'error' => __( 'このファイルの削除は許可されていません。', 'bf-secret-file-downloader' )
                );
                continue;
            }

            // ファイル存在チェック
            if ( ! file_exists( $full_path ) ) {
                $failed_files[] = array(
                    'path' => $relative_path,
                    'error' => __( 'ファイルが見つかりません。', 'bf-secret-file-downloader' )
                );
                continue;
            }

            // 削除権限チェック
            $parent_dir = dirname( $full_path );
            if ( ! $wp_filesystem->is_writable( $parent_dir ) ) {
                $failed_files[] = array(
                    'path' => $relative_path,
                    'error' => __( 'このファイルを削除する権限がありません。', 'bf-secret-file-downloader' )
                );
                continue;
            }

            $filename = basename( $full_path );
            $is_directory = is_dir( $full_path );

            // 削除実行
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

                // 現在のパスが削除されたかチェック
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
                    'error' => $is_directory ? __( 'ディレクトリの削除に失敗しました。', 'bf-secret-file-downloader' ) : __( 'ファイルの削除に失敗しました。', 'bf-secret-file-downloader' )
                );
            }
        }

        // 結果をまとめる
        $response_data = array(
            'deleted_files' => $deleted_files,
            'failed_files' => $failed_files,
            'deleted_count' => count( $deleted_files ),
            'failed_count' => count( $failed_files ),
            'current_path_deleted' => $current_path_deleted,
            'redirect_path' => $redirect_path
        );

        if ( count( $deleted_files ) > 0 && count( $failed_files ) === 0 ) {
            // 全て成功
            $message = sprintf(
                /* translators: %d: number of deleted items */
                _n(
                    '%d個のアイテムを削除しました。',
                    '%d個のアイテムを削除しました。',
                    count( $deleted_files ),
                    'bf-secret-file-downloader'
                ),
                count( $deleted_files )
            );
            $response_data['message'] = $message;
            wp_send_json_success( $response_data );
        } elseif ( count( $deleted_files ) > 0 && count( $failed_files ) > 0 ) {
            // 一部成功
            $message = sprintf(
                /* translators: 1: number of deleted items, 2: number of failed items */
                __( '%1$d個のアイテムを削除しました。%2$d個のアイテムで失敗しました。', 'bf-secret-file-downloader' ),
                count( $deleted_files ),
                count( $failed_files )
            );
            $response_data['message'] = $message;
            wp_send_json_success( $response_data );
        } else {
            // 全て失敗
            wp_send_json_error( __( '選択されたアイテムの削除に失敗しました。', 'bf-secret-file-downloader' ) );
        }
    }

    /**
     * ファイルダウンロードのAJAXハンドラ
     */
    public function ajax_download_file() {
        // セキュリティチェック
        if ( ! current_user_can( 'read' ) ) {
            wp_die( 'Unauthorized' );
        }

        check_ajax_referer( 'bf_sfd_file_list_nonce', 'nonce' );

        $relative_path = sanitize_text_field( wp_unslash( $_POST['file_path'] ?? '' ) );

        // 入力値チェック
        if ( empty( $relative_path ) ) {
            wp_send_json_error( __( 'ファイルパスが指定されていません。', 'bf-secret-file-downloader' ) );
        }

        // ベースディレクトリを取得
        $base_directory = DirectoryManager::get_secure_directory();
        if ( empty( $base_directory ) ) {
            wp_send_json_error( __( '対象ディレクトリが設定されていません。', 'bf-secret-file-downloader' ) );
        }

        // フルパスを構築
        $full_path = SecurityHelper::build_safe_path( $base_directory, $relative_path );

        // セキュリティチェック：許可されたディレクトリのみ
        if ( ! SecurityHelper::is_allowed_directory( dirname( $full_path ) ) ) {
            wp_send_json_error( __( 'このファイルのダウンロードは許可されていません。', 'bf-secret-file-downloader' ) );
        }

        // ファイル存在チェック
        if ( ! file_exists( $full_path ) || ! is_file( $full_path ) ) {
            wp_send_json_error( __( '指定されたファイルが見つかりません。', 'bf-secret-file-downloader' ) );
        }

        // 読み込み権限チェック
        if ( ! is_readable( $full_path ) ) {
            wp_send_json_error( __( 'このファイルを読み取る権限がありません。', 'bf-secret-file-downloader' ) );
        }

        // ダウンロード用の一時的なトークンを生成
        $download_token = wp_generate_password( 32, false );
        $token_data = array(
            'file_path' => $relative_path,
            'user_id' => get_current_user_id(),
            'expires' => time() + 300, // 5分間有効
        );

        // トークンをトランジェントとして保存
        set_transient( 'bf_sfd_download_' . $download_token, $token_data, 300 );

        // ダウンロードURLを生成
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
     * ファイルダウンロード処理
     */
    public function handle_file_download() {
        $download_token = sanitize_text_field( wp_unslash( $_GET['bf_download'] ?? '' ) );

        if ( empty( $download_token ) ) {
            /* translators: Error message for invalid download token */
            wp_die( esc_html( __( '無効なダウンロードトークンです。', 'bf-secret-file-downloader' ) ), 400 );
        }

        // トークンを検証
        $token_data = get_transient( 'bf_sfd_download_' . $download_token );
        if ( $token_data === false ) {
            /* translators: Error message for invalid or expired download token */
            wp_die( esc_html( __( 'ダウンロードトークンが無効または期限切れです。', 'bf-secret-file-downloader' ) ), 400 );
        }

        // トークンを削除（一回限りの使用）
        delete_transient( 'bf_sfd_download_' . $download_token );

        // トークンの有効期限をチェック
        if ( time() > $token_data['expires'] ) {
            /* translators: Error message for expired download token */
            wp_die( esc_html( __( 'ダウンロードトークンの有効期限が切れています。', 'bf-secret-file-downloader' ) ), 400 );
        }

        // ユーザー権限チェック
        if ( ! current_user_can( 'read' ) ) {
            /* translators: Error message for insufficient download permissions */
            wp_die( esc_html( __( 'ファイルをダウンロードする権限がありません。', 'bf-secret-file-downloader' ) ), 403 );
        }

        // ベースディレクトリを取得
        $base_directory = DirectoryManager::get_secure_directory();
        if ( empty( $base_directory ) ) {
            /* translators: Error message for missing target directory */
            wp_die( esc_html( __( '対象ディレクトリが設定されていません。', 'bf-secret-file-downloader' ) ), 500 );
        }

        // フルパスを構築
        $relative_path = $token_data['file_path'];
        $full_path = SecurityHelper::build_safe_path( $base_directory, $relative_path );

        // セキュリティチェック
        if ( ! SecurityHelper::is_allowed_directory( dirname( $full_path ) ) ) {
            /* translators: Error message for unauthorized file download */
            wp_die( esc_html( __( 'このファイルのダウンロードは許可されていません。', 'bf-secret-file-downloader' ) ), 403 );
        }

        // ファイル存在チェック
        if ( ! file_exists( $full_path ) || ! is_file( $full_path ) ) {
            wp_die( esc_html( __( '指定されたファイルが見つかりません。', 'bf-secret-file-downloader' ) ), 404 );
        }

        // 読み込み権限チェック
        if ( ! is_readable( $full_path ) ) {
            wp_die( esc_html( __( 'このファイルを読み取る権限がありません。', 'bf-secret-file-downloader' ) ), 403 );
        }

        // ファイル情報を取得
        $filename = basename( $full_path );
        $filesize = filesize( $full_path );
        $mime_type = wp_check_filetype( $filename )['type'] ?? 'application/octet-stream';

        // ダウンロード用のヘッダーを設定
        if ( ! headers_sent() ) {
            header( 'Content-Type: ' . $mime_type );
            header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
            header( 'Content-Length: ' . $filesize );
            header( 'Cache-Control: no-cache, must-revalidate' );
            header( 'Pragma: no-cache' );
            header( 'Expires: 0' );

            // ファイルを出力
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
     * ページを表示します
     */
    public function render() {
        // 権限チェック
        if ( ! $this->can_access_files() ) {
            wp_die( esc_html( __( 'このページにアクセスする権限がありません。', 'bf-secret-file-downloader' ) ), 403 );
        }

        // ビューで使用するデータを準備
        $import = $this->prepare_data();

        // ViewRendererを使用してビューをレンダリング
        \Breadfish\SecretFileDownloader\ViewRenderer::admin( 'file-list.php', $import );
    }

    /**
     * 現在のパスを取得します（相対パス）
     *
     * @return string 現在の相対パス
     */
    private function get_current_path() {
        return sanitize_text_field( wp_unslash( $_GET['path'] ?? '' ) );
    }

    /**
     * アイテムをソートします
     *
     * @param array $items ソート対象のアイテム配列
     * @param string $sort_by ソートフィールド
     * @param string $sort_order ソート順序
     * @return array ソート済みアイテム配列
     */
    private function sort_items( $items, $sort_by, $sort_order ) {
        usort( $items, function( $a, $b ) use ( $sort_by, $sort_order ) {
            $result = 0;

            switch ( $sort_by ) {
                case 'name':
                    $result = strcmp( strtolower( $a['name'] ), strtolower( $b['name'] ) );
                    break;
                case 'size':
                    // ディレクトリのサイズは比較対象外
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
     * ファイルタイプに応じたCSSクラスを取得します
     *
     * @param string $filename ファイル名
     * @return string CSSクラス
     */
    private function get_file_type_class( $filename ) {
        $extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

        // 画像ファイル
        $image_extensions = array( 'jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp', 'ico' );
        if ( in_array( $extension, $image_extensions ) ) {
            return 'image-file';
        }

        // ドキュメントファイル
        $document_extensions = array( 'pdf', 'doc', 'docx', 'txt', 'rtf', 'odt', 'pages' );
        if ( in_array( $extension, $document_extensions ) ) {
            return 'document-file';
        }

        // アーカイブファイル
        $archive_extensions = array( 'zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz' );
        if ( in_array( $extension, $archive_extensions ) ) {
            return 'archive-file';
        }

        return '';
    }

    /**
     * ビューで使用するデータを準備します
     *
     * @return array ビューで使用するデータ
     */
    private function prepare_data() {

        $relative_path = $this->get_current_path();
        $page = $this->get_current_page();
        $sort_by = $this->get_current_sort_by();
        $sort_order = $this->get_current_sort_order();

        // ベースディレクトリを取得
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

        // WP_Filesystemを初期化
        global $wp_filesystem;
        if ( empty( $wp_filesystem ) ) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }

        // ファイルデータにフォーマット済みサイズとタイプクラスを追加
        $formatted_files = array();
        foreach ( $files as $file ) {
            $formatted_file = $file;
            if ( $file['size'] !== '-' ) {
                $formatted_file['formatted_size'] = $this->format_file_size( $file['size'] );
            } else {
                $formatted_file['formatted_size'] = '-';
            }

            // ファイルタイプクラスを追加
            if ( $file['type'] === 'file' ) {
                $formatted_file['type_class'] = $this->get_file_type_class( $file['name'] );
            } else {
                $formatted_file['type_class'] = '';
            }

            // 削除権限情報を追加
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
            'current_path_display' => empty( $relative_path ) ? __( 'ルートディレクトリ', 'bf-secret-file-downloader' ) : $relative_path,
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
     * ファイルリストを取得します
     *
     * @param string $full_path フルパス
     * @param string $relative_path 相対パス
     * @param int $page ページ番号
     * @param string $sort_by ソートフィールド
     * @param string $sort_order ソート順序
     * @return array ファイルリスト
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
     * ディレクトリの内容を取得します
     *
     * @param string $full_path フルパス
     * @param string $relative_path 相対パス
     * @param int $page ページ番号
     * @param string $sort_by ソートフィールド
     * @param string $sort_order ソート順序
     * @return array ディレクトリの内容
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

            // 隠しファイル（ドットから始まるファイル）を除外
            if ( strpos( $item, '.' ) === 0 ) {
                continue;
            }

            // プログラムコードファイルを除外
            if ( SecurityHelper::is_program_code_file( $item ) ) {
                continue;
            }

            // セキュアディレクトリの保護ファイルを除外
            if ( $item === 'index.php' ) {
                continue;
            }

            $full_item_path = $full_path . DIRECTORY_SEPARATOR . $item;

            // 相対パスを構築
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

        // ソート処理
        $directories = $this->sort_items( $directories, $sort_by, $sort_order );
        $files = $this->sort_items( $files, $sort_by, $sort_order );

        // 全アイテムを結合（ディレクトリを先頭に）
        $all_items = array_merge( $directories, $files );
        $total_items = count( $all_items );

        // ページング処理
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
     * 親ディレクトリの相対パスを取得します
     *
     * @param string $relative_path 現在の相対パス
     * @return string 親ディレクトリの相対パス
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
     * 親ディレクトリへのナビゲーションが可能かチェックします
     *
     * @param string $relative_path 現在の相対パス
     * @return bool ナビゲーション可能フラグ
     */
    private function can_navigate_to_parent( $relative_path ) {
        // ルートディレクトリの場合はfalse
        return ! empty( $relative_path );
    }

    /**
     * ページタイトルを取得します
     *
     * @return string ページタイトル
     */
    public function get_page_title() {
        return __( 'ファイルリスト', 'bf-secret-file-downloader' );
    }

    /**
     * ページングHTMLを生成します
     *
     * @param int $current_page 現在のページ
     * @param int $total_pages 総ページ数
     * @param string $current_path 現在のパス
     * @param string $sort_by ソートフィールド
     * @param string $sort_order ソート順序
     * @return string ページングHTML
     */
    public function render_pagination( $current_page, $total_pages, $current_path, $sort_by = 'name', $sort_order = 'asc' ) {
        if ( $total_pages <= 1 ) {
            return '';
        }

        $html = '<span class="pagination-links">';

        // 前のページ
        if ( $current_page > 1 ) {
            $prev_url = add_query_arg( array(
                'page' => 'bf-secret-file-downloader',
                'path' => urlencode( $current_path ),
                'paged' => $current_page - 1,
                'sort_by' => $sort_by,
                'sort_order' => $sort_order
            ), admin_url( 'admin.php' ) );
            $html .= '<a href="' . esc_url( $prev_url ) . '">&laquo; ' . __( '前', 'bf-secret-file-downloader' ) . '</a>';
        }

        // ページ番号
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

        // 次のページ
        if ( $current_page < $total_pages ) {
            $next_url = add_query_arg( array(
                'page' => 'bf-secret-file-downloader',
                'path' => urlencode( $current_path ),
                'paged' => $current_page + 1,
                'sort_by' => $sort_by,
                'sort_order' => $sort_order
            ), admin_url( 'admin.php' ) );
            $html .= '<a href="' . esc_url( $next_url ) . '">' . __( '次', 'bf-secret-file-downloader' ) . ' &raquo;</a>';
        }

        $html .= '</span>';
        return $html;
    }

    /**
     * ファイルサイズをフォーマットします
     *
     * @param int $bytes バイト数
     * @return string フォーマット済みファイルサイズ
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
     * メニュータイトルを取得します
     *
     * @return string メニュータイトル
     */
    public function get_menu_title() {
        return __( 'ファイルリスト', 'bf-secret-file-downloader' );
    }

    /**
     * 現在のユーザーがファイルアクセス権限を持っているかチェック
     *
     * @return bool アクセス権限の有無
     */
    private function can_access_files() {
        $allow_editor_admin = (bool) get_option( 'bf_sfd_allow_editor_admin', false );
        $capability = $allow_editor_admin ? 'edit_posts' : 'manage_options';
        return current_user_can( $capability );
    }

    /**
     * ファイル総数を取得します
     *
     * @param string $path ディレクトリパス
     * @return int ファイル総数
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
                    // 隠しファイル（ドットから始まるファイル）を除外
                    if ( strpos( $item, '.' ) === 0 ) {
                        continue;
                    }
                    // プログラムコードファイルを除外
                    if ( SecurityHelper::is_program_code_file( $item ) ) {
                        continue;
                    }
                    // セキュアディレクトリの保護ファイルを除外
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
     * 総ページ数を取得します
     *
     * @param string $path ディレクトリパス
     * @return int 総ページ数
     */
    private function get_total_pages( $path = '' ) {
        $total_files = $this->get_total_files( $path );
        return ceil( $total_files / self::FILES_PER_PAGE );
    }


    /**
     * アップロード制限を取得します
     *
     * @return string アップロード制限
     */
    private function get_upload_limit() {
        $max_size = get_option( 'bf_sfd_max_file_size', 10 );
        return $max_size . 'MB';
    }

    /**
     * 現在のページ番号を取得します
     *
     * @return int 現在のページ番号
     */
    private function get_current_page() {
        return max( 1, intval( $_GET['paged'] ?? 1 ) );
    }

    /**
     * 現在のソートフィールドを取得します
     *
     * @return string 現在のソートフィールド
     */
    private function get_current_sort_by() {
        $sort_by = sanitize_text_field( wp_unslash( $_GET['sort_by'] ?? 'name' ) );
        $allowed_sorts = array( 'name', 'size', 'modified' );
        return in_array( $sort_by, $allowed_sorts ) ? $sort_by : 'name';
    }

    /**
     * 現在のソート順序を取得します
     *
     * @return string 現在のソート順序
     */
    private function get_current_sort_order() {
        $sort_order = sanitize_text_field( wp_unslash( $_GET['sort_order'] ?? 'asc' ) );
        return in_array( $sort_order, array( 'asc', 'desc' ) ) ? $sort_order : 'asc';
    }

    /**
     * ディレクトリを再帰的に削除します
     *
     * @param string $directory_path 削除するディレクトリパス
     * @return bool 削除成功フラグ
     */
    private function delete_directory_recursive( $directory_path ) {
        if ( ! is_dir( $directory_path ) ) {
            return false;
        }

        // WP_Filesystemを初期化
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
                // サブディレクトリを再帰的に削除
                if ( ! $this->delete_directory_recursive( $item_path ) ) {
                    return false;
                }
            } else {
                // ファイルを削除
                if ( ! wp_delete_file( $item_path ) ) {
                    return false;
                }
            }
        }

        // 空になったディレクトリを削除
        return $wp_filesystem->rmdir( $directory_path );
    }

    /**
     * ディレクトリ認証設定のAJAXハンドラ
     */
    public function ajax_set_directory_auth() {
        // セキュリティチェック
        if ( ! $this->can_access_files() || ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        check_ajax_referer( 'bf_sfd_file_list_nonce', 'nonce' );

        $relative_path = sanitize_text_field( wp_unslash( $_POST['path'] ?? '' ) );
        $auth_methods = array_map( 'sanitize_text_field', wp_unslash( $_POST['auth_methods'] ?? array() ) );
        $allowed_roles = array_map( 'sanitize_text_field', wp_unslash( $_POST['allowed_roles'] ?? array() ) );
        $simple_auth_password = sanitize_text_field( wp_unslash( $_POST['simple_auth_password'] ?? '' ) );
        $action_type = sanitize_text_field( wp_unslash( $_POST['action_type'] ?? 'set' ) ); // set, remove

        // ベースディレクトリを取得
        $base_directory = DirectoryManager::get_secure_directory();
        if ( empty( $base_directory ) ) {
            wp_send_json_error( __( '対象ディレクトリが設定されていません。', 'bf-secret-file-downloader' ) );
        }

        // フルパスを構築
        $full_path = SecurityHelper::build_safe_path( $base_directory, $relative_path );

        // セキュリティチェック
        if ( ! SecurityHelper::is_allowed_directory( $full_path ) ) {
            wp_send_json_error( __( 'このディレクトリへのアクセスは許可されていません。', 'bf-secret-file-downloader' ) );
        }

        // ディレクトリの存在チェック
        if ( ! is_dir( $full_path ) ) {
            wp_send_json_error( __( 'ディレクトリが存在しません。', 'bf-secret-file-downloader' ) );
        }

        if ( $action_type === 'remove' ) {
            // 認証設定を削除
            $this->remove_directory_auth( $relative_path );
            wp_send_json_success( array(
                'message' => __( 'ディレクトリの認証設定を削除しました。', 'bf-secret-file-downloader' ),
                'has_auth' => false
            ));
        } else {
            // 認証設定を保存
            if ( empty( $auth_methods ) || ! is_array( $auth_methods ) ) {
                wp_send_json_error( __( '認証方法を選択してください。', 'bf-secret-file-downloader' ) );
            }

            // 簡易認証が選択されている場合、パスワードが必要
            if ( in_array( 'simple_auth', $auth_methods ) && empty( $simple_auth_password ) ) {
                wp_send_json_error( __( '簡易認証を選択した場合は、パスワードを設定してください。', 'bf-secret-file-downloader' ) );
            }

            $this->set_directory_auth( $relative_path, $auth_methods, $allowed_roles, $simple_auth_password );
            wp_send_json_success( array(
                'message' => __( 'ディレクトリの認証設定を保存しました。', 'bf-secret-file-downloader' ),
                'has_auth' => true
            ));
        }
    }

    /**
     * ディレクトリ認証設定取得のAJAXハンドラ
     */
    public function ajax_get_directory_auth() {
        // セキュリティチェック
        if ( ! $this->can_access_files() || ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        check_ajax_referer( 'bf_sfd_file_list_nonce', 'nonce' );

        $relative_path = sanitize_text_field( wp_unslash( $_POST['path'] ?? '' ) );

        // ベースディレクトリを取得
        $base_directory = DirectoryManager::get_secure_directory();
        if ( empty( $base_directory ) ) {
            wp_send_json_error( __( '対象ディレクトリが設定されていません。', 'bf-secret-file-downloader' ) );
        }

        // フルパスを構築
        $full_path = SecurityHelper::build_safe_path( $base_directory, $relative_path );

        // セキュリティチェック
        if ( ! SecurityHelper::is_allowed_directory( $full_path ) ) {
            wp_send_json_error( __( 'このディレクトリへのアクセスは許可されていません。', 'bf-secret-file-downloader' ) );
        }

        // 認証設定を取得
        $auth_settings = $this->get_directory_auth( $relative_path );

        if ( $auth_settings !== false ) {
            wp_send_json_success( $auth_settings );
        } else {
            wp_send_json_error( __( '認証設定を取得できませんでした。', 'bf-secret-file-downloader' ) );
        }
    }

    /**
     * ディレクトリに認証設定を保存します
     *
     * @param string $relative_path 相対パス
     * @param array $auth_methods 認証方法の配列
     * @param array $allowed_roles 許可するユーザーロールの配列
     * @param string $simple_auth_password 簡易認証パスワード
     */
    private function set_directory_auth( $relative_path, $auth_methods, $allowed_roles, $simple_auth_password ) {
        $directory_auths = get_option( 'bf_sfd_directory_auths', array() );

        $auth_data = array(
            'auth_methods' => $auth_methods,
            'allowed_roles' => $allowed_roles,
        );

        // 簡易認証パスワードが設定されている場合
        if ( ! empty( $simple_auth_password ) ) {
            $auth_data['simple_auth_hash'] = wp_hash_password( $simple_auth_password );
            $auth_data['simple_auth_encrypted'] = $this->encrypt_password( $simple_auth_password );
        }

        $directory_auths[ $relative_path ] = $auth_data;
        update_option( 'bf_sfd_directory_auths', $directory_auths );
        
        // 認証設定変更時刻を更新（全ユーザーの認証を無効化）
        update_option( 'bf_sfd_auth_settings_changed', time() );
        error_log( 'Directory auth settings updated. Timestamp: ' . time() );
    }

    /**
     * ディレクトリの認証設定を削除します
     *
     * @param string $relative_path 相対パス
     */
    private function remove_directory_auth( $relative_path ) {
        $directory_auths = get_option( 'bf_sfd_directory_auths', array() );

        if ( isset( $directory_auths[ $relative_path ] ) ) {
            unset( $directory_auths[ $relative_path ] );
            update_option( 'bf_sfd_directory_auths', $directory_auths );
            
            // 認証設定変更時刻を更新（全ユーザーの認証を無効化）
            update_option( 'bf_sfd_auth_settings_changed', time() );
            error_log( 'Directory auth settings removed. Timestamp: ' . time() );
        }
    }

    /**
     * ディレクトリに認証設定があるかチェックします
     *
     * @param string $relative_path 相対パス
     * @return bool 認証設定フラグ
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
     * ディレクトリにパスワードが設定されているかチェックします
     *
     * @param string $relative_path 相対パス
     * @return bool パスワード設定フラグ
     */
    private function has_directory_password( $relative_path ) {
        $directory_passwords = get_option( 'bf_sfd_directory_passwords', array() );

        if ( ! isset( $directory_passwords[ $relative_path ] ) ) {
            return false;
        }

        // 新しい配列形式をチェック
        if ( is_array( $directory_passwords[ $relative_path ] ) ) {
            return ! empty( $directory_passwords[ $relative_path ]['hash'] );
        }

        // 古い文字列形式（後方互換性）
        return ! empty( $directory_passwords[ $relative_path ] );
    }

    /**
     * ディレクトリの認証設定を取得します
     *
     * @param string $relative_path 相対パス
     * @return array|false 認証設定、または失敗時はfalse
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

        // 簡易認証パスワードを復号化
        if ( isset( $auth_data['simple_auth_encrypted'] ) ) {
            $result['simple_auth_password'] = $this->decrypt_password( $auth_data['simple_auth_encrypted'] );
        }

        return $result;
    }

    /**
     * パスワードを暗号化します
     *
     * @param string $password 平文パスワード
     * @return string 暗号化されたパスワード
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
     * パスワードを復号化します
     *
     * @param string $encrypted_password 暗号化されたパスワード
     * @return string|false 復号化されたパスワード、または失敗時はfalse
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
     * 暗号化キーを取得します
     *
     * @return string 暗号化キー
     */
    private function get_encryption_key() {
        // WordPressのソルトを使用してキーを生成
        $salt_keys = array( AUTH_KEY, SECURE_AUTH_KEY, LOGGED_IN_KEY, NONCE_KEY );
        return hash( 'sha256', implode( '', $salt_keys ) );
    }

    /**
     * 共通認証設定を取得するAJAXハンドラ
     */
    public function ajax_get_global_auth() {
        // セキュリティチェック
        if ( ! $this->can_access_files() || ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        check_ajax_referer( 'bf_sfd_file_list_nonce', 'nonce' );

        // 共通設定を取得
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
     * セキュアディレクトリ再作成のAJAXハンドラ
     */
    public function ajax_recreate_secure_directory() {
        // セキュリティチェック
        if ( ! $this->can_access_files() || ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        check_ajax_referer( 'bf_sfd_file_list_nonce', 'nonce' );

        // 新しいセキュアディレクトリを強制作成
        $result = DirectoryManager::create_secure_directory( true );

        if ( $result ) {
            $new_directory = DirectoryManager::get_secure_directory();
            wp_send_json_success( array(
                'message' => __( '新しいセキュアディレクトリが作成されました。', 'bf-secret-file-downloader' ),
                'directory' => $new_directory
            ));
        } else {
            wp_send_json_error( __( 'セキュアディレクトリの作成に失敗しました。', 'bf-secret-file-downloader' ) );
        }
    }

}