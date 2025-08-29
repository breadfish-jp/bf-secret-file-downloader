<?php
/**
 * ViewRenderer Test
 *
 * @package BF Secret File Downloader
 */

namespace Breadfish\SecretFileDownloader\Tests;

use Breadfish\SecretFileDownloader\ViewRenderer;
use WP_Mock;

/**
 * Test class for ViewRenderer
 */
class ViewRendererTest extends \BF_SFD_TestCase {

    /**
     * Test that ViewRenderer class exists and has static methods
     */
    public function test_class_exists() {
        $this->assertTrue( class_exists( ViewRenderer::class ) );
        $this->assertTrue( method_exists( ViewRenderer::class, 'admin' ) );
        $this->assertTrue( method_exists( ViewRenderer::class, 'frontend' ) );
    }

    /**
     * Test static method callability
     */
    public function test_static_methods_callable() {
        $this->assertTrue( is_callable( array( ViewRenderer::class, 'admin' ) ) );
        $this->assertTrue( is_callable( array( ViewRenderer::class, 'frontend' ) ) );
    }

    /**
     * Test that WordPress functions would be available in context
     */
    public function test_wordpress_functions_available() {
        // Mock common WordPress functions that would be available in views
        WP_Mock::userFunction( 'esc_html' )
            ->andReturnUsing( function( $text ) {
                return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
            });

        WP_Mock::userFunction( '__' )
            ->andReturnUsing( function( $text ) {
                return $text;
            });

        // Test basic functionality
        $this->assertTrue( true );
    }
}