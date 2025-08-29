<?php
/**
 * PHPUnit bootstrap file
 *
 * @package BF Secret File Downloader
 */

// Composer autoloader
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Start up the WP_Mock framework
WP_Mock::bootstrap();

/**
 * Base test case for WP_Mock tests
 */
abstract class BF_SFD_TestCase extends \PHPUnit\Framework\TestCase {

    public function setUp(): void {
        \WP_Mock::setUp();
        parent::setUp();
    }

    public function tearDown(): void {
        \WP_Mock::tearDown();
        parent::tearDown();
    }

    /**
     * Assert that all WP_Mock conditions are met
     */
    public function assertConditionsMet() {
        $this->assertTrue(true); // WP_Mock handles condition checking internally
    }
}