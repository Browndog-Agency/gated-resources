<?php
namespace BrownDog\GatedResources\Tests;

use Brain\Monkey\Functions;
use BrownDog\GatedResources\Shortcode;
use BrownDog\GatedResources\Thumbnail;

final class ShortcodeTest extends GR_TestCase {

	public function test_parse_atts_applies_defaults_and_bounds() {
		Functions\when( 'shortcode_atts' )->alias(
			function ( $defaults, $atts ) { return array_merge( $defaults, (array) $atts ); }
		);
		$sc  = new Shortcode( new Thumbnail() );
		$out = $sc->parse_atts( array( 'count' => '8', 'columns' => '9' ) );
		$this->assertSame( 8, $out['count'] );
		$this->assertSame( 4, $out['columns'] ); // clamped to max 4
	}
}
