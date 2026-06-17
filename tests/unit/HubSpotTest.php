<?php
namespace BrownDog\GatedResources\Tests;

use Brain\Monkey\Functions;
use BrownDog\GatedResources\HubSpot;

final class HubSpotTest extends GR_TestCase {

	public function test_payload_maps_fields() {
		Functions\when( 'get_option' )->justReturn( array() );
		$payload = ( new HubSpot() )->build_payload(
			array( 'firstname' => 'Ann', 'email' => 'a@b.com', 'company' => 'Council' ),
			false
		);
		$names = array_column( $payload['fields'], 'name' );
		$this->assertContains( 'firstname', $names );
		$this->assertContains( 'email', $names );
		$this->assertContains( 'company', $names );
		$this->assertArrayNotHasKey( 'legalConsentOptions', $payload );
	}

	public function test_payload_includes_consent_when_true() {
		Functions\when( 'get_option' )->justReturn( array( 'hs_consent_subscription_id' => 7, 'consent_label' => 'Yes please' ) );
		$payload = ( new HubSpot() )->build_payload( array( 'email' => 'a@b.com' ), true );
		$this->assertArrayHasKey( 'legalConsentOptions', $payload );
		$this->assertTrue( $payload['legalConsentOptions']['consent']['consentToProcess'] );
		$this->assertSame( 7, $payload['legalConsentOptions']['consent']['communications'][0]['subscriptionTypeId'] );
	}

	public function test_consent_omits_communications_when_no_subscription_id() {
		Functions\when( 'get_option' )->justReturn( array( 'consent_label' => 'Yes please' ) );
		$payload = ( new HubSpot() )->build_payload( array( 'email' => 'a@b.com' ), true );
		$this->assertTrue( $payload['legalConsentOptions']['consent']['consentToProcess'] );
		$this->assertArrayNotHasKey( 'communications', $payload['legalConsentOptions']['consent'] );
	}

	public function test_payload_includes_context_when_present() {
		Functions\when( 'get_option' )->justReturn( array() );
		$payload = ( new HubSpot() )->build_payload(
			array( 'email' => 'a@b.com' ),
			false,
			array( 'pageUri' => 'https://x/y', 'hutk' => 'abc' )
		);
		$this->assertSame( 'https://x/y', $payload['context']['pageUri'] );
		$this->assertSame( 'abc', $payload['context']['hutk'] );
	}
}
