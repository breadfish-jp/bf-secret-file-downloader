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
                'test_condition_name' => '通知を閉じるアクションの場合 => bf_sfd_action と同名の nonce アクションが付く',
                'conditions'          => array( 'action' => Admin::ACTION_DISMISS_MOVED_NOTICE ),
                'expected'            => '/wp-admin/index.php?bf_sfd_action=bf_sfd_dismiss_moved_notice&_wpnonce=nonce-bf_sfd_dismiss_moved_notice',
            ),
            array(
                'test_condition_name' => '再確認アクションの場合 => bf_sfd_action と同名の nonce アクションが付く',
                'conditions'          => array( 'action' => Admin::ACTION_RECHECK_PROTECTION ),
                'expected'            => '/wp-admin/index.php?bf_sfd_action=bf_sfd_recheck_protection&_wpnonce=nonce-bf_sfd_recheck_protection',
            ),
            array(
                'test_condition_name' => '空のアクションの場合 => 空の値のまま URL を組み立てる',
                'conditions'          => array( 'action' => '' ),
                'expected'            => '/wp-admin/index.php?bf_sfd_action=&_wpnonce=nonce-',
            ),
        );

        foreach ( $test_cases as $case ) {
            // Mock add_query_arg (appends to the current admin URL) and wp_nonce_url (appends a fake nonce)
            // add_query_arg（現在の管理画面 URL に付加）と wp_nonce_url（ダミー nonce を付加）をモックする
            WP_Mock::userFunction( 'add_query_arg' )->andReturnUsing( function ( $key, $value ) {
                return '/wp-admin/index.php?' . $key . '=' . $value;
            } );
            WP_Mock::userFunction( 'wp_nonce_url' )->andReturnUsing( function ( $url, $action ) {
                return $url . '&_wpnonce=nonce-' . $action;
            } );

            $actual = Admin::get_notice_action_url( $case['conditions']['action'] );
            $this->assertEquals( $case['expected'], $actual, $case['test_condition_name'] );

            WP_Mock::tearDown();
            WP_Mock::setUp();
        }
    }
}
