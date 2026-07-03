<?php
namespace BrownDog\GatedResources\Tests;

use Brain\Monkey\Functions;
use BrownDog\GatedResources\Logger;

final class LoggerTest extends GR_TestCase {

	public function test_log_is_noop_when_disabled() {
		Functions\when( 'get_option' )->justReturn( array() );
		Functions\expect( 'update_option' )->never();
		Logger::log( 'fail:hubspot', array( 'x' => 1 ) );
		$this->assertTrue( true );
	}

	public function test_log_writes_newest_first_and_caps_entries() {
		$existing = array_fill( 0, Logger::MAX_ENTRIES, array( 'time' => 1, 'event' => 'old', 'detail' => array() ) );
		Functions\when( 'get_option' )->alias(
			function ( $name, $default = false ) use ( $existing ) {
				if ( 'gr_settings' === $name ) {
					return array( 'debug_log' => 1 );
				}
				if ( Logger::OPTION === $name ) {
					return $existing;
				}
				return $default;
			}
		);
		$captured = null;
		Functions\expect( 'update_option' )->once()->andReturnUsing(
			function ( $name, $value ) use ( &$captured ) {
				$captured = $value;
				return true;
			}
		);

		Logger::log( 'fail:hubspot', array( 'status' => 400 ) );

		$this->assertCount( Logger::MAX_ENTRIES, $captured );
		$this->assertSame( 'fail:hubspot', $captured[0]['event'] );
		$this->assertSame( array( 'status' => 400 ), $captured[0]['detail'] );
	}
}
