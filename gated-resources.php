<?php
/**
 * Plugin Name:       Gated Resources
 * Plugin URI:        https://github.com/Browndog-Agency/gated-resources
 * Description:       Gated PDF resource library with HubSpot lead capture, Cloudflare Turnstile, and a global 30-day unlock.
 * Version:           0.2.3
 * Author:            Browndog Agency
 * Author URI:        https://browndog.agency
 * License:           GPL-2.0-or-later
 * Text Domain:       gated-resources
 * Requires PHP:      7.4
 * Requires at least: 6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GR_VERSION', '0.2.3' );
define( 'GR_FILE', __FILE__ );
define( 'GR_DIR', plugin_dir_path( __FILE__ ) );
define( 'GR_URL', plugin_dir_url( __FILE__ ) );

require_once GR_DIR . 'includes/autoload.php';

register_activation_hook( __FILE__, array( 'BrownDog\GatedResources\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'BrownDog\GatedResources\Activator', 'deactivate' ) );

add_action(
	'plugins_loaded',
	function () {
		( new \BrownDog\GatedResources\Plugin() )->boot();
	}
);

/*
 * Self-update from the public GitHub repo. Publish a GitHub Release with a
 * semver tag matching the Version header above and WordPress shows an update.
 */
require_once GR_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';

if ( class_exists( '\\YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory' ) ) {
	$gr_update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/Browndog-Agency/gated-resources/',
		GR_FILE,
		'gated-resources'
	);
	$gr_update_checker->setBranch( 'main' );

	// Prefer the CI-built gated-resources.zip release asset so updates always
	// install into the `gated-resources` folder (the auto-generated source zip
	// uses a version-suffixed folder name). Falls back to the source zip if the
	// asset is missing on a given release.
	$gr_vcs_api = $gr_update_checker->getVcsApi();
	if ( method_exists( $gr_vcs_api, 'enableReleaseAssets' ) ) {
		$gr_vcs_api->enableReleaseAssets( '/gated-resources\.zip$/' );
	}
}
