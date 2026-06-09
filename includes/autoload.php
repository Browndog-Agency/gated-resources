<?php
/**
 * Lightweight PSR-style autoloader mapping the plugin namespace to WP-style filenames.
 * BrownDog\GatedResources\Protected_Files -> includes/class-protected-files.php
 */
spl_autoload_register(
	function ( $class ) {
		$prefix = 'BrownDog\\GatedResources\\';
		if ( strpos( $class, $prefix ) !== 0 ) {
			return;
		}
		$relative = substr( $class, strlen( $prefix ) );
		$file     = 'class-' . str_replace( '_', '-', strtolower( $relative ) ) . '.php';
		$path     = __DIR__ . '/' . $file;
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);
