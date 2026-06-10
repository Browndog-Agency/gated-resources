<?php
namespace BrownDog\GatedResources;

class Gate {

	const COOKIE = 'gr_access';

	public function register() {
		add_action( 'gr_prune_unlocks', array( $this, 'prune_expired' ) );
		add_action( 'init', array( $this, 'maybe_schedule_cron' ) );
	}

	public function maybe_schedule_cron() {
		if ( ! wp_next_scheduled( 'gr_prune_unlocks' ) ) {
			wp_schedule_event( time(), 'daily', 'gr_prune_unlocks' );
		}
	}

	public function table() {
		global $wpdb;
		return $wpdb->prefix . 'gr_unlocks';
	}

	public function generate_token() {
		return bin2hex( random_bytes( 32 ) );
	}

	public function unlock_days() {
		return (int) Settings::get( 'unlock_days', 30 );
	}

	public function create_unlock( $email, $consent, $ip = '' ) {
		global $wpdb;
		$token   = $this->generate_token();
		$now     = time();
		$expires = $now + ( $this->unlock_days() * DAY_IN_SECONDS );
		$wpdb->insert(
			$this->table(),
			array(
				'token'      => $token,
				'email'      => $email,
				'consent'    => $consent ? 1 : 0,
				'created_at' => gmdate( 'Y-m-d H:i:s', $now ),
				'expires_at' => gmdate( 'Y-m-d H:i:s', $expires ),
				'ip_hash'    => $ip ? hash( 'sha256', $ip ) : '',
			)
		);
		return array( 'token' => $token, 'expires' => $expires );
	}

	public function is_valid_token( $token ) {
		if ( empty( $token ) || ! ctype_xdigit( $token ) || strlen( $token ) !== 64 ) {
			return false;
		}
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT id, expires_at FROM {$this->table()} WHERE token = %s LIMIT 1", $token )
		);
		if ( ! $row ) {
			return false;
		}
		return strtotime( $row->expires_at . ' UTC' ) > time();
	}

	public function is_unlocked() {
		$token = isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : '';
		return $this->is_valid_token( $token );
	}

	public function set_cookie( $token, $expires ) {
		setcookie(
			self::COOKIE,
			$token,
			array(
				'expires'  => $expires,
				'path'     => defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
		$_COOKIE[ self::COOKIE ] = $token;
	}

	public function prune_expired() {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$this->table()} WHERE expires_at < UTC_TIMESTAMP()" );
	}
}
