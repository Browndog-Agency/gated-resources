<?php
namespace BrownDog\GatedResources;

class CPT {

	const SLUG = 'gated_resource';

	public function register() {
		add_action( 'init', array( $this, 'register_post_type' ) );
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
				'rewrite'      => array( 'slug' => 'resources' ),
				'supports'     => array( 'title', 'thumbnail' ),
				'show_in_rest' => true,
			)
		);
	}
}
