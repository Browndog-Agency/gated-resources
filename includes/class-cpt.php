<?php
namespace BrownDog\GatedResources;

class CPT {

	const SLUG = 'gated_resource';

	public function register() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		// Plugin updates don't run the activation hook, so re-flush rewrite
		// rules once after any version change to pick up CPT/permalink changes.
		add_action( 'admin_init', array( $this, 'maybe_flush_rewrites' ) );
	}

	/**
	 * Flush rewrite rules a single time after the plugin version changes.
	 * Runs on admin_init (after register_post_type on init), so the CPT's
	 * rules are present when we flush.
	 */
	public function maybe_flush_rewrites() {
		if ( get_option( 'gr_rewrite_version' ) !== GR_VERSION ) {
			flush_rewrite_rules();
			update_option( 'gr_rewrite_version', GR_VERSION );
		}
	}

	public function register_post_type() {
		$labels = array(
			'name'          => __( 'Resources', 'gated-resources' ),
			'singular_name' => __( 'Resource', 'gated-resources' ),
			'add_new_item'  => __( 'Add New Resource', 'gated-resources' ),
			'edit_item'     => __( 'Edit Resource', 'gated-resources' ),
			'menu_name'     => __( 'Resources', 'gated-resources' ),
		);

		register_post_type(
			self::SLUG,
			array(
				'labels'       => $labels,
				'public'       => true,
				'has_archive'  => true,
				'menu_icon'    => 'dashicons-media-document',
				'rewrite'      => array(
					'slug'       => 'resources',
					'with_front' => false,
				),
				'supports'     => array( 'title', 'thumbnail' ),
				'show_in_rest' => true,
			)
		);
	}
}
