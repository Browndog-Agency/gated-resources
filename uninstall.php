<?php
/**
 * Removes all plugin data on uninstall.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// 1. Drop the unlocks table.
$table = $wpdb->prefix . 'gr_unlocks';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" );

// 2. Delete options + scheduled cron.
delete_option( 'gr_settings' );
wp_clear_scheduled_hook( 'gr_prune_unlocks' );

// 3. Remove protected + preview directories.
require_once ABSPATH . 'wp-admin/includes/file.php';
WP_Filesystem();
$up = wp_upload_dir();
foreach ( array( 'gated-resources', 'gated-resources-previews' ) as $sub ) {
	$dir = trailingslashit( $up['basedir'] ) . $sub;
	if ( is_dir( $dir ) ) {
		global $wp_filesystem;
		$wp_filesystem->rmdir( $dir, true );
	}
}

// 4. Remove resource posts + their meta.
$ids = get_posts(
	array(
		'post_type'      => 'gated_resource',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'post_status'    => 'any',
	)
);
foreach ( $ids as $id ) {
	wp_delete_post( $id, true );
}
