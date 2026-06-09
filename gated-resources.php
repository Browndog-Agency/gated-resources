<?php
/**
 * Plugin Name:       Gated Resources
 * Plugin URI:        https://github.com/Browndog-Agency/gated-resources
 * Description:       Gated PDF resource library with HubSpot lead capture, Cloudflare Turnstile, and a global 30-day unlock.
 * Version:           0.1.0
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

define( 'GR_VERSION', '0.1.0' );
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
