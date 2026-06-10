<?php
namespace BrownDog\GatedResources;

class HubSpot {

	const ENDPOINT = 'https://api.hsforms.com/submissions/v3/integration/submit/%s/%s';

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
			$payload['legalConsentOptions'] = array(
				'consent' => array(
					'consentToProcess' => true,
					'text'             => $label,
					'communications'   => array(
						array(
							'value'              => true,
							'subscriptionTypeId' => $sub_id,
							'text'               => $label,
						),
					),
				),
			);
		}

		return $payload;
	}

	public function submit( array $fields, $consent, array $context = array() ) {
		$portal = Settings::get( 'hubspot_portal_id' );
		$guid   = Settings::get( 'hubspot_form_guid' );
		if ( ! $portal || ! $guid ) {
			return new \WP_Error( 'gr_hs_config', __( 'HubSpot is not configured.', 'gated-resources' ) );
		}

		$url  = sprintf( self::ENDPOINT, rawurlencode( $portal ), rawurlencode( $guid ) );
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
