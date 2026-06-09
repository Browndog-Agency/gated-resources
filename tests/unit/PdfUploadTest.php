<?php
namespace BrownDog\GatedResources\Tests;

use Brain\Monkey\Functions;
use BrownDog\GatedResources\PDF_Upload;
use BrownDog\GatedResources\Protected_Files;
use BrownDog\GatedResources\Thumbnail;

final class PdfUploadTest extends GR_TestCase {

	private function make() {
		return new PDF_Upload( new Protected_Files(), new Thumbnail() );
	}

	public function test_rejects_non_pdf_extension() {
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'is_wp_error' )->justReturn( true );
		$res = $this->make()->validate_file( array( 'name' => 'evil.exe', 'tmp_name' => '/tmp/x', 'size' => 10, 'error' => 0 ) );
		$this->assertInstanceOf( \WP_Error::class, $res );
	}

	public function test_rejects_oversize() {
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'get_option' )->justReturn( array( 'max_upload_mb' => 1 ) );
		Functions\when( 'wp_check_filetype_and_ext' )->justReturn( array( 'ext' => 'pdf', 'type' => 'application/pdf' ) );
		$res = $this->make()->validate_file( array( 'name' => 'big.pdf', 'tmp_name' => '/tmp/x', 'size' => 5 * 1024 * 1024, 'error' => 0 ) );
		$this->assertInstanceOf( \WP_Error::class, $res );
	}

	public function test_accepts_valid_pdf() {
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'get_option' )->justReturn( array( 'max_upload_mb' => 25 ) );
		Functions\when( 'wp_check_filetype_and_ext' )->justReturn( array( 'ext' => 'pdf', 'type' => 'application/pdf' ) );
		$res = $this->make()->validate_file( array( 'name' => 'ok.pdf', 'tmp_name' => '/tmp/x', 'size' => 1000, 'error' => 0 ) );
		$this->assertTrue( $res );
	}
}
