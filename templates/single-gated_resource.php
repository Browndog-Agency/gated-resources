<?php
/**
 * Single resource: gate form (locked) OR viewer + download (unlocked).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

use BrownDog\GatedResources\Gate;
use BrownDog\GatedResources\Form;
use BrownDog\GatedResources\Turnstile;
use BrownDog\GatedResources\HubSpot;
use BrownDog\GatedResources\Thumbnail;

get_header();

while ( have_posts() ) :
	the_post();
	$post_id   = get_the_ID();
	$gate      = new Gate();
	$thumbnail = new Thumbnail();
	$desc      = get_post_meta( $post_id, '_gr_description', true );
	?>
	<main class="gr-single">
		<div class="gr-single__inner">
			<div class="gr-single__cover">
				<img src="<?php echo esc_url( $thumbnail->cover_url( $post_id ) ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>">
			</div>
			<div class="gr-single__body">
				<h1 class="gr-single__title"><?php the_title(); ?></h1>
				<?php if ( $desc ) : ?>
					<div class="gr-single__desc"><?php echo wp_kses_post( wpautop( $desc ) ); ?></div>
				<?php endif; ?>

				<?php
				if ( $gate->is_unlocked() ) {
					include GR_DIR . 'templates/parts/viewer.php';
				} else {
					$form = new Form( new Turnstile(), new HubSpot(), $gate );
					$form->render( $post_id );
				}
				?>
			</div>
		</div>
	</main>
	<?php
endwhile;

get_footer();
