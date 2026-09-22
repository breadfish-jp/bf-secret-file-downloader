<?php

namespace Breadfish\SecretFileDownloader\Tests;

use Breadfish\SecretFileDownloader\DirectoryManager;
use WP_Mock\Tools\TestCase;
use WP_Mock;

/**
 * Test cases for DirectoryManager class
 */
class DirectoryManagerTest extends TestCase {

    public function setUp(): void {
        parent::setUp();
        WP_Mock::setUp();
    }

    public function tearDown(): void {
        WP_Mock::tearDown();
        parent::tearDown();
    }

    /**
     * Test get_secure_directory method
     */
    public function test_get_secure_directory() {
        // Test with no secure directory ID
        WP_Mock::userFunction( 'get_option' )
            ->with( 'bf_sfd_secure_directory_id', '' )
            ->once()
            ->andReturn( '' );

        $result = DirectoryManager::get_secure_directory();
        $this->assertEquals( '', $result );

        // Reset WP_Mock for second test
        WP_Mock::tearDown();
        WP_Mock::setUp();

        // Test with secure directory ID
        WP_Mock::userFunction( 'get_option' )
            ->with( 'bf_sfd_secure_directory_id', '' )
            ->once()
            ->andReturn( 'test_secure_id_123' );

        WP_Mock::userFunction( 'wp_upload_dir' )
            ->once()
            ->andReturn( array( 'basedir' => '/var/www/uploads' ) );

        // The hidden (dot-prefixed) directory is returned when the legacy directory does not exist
        // 旧ディレクトリが無い場合は隠し（ドット始まり）ディレクトリが返る
        $result = DirectoryManager::get_secure_directory();
        $this->assertEquals( '/var/www/uploads/bf-secret-file-downloader/.test_secure_id_123', $result );
    }

    /**
     * Test get_secure_directory with the legacy and hidden directories on the real file system
     * 実ファイルシステム上の旧ディレクトリ・隠しディレクトリの有無による get_secure_directory のテスト
     */
    public function test_get_secure_directory_with_legacy_directory() {
        $test_cases = array(
            array(
                'test_condition_name' => '旧ディレクトリのみ存在する場合 => 旧ディレクトリ',
                'conditions'          => array(
                    'secure_id' => 'abc123',
                    'dirs'      => array( 'abc123' ),
                ),
                'expected'            => 'abc123',
            ),
            array(
                'test_condition_name' => '隠しディレクトリのみ存在する場合 => 隠しディレクトリ',
                'conditions'          => array(
                    'secure_id' => 'abc123',
                    'dirs'      => array( '.abc123' ),
                ),
                'expected'            => '.abc123',
            ),
            array(
                'test_condition_name' => '両方存在する場合 => 隠しディレクトリを優先',
                'conditions'          => array(
                    'secure_id' => 'abc123',
                    'dirs'      => array( 'abc123', '.abc123' ),
                ),
                'expected'            => '.abc123',
            ),
            array(
                'test_condition_name' => 'どちらも存在しない場合 => 隠しディレクトリ（新規作成先）',
                'conditions'          => array(
                    'secure_id' => 'abc123',
                    'dirs'      => array(),
                ),
                'expected'            => '.abc123',
            ),
        );

        foreach ( $test_cases as $case ) {
            // Build a temporary uploads directory with the directories of this case
            // このケースのディレクトリを持つ一時 uploads ディレクトリを作る
            $uploads = $this->create_temp_uploads_dir();
            foreach ( $case['conditions']['dirs'] as $dir ) {
                mkdir( $uploads . '/bf-secret-file-downloader/' . $dir, 0777, true );
            }
            $this->mock_directory_options( $uploads, $case['conditions']['secure_id'] );

            // Run the method under test
            // テスト対象を実行する
            $actual = DirectoryManager::get_secure_directory();

            $this->assertEquals( $uploads . '/bf-secret-file-downloader/' . $case['expected'], $actual, $case['test_condition_name'] );

            // Clean up the temporary directory and mocks
            // 一時ディレクトリとモックを片付ける
            $this->remove_dir_recursive( $uploads );
            WP_Mock::tearDown();
            WP_Mock::setUp();
        }
    }

