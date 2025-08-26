<?php
/**
 * セキュリティ関連のヘルパー機能を提供するクラス
 *
 * @package BfSecretFileDownloader
 */

namespace Breadfish\SecretFileDownloader;

// セキュリティチェック：直接アクセスを防ぐ
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * SecurityHelper クラス
 * ディレクトリトラバーサル防止とファイルアクセス制御機能を提供します
 */
class SecurityHelper {

    /**
     * 安全なパスを構築します（ディレクトリトラバーサル防止）
     *
     * @param string $base_directory ベースディレクトリ
     * @param string $relative_path 相対パス
     * @return string 安全なパス
     */
    public static function build_safe_path( $base_directory, $relative_path ) {
        error_log( 'SecurityHelper::build_safe_path - Base directory: ' . $base_directory );
        error_log( 'SecurityHelper::build_safe_path - Relative path: ' . $relative_path );
        
        // 空の相対パスの場合はベースディレクトリを返す
        if ( empty( $relative_path ) ) {
            error_log( 'SecurityHelper::build_safe_path - Empty relative path, returning base directory' );
            return $base_directory;
        }

        // 危険な文字列をチェック
        if ( strpos( $relative_path, '..' ) !== false || strpos( $relative_path, '//' ) !== false ) {
            error_log( 'SecurityHelper::build_safe_path - Dangerous characters detected, returning base directory' );
            return $base_directory;
        }

        // パスを構築
        $full_path = $base_directory . DIRECTORY_SEPARATOR . ltrim( $relative_path, DIRECTORY_SEPARATOR );
        error_log( 'SecurityHelper::build_safe_path - Constructed full path: ' . $full_path );

        // パスを正規化
        $normalized_path = realpath( $full_path );
        error_log( 'SecurityHelper::build_safe_path - Normalized path: ' . ( $normalized_path !== false ? $normalized_path : 'FALSE' ) );

        // 正規化に失敗した場合や、ベースディレクトリ外の場合はベースディレクトリを返す
        $real_base = realpath( $base_directory );
        error_log( 'SecurityHelper::build_safe_path - Real base directory: ' . ( $real_base !== false ? $real_base : 'FALSE' ) );
        
        if ( $normalized_path === false || strpos( $normalized_path, $real_base ) !== 0 ) {
            error_log( 'SecurityHelper::build_safe_path - Security check failed, returning base directory' );
            error_log( 'SecurityHelper::build_safe_path - Reason: normalized=' . ( $normalized_path === false ? 'FALSE' : $normalized_path ) );
            return $base_directory;
        }

        error_log( 'SecurityHelper::build_safe_path - Returning normalized path: ' . $normalized_path );
        return $normalized_path;
    }

    /**
     * ディレクトリアクセスが許可されているかチェックします
     *
     * @param string $path チェックするパス
     * @return bool 許可されている場合はtrue
     */
    public static function is_allowed_directory( $path ) {
        error_log( 'SecurityHelper::is_allowed_directory - Checking path: ' . $path );
        
        $real_path = realpath( $path );
        error_log( 'SecurityHelper::is_allowed_directory - Real path: ' . ( $real_path !== false ? $real_path : 'FALSE' ) );
        
        if ( $real_path === false ) {
            error_log( 'SecurityHelper::is_allowed_directory - Real path is false, denying access' );
            return false;
        }

        // シンボリックリンクの場合は拒否
        if ( is_link( $path ) ) {
            error_log( 'SecurityHelper::is_allowed_directory - Path is symbolic link, denying access' );
             return false;
        }

        // 基本となる対象ディレクトリを取得
        $target_directory = \Breadfish\SecretFileDownloader\DirectoryManager::get_secure_directory();
        error_log( 'SecurityHelper::is_allowed_directory - Target directory: ' . ( ! empty( $target_directory ) ? $target_directory : 'EMPTY' ) );
        
        if ( empty( $target_directory ) ) {
            error_log( 'SecurityHelper::is_allowed_directory - Target directory is empty, denying access' );
            return false;
        }

        $real_target_directory = realpath( $target_directory );
        error_log( 'SecurityHelper::is_allowed_directory - Real target directory: ' . ( $real_target_directory !== false ? $real_target_directory : 'FALSE' ) );
        
        if ( $real_target_directory === false ) {
            error_log( 'SecurityHelper::is_allowed_directory - Real target directory is false, denying access' );
            return false;
        }

        // セキュアディレクトリ内かつ存在するディレクトリのみ許可
        $within_target = strpos( $real_path, $real_target_directory ) === 0;
        $is_dir_check = is_dir( $real_path );
        
        error_log( 'SecurityHelper::is_allowed_directory - Within target: ' . ( $within_target ? 'YES' : 'NO' ) );
        error_log( 'SecurityHelper::is_allowed_directory - Is directory: ' . ( $is_dir_check ? 'YES' : 'NO' ) );
        
        $result = $within_target && $is_dir_check;
        error_log( 'SecurityHelper::is_allowed_directory - Final result: ' . ( $result ? 'ALLOWED' : 'DENIED' ) );
        
        return $result;
    }

