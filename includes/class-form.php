<?php
namespace BrownDog\GatedResources;

class Form {

	private $turnstile;
	private $hubspot;
	private $gate;

	public function __construct( Turnstile $turnstile, HubSpot $hubspot, Gate $gate ) {
		$this->turnstile = $turnstile;
		$this->hubspot   = $hubspot;
		$this->gate      = $gate;
	}

	public function register() {
		add_action( 'wp_ajax_gr_submit', array( $this, 'handle' ) );
		add_action( 'wp_ajax_nopriv_gr_submit', array( $this, 'handle' ) );
	}

	private function fail( $code, $errors = array() ) {
		return array( 'ok' => false, 'code' => $code, 'errors' => $errors );
	}

	private function is_rate_limited( $ip ) {
		if ( ! $ip ) {
			return false;
		}
		$window = ( defined( 'MINUTE_IN_SECONDS' ) ? MINUTE_IN_SECONDS : 60 ) * 10;
		$key    = 'gr_rl_' . md5( $ip );
		$bucket = get_transient( $key );
		if ( ! is_array( $bucket ) || empty( $bucket['start'] ) ) {
			$bucket = array(
				'count' => 0,
				'start' => time(),
			);
		}
		if ( $bucket['count'] >= 10 ) {
			return true;
		}
		$bucket['count']++;
		// Preserve the original window expiry (fixed window) so a paced client
		// cannot keep renewing the TTL and slide past the limit indefinitely.
		$remaining = max( 1, ( (int) $bucket['start'] + $window ) - time() );
		set_transient( $key, $bucket, $remaining );
		return false;
	}

	private function validate( array $in ) {
		$errors = array();
		if ( empty( $in['firstname'] ) ) {
			$errors['firstname'] = __( 'Please enter your first name.', 'gated-resources' );
		}
		if ( empty( $in['lastname'] ) ) {
			$errors['lastname'] = __( 'Please enter your last name.', 'gated-resources' );
		}
		if ( empty( $in['email'] ) || ! is_email( $in['email'] ) ) {
			$errors['email'] = __( 'Please enter a valid email address.', 'gated-resources' );
		}
		if ( empty( $in['company'] ) ) {
			$errors['company'] = __( 'Please enter your organisation.', 'gated-resources' );
		}
		if ( empty( $in['jobtitle'] ) ) {
			$errors['jobtitle'] = __( 'Please enter your job title.', 'gated-resources' );
		}
		return $errors;
	}

	/**
	 * Pure pipeline. Returns ['ok'=>bool, ...]. Does NOT emit output.
	 */
	public function process( array $in ) {
		if ( ! empty( $in['hp'] ) ) {
			return $this->fail( 'spam' );
		}
		if ( $this->is_rate_limited( $in['ip'] ?? '' ) ) {
			return $this->fail( 'rate' );
		}
		if ( ! $this->turnstile->verify( $in['turnstile'] ?? '', $in['ip'] ?? '' ) ) {
			return $this->fail( 'captcha' );
		}
		$errors = $this->validate( $in );
		if ( $errors ) {
			return $this->fail( 'invalid', $errors );
		}

		$fields = array(
			'firstname' => $in['firstname'],
			'lastname'  => $in['lastname'],
			'email'     => $in['email'],
			'company'   => $in['company'],
			'jobtitle'  => $in['jobtitle'],
		);
		$result = $this->hubspot->submit( $fields, ! empty( $in['consent'] ), $in['context'] ?? array() );
		if ( is_wp_error( $result ) ) {
			return $this->fail( 'hubspot' );
		}

		$unlock = $this->gate->create_unlock( $in['email'], ! empty( $in['consent'] ), $in['ip'] ?? '' );
		return array( 'ok' => true, 'unlock' => $unlock );
	}

	public function handle() {
		if ( ! check_ajax_referer( 'gr_form', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session expired. Please refresh and try again.', 'gated-resources' ) ), 400 );
			return;
		}

		$in = array(
			'hp'        => isset( $_POST['gr_company_url'] ) ? trim( wp_unslash( $_POST['gr_company_url'] ) ) : '',
			'ip'        => $this->client_ip(),
			'turnstile' => isset( $_POST['cf-turnstile-response'] ) ? sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ) ) : '',
			'firstname' => isset( $_POST['firstname'] ) ? sanitize_text_field( wp_unslash( $_POST['firstname'] ) ) : '',
			'lastname'  => isset( $_POST['lastname'] ) ? sanitize_text_field( wp_unslash( $_POST['lastname'] ) ) : '',
			'email'     => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
			'company'   => isset( $_POST['company'] ) ? sanitize_text_field( wp_unslash( $_POST['company'] ) ) : '',
			'jobtitle'  => isset( $_POST['jobtitle'] ) ? sanitize_text_field( wp_unslash( $_POST['jobtitle'] ) ) : '',
			'consent'   => ! empty( $_POST['consent'] ),
			'context'   => array(
				'hutk'     => isset( $_COOKIE['hubspotutk'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['hubspotutk'] ) ) : '',
				'pageUri'  => isset( $_POST['page_uri'] ) ? esc_url_raw( wp_unslash( $_POST['page_uri'] ) ) : '',
				'pageName' => isset( $_POST['page_name'] ) ? sanitize_text_field( wp_unslash( $_POST['page_name'] ) ) : '',
			),
		);

		$result = $this->process( $in );

		if ( ! $result['ok'] ) {
			$messages = array(
				'spam'    => __( 'Submission blocked.', 'gated-resources' ),
				'rate'    => __( 'Too many attempts. Please try again later.', 'gated-resources' ),
				'captcha' => __( 'Anti-spam verification failed. Please try again.', 'gated-resources' ),
				'invalid' => __( 'Please check the highlighted fields.', 'gated-resources' ),
				'hubspot' => __( 'Something went wrong submitting the form. Please try again.', 'gated-resources' ),
			);
			wp_send_json_error(
				array(
					'message' => $messages[ $result['code'] ] ?? __( 'Submission failed.', 'gated-resources' ),
					'errors'  => $result['errors'] ?? array(),
				),
				400
			);
			return;
		}

		$this->gate->set_cookie( $result['unlock']['token'], $result['unlock']['expires'] );
		wp_send_json_success( array( 'message' => __( 'Thanks — your resource is unlocked.', 'gated-resources' ) ) );
	}

	private function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/**
	 * Render the gate form markup. Used by the single template.
	 */
	public function render( $post_id ) {
		$turnstile_key = $this->turnstile->site_key();
		$privacy_url   = Settings::get( 'privacy_url' );
		$consent_label = Settings::get( 'consent_label' );
		include GR_DIR . 'templates/parts/gate-form.php';
	}
}
