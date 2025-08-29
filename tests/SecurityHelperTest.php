<?php

namespace Breadfish\SecretFileDownloader\Tests;

use Breadfish\SecretFileDownloader\SecurityHelper;
use Breadfish\SecretFileDownloader\DirectoryManager;
use WP_Mock\Tools\TestCase;
use WP_Mock;

/**
 * Test cases for SecurityHelper class
 */
class SecurityHelperTest extends TestCase {

    public function setUp(): void {
        parent::setUp();
        WP_Mock::setUp();
    }

    public function tearDown(): void {
        WP_Mock::tearDown();
        parent::tearDown();
    }

    /**
     * Test build_safe_path method
     */
    public function test_build_safe_path() {
        // Test empty relative path
        $result = SecurityHelper::build_safe_path( '/base/directory', '' );
        $this->assertEquals( '/base/directory', $result );

        // Test normal relative path (need to create actual directories for realpath to work)
        $temp_base = sys_get_temp_dir() . '/bf_test_' . uniqid();
        if ( mkdir( $temp_base, 0755, true ) ) {
            $result = SecurityHelper::build_safe_path( $temp_base, 'subfolder' );
            // Since subfolder doesn't exist, it should return base directory
            $this->assertEquals( $temp_base, $result );
            rmdir( $temp_base );
        }

        // Test path with directory traversal attack
        $result = SecurityHelper::build_safe_path( '/base/directory', '../../../etc/passwd' );
        $this->assertEquals( '/base/directory', $result );

        // Test path with double slashes
        $result = SecurityHelper::build_safe_path( '/base/directory', 'folder//file.txt' );
        $this->assertEquals( '/base/directory', $result );
    }

    /**
     * Test is_allowed_directory method
     */
    public function test_is_allowed_directory() {
        // Test with non-existent path (will fail realpath check)
        $result = SecurityHelper::is_allowed_directory( '/non/existent/path' );
        $this->assertFalse( $result );
    }

    /**
     * Test check_file_upload_security method
     */
    public function test_check_file_upload_security() {
        // Test with non-existent directory (first check fails)
        $result = SecurityHelper::check_file_upload_security( 'test.txt', '/some/target/path' );
        $this->assertFalse( $result['allowed'] );
        $this->assertEquals( 'アップロード先ディレクトリへのアクセスが許可されていません。', $result['error_message'] );
    }

    /**
     * Test check_ajax_create_directory_security method
     */
    public function test_check_ajax_create_directory_security() {
        // Test with non-existent parent directory (first check fails)
        $result = SecurityHelper::check_ajax_create_directory_security( '/parent/path', 'test_dir' );
        $this->assertFalse( $result['allowed'] );
        $this->assertEquals( 'ディレクトリ作成が許可されていません。', $result['error_message'] );
    }

    /**
     * Test is_program_code_file method
     */
    public function test_is_program_code_file() {
        // Test PHP files
        $this->assertTrue( SecurityHelper::is_program_code_file( 'index.php' ) );
        $this->assertTrue( SecurityHelper::is_program_code_file( 'config.php5' ) );
        $this->assertTrue( SecurityHelper::is_program_code_file( 'script.phtml' ) );

        // Test JavaScript files
        $this->assertTrue( SecurityHelper::is_program_code_file( 'app.js' ) );
        $this->assertTrue( SecurityHelper::is_program_code_file( 'component.jsx' ) );
        $this->assertTrue( SecurityHelper::is_program_code_file( 'module.ts' ) );

        // Test config files
        $this->assertTrue( SecurityHelper::is_program_code_file( '.htaccess' ) );
        $this->assertTrue( SecurityHelper::is_program_code_file( '.env' ) );
        $this->assertTrue( SecurityHelper::is_program_code_file( 'config.ini' ) );
        $this->assertTrue( SecurityHelper::is_program_code_file( 'composer.json' ) );

        // Test shell scripts
        $this->assertTrue( SecurityHelper::is_program_code_file( 'script.sh' ) );
        $this->assertTrue( SecurityHelper::is_program_code_file( 'setup.bash' ) );

        // Test dangerous filename patterns
        $this->assertTrue( SecurityHelper::is_program_code_file( 'wp-config.php' ) );
        $this->assertTrue( SecurityHelper::is_program_code_file( 'configuration.txt' ) );
        $this->assertTrue( SecurityHelper::is_program_code_file( 'settings.ini' ) );

        // Test safe files
        $this->assertFalse( SecurityHelper::is_program_code_file( 'document.pdf' ) );
        $this->assertFalse( SecurityHelper::is_program_code_file( 'image.jpg' ) );
        $this->assertFalse( SecurityHelper::is_program_code_file( 'video.mp4' ) );
        $this->assertFalse( SecurityHelper::is_program_code_file( 'text.txt' ) );
        $this->assertFalse( SecurityHelper::is_program_code_file( 'data.csv' ) );
    }

    /**
     * Test contains_null_byte method
     */
    public function test_contains_null_byte() {
        // Test path with null byte
        $this->assertTrue( SecurityHelper::contains_null_byte( "/path/to/file\0.txt" ) );
        $this->assertTrue( SecurityHelper::contains_null_byte( "\0malicious" ) );

        // Test safe paths
        $this->assertFalse( SecurityHelper::contains_null_byte( '/path/to/file.txt' ) );
        $this->assertFalse( SecurityHelper::contains_null_byte( 'safe_filename.pdf' ) );
    }

    /**
     * Test is_absolute_path method
     */
    public function test_is_absolute_path() {
        // Test Unix absolute paths
        $this->assertTrue( SecurityHelper::is_absolute_path( '/etc/passwd' ) );
        $this->assertTrue( SecurityHelper::is_absolute_path( '/var/www/html' ) );

        // Test Windows absolute paths
        $this->assertTrue( SecurityHelper::is_absolute_path( 'C:\Windows\System32' ) );
        $this->assertTrue( SecurityHelper::is_absolute_path( 'D:/Programs/App' ) );

        // Test relative paths
        $this->assertFalse( SecurityHelper::is_absolute_path( 'relative/path' ) );
        $this->assertFalse( SecurityHelper::is_absolute_path( '../parent/dir' ) );
        $this->assertFalse( SecurityHelper::is_absolute_path( './current/dir' ) );
        $this->assertFalse( SecurityHelper::is_absolute_path( 'filename.txt' ) );
    }

}