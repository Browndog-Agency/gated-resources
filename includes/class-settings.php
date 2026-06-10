<?php
namespace BrownDog\GatedResources;

class Settings {

	const OPTION = 'gr_settings';

	const DEFAULTS = array(
		'hubspot_portal_id'          => '',
		'hubspot_form_guid'          => '',
		'turnstile_site_key'         => '',
		'turnstile_secret_key'       => '',
		'unlock_days'                => 30,
		'privacy_url'                => '',
		'consent_label'              => 'I’d like to receive occasional updates from Bartec Municipal.',
		'hs_consent_subscription_id' => 0,
		'thumb_width'                => 600,
		'thumb_dpi'                  => 150,
		'max_upload_mb'              => 25,
	);

	public static function get( $key, $default = null ) {
		$opts = get_option( self::OPTION, array() );
		if ( is_array( $opts ) && array_key_exists( $key, $opts ) && '' !== $opts[ $key ] && null !== $opts[ $key ] ) {
			return $opts[ $key ];
		}
		if ( null !== $default ) {
			return $default;
		}
		return self::DEFAULTS[ $key ] ?? null;
	}

	public function register() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'fields' ) );
	}

	public function menu() {
		add_submenu_page(
			'edit.php?post_type=gated_resource',
			__( 'Gated Resources Settings', 'gated-resources' ),
			__( 'Settings', 'gated-resources' ),
			'manage_options',
			'gr-settings',
			array( $this, 'render_page' )
		);
	}

	public function fields() {
		register_setting( 'gr_settings_group', self::OPTION, array( $this, 'sanitize' ) );
	}

	public function sanitize( $input ) {
		$out = array();
		$out['hubspot_portal_id']          = sanitize_text_field( $input['hubspot_portal_id'] ?? '' );
		$out['hubspot_form_guid']          = sanitize_text_field( $input['hubspot_form_guid'] ?? '' );
		$out['turnstile_site_key']         = sanitize_text_field( $input['turnstile_site_key'] ?? '' );
		$out['turnstile_secret_key']       = sanitize_text_field( $input['turnstile_secret_key'] ?? '' );
		$out['unlock_days']                = max( 1, (int) ( $input['unlock_days'] ?? 30 ) );
		$out['privacy_url']                = esc_url_raw( $input['privacy_url'] ?? '' );
		$out['consent_label']              = sanitize_text_field( $input['consent_label'] ?? '' );
		$out['hs_consent_subscription_id'] = (int) ( $input['hs_consent_subscription_id'] ?? 0 );
		$out['thumb_width']                = max( 200, (int) ( $input['thumb_width'] ?? 600 ) );
		$out['thumb_dpi']                  = max( 72, (int) ( $input['thumb_dpi'] ?? 150 ) );
		$out['max_upload_mb']              = max( 1, (int) ( $input['max_upload_mb'] ?? 25 ) );
		return $out;
	}

	public function render_page() {
		$o = get_option( self::OPTION, self::DEFAULTS );
		$f = function ( $k ) use ( $o ) { return esc_attr( $o[ $k ] ?? self::DEFAULTS[ $k ] ); };
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Gated Resources Settings', 'gated-resources' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'gr_settings_group' ); ?>
				<table class="form-table" role="presentation">
					<tr><th><?php esc_html_e( 'HubSpot Portal ID', 'gated-resources' ); ?></th>
						<td><input type="text" name="gr_settings[hubspot_portal_id]" value="<?php echo $f( 'hubspot_portal_id' ); ?>" class="regular-text"></td></tr>
					<tr><th><?php esc_html_e( 'HubSpot Form GUID', 'gated-resources' ); ?></th>
						<td><input type="text" name="gr_settings[hubspot_form_guid]" value="<?php echo $f( 'hubspot_form_guid' ); ?>" class="regular-text"></td></tr>
					<tr><th><?php esc_html_e( 'Turnstile Site Key', 'gated-resources' ); ?></th>
						<td><input type="text" name="gr_settings[turnstile_site_key]" value="<?php echo $f( 'turnstile_site_key' ); ?>" class="regular-text"></td></tr>
					<tr><th><?php esc_html_e( 'Turnstile Secret Key', 'gated-resources' ); ?></th>
						<td><input type="password" name="gr_settings[turnstile_secret_key]" value="<?php echo $f( 'turnstile_secret_key' ); ?>" class="regular-text"></td></tr>
					<tr><th><?php esc_html_e( 'Unlock Duration (days)', 'gated-resources' ); ?></th>
						<td><input type="number" min="1" name="gr_settings[unlock_days]" value="<?php echo $f( 'unlock_days' ); ?>"></td></tr>
					<tr><th><?php esc_html_e( 'Privacy Policy URL', 'gated-resources' ); ?></th>
						<td><input type="url" name="gr_settings[privacy_url]" value="<?php echo $f( 'privacy_url' ); ?>" class="regular-text"></td></tr>
					<tr><th><?php esc_html_e( 'Consent Checkbox Label', 'gated-resources' ); ?></th>
						<td><input type="text" name="gr_settings[consent_label]" value="<?php echo $f( 'consent_label' ); ?>" class="large-text"></td></tr>
					<tr><th><?php esc_html_e( 'HubSpot Consent Subscription ID', 'gated-resources' ); ?></th>
						<td><input type="number" name="gr_settings[hs_consent_subscription_id]" value="<?php echo $f( 'hs_consent_subscription_id' ); ?>"></td></tr>
					<tr><th><?php esc_html_e( 'Max Upload Size (MB)', 'gated-resources' ); ?></th>
						<td><input type="number" min="1" name="gr_settings[max_upload_mb]" value="<?php echo $f( 'max_upload_mb' ); ?>"></td></tr>
					<tr><th><?php esc_html_e( 'Thumbnail Width (px)', 'gated-resources' ); ?></th>
						<td><input type="number" min="200" name="gr_settings[thumb_width]" value="<?php echo $f( 'thumb_width' ); ?>"></td></tr>
					<tr><th><?php esc_html_e( 'Thumbnail Render DPI', 'gated-resources' ); ?></th>
						<td><input type="number" min="72" name="gr_settings[thumb_dpi]" value="<?php echo $f( 'thumb_dpi' ); ?>"></td></tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
