<?php
namespace BrownDog\GatedResources\Tests;

use Brain\Monkey\Functions;
use BrownDog\GatedResources\Turnstile;

final class TurnstileTest extends GR_TestCase {

	public function test_empty_token_fails_without_request() {
		$this->assertFalse( ( new Turnstile() )->verify( '' ) );
	}

	public function test_success_response_passes() {
		Functions\when( 'get_option' )->justReturn( array( 'turnstile_secret_key' => 'sec' ) );
		Functions\when( 'wp_remote_post' )->justReturn( array( 'body' => '{"success":true}' ) );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( '{"success":true}' );

		$this->assertTrue( ( new Turnstile() )->verify( 'token', '1.2.3.4' ) );
	}

	public function test_failure_response_fails() {
		Functions\when( 'get_option' )->justReturn( array( 'turnstile_secret_key' => 'sec' ) );
		Functions\when( 'wp_remote_post' )->justReturn( array( 'body' => '{"success":false}' ) );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( '{"success":false,"error-codes":["invalid-input-response"]}' );

		$this->assertFalse( ( new Turnstile() )->verify( 'token' ) );
	}

	public function test_wp_error_fails() {
		Functions\when( 'get_option' )->justReturn( array( 'turnstile_secret_key' => 'sec' ) );
		Functions\when( 'wp_remote_post' )->justReturn( 'err' );
		Functions\when( 'is_wp_error' )->justReturn( true );

		$this->assertFalse( ( new Turnstile() )->verify( 'token' ) );
	}
}
