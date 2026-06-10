<?php
namespace BrownDog\GatedResources;

class Templates {

	public function register() {
		add_filter( 'template_include', array( $this, 'route' ) );
		add_action( 'template_redirect', array( $this, 'maybe_redirect_single' ) );
	}

	/**
	 * Use the plugin's archive template for the CPT, allowing theme overrides
	 * (a theme can provide its own archive-gated_resource.php).
	 */
	public function route( $template ) {
		if ( is_post_type_archive( CPT::SLUG ) ) {
			$theme = locate_template( array( 'archive-gated_resource.php' ) );
			return $theme ? $theme : GR_DIR . 'templates/archive-gated_resource.php';
		}
		return $template;
	}

	/**
	 * Single resource pages are not used — the gate form opens in a popup on
	 * the grid and unlocked cards link straight to the file endpoint — so
	 * permanently redirect singles to the archive.
	 */
	public function maybe_redirect_single() {
		$target = $this->single_redirect_url();
		if ( $target ) {
			wp_safe_redirect( $target, 301 );
			exit;
		}
	}

	/**
	 * The redirect decision, side-effect free for testability.
	 * Returns the archive URL, or '' when no redirect should happen.
	 */
	public function single_redirect_url() {
		if ( ! is_singular( CPT::SLUG ) || isset( $_GET['gr_file'] ) ) {
			return '';
		}
		return (string) get_post_type_archive_link( CPT::SLUG );
	}
}
