<?php
namespace BrownDog\GatedResources;

class Meta_Box {

	private $thumbnail;

	public function __construct( Thumbnail $thumbnail ) {
		$this->thumbnail = $thumbnail;
	}

	public function register() {
		add_action( 'add_meta_boxes', array( $this, 'add' ) );
		add_action( 'save_post_' . CPT::SLUG, array( $this, 'save' ), 10, 2 );
	}

	public function add() {
		add_meta_box(
			'gr_resource_details',
			__( 'Resource Details', 'gated-resources' ),
			array( $this, 'render' ),
			CPT::SLUG,
			'normal',
			'high'
		);
	}

	public function render( $post ) {
		wp_nonce_field( 'gr_save_meta', 'gr_meta_nonce' );
		$desc   = get_post_meta( $post->ID, '_gr_description', true );
		$name   = get_post_meta( $post->ID, '_gr_pdf_name', true );
		$status = get_post_meta( $post->ID, '_gr_preview_status', true );
		?>
		<p>
			<label for="gr_description"><strong><?php esc_html_e( 'Resource Description (optional)', 'gated-resources' ); ?></strong></label>
			<textarea id="gr_description" name="gr_description" rows="4" class="large-text"><?php echo esc_textarea( $desc ); ?></textarea>
		</p>
		<p><strong><?php esc_html_e( 'Resource PDF', 'gated-resources' ); ?></strong></p>
		<div id="gr-upload" data-post="<?php echo (int) $post->ID; ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'gr_admin' ) ); ?>">
			<input type="file" id="gr-pdf-file" accept="application/pdf">
			<button type="button" class="button" id="gr-pdf-upload-btn"><?php esc_html_e( 'Upload PDF', 'gated-resources' ); ?></button>
			<span id="gr-upload-status" class="gr-upload-status">
				<?php echo $name ? esc_html( sprintf( __( 'Current: %s', 'gated-resources' ), $name ) ) : esc_html__( 'No PDF uploaded yet.', 'gated-resources' ); ?>
			</span>
		</div>
		<p class="description">
			<?php
			if ( 'generated' === $status ) {
				esc_html_e( 'Cover thumbnail generated from page 1 of the PDF.', 'gated-resources' );
			} elseif ( 'failed' === $status ) {
				esc_html_e( 'Could not render the PDF cover (Imagick/Ghostscript unavailable). The featured image or a placeholder will be used.', 'gated-resources' );
			} else {
				esc_html_e( 'A cover will be generated from page 1 of the PDF, or the featured image if rendering is unavailable.', 'gated-resources' );
			}
			?>
		</p>
		<?php
	}

	public function save( $post_id, $post ) {
		if ( ! isset( $_POST['gr_meta_nonce'] ) || ! wp_verify_nonce( $_POST['gr_meta_nonce'], 'gr_save_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$desc = isset( $_POST['gr_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['gr_description'] ) ) : '';
		update_post_meta( $post_id, '_gr_description', $desc );
		// The PDF itself is saved asynchronously by the uploader (Task 11), keyed to this post_id.
	}
}
