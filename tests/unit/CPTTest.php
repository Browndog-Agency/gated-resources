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
		// The CPT must NOT inherit the site's permalink front (e.g. /insights/news/),
		// so URLs are /resources/ and /resources/{slug}/.
		$this->assertFalse( $captured['args']['rewrite']['with_front'] );
		$this->assertContains( 'thumbnail', $captured['args']['supports'] );
	}

	public function test_maybe_flush_rewrites_flushes_once_when_version_changed() {
		Functions\when( 'get_option' )->justReturn( 'old-version' );
		$flushed = 0;
		Functions\when( 'flush_rewrite_rules' )->alias(
			function () use ( &$flushed ) {
				$flushed++;
			}
		);
		$saved = null;
		Functions\when( 'update_option' )->alias(
			function ( $key, $value ) use ( &$saved ) {
				$saved = array( $key, $value );
				return true;
			}
		);

		( new CPT() )->maybe_flush_rewrites();

		$this->assertSame( 1, $flushed );
		$this->assertSame( array( 'gr_rewrite_version', GR_VERSION ), $saved );
	}

	public function test_maybe_flush_rewrites_skips_when_version_matches() {
		Functions\when( 'get_option' )->justReturn( GR_VERSION );
		$flushed = 0;
		Functions\when( 'flush_rewrite_rules' )->alias(
			function () use ( &$flushed ) {
				$flushed++;
			}
		);
		Functions\when( 'update_option' )->justReturn( true );

		( new CPT() )->maybe_flush_rewrites();

		$this->assertSame( 0, $flushed );
	}
}
