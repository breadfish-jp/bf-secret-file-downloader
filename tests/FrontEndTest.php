<?php

namespace Breadfish\SecretFileDownloader\Tests;

use Breadfish\SecretFileDownloader\FrontEnd;
use WP_Mock\Tools\TestCase;
use WP_Mock;

/**
 * Test cases for FrontEnd class
 */
class FrontEndTest extends TestCase {

    private $frontend;

    public function setUp(): void {
        parent::setUp();
        WP_Mock::setUp();
        $this->frontend = new FrontEnd();
    }

    public function tearDown(): void {
        WP_Mock::tearDown();
        parent::tearDown();
    }

    /**
     * Test that FrontEnd class can be instantiated
     */
    public function test_constructor() {
        $this->assertInstanceOf( FrontEnd::class, $this->frontend );
    }

    /**
     * Test that init method is callable
     */
    public function test_init_callable() {
        $this->assertTrue( is_callable( array( $this->frontend, 'init' ) ) );
    }

    /**
     * Test that handle_file_download method is callable
     */
    public function test_handle_file_download_callable() {
        $this->assertTrue( is_callable( array( $this->frontend, 'handle_file_download' ) ) );
    }

    /**
     * Test private methods exist
     */
    public function test_private_methods_exist() {
        $reflection = new \ReflectionClass( $this->frontend );

        $private_methods = [
            'log_download',
            'get_client_ip',
            'check_authentication',
            'check_user_role',
            'is_session_timeout',
            'clear_auth_sessions'
        ];

        foreach ( $private_methods as $method ) {
            $this->assertTrue(
                $reflection->hasMethod( $method ),
                "Private method {$method} should exist"
            );
        }
    }

    /**
     * Test session timeout functionality
     */
    public function test_session_timeout_functionality() {
        $reflection = new \ReflectionClass( $this->frontend );
        $is_session_timeout = $reflection->getMethod( 'is_session_timeout' );
        $is_session_timeout->setAccessible( true );

        // Mock get_option for timeout setting
        WP_Mock::userFunction( 'get_option' )
            ->with( 'bf_sfd_auth_timeout', 30 )
            ->andReturn( 30 );

        // Mock get_option for settings change timestamp
        WP_Mock::userFunction( 'get_option' )
            ->with( 'bf_sfd_auth_settings_changed', 0 )
            ->andReturn( 0 );

        // Test case 1: No timestamp set - should timeout
        $_SESSION = [];
        $result = $is_session_timeout->invoke( $this->frontend );
        $this->assertTrue( $result, 'Should timeout when no timestamp is set' );

        // Test case 2: Recent timestamp - should not timeout  
        $_SESSION['bf_auth_timestamp'] = time() - 300; // 5 minutes ago
        $result = $is_session_timeout->invoke( $this->frontend );
        $this->assertFalse( $result, 'Should not timeout for recent timestamp' );

        // Test case 3: Old timestamp - should timeout
        $_SESSION['bf_auth_timestamp'] = time() - 2000; // 33+ minutes ago
        $result = $is_session_timeout->invoke( $this->frontend );
        $this->assertTrue( $result, 'Should timeout for old timestamp' );
    }

    /**
     * Test session invalidation due to settings change
     */
    public function test_session_invalidation_on_settings_change() {
        $reflection = new \ReflectionClass( $this->frontend );
        $is_session_timeout = $reflection->getMethod( 'is_session_timeout' );
        $is_session_timeout->setAccessible( true );

        // Mock get_option for timeout setting
        WP_Mock::userFunction( 'get_option' )
            ->with( 'bf_sfd_auth_timeout', 30 )
            ->andReturn( 30 );

        // Test case: Settings changed after authentication - should invalidate
        $_SESSION['bf_auth_timestamp'] = time() - 300; // 5 minutes ago
        
        WP_Mock::userFunction( 'get_option' )
            ->with( 'bf_sfd_auth_settings_changed', 0 )
            ->andReturn( time() - 60 ); // Settings changed 1 minute ago

        $result = $is_session_timeout->invoke( $this->frontend );
        $this->assertTrue( $result, 'Should timeout when settings changed after authentication' );
    }

    /**
     * Test session clearing functionality
     */
    public function test_clear_auth_sessions() {
        $reflection = new \ReflectionClass( $this->frontend );
        $clear_auth_sessions = $reflection->getMethod( 'clear_auth_sessions' );
        $clear_auth_sessions->setAccessible( true );

        // Set up session variables
        $_SESSION['bf_simple_auth_verified'] = true;
        $_SESSION['bf_directory_simple_auth_verified'] = true;
        $_SESSION['bf_auth_timestamp'] = time();

        // Call method
        $clear_auth_sessions->invoke( $this->frontend );

        // Verify all auth sessions are cleared
        $this->assertArrayNotHasKey( 'bf_simple_auth_verified', $_SESSION );
        $this->assertArrayNotHasKey( 'bf_directory_simple_auth_verified', $_SESSION );
        $this->assertArrayNotHasKey( 'bf_auth_timestamp', $_SESSION );
    }
}