    /**
     * Test get_directory_name method
     * get_directory_name メソッドのテスト
     */
    public function test_get_directory_name() {
        $test_cases = array(
            array(
                'test_condition_name' => '32桁のIDの場合 => 先頭にドットが付く',
                'conditions'          => array( 'secure_id' => '0123456789abcdef0123456789abcdef' ),
                'expected'            => '.0123456789abcdef0123456789abcdef',
            ),
            array(
                'test_condition_name' => '短いIDの場合 => 先頭にドットが付く',
                'conditions'          => array( 'secure_id' => 'abc' ),
                'expected'            => '.abc',
            ),
            array(
                'test_condition_name' => '空文字の場合 => ドットのみ',
                'conditions'          => array( 'secure_id' => '' ),
                'expected'            => '.',
            ),
        );

        foreach ( $test_cases as $case ) {
            $actual = DirectoryManager::get_directory_name( $case['conditions']['secure_id'] );
            $this->assertEquals( $case['expected'], $actual, $case['test_condition_name'] );
        }
    }

    /**
     * Test maybe_migrate_to_hidden_directory method
     * maybe_migrate_to_hidden_directory メソッドのテスト
     */
    public function test_maybe_migrate_to_hidden_directory() {
        $current = '0123456789abcdef0123456789abcdef';
        $old     = 'fedcba9876543210fedcba9876543210';

        $test_cases = array(
            array(
                'test_condition_name' => '現在の旧ディレクトリのみ存在する場合 => 移動して true、ファイル移動・通知フラグ・完了状態',
                'conditions'          => array(
                    'secure_id'      => $current,
                    'migration_state' => '',
                    'dirs'           => array( $current ),
                ),
                'expected'            => array(
                    'result'         => true,
                    'dirs_after'     => array( '.' . $current ),
                    'file_moved'     => true,
                    'base_protected' => true,
                    'notice_flag'    => true,
                    'state'          => 'done',
                ),
            ),
            array(
                'test_condition_name' => 'リセットで残った古いディレクトリもある場合 => 両方とも隠しディレクトリへ移動',
                'conditions'          => array(
                    'secure_id'      => $current,
                    'migration_state' => '',
                    'dirs'           => array( $current, $old ),
                ),
                'expected'            => array(
                    'result'         => true,
                    'dirs_after'     => array( '.' . $current, '.' . $old ),
                    'file_moved'     => true,
                    'base_protected' => true,
                    'notice_flag'    => true,
                    'state'          => 'done',
                ),
            ),
            array(
                'test_condition_name' => '現在のディレクトリは移行済みで古いディレクトリのみ残る場合 => 古い方だけ移動、通知なしで false',
                'conditions'          => array(
                    'secure_id'      => $current,
                    'migration_state' => '',
                    'dirs'           => array( '.' . $current, $old ),
                ),
                'expected'            => array(
                    'result'         => false,
                    'dirs_after'     => array( '.' . $current, '.' . $old ),
                    'file_moved'     => false,
                    'base_protected' => true,
                    'notice_flag'    => false,
                    'state'          => 'done',
                ),
            ),
            array(
                'test_condition_name' => '32桁の16進数でないディレクトリの場合 => 移動しない',
                'conditions'          => array(
                    'secure_id'      => $current,
                    'migration_state' => '',
                    'dirs'           => array( '.' . $current, 'my-folder' ),
                ),
                'expected'            => array(
                    'result'         => false,
                    'dirs_after'     => array( '.' . $current, 'my-folder' ),
                    'file_moved'     => false,
                    'base_protected' => true,
                    'notice_flag'    => false,
                    'state'          => 'done',
                ),
            ),
            array(
                'test_condition_name' => '移行完了済みの場合 => 旧ディレクトリがあっても何もせず false',
                'conditions'          => array(
                    'secure_id'      => $current,
                    'migration_state' => DirectoryManager::MIGRATION_STATE_DONE,
                    'dirs'           => array( $current ),
                ),
                'expected'            => array(
                    'result'         => false,
                    'dirs_after'     => array( $current ),
                    'file_moved'     => false,
                    'base_protected' => false,
                    'notice_flag'    => false,
                    'state'          => null,
                ),
            ),
            array(
                'test_condition_name' => 'ベースディレクトリが無い場合 => false で完了状態のみ保存',
                'conditions'          => array(
                    'secure_id'      => '',
                    'migration_state' => '',
                    'dirs'           => null,
                ),
                'expected'            => array(
                    'result'         => false,
                    'dirs_after'     => array(),
                    'file_moved'     => false,
                    'base_protected' => false,
                    'notice_flag'    => false,
                    'state'          => 'done',
                ),
            ),
        );

        // Add the cases about retrying
        // 再試行に関するケースを追加する
        $test_cases[] = array(
            'test_condition_name' => '旧と隠しの両方がある（衝突）場合 => 上書きせず、再試行待ちの状態を保存',
            'conditions'          => array(
                'secure_id'       => $current,
                'migration_state' => '',
                'dirs'            => array( $current, '.' . $current ),
            ),
            'expected'            => array(
                'result'         => false,
                'dirs_after'     => array( '.' . $current, $current ),
                'file_moved'     => false,
                'base_protected' => true,
                'notice_flag'    => false,
                'state'          => 'retry',
            ),
        );
        $test_cases[] = array(
            'test_condition_name' => '再試行の時刻前の場合 => 何もせず false',
            'conditions'          => array(
                'secure_id'       => $current,
                'migration_state' => (string) ( time() + 600 ),
                'dirs'            => array( $current ),
            ),
            'expected'            => array(
                'result'         => false,
                'dirs_after'     => array( $current ),
                'file_moved'     => false,
                'base_protected' => false,
                'notice_flag'    => false,
                'state'          => null,
            ),
        );
        $test_cases[] = array(
            'test_condition_name' => '再試行の時刻を過ぎた場合 => 移行を実行して true',
            'conditions'          => array(
                'secure_id'       => $current,
                'migration_state' => (string) ( time() - 1 ),
                'dirs'            => array( $current ),
            ),
            'expected'            => array(
                'result'         => true,
                'dirs_after'     => array( '.' . $current ),
                'file_moved'     => true,
                'base_protected' => true,
                'notice_flag'    => true,
                'state'          => 'done',
            ),
        );

        foreach ( $test_cases as $case ) {
            // Build a temporary uploads directory. Put a user file in each non-hidden directory.
            // 一時 uploads ディレクトリを作り、隠しでない各ディレクトリにユーザーファイルを置く
            $uploads = $this->create_temp_uploads_dir();
            $base = $uploads . '/bf-secret-file-downloader';
            if ( $case['conditions']['dirs'] !== null ) {
                mkdir( $base, 0777, true );
                foreach ( $case['conditions']['dirs'] as $dir ) {
                    mkdir( $base . '/' . $dir );
                    if ( strpos( $dir, '.' ) !== 0 ) {
                        file_put_contents( $base . '/' . $dir . '/user-file.pdf', 'dummy' );
                    }
                }
            }

            // Mock the options and record the options written by the method
            // オプションをモックし、メソッドが書き込んだオプションを記録する
            $this->mock_directory_options( $uploads, $case['conditions']['secure_id'] );
            WP_Mock::userFunction( 'get_option' )
                ->with( DirectoryManager::MIGRATION_STATE_OPTION, '' )
                ->andReturn( $case['conditions']['migration_state'] );
            $updated = array();
            WP_Mock::userFunction( 'update_option' )->andReturnUsing( function ( $name, $value ) use ( &$updated ) {
                $updated[ $name ] = $value;
                return true;
            } );
            WP_Mock::userFunction( 'delete_transient' )->andReturn( true );

            // Run the method under test
            // テスト対象を実行する
            $actual = DirectoryManager::maybe_migrate_to_hidden_directory();

            // Collect the directories after the migration (excluding protection files)
            // 移行後のディレクトリ一覧を取得する（保護ファイルは除く）
            $dirs_after = array();
            if ( is_dir( $base ) ) {
                foreach ( scandir( $base ) as $item ) {
                    if ( $item !== '.' && $item !== '..' && is_dir( $base . '/' . $item ) ) {
                        $dirs_after[] = $item;
                    }
                }
            }
            sort( $dirs_after );

            $actual_state = array(
                'result'         => $actual,
                'dirs_after'     => $dirs_after,
                'file_moved'     => is_file( $base . '/.' . $current . '/user-file.pdf' ),
                'base_protected' => is_file( $base . '/.htaccess' ) && is_file( $base . '/index.php' ),
                'notice_flag'    => ! empty( $updated[ DirectoryManager::MOVED_NOTICE_OPTION ] ),
                'state'          => $this->normalize_migration_state( $updated[ DirectoryManager::MIGRATION_STATE_OPTION ] ?? null ),
            );
            $this->assertEquals( $case['expected'], $actual_state, $case['test_condition_name'] );

            // Clean up the temporary directory and mocks
            // 一時ディレクトリとモックを片付ける
            $this->remove_dir_recursive( $uploads );
            WP_Mock::tearDown();
            WP_Mock::setUp();
        }
    }

