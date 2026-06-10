<?php
namespace BrownDog\GatedResources\Tests;

use Brain\Monkey\Functions;
use BrownDog\GatedResources\Thumbnail;

final class ThumbnailTest extends GR_TestCase {

	public function test_cover_url_prefers_generated_preview() {
		Functions\when( 'get_post_meta' )->justReturn( 'https://x/p.jpg' );
		$this->assertSame( 'https://x/p.jpg', ( new Thumbnail() )->cover_url( 1 ) );
	}

	public function test_cover_url_falls_back_to_featured_image() {
		Functions\when( 'get_post_meta' )->justReturn( '' );
		Functions\when( 'has_post_thumbnail' )->justReturn( true );
		Functions\when( 'get_the_post_thumbnail_url' )->justReturn( 'https://x/feat.jpg' );
		$this->assertSame( 'https://x/feat.jpg', ( new Thumbnail() )->cover_url( 1 ) );
	}

	public function test_cover_url_falls_back_to_placeholder() {
		Functions\when( 'get_post_meta' )->justReturn( '' );
		Functions\when( 'has_post_thumbnail' )->justReturn( false );
		$this->assertStringContainsString( 'placeholder.svg', ( new Thumbnail() )->cover_url( 1 ) );
	}
}
