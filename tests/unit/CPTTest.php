<?php
namespace BrownDog\GatedResources\Tests;

use Brain\Monkey\Functions;
use BrownDog\GatedResources\CPT;

final class CPTTest extends GR_TestCase {

	public function test_register_calls_register_post_type_with_archive_and_slug() {
		Functions\when( '__' )->returnArg( 1 );
		$captured = array();
		Functions\when( 'register_post_type' )->alias(
			function ( $slug, $args ) use ( &$captured ) {
				$captured = array( 'slug' => $slug, 'args' => $args );
			}
		);

		( new CPT() )->register_post_type();

		$this->assertSame( 'gated_resource', $captured['slug'] );
		$this->assertTrue( $captured['args']['has_archive'] );
		$this->assertSame( 'resources', $captured['args']['rewrite']['slug'] );
		$this->assertContains( 'thumbnail', $captured['args']['supports'] );
	}
}
