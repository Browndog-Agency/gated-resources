<?php
namespace BrownDog\GatedResources;

class Thumbnail {

	public function imagick_available() {
		if ( ! class_exists( '\Imagick' ) ) {
			return false;
		}
		try {
			$formats = \Imagick::queryFormats( 'PDF' );
			return ! empty( $formats );
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Render page 1 of the PDF to a JPG in the public previews dir.
	 * Returns the public URL on success, or false.
	 */
	public function generate( $pdf_abs_path, $post_id ) {
		if ( ! $this->imagick_available() || ! is_readable( $pdf_abs_path ) ) {
			update_post_meta( $post_id, '_gr_preview_status', 'failed' );
			return false;
		}
		try {
			$dpi   = (int) Settings::get( 'thumb_dpi', 150 );
			$width = (int) Settings::get( 'thumb_width', 600 );

			$im = new \Imagick();
			$im->setResolution( $dpi, $dpi );
			$im->readImage( $pdf_abs_path . '[0]' );
			$im->setImageBackgroundColor( 'white' );
			$im = $im->flattenImages();
			$im->setImageFormat( 'jpeg' );
			$im->setImageCompressionQuality( 82 );
			$im->thumbnailImage( $width, 0 );

			$up      = wp_upload_dir();
			$dir     = trailingslashit( $up['basedir'] ) . 'gated-resources-previews';
			$url_dir = trailingslashit( $up['baseurl'] ) . 'gated-resources-previews';
			wp_mkdir_p( $dir );

			$filename = 'preview-' . $post_id . '-' . substr( md5( $pdf_abs_path . filemtime( $pdf_abs_path ) ), 0, 8 ) . '.jpg';
			$im->writeImage( trailingslashit( $dir ) . $filename );
			$im->clear();
			$im->destroy();

			$url = trailingslashit( $url_dir ) . $filename;
			update_post_meta( $post_id, '_gr_preview_url', $url );
			update_post_meta( $post_id, '_gr_preview_status', 'generated' );
			return $url;
		} catch ( \Exception $e ) {
			update_post_meta( $post_id, '_gr_preview_status', 'failed' );
			return false;
		}
	}

	public function cover_url( $post_id ) {
		$generated = get_post_meta( $post_id, '_gr_preview_url', true );
		if ( $generated ) {
			return $generated;
		}
		if ( has_post_thumbnail( $post_id ) ) {
			return get_the_post_thumbnail_url( $post_id, 'large' );
		}
		return GR_URL . 'assets/images/placeholder.svg';
	}
}
