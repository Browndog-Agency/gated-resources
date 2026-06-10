<?php
/**
 * Gate form popup, rendered once per page on grid views while locked.
 *
 * @var \BrownDog\GatedResources\Form $form
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<div class="gr-modal" id="gr-modal" hidden>
	<div class="gr-modal__overlay" data-gr-close></div>
	<div class="gr-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="gr-modal-title">
		<button type="button" class="gr-modal__close" data-gr-close aria-label="<?php esc_attr_e( 'Close', 'gated-resources' ); ?>">&times;</button>
		<h2 class="gr-modal__title" id="gr-modal-title"><?php esc_html_e( 'Access our resources', 'gated-resources' ); ?></h2>
		<?php $form->render(); ?>
	</div>
</div>
