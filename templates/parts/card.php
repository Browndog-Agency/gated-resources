<?php
/**
 * @var int       $post_id
 * @var Thumbnail $thumbnail
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$cover = $thumbnail->cover_url( $post_id );
?>
<article class="gr-card">
	<a class="gr-card__media" href="<?php echo esc_url( get_permalink( $post_id ) ); ?>">
		<img src="<?php echo esc_url( $cover ); ?>" alt="<?php echo esc_attr( get_the_title( $post_id ) ); ?>" loading="lazy">
	</a>
	<h3 class="gr-card__title">
		<a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>"><?php echo esc_html( get_the_title( $post_id ) ); ?></a>
	</h3>
	<a class="gr-card__more" href="<?php echo esc_url( get_permalink( $post_id ) ); ?>">
		<?php esc_html_e( 'Read more', 'gated-resources' ); ?>
	</a>
</article>