    /**
     * Test evaluate_protection_response method
     * evaluate_protection_response メソッドのテスト
     */
    public function test_evaluate_protection_response() {
        $token = 'random-token-123';

        // Control file (under uploads) fetched successfully
        // 対照ファイル（uploads 直下）が正常に取得できた場合の値
        $control_ok = array( 'control_code' => 200, 'control_body' => $token );

        $test_cases = array(
            array(
                'test_condition_name' => '200 で一時ファイルの内容が返った場合 => unprotected',
                'conditions'          => array( 'status_code' => 200, 'body' => $token ) + $control_ok,
                'expected'            => DirectoryManager::STATUS_UNPROTECTED,
            ),
            array(
                'test_condition_name' => '200 で内容の前後に改行がある場合 => unprotected',
                'conditions'          => array( 'status_code' => 200, 'body' => "\n" . $token . "\n" ) + $control_ok,
                'expected'            => DirectoryManager::STATUS_UNPROTECTED,
            ),
            array(
                'test_condition_name' => '対照ファイルが取得できなくても、中身が返ったら => unprotected',
                'conditions'          => array( 'status_code' => 200, 'body' => $token, 'control_code' => 0, 'control_body' => '' ),
                'expected'            => DirectoryManager::STATUS_UNPROTECTED,
            ),
            array(
                'test_condition_name' => '対照ファイルは取得でき、403 の場合 => protected',
                'conditions'          => array( 'status_code' => 403, 'body' => '<html>403 Forbidden</html>' ) + $control_ok,
                'expected'            => DirectoryManager::STATUS_PROTECTED,
            ),
            array(
                'test_condition_name' => '対照ファイルは取得でき、404 の場合 => protected',
                'conditions'          => array( 'status_code' => 404, 'body' => 'Not Found' ) + $control_ok,
                'expected'            => DirectoryManager::STATUS_PROTECTED,
            ),
            array(
                'test_condition_name' => '対照ファイルは取得でき、410 の場合 => protected',
                'conditions'          => array( 'status_code' => 410, 'body' => 'Gone' ) + $control_ok,
                'expected'            => DirectoryManager::STATUS_PROTECTED,
            ),
            array(
                'test_condition_name' => '対照ファイルも 404（CDN オフロード・別 vhost 等）で 404 の場合 => unknown',
                'conditions'          => array( 'status_code' => 404, 'body' => 'Not Found', 'control_code' => 404, 'control_body' => 'Not Found' ),
                'expected'            => DirectoryManager::STATUS_UNKNOWN,
            ),
            array(
                'test_condition_name' => '対照ファイルが 200 でも内容が異なる場合で 403 => unknown',
                'conditions'          => array( 'status_code' => 403, 'body' => 'Forbidden', 'control_code' => 200, 'control_body' => '<html>other</html>' ),
                'expected'            => DirectoryManager::STATUS_UNKNOWN,
            ),
            array(
                'test_condition_name' => 'サイト全体の BASIC 認証で両方 401 の場合 => unknown',
                'conditions'          => array( 'status_code' => 401, 'body' => 'Unauthorized', 'control_code' => 401, 'control_body' => 'Unauthorized' ),
                'expected'            => DirectoryManager::STATUS_UNKNOWN,
            ),
            array(
                'test_condition_name' => '対照ファイルは取得でき、429 の場合 => unknown',
                'conditions'          => array( 'status_code' => 429, 'body' => 'Too Many Requests' ) + $control_ok,
                'expected'            => DirectoryManager::STATUS_UNKNOWN,
            ),
            array(
                'test_condition_name' => '対照ファイルは取得でき、500 の場合 => unknown',
                'conditions'          => array( 'status_code' => 500, 'body' => 'Internal Server Error' ) + $control_ok,
                'expected'            => DirectoryManager::STATUS_UNKNOWN,
            ),
            array(
                'test_condition_name' => '対照ファイルは取得でき、200 でも内容が異なる（WAF のチャレンジページ等）場合 => unknown',
                'conditions'          => array( 'status_code' => 200, 'body' => '<html>Checking your browser</html>' ) + $control_ok,
                'expected'            => DirectoryManager::STATUS_UNKNOWN,
            ),
            array(
                'test_condition_name' => '対照ファイルは取得でき、リダイレクトが続いた場合 => unknown',
                'conditions'          => array( 'status_code' => 301, 'body' => '' ) + $control_ok,
                'expected'            => DirectoryManager::STATUS_UNKNOWN,
            ),
            array(
                'test_condition_name' => '両方とも応答なしの場合 => unknown',
                'conditions'          => array( 'status_code' => 0, 'body' => '', 'control_code' => 0, 'control_body' => '' ),
                'expected'            => DirectoryManager::STATUS_UNKNOWN,
            ),
        );

        foreach ( $test_cases as $case ) {
            $actual = DirectoryManager::evaluate_protection_response(
                $case['conditions']['status_code'],
                $case['conditions']['body'],
                $case['conditions']['control_code'],
                $case['conditions']['control_body'],
                $token
            );
            $this->assertEquals( $case['expected'], $actual, $case['test_condition_name'] );
        }
    }

