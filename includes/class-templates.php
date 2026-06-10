<?php
namespace BrownDog\GatedResources;

class Templates {

	/**
	 * Use plugin templates for the CPT, allowing theme overrides
	 * (theme can place single-gated_resource.php / archive-gated_resource.php).
	 */
	public function route( $template ) {
		if ( is_singular( CPT::SLUG ) ) {
			$theme = locate_template( array( 'single-gated_resource.php' ) );
			return $theme ? $theme : GR_DIR . 'templates/single-gated_resource.php';
		}
		if ( is_post_type_archive( CPT::SLUG ) ) {
			$theme = locate_template( array( 'archive-gated_resource.php' ) );
			return $theme ? $theme : GR_DIR . 'templates/archive-gated_resource.php';
		}
		return $template;
	}
}
