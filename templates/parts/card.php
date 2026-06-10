<?php
/**
 * @var int       $post_id
 * @var Thumbnail $thumbnail
 * @var bool      $unlocked  Whether the visitor holds a valid unlock.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$cover     = $thumbnail->cover_url( $post_id );
$file_url  = add_query_arg( array( 'gr_file' => $post_id, 'gr_disp' => 'inline' ), home_url( '/' ) );
$is_locked = empty( $unlocked );
// Locked cards open the gate popup (JS intercepts .gr-gate-trigger);
// unlocked cards open the PDF straight from the protected endpoint.
$trigger = $is_locked ? ' gr-gate-trigger' : '';
$target  = $is_locked ? '' : ' target="_blank" rel="noopener"';
?>
<article class="gr-card">
	<a class="gr-card__media<?php echo esc_attr( $trigger ); ?>" href="<?php echo esc_url( $file_url ); ?>"<?php echo $target; // phpcs:ignore WordPress.Security.EscapeOutput -- constant markup ?>>
		<img src="<?php echo esc_url( $cover ); ?>" alt="<?php echo esc_attr( get_the_title( $post_id ) ); ?>" loading="lazy">
	</a>
	<h3 class="gr-card__title">
		<a class="<?php echo esc_attr( ltrim( $trigger ) ); ?>" href="<?php echo esc_url( $file_url ); ?>"<?php echo $target; // phpcs:ignore WordPress.Security.EscapeOutput -- constant markup ?>><?php echo esc_html( get_the_title( $post_id ) ); ?></a>
	</h3>
	<a class="gr-card__more<?php echo esc_attr( $trigger ); ?>" href="<?php echo esc_url( $file_url ); ?>"<?php echo $target; // phpcs:ignore WordPress.Security.EscapeOutput -- constant markup ?>>
		<?php esc_html_e( 'View resource', 'gated-resources' ); ?>
	</a>
</article>
