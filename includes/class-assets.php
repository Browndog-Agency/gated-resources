<?php
namespace BrownDog\GatedResources;

class Assets {

	private $turnstile;

	public function __construct( Turnstile $turnstile ) {
		$this->turnstile = $turnstile;
	}

	public function register() {
		add_action( 'wp_enqueue_scripts', array( $this, 'front' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin' ) );
	}

	public function front() {
		wp_enqueue_style( 'gated-resources', GR_URL . 'assets/css/gated-resources.css', array(), GR_VERSION );

		if ( $this->needs_form_assets() ) {
			wp_enqueue_script( 'gated-resources', GR_URL . 'assets/js/gated-resources.js', array(), GR_VERSION, true );
			wp_localize_script(
				'gated-resources',
				'GR_Front',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'i18n'    => array(
						'submitting' => __( 'Submitting…', 'gated-resources' ),
						'error'      => __( 'Something went wrong. Please try again.', 'gated-resources' ),
					),
				)
			);
			if ( $this->turnstile->is_configured() ) {
				wp_enqueue_script( 'cf-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', array(), null, true );
			}
		}
	}

	/**
	 * The gate popup (form JS + Turnstile) is needed on any grid view:
	 * the CPT archive, or a page running the [gated_resources] shortcode.
	 */
	private function needs_form_assets() {
		if ( is_post_type_archive( CPT::SLUG ) || is_singular( CPT::SLUG ) ) {
			return true;
		}
		if ( is_singular() ) {
			$post = get_post();
			return $post && has_shortcode( (string) $post->post_content, 'gated_resources' );
		}
		return false;
	}

	public function admin( $hook ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && CPT::SLUG === $screen->post_type ) {
			wp_enqueue_style( 'gated-resources-admin', GR_URL . 'assets/css/admin.css', array(), GR_VERSION );
			wp_enqueue_script( 'gated-resources-admin', GR_URL . 'assets/js/admin-uploader.js', array(), GR_VERSION, true );
			wp_localize_script(
				'gated-resources-admin',
				'GR_Admin',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'i18n'    => array(
						'choose'    => __( 'Please choose a PDF file first.', 'gated-resources' ),
						'uploading' => __( 'Uploading…', 'gated-resources' ),
						'done'      => __( 'Uploaded:', 'gated-resources' ),
						'error'     => __( 'Upload failed.', 'gated-resources' ),
					),
				)
			);
		}
	}
}