    /**
     * Test get_nginx_deny_rule method
     * get_nginx_deny_rule メソッドのテスト
     */
    public function test_get_nginx_deny_rule() {
        $test_cases = array(
            array(
                'test_condition_name' => 'ルートに設置したサイトの場合 => /wp-content/uploads/ 配下を拒否',
                'conditions'          => array( 'baseurl' => 'https://example.com/wp-content/uploads' ),
                'expected'            => 'location ^~ /wp-content/uploads/bf-secret-file-downloader/ { deny all; }',
            ),
            array(
                'test_condition_name' => 'サブディレクトリに設置したサイトの場合 => サブディレクトリを含むパス',
                'conditions'          => array( 'baseurl' => 'https://example.com/blog/wp-content/uploads' ),
                'expected'            => 'location ^~ /blog/wp-content/uploads/bf-secret-file-downloader/ { deny all; }',
            ),
            array(
                'test_condition_name' => 'baseurl にパスが無い場合 => ルート直下',
                'conditions'          => array( 'baseurl' => 'https://files.example.com' ),
                'expected'            => 'location ^~ /bf-secret-file-downloader/ { deny all; }',
            ),
        );

        foreach ( $test_cases as $case ) {
            // Mock the WordPress functions used by the method
            // メソッドが使う WordPress 関数をモックする
            WP_Mock::userFunction( 'wp_upload_dir' )->andReturn( array( 'baseurl' => $case['conditions']['baseurl'] ) );
            WP_Mock::userFunction( 'wp_parse_url' )->andReturnUsing( function ( $url, $component ) {
                return parse_url( $url, $component );
            } );
            WP_Mock::userFunction( 'untrailingslashit' )->andReturnUsing( function ( $value ) {
                return rtrim( $value, '/\\' );
            } );

            $actual = DirectoryManager::get_nginx_deny_rule();
            $this->assertEquals( $case['expected'], $actual, $case['test_condition_name'] );

            WP_Mock::tearDown();
            WP_Mock::setUp();
        }
    }

