<?php

namespace Breadfish\SecretFileDownloader\Tests;

use Breadfish\SecretFileDownloader\Admin;
use WP_Mock\Tools\TestCase;
use WP_Mock;

/**
 * Test cases for Admin class
 * Admin クラスのテスト
 */
class AdminTest extends TestCase {

    public function setUp(): void {
        parent::setUp();
        WP_Mock::setUp();
    }

    public function tearDown(): void {
        WP_Mock::tearDown();
        parent::tearDown();
    }

    /**
     * Test get_notice_action_url method
     * get_notice_action_url メソッドのテスト
     */
    public function test_get_notice_action_url() {
        $test_cases = array(
            array(
                'test_condition_name' => '通知を閉じるアクション・ファイル一覧画面の場合 => ファイル一覧画面宛てのリンク',
                'conditions'          => array( 'action' => Admin::ACTION_DISMISS_MOVED_NOTICE, 'page_slug' => 'bf-secret-file-downloader' ),
                'expected'            => 'https://example.com/wp-admin/admin.php?page=bf-secret-file-downloader&bf_sfd_action=bf_sfd_dismiss_moved_notice&_wpnonce=nonce-bf_sfd_dismiss_moved_notice',
            ),
            array(
                'test_condition_name' => '再確認アクション・設定画面の場合 => 設定画面宛てのリンク',
                'conditions'          => array( 'action' => Admin::ACTION_RECHECK_PROTECTION, 'page_slug' => 'bf-secret-file-downloader-settings' ),
                'expected'            => 'https://example.com/wp-admin/admin.php?page=bf-secret-file-downloader-settings&bf_sfd_action=bf_sfd_recheck_protection&_wpnonce=nonce-bf_sfd_recheck_protection',
            ),
            array(
                'test_condition_name' => 'ページが空の場合 => 空の page のまま組み立てる（戻り先は get_return_page_slug で補正）',
                'conditions'          => array( 'action' => Admin::ACTION_RECHECK_PROTECTION, 'page_slug' => '' ),
                'expected'            => 'https://example.com/wp-admin/admin.php?page=&bf_sfd_action=bf_sfd_recheck_protection&_wpnonce=nonce-bf_sfd_recheck_protection',
            ),
        );

        foreach ( $test_cases as $case ) {
            // Mock admin_url, add_query_arg (builds a query string) and wp_nonce_url (appends a fake nonce)
            // admin_url・add_query_arg（クエリ文字列を組み立てる）・wp_nonce_url（ダミー nonce を付加）をモックする
            WP_Mock::userFunction( 'admin_url' )->andReturnUsing( function ( $path ) {
                return 'https://example.com/wp-admin/' . $path;
            } );
            WP_Mock::userFunction( 'add_query_arg' )->andReturnUsing( function ( $args, $url ) {
                return $url . '?' . http_build_query( $args );
            } );
            WP_Mock::userFunction( 'wp_nonce_url' )->andReturnUsing( function ( $url, $action ) {
                return $url . '&_wpnonce=nonce-' . $action;
            } );

            $actual = Admin::get_notice_action_url( $case['conditions']['action'], $case['conditions']['page_slug'] );
            $this->assertEquals( $case['expected'], $actual, $case['test_condition_name'] );

            WP_Mock::tearDown();
            WP_Mock::setUp();
        }
    }

    /**
     * Test get_return_page_slug method
     * get_return_page_slug メソッドのテスト
     */
    public function test_get_return_page_slug() {
        $test_cases = array(
            array(
                'test_condition_name' => 'ファイル一覧画面の場合 => そのまま',
                'conditions'          => array( 'page_slug' => 'bf-secret-file-downloader' ),
                'expected'            => 'bf-secret-file-downloader',
            ),
            array(
                'test_condition_name' => '設定画面の場合 => そのまま',
                'conditions'          => array( 'page_slug' => 'bf-secret-file-downloader-settings' ),
                'expected'            => 'bf-secret-file-downloader-settings',
            ),
            array(
                'test_condition_name' => '他プラグインの画面の場合 => ファイル一覧画面',
                'conditions'          => array( 'page_slug' => 'other-plugin' ),
                'expected'            => 'bf-secret-file-downloader',
            ),
            array(
                'test_condition_name' => '空の場合 => ファイル一覧画面',
                'conditions'          => array( 'page_slug' => '' ),
                'expected'            => 'bf-secret-file-downloader',
            ),
        );

        foreach ( $test_cases as $case ) {
            $actual = Admin::get_return_page_slug( $case['conditions']['page_slug'] );
            $this->assertEquals( $case['expected'], $actual, $case['test_condition_name'] );
        }
    }
}
