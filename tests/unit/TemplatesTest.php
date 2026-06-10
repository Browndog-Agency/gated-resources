<?php
namespace BrownDog\GatedResources\Tests;

use Brain\Monkey\Functions;
use BrownDog\GatedResources\Templates;

final class TemplatesTest extends GR_TestCase {

	protected function tearDown(): void {
		unset( $_GET['gr_file'] );
		parent::tearDown();
	}

	public function test_single_redirect_url_returns_archive_link_on_single() {
		Functions\when( 'is_singular' )->justReturn( true );
		Functions\when( 'get_post_type_archive_link' )->justReturn( 'https://x/resources/' );

		$this->assertSame( 'https://x/resources/', ( new Templates() )->single_redirect_url() );
	}

	public function test_single_redirect_url_empty_when_not_single() {
		Functions\when( 'is_singular' )->justReturn( false );

		$this->assertSame( '', ( new Templates() )->single_redirect_url() );
	}

	public function test_single_redirect_url_empty_for_file_endpoint_requests() {
		Functions\when( 'is_singular' )->justReturn( true );
		$_GET['gr_file'] = '12';

		$this->assertSame( '', ( new Templates() )->single_redirect_url() );
	}
}
