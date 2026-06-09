<?php
namespace BrownDog\GatedResources;

class Protected_Files {

	/** @var Gate */
	private $gate;

	public function register( Gate $gate ) {
		$this->gate = $gate;
		add_action( 'template_redirect', array( $this, 'maybe_stream' ) );
	}

	public function base_dir() {
		$up = wp_upload_dir();
		return trailingslashit( $up['basedir'] ) . 'gated-resources';
	}

	public function disposition( $disp ) {
		return ( 'download' === $disp ) ? 'attachment' : 'inline';
	}

	public static function build_relative_path( $filename, $hash ) {
		$safe = sanitize_file_name( $filename );
		return substr( $hash, 0, 2 ) . '/' . $hash . '/' . $safe;
	}

	public function absolute_path( $relative ) {
		return trailingslashit( $this->base_dir() ) . ltrim( $relative, '/' );
	}

	/**
	 * Move an uploaded temp file into the protected dir. Returns the relative path or WP_Error.
	 */
	public function store( $tmp_path, $filename ) {
		$hash     = bin2hex( random_bytes( 16 ) );
		$relative = self::build_relative_path( $filename, $hash );
		$dest     = $this->absolute_path( $relative );

		wp_mkdir_p( dirname( $dest ) );
		if ( ! @move_uploaded_file( $tmp_path, $dest ) && ! @rename( $tmp_path, $dest ) ) {
			return new \WP_Error( 'gr_store', __( 'Could not store the uploaded file.', 'gated-resources' ) );
		}
		return $relative;
	}

	public function maybe_stream() {
		if ( ! isset( $_GET['gr_file'] ) ) {
			return;
		}
		$post_id = (int) $_GET['gr_file'];
		$disp    = isset( $_GET['gr_disp'] ) ? sanitize_key( $_GET['gr_disp'] ) : 'inline';
		$this->stream( $post_id, $disp );
	}

	public function stream( $post_id, $disp = 'inline' ) {
		if ( ! $this->gate->is_unlocked() ) {
			status_header( 403 );
			wp_die( esc_html__( 'You need to unlock this resource first.', 'gated-resources' ), 403 );
		}

		$relative = get_post_meta( $post_id, '_gr_pdf_path', true );
		$name     = get_post_meta( $post_id, '_gr_pdf_name', true ) ?: 'resource.pdf';
		if ( ! $relative ) {
			status_header( 404 );
			wp_die( esc_html__( 'Resource not found.', 'gated-resources' ), 404 );
		}

		$abs = $this->absolute_path( $relative );
		// Guard against path traversal: resolved path must stay inside base_dir.
		$real_base = realpath( $this->base_dir() );
		$real_file = realpath( $abs );
		if ( ! $real_file || ! $real_base || strpos( $real_file, $real_base ) !== 0 || ! is_readable( $real_file ) ) {
			status_header( 404 );
			wp_die( esc_html__( 'Resource not found.', 'gated-resources' ), 404 );
		}

		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: ' . $this->disposition( $disp ) . '; filename="' . sanitize_file_name( $name ) . '"' );
		header( 'Content-Length: ' . filesize( $real_file ) );
		header( 'X-Content-Type-Options: nosniff' );
		readfile( $real_file );
		exit;
	}
}