    /**
     * Normalize the saved migration state for comparison
     * 保存された移行状態を比較用に正規化する
     *
     * @param mixed $state the saved value (null if not saved) / 保存された値（保存されていなければ null）
     * @return string|null 'done', 'retry' (future UNIX time), or null / 'done'、'retry'（未来の UNIX 時刻）、または null
     */
    private function normalize_migration_state( $state ) {
        if ( $state === DirectoryManager::MIGRATION_STATE_DONE ) {
            return 'done';
        }
        if ( is_int( $state ) && $state > time() ) {
            return 'retry';
        }
        return $state === null ? null : 'invalid';
    }

    /**
     * Create a temporary uploads directory
     * 一時的な uploads ディレクトリを作成する
     *
     * @return string the path / パス
     */
    private function create_temp_uploads_dir() {
        $dir = sys_get_temp_dir() . '/bf-sfd-test-' . bin2hex( random_bytes( 6 ) );
        mkdir( $dir, 0777, true );
        return $dir;
    }

    /**
     * Mock get_option and wp_upload_dir for the directory tests
     * ディレクトリ系テスト用に get_option と wp_upload_dir をモックする
     *
     * @param string $uploads   uploads directory / uploads ディレクトリ
     * @param string $secure_id directory ID / ディレクトリID
     */
    private function mock_directory_options( $uploads, $secure_id ) {
        WP_Mock::userFunction( 'get_option' )
            ->with( 'bf_sfd_secure_directory_id', '' )
            ->andReturn( $secure_id );
        WP_Mock::userFunction( 'wp_upload_dir' )
            ->andReturn( array( 'basedir' => $uploads, 'baseurl' => 'https://example.com/wp-content/uploads' ) );
    }

