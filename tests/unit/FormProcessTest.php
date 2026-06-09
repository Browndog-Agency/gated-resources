<?php
namespace BrownDog\GatedResources\Tests;

use Brain\Monkey\Functions;
use BrownDog\GatedResources\Form;

final class FormProcessTest extends GR_TestCase {

	private function input( $overrides = array() ) {
		return array_merge(
			array(
				'hp'        => '',
				'ip'        => '1.2.3.4',
				'turnstile' => 'tok',
				'firstname' => 'Ann',
				'lastname'  => 'Jones',
				'email'     => 'ann@council.gov',
				'company'   => 'Council',
				'jobtitle'  => 'Officer',
				'consent'   => false,
				'context'   => array(),
			),
			$overrides
		);
	}

	public function test_honeypot_filled_fails() {
		Functions\when( '__' )->returnArg( 1 );
		$form = new Form( \Mockery::mock(), \Mockery::mock(), \Mockery::mock() );
		$res  = $form->process( $this->input( array( 'hp' => 'bot' ) ) );
		$this->assertFalse( $res['ok'] );
		$this->assertSame( 'spam', $res['code'] );
	}

	public function test_turnstile_failure_blocks_hubspot() {
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'is_email' )->justReturn( true );
		Functions\when( 'get_transient' )->justReturn( 0 );
		Functions\when( 'set_transient' )->justReturn( true );
		$turnstile = \Mockery::mock();
		$turnstile->shouldReceive( 'verify' )->andReturn( false );
		$hubspot = \Mockery::mock();
		$hubspot->shouldNotReceive( 'submit' );
		$gate = \Mockery::mock();

		$res = ( new Form( $turnstile, $hubspot, $gate ) )->process( $this->input() );
		$this->assertFalse( $res['ok'] );
		$this->assertSame( 'captcha', $res['code'] );
	}

	public function test_invalid_email_fails_validation() {
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'is_email' )->justReturn( false );
		Functions\when( 'get_transient' )->justReturn( 0 );
		Functions\when( 'set_transient' )->justReturn( true );
		$turnstile = \Mockery::mock();
		$turnstile->shouldReceive( 'verify' )->andReturn( true );

		$res = ( new Form( $turnstile, \Mockery::mock(), \Mockery::mock() ) )
			->process( $this->input( array( 'email' => 'nope' ) ) );
		$this->assertFalse( $res['ok'] );
		$this->assertSame( 'invalid', $res['code'] );
	}

	public function test_hubspot_error_does_not_unlock() {
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'is_email' )->justReturn( true );
		Functions\when( 'is_wp_error' )->justReturn( true );
		Functions\when( 'get_transient' )->justReturn( 0 );
		Functions\when( 'set_transient' )->justReturn( true );
		$turnstile = \Mockery::mock();
		$turnstile->shouldReceive( 'verify' )->andReturn( true );
		$hubspot = \Mockery::mock();
		$hubspot->shouldReceive( 'submit' )->andReturn( new \WP_Error( 'x', 'fail' ) );
		$gate = \Mockery::mock();
		$gate->shouldNotReceive( 'create_unlock' );

		$res = ( new Form( $turnstile, $hubspot, $gate ) )->process( $this->input() );
		$this->assertFalse( $res['ok'] );
		$this->assertSame( 'hubspot', $res['code'] );
	}

	public function test_happy_path_creates_unlock() {
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'is_email' )->justReturn( true );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'get_transient' )->justReturn( 0 );
		Functions\when( 'set_transient' )->justReturn( true );
		$turnstile = \Mockery::mock();
		$turnstile->shouldReceive( 'verify' )->andReturn( true );
		$hubspot = \Mockery::mock();
		$hubspot->shouldReceive( 'submit' )->andReturn( true );
		$gate = \Mockery::mock();
		$gate->shouldReceive( 'create_unlock' )->once()->andReturn( array( 'token' => 't', 'expires' => 999 ) );

		$res = ( new Form( $turnstile, $hubspot, $gate ) )->process( $this->input() );
		$this->assertTrue( $res['ok'] );
		$this->assertSame( 't', $res['unlock']['token'] );
	}
}
