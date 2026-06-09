<?php
namespace BrownDog\GatedResources;

class Activator {

	public static function activate() {
		self::create_table();
		self::create_dirs();
		self::set_defaults();
		( new CPT() )->register();
		flush_rewrite_rules();
		if ( ! wp_next_scheduled( 'gr_prune_unlocks' ) ) {
			wp_schedule_event( time(), 'daily', 'gr_prune_unlocks' );
		}
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( 'gr_prune_unlocks' );
		flush_rewrite_rules();
	}

	public static function table_sql( $table, $collate ) {
		$collate_clause = $collate ? "COLLATE $collate" : '';
		return "CREATE TABLE $table (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			token CHAR(64) NOT NULL,
			email VARCHAR(255) NOT NULL,
			consent TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			expires_at DATETIME NOT NULL,
			ip_hash CHAR(64) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			UNIQUE KEY token (token),
			KEY expires_at (expires_at)
		) $collate_clause;";
	}

	public static function htaccess_contents() {
		return "# Gated Resources — block direct access\n"
			. "<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n"
			. "<IfModule !mod_authz_core.c>\n\tOrder allow,deny\n\tDeny from all\n</IfModule>\n";
	}

	private static function create_table() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = $wpdb->prefix . 'gr_unlocks';
		$collate = $wpdb->collate ?: '';
		dbDelta( self::table_sql( $table, $collate ) );
	}

	private static function create_dirs() {
		$up        = wp_upload_dir();
		$protected = trailingslashit( $up['basedir'] ) . 'gated-resources';
		$previews  = trailingslashit( $up['basedir'] ) . 'gated-resources-previews';

		wp_mkdir_p( $protected );
		wp_mkdir_p( $previews );

		$htaccess = trailingslashit( $protected ) . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			file_put_contents( $htaccess, self::htaccess_contents() );
		}
		$index = trailingslashit( $protected ) . 'index.php';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, "<?php // Silence is golden.\n" );
		}
	}

	private static function set_defaults() {
		if ( false === get_option( Settings::OPTION, false ) ) {
			add_option( Settings::OPTION, Settings::DEFAULTS );
		}
	}
}