    /**
     * ファイルアップロードのセキュリティチェック
     *
     * @param string $filename ファイル名
     * @param string $target_path ターゲットパス
     * @return array チェック結果
     */
    public static function check_file_upload_security( $filename, $target_path ) {
        // ディレクトリが許可されているかチェック
        // target_pathがディレクトリの場合はそのまま、ファイルの場合はdirname()を使用
        $check_dir = is_dir( $target_path ) ? $target_path : dirname( $target_path );

        if ( ! self::is_allowed_directory( $check_dir ) ) {
            return array( 'allowed' => false, 'error_message' => 'アップロード先ディレクトリへのアクセスが許可されていません。' );
        }

        // 基本的なファイル名チェック（プログラムコードファイルは別途チェック）
        if ( empty( $filename ) || strpos( $filename, '..' ) !== false ) {
            return array( 'allowed' => false, 'error_message' => '無効なファイル名です。' );
        }

        return array( 'allowed' => true, 'error_message' => '' );
    }

    /**
     * ディレクトリ作成のセキュリティチェック
     *
     * @param string $parent_path 親ディレクトリパス
     * @param string $directory_name 作成するディレクトリ名
     * @return array チェック結果
     */
    public static function check_ajax_create_directory_security( $parent_path, $directory_name ) {
        // 親ディレクトリが許可されているかチェック
        if ( ! self::is_allowed_directory( $parent_path ) ) {
            return array( 'allowed' => false, 'error_message' => 'ディレクトリ作成が許可されていません。' );
        }

        // ディレクトリ名の基本チェック
        if ( empty( $directory_name ) || strpos( $directory_name, '..' ) !== false || strpos( $directory_name, '/' ) !== false ) {
            return array( 'allowed' => false, 'error_message' => '無効なディレクトリ名です。' );
        }

        return array( 'allowed' => true, 'error_message' => '' );
    }

    /**
     * ファイル名がプログラムコードファイルかどうかチェック
     *
     * @param string $filename ファイル名
     * @return bool プログラムコードファイルの場合はtrue
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

        // 設定ファイルの一般的なファイル名パターンをチェック
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
     * パスにヌルバイトが含まれているかチェック
     *
     * @param string $path チェックするパス
     * @return bool ヌルバイトが含まれている場合はtrue
     */
    public static function contains_null_byte( $path ) {
        return strpos( $path, "\0" ) !== false;
    }

    /**
     * パスが絶対パスかどうかチェック
     *
     * @param string $path チェックするパス
     * @return bool 絶対パスの場合はtrue
     */
    public static function is_absolute_path( $path ) {
        // Unix系の絶対パス
        if ( substr( $path, 0, 1 ) === '/' ) {
            return true;
        }

        // Windowsの絶対パス（C:\ など）
        if ( preg_match( '/^[a-zA-Z]:[\\\\\/]/', $path ) ) {
            return true;
        }

        return false;
    }
}