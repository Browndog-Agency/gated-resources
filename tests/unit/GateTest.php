<?php
namespace BrownDog\GatedResources\Tests;

use Brain\Monkey\Functions;
use BrownDog\GatedResources\Gate;

final class GateTest extends GR_TestCase {

	public function test_generate_token_is_64_hex_chars() {
		$token = ( new Gate() )->generate_token();
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{64}$/', $token );
	}

	public function test_is_valid_token_rejects_non_hex() {
		$gate = new Gate();
		$this->assertFalse( $gate->is_valid_token( 'not-a-token!' ) );
		$this->assertFalse( $gate->is_valid_token( '' ) );
	}

	public function test_is_valid_token_true_for_future_expiry() {
		$wpdb         = \Mockery::mock();
		$wpdb->prefix = 'wp_';
		$wpdb->shouldReceive( 'prepare' )->andReturn( 'SQL' );
		$wpdb->shouldReceive( 'get_row' )->andReturn(
			(object) array( 'id' => 1, 'expires_at' => gmdate( 'Y-m-d H:i:s', time() + 1000 ) )
		);
		$GLOBALS['wpdb'] = $wpdb;

		$this->assertTrue( ( new Gate() )->is_valid_token( str_repeat( 'a', 64 ) ) );
	}

	public function test_is_valid_token_false_for_past_expiry() {
		$wpdb         = \Mockery::mock();
		$wpdb->prefix = 'wp_';
		$wpdb->shouldReceive( 'prepare' )->andReturn( 'SQL' );
		$wpdb->shouldReceive( 'get_row' )->andReturn(
			(object) array( 'id' => 1, 'expires_at' => gmdate( 'Y-m-d H:i:s', time() - 1000 ) )
		);
		$GLOBALS['wpdb'] = $wpdb;

		$this->assertFalse( ( new Gate() )->is_valid_token( str_repeat( 'b', 64 ) ) );
	}

	public function test_is_valid_token_false_when_no_row() {
		$wpdb         = \Mockery::mock();
		$wpdb->prefix = 'wp_';
		$wpdb->shouldReceive( 'prepare' )->andReturn( 'SQL' );
		$wpdb->shouldReceive( 'get_row' )->andReturn( null );
		$GLOBALS['wpdb'] = $wpdb;

		$this->assertFalse( ( new Gate() )->is_valid_token( str_repeat( 'c', 64 ) ) );
	}
}