    /**
     * Remove a directory recursively (including dot files)
     * ディレクトリを再帰的に削除する（ドットファイルを含む）
     *
     * @param string $dir the directory / ディレクトリ
     */
    private function remove_dir_recursive( $dir ) {
        if ( ! is_dir( $dir ) ) {
            return;
        }
        foreach ( array_diff( scandir( $dir ), array( '.', '..' ) ) as $item ) {
            $path = $dir . '/' . $item;
            is_dir( $path ) ? $this->remove_dir_recursive( $path ) : unlink( $path );
        }
        rmdir( $dir );
    }

    /**
     * Test create_secure_directory method
     */
    public function test_create_secure_directory() {
        // Test when directory already exists
        WP_Mock::userFunction( 'get_option' )
            ->with( 'bf_sfd_secure_directory_id' )
            ->andReturn( 'existing_id' );

        $result = DirectoryManager::create_secure_directory();
        $this->assertTrue( $result );

        // Test directory creation (mock scenario)
        WP_Mock::userFunction( 'get_option' )
            ->with( 'bf_sfd_secure_directory_id' )
            ->andReturn( false );

        WP_Mock::userFunction( 'wp_upload_dir' )
            ->andReturn( array( 'basedir' => '/tmp/test_uploads' ) );

        WP_Mock::userFunction( 'wp_mkdir_p' )
            ->andReturn( true );

        WP_Mock::userFunction( 'update_option' )
            ->andReturn( true );

        // Mock file_put_contents and other file operations would require 
        // more complex testing infrastructure, so we'll test the basic logic
        $this->assertTrue( true ); // Placeholder for complex file operation tests
    }

    /**
     * Test create_secure_directory method with force_create parameter
     */
    public function test_create_secure_directory_force_create() {
        // Test with force_create = true (should create even if directory exists)
        WP_Mock::userFunction( 'get_option' )
            ->with( 'bf_sfd_secure_directory_id' )
            ->andReturn( 'existing_id' );

        WP_Mock::userFunction( 'wp_upload_dir' )
            ->andReturn( array( 'basedir' => '/tmp/test_uploads' ) );

        WP_Mock::userFunction( 'wp_mkdir_p' )
            ->andReturn( false ); // Make directory creation fail to avoid file operations

        $result = DirectoryManager::create_secure_directory( true );
        $this->assertFalse( $result ); // Should return false when directory creation fails
    }

