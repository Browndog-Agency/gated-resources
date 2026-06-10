<?php
/**
 * @var int $post_id
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$inline_url   = add_query_arg( array( 'gr_file' => $post_id, 'gr_disp' => 'inline' ), home_url( '/' ) );
$download_url = add_query_arg( array( 'gr_file' => $post_id, 'gr_disp' => 'download' ), home_url( '/' ) );
?>
<div class="gr-viewer">
	<div class="gr-viewer__actions">
		<a class="gr-btn gr-btn--primary" href="<?php echo esc_url( $download_url ); ?>">
			<?php esc_html_e( 'Download PDF', 'gated-resources' ); ?>
		</a>
	</div>
	<iframe class="gr-viewer__frame" src="<?php echo esc_url( $inline_url ); ?>" title="<?php echo esc_attr( get_the_title( $post_id ) ); ?>"></iframe>
</div>
