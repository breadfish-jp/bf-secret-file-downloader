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
     * Name of the base directory created under wp-content/uploads
     * wp-content/uploads 直下に作成するベースディレクトリ名
     */
    const BASE_DIRECTORY_NAME = 'bf-secret-file-downloader';

    /**
     * Prefix of the secure directory name.
     * A dot-prefixed (hidden) directory is denied by the "location ~ /\. { deny all; }"
     * rule that most Nginx configurations for WordPress include, so files are
     * protected on Nginx without any additional server configuration.
     *
     * セキュアディレクトリ名の接頭辞。
     * ドットで始まる（隠し）ディレクトリは、WordPress 向けの多くの Nginx 設定に
     * 含まれる「location ~ /\. { deny all; }」ルールで拒否されるため、
     * サーバー設定を追加しなくても Nginx 上でファイルが保護される。
     */
    const HIDDEN_DIRECTORY_PREFIX = '.';

    /**
     * Transient key for caching the protection status
     * 保護状態をキャッシュする transient のキー
     */
    const PROTECTION_STATUS_TRANSIENT = 'bf_sfd_protection_status';

    /**
     * Option key of the flag to show the "directory moved" notice
     * 「ディレクトリを移動した」通知を表示するフラグのオプションキー
     */
    const MOVED_NOTICE_OPTION = 'bf_sfd_show_directory_moved_notice';

    /**
     * Protection status: direct access is blocked
     * 保護状態: 直接アクセスが遮断されている
     */
    const STATUS_PROTECTED = 'protected';

    /**
     * Protection status: files can be downloaded directly
     * 保護状態: ファイルを直接ダウンロードできてしまう
     */
    const STATUS_UNPROTECTED = 'unprotected';

    /**
     * Protection status: could not be determined (e.g. loopback request failed)
     * 保護状態: 判定できなかった（ループバックリクエストの失敗など）
     */
    const STATUS_UNKNOWN = 'unknown';

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

        // Create a hidden directory under wp-content/uploads/bf-secret-file-downloader
        // wp-content/uploads/bf-secret-file-downloader 配下に隠しディレクトリを作成する
        $secure_base_dir = self::get_base_directory();
        $secure_dir = $secure_base_dir . '/' . self::get_directory_name( $random_id );

        // Create a directory
        if ( ! wp_mkdir_p( $secure_dir ) ) {
            return false;
        }

        // Place protection files in both the base directory and the secure directory.
        // The base directory also needs them so that the directory ID is not exposed by directory listing.
        // ベースディレクトリにも保護ファイルを置き、ディレクトリ一覧からIDが漏れないようにする
        self::write_protection_files( $secure_base_dir );
        self::write_protection_files( $secure_dir );

        // Update existing settings (using update_option instead of add_option)
        update_option( 'bf_sfd_secure_directory_id', $random_id );
        update_option( 'bf_sfd_target_directory', $secure_dir );

        // The directory has changed, so discard the cached protection status
        // ディレクトリが変わったので、キャッシュ済みの保護状態を破棄する
        delete_transient( self::PROTECTION_STATUS_TRANSIENT );

        return true;
    }

    /**
     * Get the path to the base directory (wp-content/uploads/bf-secret-file-downloader)
     * ベースディレクトリ（wp-content/uploads/bf-secret-file-downloader）のパスを取得する
     *
     * @return string the path to the base directory / ベースディレクトリのパス
     */
    public static function get_base_directory() {
        $uploads_dir = wp_upload_dir();
        return $uploads_dir['basedir'] . '/' . self::BASE_DIRECTORY_NAME;
    }

    /**
     * Get the secure directory name from the directory ID
     * ディレクトリIDからセキュアディレクトリ名を取得する
     *
     * @param string $secure_id the directory ID / ディレクトリID
     * @return string the directory name (dot-prefixed) / ディレクトリ名（ドット始まり）
     */
    public static function get_directory_name( $secure_id ) {
        return self::HIDDEN_DIRECTORY_PREFIX . $secure_id;
    }

    /**
     * Get the path to the secure directory
     * セキュアディレクトリのパスを取得する
     *
     * Returns the legacy (non-hidden) directory only while it has not been migrated yet,
     * so that the plugin keeps working even if the migration fails.
     * 移行前の旧（隠しでない）ディレクトリが残っている間はそちらを返し、
     * 移行に失敗してもプラグインが動作し続けるようにする。
     *
     * @return string the path to the secure directory (empty if it does not exist)
     */
    public static function get_secure_directory() {
        $secure_id = get_option( 'bf_sfd_secure_directory_id', '' );
        if ( empty( $secure_id ) ) {
            return '';
        }

        $base_dir = self::get_base_directory();
        $hidden_dir = $base_dir . '/' . self::get_directory_name( $secure_id );
        $legacy_dir = $base_dir . '/' . $secure_id;

        // Use the legacy directory only when the hidden directory does not exist yet
        // 隠しディレクトリがまだ無く、旧ディレクトリがある場合のみ旧ディレクトリを使う
        if ( ! is_dir( $hidden_dir ) && is_dir( $legacy_dir ) ) {
            return $legacy_dir;
        }

        return $hidden_dir;
    }

    /**
     * Migrate the legacy (non-hidden) secure directory to the hidden directory
     * 旧（隠しでない）セキュアディレクトリを隠しディレクトリへ移行する
     *
     * Called on every request, but costs only a single is_dir() check once migrated.
     * 毎リクエスト呼ばれるが、移行済みなら is_dir() 1回だけで終わる。
     *
     * @return bool true if the directory was moved in this call / この呼び出しで移動した場合 true
     */
    public static function maybe_migrate_to_hidden_directory() {
        $secure_id = get_option( 'bf_sfd_secure_directory_id', '' );
        if ( empty( $secure_id ) ) {
            return false;
        }

        $base_dir = self::get_base_directory();
        $hidden_dir = $base_dir . '/' . self::get_directory_name( $secure_id );
        $legacy_dir = $base_dir . '/' . $secure_id;

        // Already migrated, or nothing to migrate
        // 移行済み、または移行対象が無い
        if ( is_dir( $hidden_dir ) || ! is_dir( $legacy_dir ) ) {
            return false;
        }

        // Rename the directory. WP_Filesystem is not used because it may require
        // FTP credentials and this runs on front-end requests as well.
        // ディレクトリ名を変更する。フロントエンドのリクエストでも実行され、
        // WP_Filesystem は FTP 認証情報を要求する場合があるため rename() を使う。
        if ( ! @rename( $legacy_dir, $hidden_dir ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename, WordPress.PHP.NoSilencedErrors.Discouraged
            return false;
        }

        // Protect the base directory as well (older versions did not create protection files there)
        // ベースディレクトリも保護する（旧バージョンでは保護ファイルを置いていなかった）
        self::write_protection_files( $base_dir );

        update_option( 'bf_sfd_target_directory', $hidden_dir );
        delete_transient( self::PROTECTION_STATUS_TRANSIENT );

        // Tell the administrator that the location for FTP uploads has changed
        // FTP でのアップロード先が変わったことを管理者に知らせる
        update_option( self::MOVED_NOTICE_OPTION, true );

        return true;
    }

    /**
     * Write the protection files (.htaccess and index.php) to the specified directory
     * 指定ディレクトリに保護ファイル（.htaccess と index.php）を書き込む
     *
     * @param string $directory the target directory / 対象ディレクトリ
     * @return bool true if both files were written / 両方書き込めた場合 true
     */
    private static function write_protection_files( $directory ) {
        // Create an .htaccess file to completely block access (Apache)
        // アクセスを完全に遮断する .htaccess を作成する（Apache 用）
        $htaccess_content = "# Deny all access\nDeny from all\n";
        $htaccess_written = file_put_contents( $directory . '/.htaccess', $htaccess_content );

        // Create an index.php file to prevent directory listing
        // ディレクトリ一覧の表示を防ぐ index.php を作成する
        $index_content = "<?php\n// Silence is golden.\nexit;";
        $index_written = file_put_contents( $directory . '/index.php', $index_content );

        return $htaccess_written !== false && $index_written !== false;
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
     * Note: this only checks that the files exist. Whether the web server actually
     * blocks direct access is checked by get_protection_status().
     * 注意: ファイルの存在を確認するだけ。Web サーバーが実際に直接アクセスを
     * 遮断しているかは get_protection_status() で確認する。
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

        // Re-create the protection files in the base directory and the secure directory
        // ベースディレクトリとセキュアディレクトリの保護ファイルを作り直す
        $base_repaired = self::write_protection_files( self::get_base_directory() );
        $secure_repaired = self::write_protection_files( $secure_dir );

        return $base_repaired && $secure_repaired;
    }

    /**
     * Get the URL of the secure directory
     * セキュアディレクトリの URL を取得する
     *
     * @return string the URL (empty if the directory is not set) / URL（未設定なら空文字）
     */
    public static function get_secure_directory_url() {
        $secure_dir = self::get_secure_directory();
        if ( empty( $secure_dir ) ) {
            return '';
        }

        $uploads_dir = wp_upload_dir();
        return $uploads_dir['baseurl'] . '/' . self::BASE_DIRECTORY_NAME . '/' . basename( $secure_dir );
    }

    /**
     * Get the protection status of the secure directory
     * セキュアディレクトリの保護状態を取得する
     *
     * The result is cached in a transient because the check sends an HTTP request.
     * HTTP リクエストを伴うため、結果は transient にキャッシュする。
     *
     * @param bool $force true to ignore the cache and check again / キャッシュを無視して再確認する場合 true
     * @return string one of the STATUS_* constants / STATUS_* 定数のいずれか
     */
    public static function get_protection_status( $force = false ) {
        $valid_statuses = array( self::STATUS_PROTECTED, self::STATUS_UNPROTECTED, self::STATUS_UNKNOWN );

        // Return the cached status if available
        // キャッシュがあればそれを返す
        if ( ! $force ) {
            $cached = get_transient( self::PROTECTION_STATUS_TRANSIENT );
            if ( in_array( $cached, $valid_statuses, true ) ) {
                return $cached;
            }
        }

        $status = self::check_protection_via_http();

        // Re-check an undetermined status sooner, since the cause may be temporary
        // 判定不能は一時的な原因の場合もあるため、短めの間隔で再確認する
        $expiration = ( $status === self::STATUS_UNKNOWN ) ? HOUR_IN_SECONDS : DAY_IN_SECONDS;
        set_transient( self::PROTECTION_STATUS_TRANSIENT, $status, $expiration );

        return $status;
    }

    /**
     * Check whether files in the secure directory can be downloaded directly
     * セキュアディレクトリ内のファイルが直接ダウンロードできるかを確認する
     *
     * Places a temporary file with random content, requests its URL via a loopback
     * request, and deletes it immediately.
     * ランダムな内容の一時ファイルを置き、ループバックリクエストでその URL を取得して、
     * すぐに削除する。
     *
     * @return string one of the STATUS_* constants / STATUS_* 定数のいずれか
     */
    private static function check_protection_via_http() {
        $secure_dir = self::get_secure_directory();
        if ( empty( $secure_dir ) || ! is_dir( $secure_dir ) ) {
            return self::STATUS_UNKNOWN;
        }

        // Use a random file name so that the file cannot be targeted during the check
        // 確認中にファイルを狙われないよう、ファイル名もランダムにする
        $token = wp_generate_password( 32, false );
        $filename = 'bf-sfd-protection-check-' . wp_generate_password( 16, false ) . '.txt';
        $file_path = $secure_dir . '/' . $filename;

        if ( file_put_contents( $file_path, $token ) === false ) {
            return self::STATUS_UNKNOWN;
        }

        $response = wp_remote_get(
            self::get_secure_directory_url() . '/' . $filename,
            array(
                'timeout'     => 5,
                'redirection' => 3,
                // Same as WordPress core loopback requests (Site Health)
                // WordPress コアのループバックリクエスト（サイトヘルス）と同じ扱い
                'sslverify'   => apply_filters( 'https_local_ssl_verify', false ),
            )
        );

        // Delete the temporary file right after the request
        // リクエスト直後に一時ファイルを削除する
        wp_delete_file( $file_path );

        if ( is_wp_error( $response ) ) {
            return self::STATUS_UNKNOWN;
        }

        return self::evaluate_protection_response(
            (int) wp_remote_retrieve_response_code( $response ),
            (string) wp_remote_retrieve_body( $response ),
            $token
        );
    }

    /**
     * Determine the protection status from the loopback response
     * ループバックリクエストの応答から保護状態を判定する
     *
     * @param int    $status_code HTTP status code (0 if unavailable) / HTTP ステータスコード（取得できない場合 0）
     * @param string $body        response body / レスポンスボディ
     * @param string $token       content of the temporary file / 一時ファイルの内容
     * @return string one of the STATUS_* constants / STATUS_* 定数のいずれか
     */
    public static function evaluate_protection_response( $status_code, $body, $token ) {
        // The file content was returned: files can be downloaded directly
        // ファイルの内容が返ってきた: 直接ダウンロードできる状態
        if ( $status_code === 200 && $token !== '' && trim( $body ) === $token ) {
            return self::STATUS_UNPROTECTED;
        }

        // No response, or still redirecting: cannot be determined
        // 応答なし、またはリダイレクトが続いた: 判定できない
        if ( $status_code < 200 || ( $status_code >= 300 && $status_code < 400 ) ) {
            return self::STATUS_UNKNOWN;
        }

        // Any other response (403, 404, error page, etc.) did not expose the file
        // それ以外の応答（403・404・エラーページなど）ではファイルは漏れていない
        return self::STATUS_PROTECTED;
    }

    /**
     * Get an Nginx configuration example that denies direct access to the base directory
     * ベースディレクトリへの直接アクセスを拒否する Nginx 設定例を取得する
     *
     * @return string the Nginx configuration / Nginx 設定
     */
    public static function get_nginx_deny_rule() {
        $uploads_dir = wp_upload_dir();
        $uploads_path = wp_parse_url( $uploads_dir['baseurl'], PHP_URL_PATH );
        $uploads_path = is_string( $uploads_path ) ? untrailingslashit( $uploads_path ) : '';

        return 'location ^~ ' . $uploads_path . '/' . self::BASE_DIRECTORY_NAME . '/ { deny all; }';
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
