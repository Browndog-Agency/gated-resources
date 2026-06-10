<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
use BrownDog\GatedResources\Thumbnail;

get_header();
$thumbnail = new Thumbnail();
?>
<main class="gr-archive">
	<div class="gr-container">
		<header class="gr-archive__header">
			<h1 class="gr-archive__title"><?php post_type_archive_title(); ?></h1>
		</header>

		<?php if ( have_posts() ) : ?>
			<div class="gr-grid">
				<?php
				while ( have_posts() ) :
					the_post();
					$post_id = get_the_ID();
					include GR_DIR . 'templates/parts/card.php';
				endwhile;
				?>
			</div>
			<div class="gr-pagination"><?php the_posts_pagination(); ?></div>
		<?php else : ?>
			<p><?php esc_html_e( 'No resources found.', 'gated-resources' ); ?></p>
		<?php endif; ?>
	</div>
</main>
<?php
get_footer();
