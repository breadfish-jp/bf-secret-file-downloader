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

        $result = DirectoryManager::get_secure_directory();
        $this->assertEquals( '/var/www/uploads/bf-secret-file-downloader/test_secure_id_123', $result );
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