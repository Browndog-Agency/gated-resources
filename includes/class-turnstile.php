<?php
namespace BrownDog\GatedResources;

class Turnstile {

	const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

	public function is_configured() {
		return (bool) Settings::get( 'turnstile_secret_key' ) && (bool) Settings::get( 'turnstile_site_key' );
	}

	public function site_key() {
		return Settings::get( 'turnstile_site_key' );
	}

	public function verify( $token, $ip = '' ) {
		if ( empty( $token ) ) {
			return false;
		}
		$resp = wp_remote_post(
			self::VERIFY_URL,
			array(
				'timeout' => 10,
				'body'    => array(
					'secret'   => Settings::get( 'turnstile_secret_key' ),
					'response' => $token,
					'remoteip' => $ip,
				),
			)
		);
		if ( is_wp_error( $resp ) ) {
			return false;
		}
		$body = json_decode( wp_remote_retrieve_body( $resp ), true );
		return ! empty( $body['success'] );
	}
}
