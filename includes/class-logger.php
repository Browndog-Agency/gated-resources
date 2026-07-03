<?php
namespace BrownDog\GatedResources;

/**
 * Debug log for form submissions, toggled from Resources > Settings and viewed
 * on the same page. Stores the most recent entries in a non-autoloaded option
 * so failed HubSpot submissions can be diagnosed without server access.
 */
class Logger {

	const OPTION      = 'gr_debug_log';
	const MAX_ENTRIES = 20;

	public static function enabled() {
		return (bool) Settings::get( 'debug_log', 0 );
	}

	/**
	 * Record an event. No-op unless debug logging is enabled in settings.
	 *
	 * @param string $event  Short event slug, e.g. "fail:hubspot".
	 * @param array  $detail Arbitrary context (error messages, response bodies).
	 */
	public static function log( $event, array $detail = array() ) {
		if ( ! self::enabled() ) {
			return;
		}
		$entries = get_option( self::OPTION, array() );
		if ( ! is_array( $entries ) ) {
			$entries = array();
		}
		array_unshift(
			$entries,
			array(
				'time'   => time(),
				'event'  => (string) $event,
				'detail' => $detail,
			)
		);
		update_option( self::OPTION, array_slice( $entries, 0, self::MAX_ENTRIES ), false );
	}

	public static function entries() {
		$entries = get_option( self::OPTION, array() );
		return is_array( $entries ) ? $entries : array();
	}

	public static function clear() {
		delete_option( self::OPTION );
	}
}
