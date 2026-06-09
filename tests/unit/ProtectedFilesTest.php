<?php
namespace BrownDog\GatedResources\Tests;

use Brain\Monkey\Functions;
use BrownDog\GatedResources\Protected_Files;

final class ProtectedFilesTest extends GR_TestCase {

	public function test_disposition_maps_download_to_attachment() {
		$pf = new Protected_Files();
		$this->assertSame( 'attachment', $pf->disposition( 'download' ) );
		$this->assertSame( 'inline', $pf->disposition( 'inline' ) );
		$this->assertSame( 'inline', $pf->disposition( 'anything-else' ) );
	}

	public function test_relative_path_format_is_hashed_and_sanitised() {
		Functions\when( 'sanitize_file_name' )->alias( function ( $n ) { return $n; } );
		$rel = Protected_Files::build_relative_path( 'report final.pdf', 'aabbccddeeff00112233445566778899' );
		// aa/aabbcc.../report final.pdf
		$this->assertMatchesRegularExpression( '#^[0-9a-f]{2}/[0-9a-f]{32}/report final\.pdf$#', $rel );
	}
}
