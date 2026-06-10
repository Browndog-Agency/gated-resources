<?php
namespace BrownDog\GatedResources;

class Shortcode {

	private $thumbnail;

	public function __construct( Thumbnail $thumbnail ) {
		$this->thumbnail = $thumbnail;
	}

	public function register() {
		add_shortcode( 'gated_resources', array( $this, 'render' ) );
	}

	public function parse_atts( $atts ) {
		$a = shortcode_atts(
			array(
				'count'   => 9,
				'columns' => 3,
			),
			$atts,
			'gated_resources'
		);
		$a['count']   = max( 1, (int) $a['count'] );
		$a['columns'] = min( 4, max( 1, (int) $a['columns'] ) );
		return $a;
	}

	public function render( $atts ) {
		$a = $this->parse_atts( $atts );
		$q = new \WP_Query(
			array(
				'post_type'      => CPT::SLUG,
				'posts_per_page' => $a['count'],
				'no_found_rows'  => true,
			)
		);
		if ( ! $q->have_posts() ) {
			return '';
		}

		$thumbnail = $this->thumbnail;
		ob_start();
		echo '<div class="gr-grid gr-grid--cols-' . (int) $a['columns'] . '">';
		while ( $q->have_posts() ) {
			$q->the_post();
			$post_id = get_the_ID();
			include GR_DIR . 'templates/parts/card.php';
		}
		echo '</div>';
		wp_reset_postdata();
		return ob_get_clean();
	}
}
