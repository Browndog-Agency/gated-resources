<?php
namespace BrownDog\GatedResources\Tests;

use BrownDog\GatedResources\Activator;

final class ActivatorTest extends GR_TestCase {

	public function test_table_sql_contains_expected_columns() {
		$sql = Activator::table_sql( 'wp_gr_unlocks', 'utf8mb4_unicode_520_ci' );
		$this->assertStringContainsString( 'CREATE TABLE wp_gr_unlocks', $sql );
		foreach ( array( 'token', 'email', 'consent', 'created_at', 'expires_at', 'ip_hash' ) as $col ) {
			$this->assertStringContainsString( $col, $sql );
		}
		$this->assertStringContainsString( 'UNIQUE KEY token', $sql );
	}

	public function test_htaccess_denies_all() {
		$contents = Activator::htaccess_contents();
		$this->assertStringContainsString( 'Require all denied', $contents );
		$this->assertStringContainsString( 'Deny from all', $contents );
	}
}
