<?php
namespace BrownDog\GatedResources;

class HubSpot {

	const ENDPOINT = 'https://%s/submissions/v3/integration/submit/%s/%s';

	/**
	 * Forms submission host for the portal's data region. EU portals (the embed
	 * snippet shows data-region="eu1") must use api-eu1; the US default rejects
	 * them. Anything other than eu1 falls back to the US host.
	 */
	private function host() {
		return 'eu1' === Settings::get( 'hubspot_region', 'na1' )
			? 'api-eu1.hsforms.com'
			: 'api.hsforms.com';
	}

	public function build_payload( array $fields, $consent, array $context = array() ) {
		$hs_fields = array();
		foreach ( $fields as $name => $value ) {
			if ( '' === $value || null === $value ) {
				continue;
			}
			$hs_fields[] = array(
				'name'  => $name,
				'value' => (string) $value,
			);
		}

		$payload = array( 'fields' => $hs_fields );

		$ctx = array_filter(
			array(
				'hutk'     => $context['hutk'] ?? '',
				'pageUri'  => $context['pageUri'] ?? '',
				'pageName' => $context['pageName'] ?? '',
			)
		);
		if ( $ctx ) {
			$payload['context'] = $ctx;
		}

		if ( $consent ) {
			$label  = Settings::get( 'consent_label', 'I agree to be contacted.' );
			$sub_id = (int) Settings::get( 'hs_consent_subscription_id', 0 );
			$consent_block = array(
				'consentToProcess' => true,
				'text'             => $label,
			);
			// Only attach the communications opt-in when a real subscription type
			// is configured. Sending subscriptionTypeId 0 (the unset default) makes
			// HubSpot reject the whole submission with an invalid-subscription error.
			if ( $sub_id > 0 ) {
				$consent_block['communications'] = array(
					array(
						'value'              => true,
						'subscriptionTypeId' => $sub_id,
						'text'               => $label,
					),
				);
			}
			$payload['legalConsentOptions'] = array( 'consent' => $consent_block );
		}

		return $payload;
	}

	public function submit( array $fields, $consent, array $context = array() ) {
		$portal = Settings::get( 'hubspot_portal_id' );
		$guid   = Settings::get( 'hubspot_form_guid' );
		if ( ! $portal || ! $guid ) {
			return new \WP_Error( 'gr_hs_config', __( 'HubSpot is not configured.', 'gated-resources' ) );
		}

		$url  = sprintf( self::ENDPOINT, $this->host(), rawurlencode( $portal ), rawurlencode( $guid ) );
		$resp = wp_remote_post(
			$url,
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $this->build_payload( $fields, $consent, $context ) ),
			)
		);

		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$code = (int) wp_remote_retrieve_response_code( $resp );
		if ( $code < 200 || $code >= 300 ) {
			return new \WP_Error(
				'gr_hs_http',
				__( 'HubSpot rejected the submission.', 'gated-resources' ),
				array( 'status' => $code, 'body' => wp_remote_retrieve_body( $resp ) )
			);
		}
		return true;
	}
}
