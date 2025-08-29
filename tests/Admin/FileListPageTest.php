<?php
/**
 * FileListPage Test
 *
 * @package BF Secret File Downloader
 */

namespace Breadfish\SecretFileDownloader\Tests\Admin;

use Breadfish\SecretFileDownloader\Admin\FileListPage;
use WP_Mock;

/**
 * Test class for FileListPage
 */
class FileListPageTest extends \BF_SFD_TestCase {

    private $file_list_page;

    public function setUp(): void {
        parent::setUp();
        $this->file_list_page = new FileListPage();
    }

    /**
     * Test constructor
     */
    public function test_constructor() {
        $this->assertInstanceOf( FileListPage::class, $this->file_list_page );
    }

    /**
     * Test constants
     */
    public function test_constants() {
        $this->assertEquals( 'bf-secret-file-downloader', FileListPage::PAGE_SLUG );
        $this->assertEquals( 20, FileListPage::FILES_PER_PAGE );
    }

    /**
     * Test init method registers hooks
     */
    public function test_init_registers_hooks() {
        WP_Mock::expectActionAdded( 'wp_ajax_bf_sfd_browse_files', array( $this->file_list_page, 'ajax_browse_files' ) );
        WP_Mock::expectActionAdded( 'wp_ajax_bf_sfd_upload_file', array( $this->file_list_page, 'ajax_upload_file' ) );
        WP_Mock::expectActionAdded( 'wp_ajax_bf_sfd_create_directory', array( $this->file_list_page, 'ajax_create_directory' ) );
        WP_Mock::expectActionAdded( 'wp_ajax_bf_sfd_delete_file', array( $this->file_list_page, 'ajax_delete_file' ) );
        WP_Mock::expectActionAdded( 'wp_ajax_bf_sfd_bulk_delete', array( $this->file_list_page, 'ajax_bulk_delete' ) );
        WP_Mock::expectActionAdded( 'wp_ajax_bf_sfd_download_file', array( $this->file_list_page, 'ajax_download_file' ) );
        WP_Mock::expectActionAdded( 'wp_ajax_bf_sfd_set_directory_auth', array( $this->file_list_page, 'ajax_set_directory_auth' ) );
        WP_Mock::expectActionAdded( 'wp_ajax_bf_sfd_get_directory_auth', array( $this->file_list_page, 'ajax_get_directory_auth' ) );
        WP_Mock::expectActionAdded( 'wp_ajax_bf_sfd_get_global_auth', array( $this->file_list_page, 'ajax_get_global_auth' ) );
        WP_Mock::expectActionAdded( 'admin_post_nopriv_bf_sfd_file_download', array( $this->file_list_page, 'handle_file_download' ) );
        WP_Mock::expectActionAdded( 'admin_post_bf_sfd_file_download', array( $this->file_list_page, 'handle_file_download' ) );
        WP_Mock::expectActionAdded( 'admin_enqueue_scripts', array( $this->file_list_page, 'enqueue_admin_scripts' ) );

        $this->file_list_page->init();
        $this->assertConditionsMet();
    }

    /**
     * Test get_page_title
     */
    public function test_get_page_title() {
        WP_Mock::userFunction( '__' )
            ->with( 'ファイルリスト', 'bf-secret-file-downloader' )
            ->andReturn( 'ファイルリスト' );

        $result = $this->file_list_page->get_page_title();
        $this->assertEquals( 'ファイルリスト', $result );
    }

    /**
     * Test get_menu_title
     */
    public function test_get_menu_title() {
        WP_Mock::userFunction( '__' )
            ->with( 'ファイルリスト', 'bf-secret-file-downloader' )
            ->andReturn( 'ファイルリスト' );

        $result = $this->file_list_page->get_menu_title();
        $this->assertEquals( 'ファイルリスト', $result );
    }

    /**
     * Test format_file_size
     */
    public function test_format_file_size() {
        $result = $this->file_list_page->format_file_size( 0 );
        $this->assertEquals( '0 B', $result );

        $result = $this->file_list_page->format_file_size( 1024 );
        $this->assertEquals( '1 KB', $result );

        $result = $this->file_list_page->format_file_size( 1048576 );
        $this->assertEquals( '1 MB', $result );

        $result = $this->file_list_page->format_file_size( 1536 );
        $this->assertEquals( '1.5 KB', $result );
    }

    /**
     * Test that all required methods exist
     */
    public function test_required_methods_exist() {
        $methods = [
            'init',
            'ajax_browse_files',
            'ajax_upload_file',
            'ajax_create_directory',
            'ajax_delete_file',
            'ajax_bulk_delete',
            'ajax_download_file',
            'ajax_set_directory_auth',
            'ajax_get_directory_auth',
            'ajax_get_global_auth',
            'handle_file_download',
            'enqueue_admin_scripts',
            'get_page_title',
            'get_menu_title',
            'format_file_size'
        ];

        foreach ( $methods as $method ) {
            $this->assertTrue(
                method_exists( $this->file_list_page, $method ),
                "Method {$method} should exist"
            );
        }
    }
}