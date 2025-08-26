<?php
/**
 * ビューレンダラークラス
 *
 * @package BfSecretFileDownloader
 */

namespace Breadfish\SecretFileDownloader;

// セキュリティチェック：直接アクセスを防ぐ
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ViewRenderer クラス
 * ビューファイルのレンダリングと変数スコープを管理します
 */
class ViewRenderer {

    /**
     * ビューファイルをレンダリングします
     *
     * @param string $view_file ビューファイルのパス（相対パス）
     * @param array  $import    ビューに渡す変数の配列
     * @param string $view_type ビューの種類（Admin, Frontend, Blocks等）
     */
    public static function render( $view_file, $import = array(), $view_type = 'Admin' ) {
        // ビューファイルの完全パスを構築
        $view_path = BF_SECRET_FILE_DOWNLOADER_PLUGIN_DIR . 'inc/views/' . $view_type . '/' . $view_file;

        // ファイルが存在するかチェック
        if ( ! file_exists( $view_path ) ) {
            wp_die(
                sprintf(
                    /* translators: %s: ビューファイルのパス */
                    esc_html__( 'ビューファイルが見つかりません: %s', 'bf-secret-file-downloader' ),
                    esc_html( $view_type . '/' . $view_file )
                )
            );
        }

        // ビューファイルを安全にインクルード
        // 変数を明示的に渡す（extractを使用しない）
        // グローバルスコープで変数を利用可能にする
        foreach ( $import as $key => $value ) {
            $$key = $value;
        }
        include $view_path;
    }

    /**
     * 管理画面用ビューをレンダリングします（ショートカット）
     *
     * @param string $view_file ビューファイルのパス（相対パス）
     * @param array  $import    ビューに渡す変数の配列
     */
    public static function admin( $view_file, $import = array() ) {
        self::render( $view_file, $import, 'Admin' );
    }

    /**
     * フロントエンド用ビューをレンダリングします（ショートカット）
     *
     * @param string $view_file ビューファイルのパス（相対パス）
     * @param array  $import    ビューに渡す変数の配列
     */
    public static function frontend( $view_file, $import = array() ) {
        self::render( $view_file, $import, 'Frontend' );
    }
}