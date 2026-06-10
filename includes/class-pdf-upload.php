<?php
namespace BrownDog\GatedResources;

class PDF_Upload {

	private $files;
	private $thumbnail;

	public function __construct( Protected_Files $files, Thumbnail $thumbnail ) {
		$this->files     = $files;
		$this->thumbnail = $thumbnail;
	}

	public function register() {
		add_action( 'wp_ajax_gr_upload_pdf', array( $this, 'handle' ) );
	}

	public function max_bytes() {
		return ( (int) Settings::get( 'max_upload_mb', 25 ) ) * 1024 * 1024;
	}

	public function validate_file( array $file ) {
		if ( empty( $file['name'] ) || ! empty( $file['error'] ) ) {
			return new \WP_Error( 'gr_upload', __( 'No file was uploaded.', 'gated-resources' ) );
		}
		$ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		if ( 'pdf' !== $ext ) {
			return new \WP_Error( 'gr_ext', __( 'Only PDF files are allowed.', 'gated-resources' ) );
		}
		$check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
		if ( empty( $check['type'] ) || 'application/pdf' !== $check['type'] ) {
			return new \WP_Error( 'gr_mime', __( 'The file is not a valid PDF.', 'gated-resources' ) );
		}
		if ( (int) $file['size'] > $this->max_bytes() ) {
			return new \WP_Error( 'gr_size', __( 'The file exceeds the maximum allowed size.', 'gated-resources' ) );
		}
		return true;
	}

	public function handle() {
		if ( ! current_user_can( 'edit_posts' ) || ! check_ajax_referer( 'gr_admin', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gated-resources' ) ), 403 );
			return;
		}
		$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		$file    = isset( $_FILES['file'] ) ? $_FILES['file'] : array();

		// Per-post authorisation: a user with edit_posts must still own (be able
		// to edit) the specific resource they are attaching the PDF to.
		if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gated-resources' ) ), 403 );
			return;
		}

		$valid = $this->validate_file( (array) $file );
		if ( is_wp_error( $valid ) ) {
			wp_send_json_error( array( 'message' => $valid->get_error_message() ), 400 );
			return;
		}

		$relative = $this->files->store( $file['tmp_name'], $file['name'] );
		if ( is_wp_error( $relative ) ) {
			wp_send_json_error( array( 'message' => $relative->get_error_message() ), 500 );
			return;
		}

		if ( $post_id ) {
			update_post_meta( $post_id, '_gr_pdf_path', $relative );
			update_post_meta( $post_id, '_gr_pdf_name', sanitize_file_name( $file['name'] ) );
			update_post_meta( $post_id, '_gr_pdf_size', (int) $file['size'] );
			$this->thumbnail->generate( $this->files->absolute_path( $relative ), $post_id );
		}

		wp_send_json_success(
			array(
				'name'      => sanitize_file_name( $file['name'] ),
				'path'      => $relative,
				'cover_url' => $post_id ? $this->thumbnail->cover_url( $post_id ) : '',
			)
		);
	}
}
