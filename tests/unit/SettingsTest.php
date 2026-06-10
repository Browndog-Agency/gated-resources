<?php
namespace BrownDog\GatedResources\Tests;

use Brain\Monkey\Functions;
use BrownDog\GatedResources\Settings;

final class SettingsTest extends GR_TestCase {
	public function test_get_returns_stored_value() {
		Functions\when( 'get_option' )->justReturn( array( 'unlock_days' => 14 ) );
		$this->assertSame( 14, Settings::get( 'unlock_days' ) );
	}

	public function test_get_returns_default_when_missing() {
		Functions\when( 'get_option' )->justReturn( array() );
		$this->assertSame( 'fallback', Settings::get( 'nope', 'fallback' ) );
	}
}