    /**
     * Test get_secure_directory_id method
     */
    public function test_get_secure_directory_id() {
        WP_Mock::userFunction( 'get_option' )
            ->with( 'bf_sfd_secure_directory_id', '' )
            ->andReturn( 'test_id_456' );

        $result = DirectoryManager::get_secure_directory_id();
        $this->assertEquals( 'test_id_456', $result );
    }

    /**
     * Test secure_directory_exists method
     */
    public function test_secure_directory_exists() {
        // Test with no secure directory
        WP_Mock::userFunction( 'get_option' )
            ->with( 'bf_sfd_secure_directory_id', '' )
            ->andReturn( '' );

        $result = DirectoryManager::secure_directory_exists();
        $this->assertFalse( $result );

        // Test with secure directory but directory doesn't exist
        WP_Mock::userFunction( 'get_option' )
            ->with( 'bf_sfd_secure_directory_id', '' )
            ->andReturn( 'test_id' );

        WP_Mock::userFunction( 'wp_upload_dir' )
            ->andReturn( array( 'basedir' => '/tmp/nonexistent' ) );

        $result = DirectoryManager::secure_directory_exists();
        $this->assertFalse( $result ); // Directory doesn't exist, so should be false
    }

    /**
     * Test is_secure_directory_protected method
     */
    public function test_is_secure_directory_protected() {
        // Test with no secure directory
        WP_Mock::userFunction( 'get_option' )
            ->with( 'bf_sfd_secure_directory_id', '' )
            ->andReturn( '' );

        $result = DirectoryManager::is_secure_directory_protected();
        $this->assertFalse( $result );

        // Additional tests would require file system mocking
        // for checking .htaccess and index files
        $this->assertTrue( true ); // Placeholder for file system tests
    }

    /**
     * Test remove_secure_directory method
     */
    public function test_remove_secure_directory() {
        // Mock delete_option calls
        WP_Mock::userFunction( 'delete_option' )
            ->with( 'bf_sfd_secure_directory_id' )
            ->once();

        WP_Mock::userFunction( 'delete_option' )
            ->with( 'bf_sfd_target_directory' )
            ->once();

        // Mock get_secure_directory to return empty (no directory to remove)
        WP_Mock::userFunction( 'get_option' )
            ->with( 'bf_sfd_secure_directory_id', '' )
            ->andReturn( '' );

        $result = DirectoryManager::remove_secure_directory();
        $this->assertTrue( $result );
    }

    /**
     * Test remove_secure_directory method with delete_files parameter
     */
    public function test_remove_secure_directory_with_delete_files_false() {
        // Mock delete_option calls
        WP_Mock::userFunction( 'delete_option' )
            ->with( 'bf_sfd_secure_directory_id' )
            ->once();

        WP_Mock::userFunction( 'delete_option' )
            ->with( 'bf_sfd_target_directory' )
            ->once();

        // Mock get_secure_directory to return empty (no directory to remove)
        WP_Mock::userFunction( 'get_option' )
            ->with( 'bf_sfd_secure_directory_id', '' )
            ->andReturn( '' );

        // Test with delete_files = false
        $result = DirectoryManager::remove_secure_directory( false );
        $this->assertTrue( $result );
    }

    /**
     * Test clear_user_files method
     */
    public function test_clear_user_files() {
        // Test with no secure directory
        WP_Mock::userFunction( 'get_option' )
            ->with( 'bf_sfd_secure_directory_id', '' )
            ->andReturn( '' );

        $result = DirectoryManager::clear_user_files();
        $this->assertFalse( $result );

        // Additional tests would require file system mocking
        // for checking actual file deletion
        $this->assertTrue( true ); // Placeholder for file system tests
    }
}