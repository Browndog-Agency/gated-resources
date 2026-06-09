# Gated Resources Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a self-contained WordPress plugin that gates PDF resources behind a custom form, securely sends leads to HubSpot (with Cloudflare Turnstile), and grants a global 30-day unlock — auto-updating from a public GitHub repo.

**Architecture:** Namespaced (`BrownDog\GatedResources`) plugin, one class per responsibility, loaded by a small custom autoloader. Security-critical logic (token/unlock, Turnstile, HubSpot payload, file access, form pipeline, upload validation) is factored into pure, testable methods covered by isolated PHPUnit + Brain Monkey/Mockery tests. WordPress glue (CPT, meta box, settings, templates) is implemented against those tested units. PDFs are stored outside the media library in a protected directory and streamed through a PHP endpoint after an unlock check.

**Tech Stack:** PHP 7.4+, WordPress 6.x, Composer (dev only), PHPUnit 9, Brain Monkey + Mockery (WP function mocking), Imagick + Ghostscript (PDF page-1 render, optional with fallback), Cloudflare Turnstile, HubSpot Forms Submissions API v3, plugin-update-checker (YahnisElsts).

---

## Conventions (used by every task)

- **Namespace:** `BrownDog\GatedResources`
- **Text domain:** `gated-resources`
- **Autoloader maps:** `BrownDog\GatedResources\Protected_Files` → `includes/class-protected-files.php` (class name lowercased, `_` → `-`, prefixed `class-`).
- **Settings:** single option array `gr_settings`; read via `Settings::get( $key, $default )`.
  - keys: `hubspot_portal_id`, `hubspot_form_guid`, `turnstile_site_key`, `turnstile_secret_key`, `unlock_days` (default 30), `privacy_url`, `consent_label`, `hs_consent_subscription_id`, `thumb_width` (default 600), `thumb_dpi` (default 150), `max_upload_mb` (default 25).
- **DB table:** `{$wpdb->prefix}gr_unlocks`.
- **Cookie:** `gr_access`.
- **Meta keys:** `_gr_pdf_path`, `_gr_pdf_name`, `_gr_pdf_size`, `_gr_description`, `_gr_preview_url`, `_gr_preview_status`.
- **Protected dir:** `wp_upload_dir()['basedir'] . '/gated-resources'`.
- **Previews dir (public):** `wp_upload_dir()['basedir'] . '/gated-resources-previews'`.
- **AJAX actions:** `gr_submit` (front, nopriv + priv), `gr_upload_pdf` (admin only).
- **Nonces:** `gr_form` (front), `gr_admin` (admin).
- **File endpoint:** `?gr_file={post_id}&gr_disp={inline|download}` intercepted on `template_redirect`.
- **Constants** (defined in main file): `GR_VERSION`, `GR_FILE`, `GR_DIR` (plugin dir, trailing slash), `GR_URL` (plugin url, trailing slash).

## File Structure

```
gated-resources/                     # repo root = plugin folder
  gated-resources.php                # header, constants, autoloader require, bootstrap, updater init
  uninstall.php                      # drop table, delete options, remove dirs
  composer.json                      # dev tooling only
  phpunit.xml.dist
  includes/
    autoload.php                     # spl_autoload_register for the namespace
    class-plugin.php                 # wires components to WP hooks
    class-activator.php              # dirs + .htaccess + table + defaults + flush
    class-settings.php               # Settings API page + Settings::get()
    class-cpt.php                    # register gated_resource
    class-meta-box.php               # PDF uploader UI + description + save
    class-pdf-upload.php             # AJAX upload → protected dir (+ validate)
    class-thumbnail.php              # Imagick page-1 render + fallback chain
    class-protected-files.php        # storage paths, ensure_protected(), stream()
    class-gate.php                   # unlock table, token, cookie, access checks
    class-turnstile.php              # siteverify
    class-hubspot.php                # Forms API payload + submit
    class-form.php                   # render form + process() pipeline + AJAX
    class-shortcode.php              # [gated_resources] + grid render
    class-assets.php                 # enqueue front/admin CSS+JS
  templates/
    archive-gated_resource.php
    single-gated_resource.php
    parts/card.php
    parts/gate-form.php
    parts/viewer.php
  assets/
    css/gated-resources.css
    css/admin.css
    js/gated-resources.js
    js/admin-uploader.js
    images/placeholder.svg
  lib/plugin-update-checker/         # bundled (added in Task 17)
  tests/
    bootstrap.php
    class-gr-testcase.php
    unit/                            # one file per tested unit
  docs/                              # spec + README + release notes
  readme.txt                         # WordPress-style readme (optional, for updater)
```

---

## Task 1: Dev tooling & test harness

**Files:**
- Create: `composer.json`
- Create: `phpunit.xml.dist`
- Create: `tests/bootstrap.php`
- Create: `tests/class-gr-testcase.php`
- Create: `includes/autoload.php`
- Create: `tests/unit/SanityTest.php`

- [ ] **Step 1: Create `composer.json`**

```json
{
  "name": "browndog-agency/gated-resources",
  "description": "Gated resource library for Bartec Municipal.",
  "type": "wordpress-plugin",
  "license": "GPL-2.0-or-later",
  "require": {
    "php": ">=7.4"
  },
  "require-dev": {
    "phpunit/phpunit": "^9.6",
    "brain/monkey": "^2.6",
    "mockery/mockery": "^1.6"
  },
  "scripts": {
    "test": "phpunit"
  },
  "config": {
    "allow-plugins": { "*": true }
  }
}
```

- [ ] **Step 2: Create `includes/autoload.php`**

```php
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
```

- [ ] **Step 3: Create `tests/bootstrap.php`**

```php
<?php
require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once dirname( __DIR__ ) . '/includes/autoload.php';

// Constants the plugin classes reference, stubbed for unit tests.
if ( ! defined( 'GR_VERSION' ) ) { define( 'GR_VERSION', 'test' ); }
if ( ! defined( 'GR_DIR' ) ) { define( 'GR_DIR', dirname( __DIR__ ) . '/' ); }
if ( ! defined( 'GR_URL' ) ) { define( 'GR_URL', 'https://example.test/wp-content/plugins/gated-resources/' ); }
if ( ! defined( 'DAY_IN_SECONDS' ) ) { define( 'DAY_IN_SECONDS', 86400 ); }
```

- [ ] **Step 4: Create `tests/class-gr-testcase.php`**

```php
<?php
namespace BrownDog\GatedResources\Tests;

use Brain\Monkey;
use PHPUnit\Framework\TestCase;

abstract class GR_TestCase extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}
}
```

- [ ] **Step 5: Create `phpunit.xml.dist`**

```xml
<?xml version="1.0"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/9.6/phpunit.xsd"
         bootstrap="tests/bootstrap.php"
         colors="true"
         failOnWarning="true">
  <testsuites>
    <testsuite name="unit">
      <directory suffix="Test.php">tests/unit</directory>
    </testsuite>
  </testsuites>
</phpunit>
```

- [ ] **Step 6: Create `tests/unit/SanityTest.php`**

```php
<?php
namespace BrownDog\GatedResources\Tests;

final class SanityTest extends GR_TestCase {
	public function test_harness_runs() {
		$this->assertTrue( true );
	}
}
```

- [ ] **Step 7: Install deps and run the suite**

Run: `composer install && composer test`
Expected: PHPUnit runs, `SanityTest` passes (1 test, 1 assertion, OK).

- [ ] **Step 8: Commit**

```bash
git add composer.json phpunit.xml.dist includes/autoload.php tests/
git commit -m "chore: add composer + phpunit/brain-monkey test harness"
```

---

## Task 2: Plugin main file & bootstrap

**Files:**
- Create: `gated-resources.php`
- Create: `includes/class-plugin.php`

- [ ] **Step 1: Create `gated-resources.php`**

```php
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
```

- [ ] **Step 2: Create `includes/class-plugin.php`**

```php
<?php
namespace BrownDog\GatedResources;

class Plugin {

	public function boot() {
		$settings        = new Settings();
		$gate            = new Gate();
		$protected_files = new Protected_Files();
		$thumbnail       = new Thumbnail();
		$turnstile       = new Turnstile();
		$hubspot         = new HubSpot();
		$form            = new Form( $turnstile, $hubspot, $gate );

		( new CPT() )->register();
		( new Meta_Box( $thumbnail ) )->register();
		( new PDF_Upload( $protected_files, $thumbnail ) )->register();
		( new Shortcode( $thumbnail ) )->register();
		( new Assets( $turnstile ) )->register();

		$settings->register();
		$form->register();
		$protected_files->register( $gate );
		$gate->register();

		add_filter( 'template_include', array( new Templates(), 'route' ) );
	}
}
```

Note: `Templates` is a tiny router added in Task 14; if implementing strictly in order, temporarily comment the `template_include` line until Task 14, then uncomment. (Kept here so the wiring is visible.)

- [ ] **Step 3: Verify PHP parses**

Run: `php -l gated-resources.php && php -l includes/class-plugin.php`
Expected: `No syntax errors detected` for both.

- [ ] **Step 4: Commit**

```bash
git add gated-resources.php includes/class-plugin.php
git commit -m "feat: plugin bootstrap and component wiring"
```

---

## Task 3: Settings (Settings::get + admin page)

**Files:**
- Create: `includes/class-settings.php`
- Test: `tests/unit/SettingsTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
namespace BrownDog\GatedResources\Tests;

use Brain\Monkey\Functions;
use BrownDog\GatedResources\Settings;

final class SettingsTest extends GR_TestCase {
	public function test_get_returns_stored_value() {
		Functions\when( 'get_option' )->justReturn( array( 'unlock_days' => 14 ) );
		$this->assertSame( 14, Settings::get( 'unlock_days' ) );
	}

	public function test_get_returns_default_when_missing() {
		Functions\when( 'get_option' )->justReturn( array() );
		$this->assertSame( 'fallback', Settings::get( 'nope', 'fallback' ) );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer test -- --filter SettingsTest`
Expected: FAIL — class `Settings` not found.

- [ ] **Step 3: Implement `includes/class-settings.php`**

```php
<?php
namespace BrownDog\GatedResources;

class Settings {

	const OPTION = 'gr_settings';

	const DEFAULTS = array(
		'hubspot_portal_id'         => '',
		'hubspot_form_guid'         => '',
		'turnstile_site_key'        => '',
		'turnstile_secret_key'      => '',
		'unlock_days'               => 30,
		'privacy_url'               => '',
		'consent_label'             => 'I’d like to receive occasional updates from Bartec Municipal.',
		'hs_consent_subscription_id'=> 0,
		'thumb_width'               => 600,
		'thumb_dpi'                 => 150,
		'max_upload_mb'             => 25,
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
```

- [ ] **Step 4: Run test to verify it passes**

Run: `composer test -- --filter SettingsTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add includes/class-settings.php tests/unit/SettingsTest.php
git commit -m "feat: settings store and admin settings page"
```

---

## Task 4: Gate — token, unlock table, cookie, access checks

**Files:**
- Create: `includes/class-gate.php`
- Test: `tests/unit/GateTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
namespace BrownDog\GatedResources\Tests;

use Brain\Monkey\Functions;
use BrownDog\GatedResources\Gate;

final class GateTest extends GR_TestCase {

	public function test_generate_token_is_64_hex_chars() {
		$token = ( new Gate() )->generate_token();
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{64}$/', $token );
	}

	public function test_is_valid_token_rejects_non_hex() {
		$gate = new Gate();
		$this->assertFalse( $gate->is_valid_token( 'not-a-token!' ) );
		$this->assertFalse( $gate->is_valid_token( '' ) );
	}

	public function test_is_valid_token_true_for_future_expiry() {
		$wpdb         = \Mockery::mock();
		$wpdb->prefix = 'wp_';
		$wpdb->shouldReceive( 'prepare' )->andReturn( 'SQL' );
		$wpdb->shouldReceive( 'get_row' )->andReturn(
			(object) array( 'id' => 1, 'expires_at' => gmdate( 'Y-m-d H:i:s', time() + 1000 ) )
		);
		$GLOBALS['wpdb'] = $wpdb;

		$this->assertTrue( ( new Gate() )->is_valid_token( str_repeat( 'a', 64 ) ) );
	}

	public function test_is_valid_token_false_for_past_expiry() {
		$wpdb         = \Mockery::mock();
		$wpdb->prefix = 'wp_';
		$wpdb->shouldReceive( 'prepare' )->andReturn( 'SQL' );
		$wpdb->shouldReceive( 'get_row' )->andReturn(
			(object) array( 'id' => 1, 'expires_at' => gmdate( 'Y-m-d H:i:s', time() - 1000 ) )
		);
		$GLOBALS['wpdb'] = $wpdb;

		$this->assertFalse( ( new Gate() )->is_valid_token( str_repeat( 'b', 64 ) ) );
	}

	public function test_is_valid_token_false_when_no_row() {
		$wpdb         = \Mockery::mock();
		$wpdb->prefix = 'wp_';
		$wpdb->shouldReceive( 'prepare' )->andReturn( 'SQL' );
		$wpdb->shouldReceive( 'get_row' )->andReturn( null );
		$GLOBALS['wpdb'] = $wpdb;

		$this->assertFalse( ( new Gate() )->is_valid_token( str_repeat( 'c', 64 ) ) );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer test -- --filter GateTest`
Expected: FAIL — class `Gate` not found.

- [ ] **Step 3: Implement `includes/class-gate.php`**

```php
<?php
namespace BrownDog\GatedResources;

class Gate {

	const COOKIE = 'gr_access';

	public function register() {
		add_action( 'gr_prune_unlocks', array( $this, 'prune_expired' ) );
		add_action( 'init', array( $this, 'maybe_schedule_cron' ) );
	}

	public function maybe_schedule_cron() {
		if ( ! wp_next_scheduled( 'gr_prune_unlocks' ) ) {
			wp_schedule_event( time(), 'daily', 'gr_prune_unlocks' );
		}
	}

	public function table() {
		global $wpdb;
		return $wpdb->prefix . 'gr_unlocks';
	}

	public function generate_token() {
		return bin2hex( random_bytes( 32 ) );
	}

	public function unlock_days() {
		return (int) Settings::get( 'unlock_days', 30 );
	}

	public function create_unlock( $email, $consent, $ip = '' ) {
		global $wpdb;
		$token   = $this->generate_token();
		$now     = time();
		$expires = $now + ( $this->unlock_days() * DAY_IN_SECONDS );
		$wpdb->insert(
			$this->table(),
			array(
				'token'      => $token,
				'email'      => $email,
				'consent'    => $consent ? 1 : 0,
				'created_at' => gmdate( 'Y-m-d H:i:s', $now ),
				'expires_at' => gmdate( 'Y-m-d H:i:s', $expires ),
				'ip_hash'    => $ip ? hash( 'sha256', $ip ) : '',
			)
		);
		return array( 'token' => $token, 'expires' => $expires );
	}

	public function is_valid_token( $token ) {
		if ( empty( $token ) || ! ctype_xdigit( $token ) || strlen( $token ) !== 64 ) {
			return false;
		}
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT id, expires_at FROM {$this->table()} WHERE token = %s LIMIT 1", $token )
		);
		if ( ! $row ) {
			return false;
		}
		return strtotime( $row->expires_at . ' UTC' ) > time();
	}

	public function is_unlocked() {
		$token = isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : '';
		return $this->is_valid_token( $token );
	}

	public function set_cookie( $token, $expires ) {
		setcookie(
			self::COOKIE,
			$token,
			array(
				'expires'  => $expires,
				'path'     => defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
		$_COOKIE[ self::COOKIE ] = $token;
	}

	public function prune_expired() {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$this->table()} WHERE expires_at < UTC_TIMESTAMP()" );
	}
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `composer test -- --filter GateTest`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add includes/class-gate.php tests/unit/GateTest.php
git commit -m "feat: gate token/unlock table and access checks"
```

---

## Task 5: Activator — dirs, .htaccess, DB table, defaults, flush

**Files:**
- Create: `includes/class-activator.php`
- Test: `tests/unit/ActivatorTest.php`

- [ ] **Step 1: Write the failing test (pure SQL/path helpers)**

```php
<?php
namespace BrownDog\GatedResources\Tests;

use BrownDog\GatedResources\Activator;

final class ActivatorTest extends GR_TestCase {

	public function test_table_sql_contains_expected_columns() {
		$sql = Activator::table_sql( 'wp_gr_unlocks', 'utf8mb4_unicode_520_ci' );
		$this->assertStringContainsString( 'CREATE TABLE wp_gr_unlocks', $sql );
		foreach ( array( 'token', 'email', 'consent', 'created_at', 'expires_at', 'ip_hash' ) as $col ) {
			$this->assertStringContainsString( $col, $sql );
		}
		$this->assertStringContainsString( 'UNIQUE KEY token', $sql );
	}

	public function test_htaccess_denies_all() {
		$contents = Activator::htaccess_contents();
		$this->assertStringContainsString( 'Require all denied', $contents );
		$this->assertStringContainsString( 'Deny from all', $contents );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer test -- --filter ActivatorTest`
Expected: FAIL — class `Activator` not found.

- [ ] **Step 3: Implement `includes/class-activator.php`**

```php
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
```

- [ ] **Step 4: Run test to verify it passes**

Run: `composer test -- --filter ActivatorTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add includes/class-activator.php tests/unit/ActivatorTest.php
git commit -m "feat: activator creates table, protected dirs, htaccess, defaults"
```

---

## Task 6: Turnstile verification

**Files:**
- Create: `includes/class-turnstile.php`
- Test: `tests/unit/TurnstileTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
namespace BrownDog\GatedResources\Tests;

use Brain\Monkey\Functions;
use BrownDog\GatedResources\Turnstile;

final class TurnstileTest extends GR_TestCase {

	public function test_empty_token_fails_without_request() {
		$this->assertFalse( ( new Turnstile() )->verify( '' ) );
	}

	public function test_success_response_passes() {
		Functions\when( 'get_option' )->justReturn( array( 'turnstile_secret_key' => 'sec' ) );
		Functions\when( 'wp_remote_post' )->justReturn( array( 'body' => '{"success":true}' ) );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( '{"success":true}' );

		$this->assertTrue( ( new Turnstile() )->verify( 'token', '1.2.3.4' ) );
	}

	public function test_failure_response_fails() {
		Functions\when( 'get_option' )->justReturn( array( 'turnstile_secret_key' => 'sec' ) );
		Functions\when( 'wp_remote_post' )->justReturn( array( 'body' => '{"success":false}' ) );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( '{"success":false,"error-codes":["invalid-input-response"]}' );

		$this->assertFalse( ( new Turnstile() )->verify( 'token' ) );
	}

	public function test_wp_error_fails() {
		Functions\when( 'get_option' )->justReturn( array( 'turnstile_secret_key' => 'sec' ) );
		Functions\when( 'wp_remote_post' )->justReturn( 'err' );
		Functions\when( 'is_wp_error' )->justReturn( true );

		$this->assertFalse( ( new Turnstile() )->verify( 'token' ) );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer test -- --filter TurnstileTest`
Expected: FAIL — class `Turnstile` not found.

- [ ] **Step 3: Implement `includes/class-turnstile.php`**

```php
<?php
namespace BrownDog\GatedResources;

class Turnstile {

	const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

	public function is_configured() {
		return (bool) Settings::get( 'turnstile_secret_key' ) && (bool) Settings::get( 'turnstile_site_key' );
	}

	public function site_key() {
		return Settings::get( 'turnstile_site_key' );
	}

	public function verify( $token, $ip = '' ) {
		if ( empty( $token ) ) {
			return false;
		}
		$resp = wp_remote_post(
			self::VERIFY_URL,
			array(
				'timeout' => 10,
				'body'    => array(
					'secret'   => Settings::get( 'turnstile_secret_key' ),
					'response' => $token,
					'remoteip' => $ip,
				),
			)
		);
		if ( is_wp_error( $resp ) ) {
			return false;
		}
		$body = json_decode( wp_remote_retrieve_body( $resp ), true );
		return ! empty( $body['success'] );
	}
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `composer test -- --filter TurnstileTest`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add includes/class-turnstile.php tests/unit/TurnstileTest.php
git commit -m "feat: cloudflare turnstile server-side verification"
```

---

## Task 7: HubSpot Forms API client

**Files:**
- Create: `includes/class-hubspot.php`
- Test: `tests/unit/HubSpotTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
namespace BrownDog\GatedResources\Tests;

use Brain\Monkey\Functions;
use BrownDog\GatedResources\HubSpot;

final class HubSpotTest extends GR_TestCase {

	public function test_payload_maps_fields() {
		Functions\when( 'get_option' )->justReturn( array() );
		$payload = ( new HubSpot() )->build_payload(
			array( 'firstname' => 'Ann', 'email' => 'a@b.com', 'company' => 'Council' ),
			false
		);
		$names = array_column( $payload['fields'], 'name' );
		$this->assertContains( 'firstname', $names );
		$this->assertContains( 'email', $names );
		$this->assertContains( 'company', $names );
		$this->assertArrayNotHasKey( 'legalConsentOptions', $payload );
	}

	public function test_payload_includes_consent_when_true() {
		Functions\when( 'get_option' )->justReturn( array( 'hs_consent_subscription_id' => 7, 'consent_label' => 'Yes please' ) );
		$payload = ( new HubSpot() )->build_payload( array( 'email' => 'a@b.com' ), true );
		$this->assertArrayHasKey( 'legalConsentOptions', $payload );
		$this->assertTrue( $payload['legalConsentOptions']['consent']['consentToProcess'] );
		$this->assertSame( 7, $payload['legalConsentOptions']['consent']['communications'][0]['subscriptionTypeId'] );
	}

	public function test_payload_includes_context_when_present() {
		Functions\when( 'get_option' )->justReturn( array() );
		$payload = ( new HubSpot() )->build_payload(
			array( 'email' => 'a@b.com' ),
			false,
			array( 'pageUri' => 'https://x/y', 'hutk' => 'abc' )
		);
		$this->assertSame( 'https://x/y', $payload['context']['pageUri'] );
		$this->assertSame( 'abc', $payload['context']['hutk'] );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer test -- --filter HubSpotTest`
Expected: FAIL — class `HubSpot` not found.

- [ ] **Step 3: Implement `includes/class-hubspot.php`**

```php
<?php
namespace BrownDog\GatedResources;

class HubSpot {

	const ENDPOINT = 'https://api.hsforms.com/submissions/v3/integration/submit/%s/%s';

	public function build_payload( array $fields, $consent, array $context = array() ) {
		$hs_fields = array();
		foreach ( $fields as $name => $value ) {
			if ( '' === $value || null === $value ) {
				continue;
			}
			$hs_fields[] = array(
				'name'  => $name,
				'value' => (string) $value,
			);
		}

		$payload = array( 'fields' => $hs_fields );

		$ctx = array_filter(
			array(
				'hutk'     => $context['hutk'] ?? '',
				'pageUri'  => $context['pageUri'] ?? '',
				'pageName' => $context['pageName'] ?? '',
			)
		);
		if ( $ctx ) {
			$payload['context'] = $ctx;
		}

		if ( $consent ) {
			$label  = Settings::get( 'consent_label', 'I agree to be contacted.' );
			$sub_id = (int) Settings::get( 'hs_consent_subscription_id', 0 );
			$payload['legalConsentOptions'] = array(
				'consent' => array(
					'consentToProcess' => true,
					'text'             => $label,
					'communications'   => array(
						array(
							'value'              => true,
							'subscriptionTypeId' => $sub_id,
							'text'               => $label,
						),
					),
				),
			);
		}

		return $payload;
	}

	public function submit( array $fields, $consent, array $context = array() ) {
		$portal = Settings::get( 'hubspot_portal_id' );
		$guid   = Settings::get( 'hubspot_form_guid' );
		if ( ! $portal || ! $guid ) {
			return new \WP_Error( 'gr_hs_config', __( 'HubSpot is not configured.', 'gated-resources' ) );
		}

		$url  = sprintf( self::ENDPOINT, rawurlencode( $portal ), rawurlencode( $guid ) );
		$resp = wp_remote_post(
			$url,
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $this->build_payload( $fields, $consent, $context ) ),
			)
		);

		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$code = (int) wp_remote_retrieve_response_code( $resp );
		if ( $code < 200 || $code >= 300 ) {
			return new \WP_Error(
				'gr_hs_http',
				__( 'HubSpot rejected the submission.', 'gated-resources' ),
				array( 'status' => $code, 'body' => wp_remote_retrieve_body( $resp ) )
			);
		}
		return true;
	}
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `composer test -- --filter HubSpotTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add includes/class-hubspot.php tests/unit/HubSpotTest.php
git commit -m "feat: hubspot forms api payload builder and submit"
```

---

## Task 8: Thumbnail — Imagick render + fallback chain

**Files:**
- Create: `includes/class-thumbnail.php`
- Create: `assets/images/placeholder.svg`
- Test: `tests/unit/ThumbnailTest.php`

- [ ] **Step 1: Write the failing test (fallback resolution)**

```php
<?php
namespace BrownDog\GatedResources\Tests;

use Brain\Monkey\Functions;
use BrownDog\GatedResources\Thumbnail;

final class ThumbnailTest extends GR_TestCase {

	public function test_cover_url_prefers_generated_preview() {
		Functions\when( 'get_post_meta' )->justReturn( 'https://x/p.jpg' );
		$this->assertSame( 'https://x/p.jpg', ( new Thumbnail() )->cover_url( 1 ) );
	}

	public function test_cover_url_falls_back_to_featured_image() {
		Functions\when( 'get_post_meta' )->justReturn( '' );
		Functions\when( 'has_post_thumbnail' )->justReturn( true );
		Functions\when( 'get_the_post_thumbnail_url' )->justReturn( 'https://x/feat.jpg' );
		$this->assertSame( 'https://x/feat.jpg', ( new Thumbnail() )->cover_url( 1 ) );
	}

	public function test_cover_url_falls_back_to_placeholder() {
		Functions\when( 'get_post_meta' )->justReturn( '' );
		Functions\when( 'has_post_thumbnail' )->justReturn( false );
		$this->assertStringContainsString( 'placeholder.svg', ( new Thumbnail() )->cover_url( 1 ) );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer test -- --filter ThumbnailTest`
Expected: FAIL — class `Thumbnail` not found.

- [ ] **Step 3: Implement `includes/class-thumbnail.php`**

```php
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
```

- [ ] **Step 4: Create `assets/images/placeholder.svg`**

```svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 800" role="img" aria-label="Resource">
  <rect width="600" height="800" fill="#ECEAF6"/>
  <rect x="170" y="250" width="260" height="320" rx="10" fill="#FFFFFF" stroke="#2D1B69" stroke-width="6"/>
  <rect x="200" y="300" width="200" height="18" rx="6" fill="#2D1B69"/>
  <rect x="200" y="340" width="160" height="14" rx="6" fill="#C9C3E6"/>
  <rect x="200" y="370" width="180" height="14" rx="6" fill="#C9C3E6"/>
  <rect x="200" y="520" width="120" height="10" rx="5" fill="#16C098"/>
</svg>
```

- [ ] **Step 5: Run test to verify it passes**

Run: `composer test -- --filter ThumbnailTest`
Expected: PASS (3 tests).

- [ ] **Step 6: Commit**

```bash
git add includes/class-thumbnail.php assets/images/placeholder.svg tests/unit/ThumbnailTest.php
git commit -m "feat: pdf page-1 thumbnail with featured-image/placeholder fallback"
```

---

## Task 9: Protected files — storage paths, disposition, ensure, stream

**Files:**
- Create: `includes/class-protected-files.php`
- Test: `tests/unit/ProtectedFilesTest.php`

- [ ] **Step 1: Write the failing test (pure helpers)**

```php
<?php
namespace BrownDog\GatedResources\Tests;

use Brain\Monkey\Functions;
use BrownDog\GatedResources\Protected_Files;

final class ProtectedFilesTest extends GR_TestCase {

	public function test_disposition_maps_download_to_attachment() {
		$pf = new Protected_Files();
		$this->assertSame( 'attachment', $pf->disposition( 'download' ) );
		$this->assertSame( 'inline', $pf->disposition( 'inline' ) );
		$this->assertSame( 'inline', $pf->disposition( 'anything-else' ) );
	}

	public function test_relative_path_format_is_hashed_and_sanitised() {
		Functions\when( 'sanitize_file_name' )->alias( function ( $n ) { return $n; } );
		$rel = Protected_Files::build_relative_path( 'report final.pdf', 'aabbccddeeff00112233445566778899' );
		// aa/aabbcc.../report final.pdf
		$this->assertMatchesRegularExpression( '#^[0-9a-f]{2}/[0-9a-f]{32}/report final\.pdf$#', $rel );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer test -- --filter ProtectedFilesTest`
Expected: FAIL — class `Protected_Files` not found.

- [ ] **Step 3: Implement `includes/class-protected-files.php`**

```php
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
```

- [ ] **Step 4: Run test to verify it passes**

Run: `composer test -- --filter ProtectedFilesTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add includes/class-protected-files.php tests/unit/ProtectedFilesTest.php
git commit -m "feat: protected file storage + gated streaming endpoint"
```

---

## Task 10: CPT registration

**Files:**
- Create: `includes/class-cpt.php`
- Test: `tests/unit/CPTTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
namespace BrownDog\GatedResources\Tests;

use Brain\Monkey\Functions;
use BrownDog\GatedResources\CPT;

final class CPTTest extends GR_TestCase {

	public function test_register_calls_register_post_type_with_archive_and_slug() {
		Functions\when( '__' )->returnArg( 1 );
		$captured = array();
		Functions\when( 'register_post_type' )->alias(
			function ( $slug, $args ) use ( &$captured ) {
				$captured = array( 'slug' => $slug, 'args' => $args );
			}
		);

		( new CPT() )->register_post_type();

		$this->assertSame( 'gated_resource', $captured['slug'] );
		$this->assertTrue( $captured['args']['has_archive'] );
		$this->assertSame( 'resources', $captured['args']['rewrite']['slug'] );
		$this->assertContains( 'thumbnail', $captured['args']['supports'] );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer test -- --filter CPTTest`
Expected: FAIL — class `CPT` not found.

- [ ] **Step 3: Implement `includes/class-cpt.php`**

```php
<?php
namespace BrownDog\GatedResources;

class CPT {

	const SLUG = 'gated_resource';

	public function register() {
		add_action( 'init', array( $this, 'register_post_type' ) );
	}

	public function register_post_type() {
		$labels = array(
			'name'          => __( 'Resources', 'gated-resources' ),
			'singular_name' => __( 'Resource', 'gated-resources' ),
			'add_new_item'  => __( 'Add New Resource', 'gated-resources' ),
			'edit_item'     => __( 'Edit Resource', 'gated-resources' ),
			'menu_name'     => __( 'Resources', 'gated-resources' ),
		);

		register_post_type(
			self::SLUG,
			array(
				'labels'       => $labels,
				'public'       => true,
				'has_archive'  => true,
				'menu_icon'    => 'dashicons-media-document',
				'rewrite'      => array( 'slug' => 'resources' ),
				'supports'     => array( 'title', 'thumbnail' ),
				'show_in_rest' => true,
			)
		);
	}
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `composer test -- --filter CPTTest`
Expected: PASS (1 test).

- [ ] **Step 5: Commit**

```bash
git add includes/class-cpt.php tests/unit/CPTTest.php
git commit -m "feat: register gated_resource custom post type"
```

---

## Task 11: PDF upload — validation + AJAX handler

**Files:**
- Create: `includes/class-pdf-upload.php`
- Test: `tests/unit/PdfUploadTest.php`

- [ ] **Step 1: Write the failing test (validation logic)**

```php
<?php
namespace BrownDog\GatedResources\Tests;

use Brain\Monkey\Functions;
use BrownDog\GatedResources\PDF_Upload;
use BrownDog\GatedResources\Protected_Files;
use BrownDog\GatedResources\Thumbnail;

final class PdfUploadTest extends GR_TestCase {

	private function make() {
		return new PDF_Upload( new Protected_Files(), new Thumbnail() );
	}

	public function test_rejects_non_pdf_extension() {
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'is_wp_error' )->justReturn( true );
		$res = $this->make()->validate_file( array( 'name' => 'evil.exe', 'tmp_name' => '/tmp/x', 'size' => 10, 'error' => 0 ) );
		$this->assertInstanceOf( \WP_Error::class, $res );
	}

	public function test_rejects_oversize() {
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'get_option' )->justReturn( array( 'max_upload_mb' => 1 ) );
		Functions\when( 'wp_check_filetype_and_ext' )->justReturn( array( 'ext' => 'pdf', 'type' => 'application/pdf' ) );
		$res = $this->make()->validate_file( array( 'name' => 'big.pdf', 'tmp_name' => '/tmp/x', 'size' => 5 * 1024 * 1024, 'error' => 0 ) );
		$this->assertInstanceOf( \WP_Error::class, $res );
	}

	public function test_accepts_valid_pdf() {
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'get_option' )->justReturn( array( 'max_upload_mb' => 25 ) );
		Functions\when( 'wp_check_filetype_and_ext' )->justReturn( array( 'ext' => 'pdf', 'type' => 'application/pdf' ) );
		$res = $this->make()->validate_file( array( 'name' => 'ok.pdf', 'tmp_name' => '/tmp/x', 'size' => 1000, 'error' => 0 ) );
		$this->assertTrue( $res );
	}
}
```

Note: define a minimal `WP_Error` stand-in in `tests/bootstrap.php` if not already present (see Step 4).

- [ ] **Step 2: Run test to verify it fails**

Run: `composer test -- --filter PdfUploadTest`
Expected: FAIL — class `PDF_Upload` / `WP_Error` not found.

- [ ] **Step 3: Add a `WP_Error` stub to `tests/bootstrap.php`**

Append to `tests/bootstrap.php`:

```php
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public $code;
		public $message;
		public $data;
		public function __construct( $code = '', $message = '', $data = '' ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}
		public function get_error_message() { return $this->message; }
		public function get_error_code() { return $this->code; }
	}
}
```

- [ ] **Step 4: Implement `includes/class-pdf-upload.php`**

```php
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
		}
		$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		$file    = isset( $_FILES['file'] ) ? $_FILES['file'] : array();

		$valid = $this->validate_file( (array) $file );
		if ( is_wp_error( $valid ) ) {
			wp_send_json_error( array( 'message' => $valid->get_error_message() ), 400 );
		}

		$relative = $this->files->store( $file['tmp_name'], $file['name'] );
		if ( is_wp_error( $relative ) ) {
			wp_send_json_error( array( 'message' => $relative->get_error_message() ), 500 );
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
```

- [ ] **Step 5: Run test to verify it passes**

Run: `composer test -- --filter PdfUploadTest`
Expected: PASS (3 tests).

- [ ] **Step 6: Commit**

```bash
git add includes/class-pdf-upload.php tests/bootstrap.php tests/unit/PdfUploadTest.php
git commit -m "feat: admin pdf upload validation + ajax handler to protected dir"
```

---

## Task 12: Meta box (admin UI + save) + admin uploader JS

**Files:**
- Create: `includes/class-meta-box.php`
- Create: `assets/js/admin-uploader.js`
- Create: `assets/css/admin.css`

- [ ] **Step 1: Implement `includes/class-meta-box.php`**

```php
<?php
namespace BrownDog\GatedResources;

class Meta_Box {

	private $thumbnail;

	public function __construct( Thumbnail $thumbnail ) {
		$this->thumbnail = $thumbnail;
	}

	public function register() {
		add_action( 'add_meta_boxes', array( $this, 'add' ) );
		add_action( 'save_post_' . CPT::SLUG, array( $this, 'save' ), 10, 2 );
	}

	public function add() {
		add_meta_box(
			'gr_resource_details',
			__( 'Resource Details', 'gated-resources' ),
			array( $this, 'render' ),
			CPT::SLUG,
			'normal',
			'high'
		);
	}

	public function render( $post ) {
		wp_nonce_field( 'gr_save_meta', 'gr_meta_nonce' );
		$desc   = get_post_meta( $post->ID, '_gr_description', true );
		$name   = get_post_meta( $post->ID, '_gr_pdf_name', true );
		$status = get_post_meta( $post->ID, '_gr_preview_status', true );
		?>
		<p>
			<label for="gr_description"><strong><?php esc_html_e( 'Resource Description (optional)', 'gated-resources' ); ?></strong></label>
			<textarea id="gr_description" name="gr_description" rows="4" class="large-text"><?php echo esc_textarea( $desc ); ?></textarea>
		</p>
		<p><strong><?php esc_html_e( 'Resource PDF', 'gated-resources' ); ?></strong></p>
		<div id="gr-upload" data-post="<?php echo (int) $post->ID; ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'gr_admin' ) ); ?>">
			<input type="file" id="gr-pdf-file" accept="application/pdf">
			<button type="button" class="button" id="gr-pdf-upload-btn"><?php esc_html_e( 'Upload PDF', 'gated-resources' ); ?></button>
			<span id="gr-upload-status" class="gr-upload-status">
				<?php echo $name ? esc_html( sprintf( __( 'Current: %s', 'gated-resources' ), $name ) ) : esc_html__( 'No PDF uploaded yet.', 'gated-resources' ); ?>
			</span>
		</div>
		<p class="description">
			<?php
			if ( 'generated' === $status ) {
				esc_html_e( 'Cover thumbnail generated from page 1 of the PDF.', 'gated-resources' );
			} elseif ( 'failed' === $status ) {
				esc_html_e( 'Could not render the PDF cover (Imagick/Ghostscript unavailable). The featured image or a placeholder will be used.', 'gated-resources' );
			} else {
				esc_html_e( 'A cover will be generated from page 1 of the PDF, or the featured image if rendering is unavailable.', 'gated-resources' );
			}
			?>
		</p>
		<?php
	}

	public function save( $post_id, $post ) {
		if ( ! isset( $_POST['gr_meta_nonce'] ) || ! wp_verify_nonce( $_POST['gr_meta_nonce'], 'gr_save_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$desc = isset( $_POST['gr_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['gr_description'] ) ) : '';
		update_post_meta( $post_id, '_gr_description', $desc );
		// The PDF itself is saved asynchronously by the uploader (Task 11), keyed to this post_id.
	}
}
```

- [ ] **Step 2: Implement `assets/js/admin-uploader.js`**

```js
(function () {
	var root = document.getElementById('gr-upload');
	if (!root) { return; }
	var btn = document.getElementById('gr-pdf-upload-btn');
	var input = document.getElementById('gr-pdf-file');
	var status = document.getElementById('gr-upload-status');

	btn.addEventListener('click', function () {
		if (!input.files.length) { status.textContent = GR_Admin.i18n.choose; return; }
		var fd = new FormData();
		fd.append('action', 'gr_upload_pdf');
		fd.append('nonce', root.getAttribute('data-nonce'));
		fd.append('post_id', root.getAttribute('data-post'));
		fd.append('file', input.files[0]);

		status.textContent = GR_Admin.i18n.uploading;
		btn.disabled = true;

		fetch(GR_Admin.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				btn.disabled = false;
				if (res && res.success) {
					status.textContent = GR_Admin.i18n.done + ' ' + res.data.name;
				} else {
					status.textContent = (res && res.data && res.data.message) ? res.data.message : GR_Admin.i18n.error;
				}
			})
			.catch(function () { btn.disabled = false; status.textContent = GR_Admin.i18n.error; });
	});
})();
```

- [ ] **Step 3: Implement `assets/css/admin.css`**

```css
#gr-upload { padding: 8px 0; }
#gr-upload .gr-upload-status { display: inline-block; margin-left: 10px; color: #50575e; }
#gr-pdf-upload-btn { margin-left: 6px; }
```

- [ ] **Step 4: Verify PHP parses**

Run: `php -l includes/class-meta-box.php`
Expected: `No syntax errors detected`.

- [ ] **Step 5: Commit**

```bash
git add includes/class-meta-box.php assets/js/admin-uploader.js assets/css/admin.css
git commit -m "feat: resource meta box with async pdf uploader"
```

---

## Task 13: Form — render, process() pipeline, AJAX handler

**Files:**
- Create: `includes/class-form.php`
- Create: `templates/parts/gate-form.php`
- Test: `tests/unit/FormProcessTest.php`

- [ ] **Step 1: Write the failing test (pipeline orchestration via mocks)**

```php
<?php
namespace BrownDog\GatedResources\Tests;

use Brain\Monkey\Functions;
use BrownDog\GatedResources\Form;

final class FormProcessTest extends GR_TestCase {

	private function input( $overrides = array() ) {
		return array_merge(
			array(
				'hp'        => '',
				'ip'        => '1.2.3.4',
				'turnstile' => 'tok',
				'firstname' => 'Ann',
				'lastname'  => 'Jones',
				'email'     => 'ann@council.gov',
				'company'   => 'Council',
				'jobtitle'  => 'Officer',
				'consent'   => false,
				'context'   => array(),
			),
			$overrides
		);
	}

	public function test_honeypot_filled_fails() {
		Functions\when( '__' )->returnArg( 1 );
		$form = new Form( \Mockery::mock(), \Mockery::mock(), \Mockery::mock() );
		$res  = $form->process( $this->input( array( 'hp' => 'bot' ) ) );
		$this->assertFalse( $res['ok'] );
		$this->assertSame( 'spam', $res['code'] );
	}

	public function test_turnstile_failure_blocks_hubspot() {
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'is_email' )->justReturn( true );
		$turnstile = \Mockery::mock();
		$turnstile->shouldReceive( 'verify' )->andReturn( false );
		$hubspot = \Mockery::mock();
		$hubspot->shouldNotReceive( 'submit' );
		$gate = \Mockery::mock();

		$res = ( new Form( $turnstile, $hubspot, $gate ) )->process( $this->input() );
		$this->assertFalse( $res['ok'] );
		$this->assertSame( 'captcha', $res['code'] );
	}

	public function test_invalid_email_fails_validation() {
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'is_email' )->justReturn( false );
		$turnstile = \Mockery::mock();
		$turnstile->shouldReceive( 'verify' )->andReturn( true );

		$res = ( new Form( $turnstile, \Mockery::mock(), \Mockery::mock() ) )
			->process( $this->input( array( 'email' => 'nope' ) ) );
		$this->assertFalse( $res['ok'] );
		$this->assertSame( 'invalid', $res['code'] );
	}

	public function test_hubspot_error_does_not_unlock() {
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'is_email' )->justReturn( true );
		Functions\when( 'is_wp_error' )->justReturn( true );
		$turnstile = \Mockery::mock();
		$turnstile->shouldReceive( 'verify' )->andReturn( true );
		$hubspot = \Mockery::mock();
		$hubspot->shouldReceive( 'submit' )->andReturn( new \WP_Error( 'x', 'fail' ) );
		$gate = \Mockery::mock();
		$gate->shouldNotReceive( 'create_unlock' );

		$res = ( new Form( $turnstile, $hubspot, $gate ) )->process( $this->input() );
		$this->assertFalse( $res['ok'] );
		$this->assertSame( 'hubspot', $res['code'] );
	}

	public function test_happy_path_creates_unlock() {
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'is_email' )->justReturn( true );
		Functions\when( 'is_wp_error' )->justReturn( false );
		$turnstile = \Mockery::mock();
		$turnstile->shouldReceive( 'verify' )->andReturn( true );
		$hubspot = \Mockery::mock();
		$hubspot->shouldReceive( 'submit' )->andReturn( true );
		$gate = \Mockery::mock();
		$gate->shouldReceive( 'create_unlock' )->once()->andReturn( array( 'token' => 't', 'expires' => 999 ) );

		$res = ( new Form( $turnstile, $hubspot, $gate ) )->process( $this->input() );
		$this->assertTrue( $res['ok'] );
		$this->assertSame( 't', $res['unlock']['token'] );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer test -- --filter FormProcessTest`
Expected: FAIL — class `Form` not found.

- [ ] **Step 3: Implement `includes/class-form.php`**

```php
<?php
namespace BrownDog\GatedResources;

class Form {

	private $turnstile;
	private $hubspot;
	private $gate;

	public function __construct( Turnstile $turnstile = null, HubSpot $hubspot = null, Gate $gate = null ) {
		$this->turnstile = $turnstile;
		$this->hubspot   = $hubspot;
		$this->gate      = $gate;
	}

	public function register() {
		add_action( 'wp_ajax_gr_submit', array( $this, 'handle' ) );
		add_action( 'wp_ajax_nopriv_gr_submit', array( $this, 'handle' ) );
	}

	private function fail( $code, $errors = array() ) {
		return array( 'ok' => false, 'code' => $code, 'errors' => $errors );
	}

	private function is_rate_limited( $ip ) {
		if ( ! $ip ) {
			return false;
		}
		$key   = 'gr_rl_' . md5( $ip );
		$count = (int) get_transient( $key );
		if ( $count >= 10 ) {
			return true;
		}
		set_transient( $key, $count + 1, 10 * MINUTE_IN_SECONDS );
		return false;
	}

	public function validate( array $in ) {
		$errors = array();
		if ( empty( $in['firstname'] ) ) {
			$errors['firstname'] = __( 'Please enter your first name.', 'gated-resources' );
		}
		if ( empty( $in['lastname'] ) ) {
			$errors['lastname'] = __( 'Please enter your last name.', 'gated-resources' );
		}
		if ( empty( $in['email'] ) || ! is_email( $in['email'] ) ) {
			$errors['email'] = __( 'Please enter a valid email address.', 'gated-resources' );
		}
		if ( empty( $in['company'] ) ) {
			$errors['company'] = __( 'Please enter your organisation.', 'gated-resources' );
		}
		if ( empty( $in['jobtitle'] ) ) {
			$errors['jobtitle'] = __( 'Please enter your job title.', 'gated-resources' );
		}
		return $errors;
	}

	/**
	 * Pure pipeline. Returns ['ok'=>bool, ...]. Does NOT emit output.
	 */
	public function process( array $in ) {
		if ( ! empty( $in['hp'] ) ) {
			return $this->fail( 'spam' );
		}
		if ( $this->is_rate_limited( $in['ip'] ?? '' ) ) {
			return $this->fail( 'rate' );
		}
		if ( ! $this->turnstile->verify( $in['turnstile'] ?? '', $in['ip'] ?? '' ) ) {
			return $this->fail( 'captcha' );
		}
		$errors = $this->validate( $in );
		if ( $errors ) {
			return $this->fail( 'invalid', $errors );
		}

		$fields = array(
			'firstname' => $in['firstname'],
			'lastname'  => $in['lastname'],
			'email'     => $in['email'],
			'company'   => $in['company'],
			'jobtitle'  => $in['jobtitle'],
		);
		$result = $this->hubspot->submit( $fields, ! empty( $in['consent'] ), $in['context'] ?? array() );
		if ( is_wp_error( $result ) ) {
			return $this->fail( 'hubspot' );
		}

		$unlock = $this->gate->create_unlock( $in['email'], ! empty( $in['consent'] ), $in['ip'] ?? '' );
		return array( 'ok' => true, 'unlock' => $unlock );
	}

	public function handle() {
		if ( ! check_ajax_referer( 'gr_form', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session expired. Please refresh and try again.', 'gated-resources' ) ), 400 );
		}

		$in = array(
			'hp'        => isset( $_POST['gr_company_url'] ) ? trim( wp_unslash( $_POST['gr_company_url'] ) ) : '',
			'ip'        => $this->client_ip(),
			'turnstile' => isset( $_POST['cf-turnstile-response'] ) ? sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ) ) : '',
			'firstname' => isset( $_POST['firstname'] ) ? sanitize_text_field( wp_unslash( $_POST['firstname'] ) ) : '',
			'lastname'  => isset( $_POST['lastname'] ) ? sanitize_text_field( wp_unslash( $_POST['lastname'] ) ) : '',
			'email'     => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
			'company'   => isset( $_POST['company'] ) ? sanitize_text_field( wp_unslash( $_POST['company'] ) ) : '',
			'jobtitle'  => isset( $_POST['jobtitle'] ) ? sanitize_text_field( wp_unslash( $_POST['jobtitle'] ) ) : '',
			'consent'   => ! empty( $_POST['consent'] ),
			'context'   => array(
				'hutk'     => isset( $_COOKIE['hubspotutk'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['hubspotutk'] ) ) : '',
				'pageUri'  => isset( $_POST['page_uri'] ) ? esc_url_raw( wp_unslash( $_POST['page_uri'] ) ) : '',
				'pageName' => isset( $_POST['page_name'] ) ? sanitize_text_field( wp_unslash( $_POST['page_name'] ) ) : '',
			),
		);

		$result = $this->process( $in );

		if ( ! $result['ok'] ) {
			$messages = array(
				'spam'    => __( 'Submission blocked.', 'gated-resources' ),
				'rate'    => __( 'Too many attempts. Please try again later.', 'gated-resources' ),
				'captcha' => __( 'Anti-spam verification failed. Please try again.', 'gated-resources' ),
				'invalid' => __( 'Please check the highlighted fields.', 'gated-resources' ),
				'hubspot' => __( 'Something went wrong submitting the form. Please try again.', 'gated-resources' ),
			);
			wp_send_json_error(
				array(
					'message' => $messages[ $result['code'] ] ?? __( 'Submission failed.', 'gated-resources' ),
					'errors'  => $result['errors'] ?? array(),
				),
				400
			);
		}

		$this->gate->set_cookie( $result['unlock']['token'], $result['unlock']['expires'] );
		wp_send_json_success( array( 'message' => __( 'Thanks — your resource is unlocked.', 'gated-resources' ) ) );
	}

	private function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/**
	 * Render the gate form markup. Used by the single template.
	 */
	public function render( $post_id ) {
		$turnstile_key = $this->turnstile->site_key();
		$privacy_url   = Settings::get( 'privacy_url' );
		$consent_label = Settings::get( 'consent_label' );
		include GR_DIR . 'templates/parts/gate-form.php';
	}
}
```

- [ ] **Step 4: Create `templates/parts/gate-form.php`**

```php
<?php
/**
 * @var int    $post_id
 * @var string $turnstile_key
 * @var string $privacy_url
 * @var string $consent_label
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<form class="gr-form" id="gr-form" novalidate
	data-nonce="<?php echo esc_attr( wp_create_nonce( 'gr_form' ) ); ?>"
	data-page="<?php echo esc_attr( get_permalink( $post_id ) ); ?>"
	data-pagename="<?php echo esc_attr( get_the_title( $post_id ) ); ?>">

	<p class="gr-form__intro"><?php esc_html_e( 'Complete the form below to access this resource and our full library.', 'gated-resources' ); ?></p>

	<div class="gr-field">
		<label for="gr-firstname"><?php esc_html_e( 'First name', 'gated-resources' ); ?> *</label>
		<input type="text" id="gr-firstname" name="firstname" required>
	</div>
	<div class="gr-field">
		<label for="gr-lastname"><?php esc_html_e( 'Last name', 'gated-resources' ); ?> *</label>
		<input type="text" id="gr-lastname" name="lastname" required>
	</div>
	<div class="gr-field">
		<label for="gr-email"><?php esc_html_e( 'Work email', 'gated-resources' ); ?> *</label>
		<input type="email" id="gr-email" name="email" required>
	</div>
	<div class="gr-field">
		<label for="gr-company"><?php esc_html_e( 'Organisation / Council', 'gated-resources' ); ?> *</label>
		<input type="text" id="gr-company" name="company" required>
	</div>
	<div class="gr-field">
		<label for="gr-jobtitle"><?php esc_html_e( 'Job title', 'gated-resources' ); ?> *</label>
		<input type="text" id="gr-jobtitle" name="jobtitle" required>
	</div>

	<?php /* Honeypot: hidden from humans, tempting to bots. */ ?>
	<div class="gr-hp" aria-hidden="true">
		<label>Company URL<input type="text" name="gr_company_url" tabindex="-1" autocomplete="off"></label>
	</div>

	<div class="gr-field gr-field--consent">
		<label>
			<input type="checkbox" name="consent" value="1">
			<?php echo esc_html( $consent_label ); ?>
		</label>
	</div>

	<?php if ( $turnstile_key ) : ?>
		<div class="cf-turnstile" data-sitekey="<?php echo esc_attr( $turnstile_key ); ?>"></div>
	<?php endif; ?>

	<?php if ( $privacy_url ) : ?>
		<p class="gr-form__privacy">
			<?php
			printf(
				/* translators: %s: privacy policy link */
				esc_html__( 'We process your details to deliver this resource. See our %s for how we handle your data.', 'gated-resources' ),
				'<a href="' . esc_url( $privacy_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'privacy policy', 'gated-resources' ) . '</a>'
			);
			?>
		</p>
	<?php endif; ?>

	<button type="submit" class="gr-btn gr-btn--primary"><?php esc_html_e( 'Access resource', 'gated-resources' ); ?></button>
	<p class="gr-form__msg" role="alert" aria-live="polite"></p>
</form>
```

- [ ] **Step 5: Run test to verify it passes**

Run: `composer test -- --filter FormProcessTest`
Expected: PASS (5 tests).

- [ ] **Step 6: Commit**

```bash
git add includes/class-form.php templates/parts/gate-form.php tests/unit/FormProcessTest.php
git commit -m "feat: gate form rendering + tested submission pipeline"
```

---

## Task 14: Templates router + single/archive/viewer/card templates

**Files:**
- Create: `includes/class-templates.php`
- Create: `templates/single-gated_resource.php`
- Create: `templates/archive-gated_resource.php`
- Create: `templates/parts/viewer.php`
- Create: `templates/parts/card.php`

- [ ] **Step 1: Implement `includes/class-templates.php`**

```php
<?php
namespace BrownDog\GatedResources;

class Templates {

	/**
	 * Use plugin templates for the CPT, allowing theme overrides
	 * (theme can place single-gated_resource.php / archive-gated_resource.php).
	 */
	public function route( $template ) {
		if ( is_singular( CPT::SLUG ) ) {
			$theme = locate_template( array( 'single-gated_resource.php' ) );
			return $theme ? $theme : GR_DIR . 'templates/single-gated_resource.php';
		}
		if ( is_post_type_archive( CPT::SLUG ) ) {
			$theme = locate_template( array( 'archive-gated_resource.php' ) );
			return $theme ? $theme : GR_DIR . 'templates/archive-gated_resource.php';
		}
		return $template;
	}

	public static function gate() {
		return new Gate();
	}
}
```

- [ ] **Step 2: Implement `templates/parts/card.php`**

```php
<?php
/**
 * @var int       $post_id
 * @var Thumbnail $thumbnail
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$cover = $thumbnail->cover_url( $post_id );
?>
<article class="gr-card">
	<a class="gr-card__media" href="<?php echo esc_url( get_permalink( $post_id ) ); ?>">
		<img src="<?php echo esc_url( $cover ); ?>" alt="<?php echo esc_attr( get_the_title( $post_id ) ); ?>" loading="lazy">
	</a>
	<h3 class="gr-card__title">
		<a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>"><?php echo esc_html( get_the_title( $post_id ) ); ?></a>
	</h3>
	<a class="gr-card__more" href="<?php echo esc_url( get_permalink( $post_id ) ); ?>">
		<?php esc_html_e( 'Read more', 'gated-resources' ); ?>
	</a>
</article>
```

- [ ] **Step 3: Implement `templates/parts/viewer.php`**

```php
<?php
/**
 * @var int $post_id
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$inline_url   = add_query_arg( array( 'gr_file' => $post_id, 'gr_disp' => 'inline' ), home_url( '/' ) );
$download_url = add_query_arg( array( 'gr_file' => $post_id, 'gr_disp' => 'download' ), home_url( '/' ) );
?>
<div class="gr-viewer">
	<div class="gr-viewer__actions">
		<a class="gr-btn gr-btn--primary" href="<?php echo esc_url( $download_url ); ?>">
			<?php esc_html_e( 'Download PDF', 'gated-resources' ); ?>
		</a>
	</div>
	<iframe class="gr-viewer__frame" src="<?php echo esc_url( $inline_url ); ?>" title="<?php echo esc_attr( get_the_title( $post_id ) ); ?>"></iframe>
</div>
```

- [ ] **Step 4: Implement `templates/single-gated_resource.php`**

```php
<?php
/**
 * Single resource: gate form (locked) OR viewer + download (unlocked).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

use BrownDog\GatedResources\Gate;
use BrownDog\GatedResources\Form;
use BrownDog\GatedResources\Turnstile;
use BrownDog\GatedResources\HubSpot;
use BrownDog\GatedResources\Thumbnail;

get_header();

while ( have_posts() ) :
	the_post();
	$post_id   = get_the_ID();
	$gate      = new Gate();
	$thumbnail = new Thumbnail();
	$desc      = get_post_meta( $post_id, '_gr_description', true );
	?>
	<main class="gr-single">
		<div class="gr-single__inner">
			<div class="gr-single__cover">
				<img src="<?php echo esc_url( $thumbnail->cover_url( $post_id ) ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>">
			</div>
			<div class="gr-single__body">
				<h1 class="gr-single__title"><?php the_title(); ?></h1>
				<?php if ( $desc ) : ?>
					<div class="gr-single__desc"><?php echo wp_kses_post( wpautop( $desc ) ); ?></div>
				<?php endif; ?>

				<?php
				if ( $gate->is_unlocked() ) {
					include GR_DIR . 'templates/parts/viewer.php';
				} else {
					$form = new Form( new Turnstile(), new HubSpot(), $gate );
					$form->render( $post_id );
				}
				?>
			</div>
		</div>
	</main>
	<?php
endwhile;

get_footer();
```

- [ ] **Step 5: Implement `templates/archive-gated_resource.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
use BrownDog\GatedResources\Thumbnail;

get_header();
$thumbnail = new Thumbnail();
?>
<main class="gr-archive">
	<header class="gr-archive__header">
		<h1 class="gr-archive__title"><?php post_type_archive_title(); ?></h1>
	</header>

	<?php if ( have_posts() ) : ?>
		<div class="gr-grid">
			<?php
			while ( have_posts() ) :
				the_post();
				$post_id = get_the_ID();
				include GR_DIR . 'templates/parts/card.php';
			endwhile;
			?>
		</div>
		<div class="gr-pagination"><?php the_posts_pagination(); ?></div>
	<?php else : ?>
		<p><?php esc_html_e( 'No resources found.', 'gated-resources' ); ?></p>
	<?php endif; ?>
</main>
<?php
get_footer();
```

- [ ] **Step 6: Uncomment the `template_include` wiring**

In `includes/class-plugin.php` ensure this line is active (added in Task 2):

```php
add_filter( 'template_include', array( new Templates(), 'route' ) );
```

- [ ] **Step 7: Verify PHP parses**

Run: `php -l includes/class-templates.php && php -l templates/single-gated_resource.php && php -l templates/archive-gated_resource.php`
Expected: `No syntax errors detected` for all.

- [ ] **Step 8: Commit**

```bash
git add includes/class-templates.php templates/
git commit -m "feat: single/archive templates, card + pdf viewer parts"
```

---

## Task 15: Shortcode + grid

**Files:**
- Create: `includes/class-shortcode.php`
- Test: `tests/unit/ShortcodeTest.php`

- [ ] **Step 1: Write the failing test (attribute parsing)**

```php
<?php
namespace BrownDog\GatedResources\Tests;

use Brain\Monkey\Functions;
use BrownDog\GatedResources\Shortcode;
use BrownDog\GatedResources\Thumbnail;

final class ShortcodeTest extends GR_TestCase {

	public function test_parse_atts_applies_defaults_and_bounds() {
		Functions\when( 'shortcode_atts' )->alias(
			function ( $defaults, $atts ) { return array_merge( $defaults, (array) $atts ); }
		);
		$sc  = new Shortcode( new Thumbnail() );
		$out = $sc->parse_atts( array( 'count' => '8', 'columns' => '9' ) );
		$this->assertSame( 8, $out['count'] );
		$this->assertSame( 4, $out['columns'] ); // clamped to max 4
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer test -- --filter ShortcodeTest`
Expected: FAIL — class `Shortcode` not found.

- [ ] **Step 3: Implement `includes/class-shortcode.php`**

```php
<?php
namespace BrownDog\GatedResources;

class Shortcode {

	private $thumbnail;

	public function __construct( Thumbnail $thumbnail ) {
		$this->thumbnail = $thumbnail;
	}

	public function register() {
		add_shortcode( 'gated_resources', array( $this, 'render' ) );
	}

	public function parse_atts( $atts ) {
		$a = shortcode_atts(
			array(
				'count'   => 9,
				'columns' => 3,
			),
			$atts,
			'gated_resources'
		);
		$a['count']   = max( 1, (int) $a['count'] );
		$a['columns'] = min( 4, max( 1, (int) $a['columns'] ) );
		return $a;
	}

	public function render( $atts ) {
		$a   = $this->parse_atts( $atts );
		$q   = new \WP_Query(
			array(
				'post_type'      => CPT::SLUG,
				'posts_per_page' => $a['count'],
				'no_found_rows'  => true,
			)
		);
		if ( ! $q->have_posts() ) {
			return '';
		}

		$thumbnail = $this->thumbnail;
		ob_start();
		echo '<div class="gr-grid gr-grid--cols-' . (int) $a['columns'] . '">';
		while ( $q->have_posts() ) {
			$q->the_post();
			$post_id = get_the_ID();
			include GR_DIR . 'templates/parts/card.php';
		}
		echo '</div>';
		wp_reset_postdata();
		return ob_get_clean();
	}
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `composer test -- --filter ShortcodeTest`
Expected: PASS (1 test).

- [ ] **Step 5: Commit**

```bash
git add includes/class-shortcode.php tests/unit/ShortcodeTest.php
git commit -m "feat: [gated_resources] shortcode grid"
```

---

## Task 16: Assets — enqueue + front-end JS + grid CSS

**Files:**
- Create: `includes/class-assets.php`
- Create: `assets/js/gated-resources.js`
- Create: `assets/css/gated-resources.css`

- [ ] **Step 1: Implement `includes/class-assets.php`**

```php
<?php
namespace BrownDog\GatedResources;

class Assets {

	private $turnstile;

	public function __construct( Turnstile $turnstile ) {
		$this->turnstile = $turnstile;
	}

	public function register() {
		add_action( 'wp_enqueue_scripts', array( $this, 'front' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin' ) );
	}

	public function front() {
		wp_enqueue_style( 'gated-resources', GR_URL . 'assets/css/gated-resources.css', array(), GR_VERSION );

		if ( is_singular( CPT::SLUG ) ) {
			wp_enqueue_script( 'gated-resources', GR_URL . 'assets/js/gated-resources.js', array(), GR_VERSION, true );
			wp_localize_script(
				'gated-resources',
				'GR_Front',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'i18n'    => array(
						'submitting' => __( 'Submitting…', 'gated-resources' ),
						'error'      => __( 'Something went wrong. Please try again.', 'gated-resources' ),
					),
				)
			);
			if ( $this->turnstile->is_configured() ) {
				wp_enqueue_script( 'cf-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', array(), null, true );
			}
		}
	}

	public function admin( $hook ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && CPT::SLUG === $screen->post_type ) {
			wp_enqueue_style( 'gated-resources-admin', GR_URL . 'assets/css/admin.css', array(), GR_VERSION );
			wp_enqueue_script( 'gated-resources-admin', GR_URL . 'assets/js/admin-uploader.js', array(), GR_VERSION, true );
			wp_localize_script(
				'gated-resources-admin',
				'GR_Admin',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'i18n'    => array(
						'choose'    => __( 'Please choose a PDF file first.', 'gated-resources' ),
						'uploading' => __( 'Uploading…', 'gated-resources' ),
						'done'      => __( 'Uploaded:', 'gated-resources' ),
						'error'     => __( 'Upload failed.', 'gated-resources' ),
					),
				)
			);
		}
	}
}
```

- [ ] **Step 2: Implement `assets/js/gated-resources.js`**

```js
(function () {
	var form = document.getElementById('gr-form');
	if (!form) { return; }
	var msg = form.querySelector('.gr-form__msg');
	var btn = form.querySelector('button[type="submit"]');

	form.addEventListener('submit', function (e) {
		e.preventDefault();
		msg.textContent = GR_Front.i18n.submitting;
		btn.disabled = true;

		var fd = new FormData(form);
		fd.append('action', 'gr_submit');
		fd.append('nonce', form.getAttribute('data-nonce'));
		fd.append('page_uri', form.getAttribute('data-page'));
		fd.append('page_name', form.getAttribute('data-pagename'));

		fetch(GR_Front.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				if (res && res.success) {
					// Reload so the server renders the unlocked viewer.
					window.location.reload();
				} else {
					btn.disabled = false;
					msg.textContent = (res && res.data && res.data.message) ? res.data.message : GR_Front.i18n.error;
					if (window.turnstile) { try { window.turnstile.reset(); } catch (e2) {} }
				}
			})
			.catch(function () {
				btn.disabled = false;
				msg.textContent = GR_Front.i18n.error;
			});
	});
})();
```

- [ ] **Step 3: Implement `assets/css/gated-resources.css`**

```css
/* ---- Brand tokens (confirm against live site) ---- */
:root {
	--gr-purple: #2D1B69;
	--gr-teal:   #16C098;
	--gr-bg:     #ECEAF6;
	--gr-text:   #2D1B69;
}

/* ---- Grid ---- */
.gr-grid {
	display: grid;
	grid-template-columns: repeat(3, 1fr);
	gap: 40px 32px;
}
.gr-grid--cols-1 { grid-template-columns: 1fr; }
.gr-grid--cols-2 { grid-template-columns: repeat(2, 1fr); }
.gr-grid--cols-4 { grid-template-columns: repeat(4, 1fr); }

@media (max-width: 1024px) { .gr-grid, .gr-grid--cols-3, .gr-grid--cols-4 { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 600px)  { .gr-grid, .gr-grid--cols-2, .gr-grid--cols-3, .gr-grid--cols-4 { grid-template-columns: 1fr; } }

/* ---- Card (portrait cover) ---- */
.gr-card__media {
	display: block;
	aspect-ratio: 3 / 4;
	overflow: hidden;
	border-radius: 4px;
	background: var(--gr-bg);
}
.gr-card__media img {
	width: 100%;
	height: 100%;
	object-fit: cover;
	display: block;
}
.gr-card__title {
	margin: 18px 0 14px;
	font-size: 1.2rem;
	line-height: 1.3;
}
.gr-card__title a { color: var(--gr-purple); text-decoration: none; }
.gr-card__title a:hover { text-decoration: underline; }
.gr-card__more {
	display: inline-block;
	color: var(--gr-purple);
	font-weight: 700;
	text-decoration: none;
	padding-bottom: 6px;
	border-bottom: 3px solid var(--gr-teal);
}

/* ---- Single ---- */
.gr-single__inner { display: grid; grid-template-columns: 320px 1fr; gap: 40px; align-items: start; }
.gr-single__cover img { width: 100%; border-radius: 6px; box-shadow: 0 8px 30px rgba(45,27,105,.15); }
.gr-single__title { color: var(--gr-purple); }
@media (max-width: 768px) { .gr-single__inner { grid-template-columns: 1fr; } }

/* ---- Form ---- */
.gr-form { max-width: 520px; }
.gr-field { margin-bottom: 16px; }
.gr-field label { display: block; font-weight: 600; margin-bottom: 6px; color: var(--gr-text); }
.gr-field input[type="text"],
.gr-field input[type="email"] { width: 100%; padding: 12px; border: 1px solid #c9c3e6; border-radius: 4px; }
.gr-field--consent label { font-weight: 400; display: flex; gap: 8px; align-items: flex-start; }
.gr-hp { position: absolute; left: -5000px; height: 0; overflow: hidden; }
.gr-form__privacy { font-size: .85rem; color: #50575e; }
.gr-form__msg { margin-top: 12px; color: #b32d2e; }

.gr-btn { display: inline-block; cursor: pointer; border: 0; border-radius: 4px; padding: 14px 28px; font-weight: 700; text-decoration: none; }
.gr-btn--primary { background: var(--gr-teal); color: #fff; }
.gr-btn--primary:hover { filter: brightness(.95); }

/* ---- Viewer ---- */
.gr-viewer__actions { margin-bottom: 16px; }
.gr-viewer__frame { width: 100%; height: 80vh; border: 1px solid #c9c3e6; border-radius: 4px; }
```

- [ ] **Step 4: Verify PHP parses**

Run: `php -l includes/class-assets.php`
Expected: `No syntax errors detected`.

- [ ] **Step 5: Commit**

```bash
git add includes/class-assets.php assets/js/gated-resources.js assets/css/gated-resources.css
git commit -m "feat: enqueue assets, front-end form JS, responsive grid CSS"
```

---

## Task 17: Auto-updater (plugin-update-checker)

**Files:**
- Create: `lib/plugin-update-checker/` (vendored library)
- Modify: `gated-resources.php`

- [ ] **Step 1: Vendor the library**

Run:
```bash
curl -L -o /tmp/puc.zip https://github.com/YahnisElsts/plugin-update-checker/archive/refs/tags/v5.5.zip \
  && unzip -q /tmp/puc.zip -d /tmp/puc \
  && mkdir -p lib \
  && rm -rf lib/plugin-update-checker \
  && mv /tmp/puc/plugin-update-checker-5.5 lib/plugin-update-checker
```
Expected: `lib/plugin-update-checker/plugin-update-checker.php` exists.

- [ ] **Step 2: Initialise the updater in `gated-resources.php`**

Add after the `add_action( 'plugins_loaded', ... )` block:

```php
require_once GR_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$gr_update_checker = PucFactory::buildUpdateChecker(
	'https://github.com/Browndog-Agency/gated-resources/',
	GR_FILE,
	'gated-resources'
);
$gr_update_checker->getVcsApi()->enableReleaseAssets();
$gr_update_checker->setBranch( 'main' );
```

(Place the `use` statement at the top of the file, just under the `define()` block, since `use` must be at file scope before usage.)

- [ ] **Step 3: Verify PHP parses**

Run: `php -l gated-resources.php`
Expected: `No syntax errors detected`.

- [ ] **Step 4: Commit**

```bash
git add lib/plugin-update-checker gated-resources.php
git commit -m "feat: self-update from public GitHub repo via plugin-update-checker"
```

---

## Task 18: Uninstall cleanup

**Files:**
- Create: `uninstall.php`

- [ ] **Step 1: Implement `uninstall.php`**

```php
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
```

- [ ] **Step 2: Verify PHP parses**

Run: `php -l uninstall.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add uninstall.php
git commit -m "feat: uninstall removes table, options, files, and resource posts"
```

---

## Task 19: Full suite, README, nginx note, manual QA checklist

**Files:**
- Create: `README.md`
- Create: `docs/manual-qa-checklist.md`

- [ ] **Step 1: Run the entire test suite**

Run: `composer test`
Expected: All unit tests pass (Settings, Gate, Activator, Turnstile, HubSpot, Thumbnail, ProtectedFiles, CPT, PdfUpload, FormProcess, Shortcode, Sanity).

- [ ] **Step 2: Create `README.md`**

````markdown
# Gated Resources

WordPress plugin: a gated PDF resource library for Bartec Municipal. Visitors complete a
short form once to unlock the whole library for 30 days; leads are sent to HubSpot, with
Cloudflare Turnstile anti-spam. PDFs are served through a protected endpoint.

## Requirements
- WordPress 6.0+, PHP 7.4+
- For PDF cover thumbnails: Imagick PHP extension + Ghostscript (optional; falls back to
  featured image, then a placeholder)

## Setup
1. Install & activate the plugin.
2. **Resources → Settings**: enter HubSpot Portal ID + Form GUID, Turnstile site + secret keys,
   privacy policy URL, consent label + HubSpot subscription ID, unlock duration.
3. Add Resources (title, PDF upload, optional description). Optionally set a featured image as
   a thumbnail fallback.
4. Show the grid at `/resources/` or with the shortcode: `[gated_resources count="9" columns="3"]`.

## nginx note (protected files)
The protected directory ships an Apache `.htaccess` deny rule. On **nginx**, add:

```nginx
location ~* /wp-content/uploads/gated-resources/ { deny all; return 403; }
```

Files also live under an unguessable hashed path as defence in depth, and are only served
through the gated PHP endpoint (`?gr_file=ID`).

## Updating
This plugin self-updates from `Browndog-Agency/gated-resources`. To release:
1. Bump `Version:` in `gated-resources.php`.
2. `git tag vX.Y.Z && git push --tags`
3. Publish a GitHub **Release** for that tag.
WordPress will then show a one-click update.

## Development
```bash
composer install
composer test
```
````

- [ ] **Step 3: Create `docs/manual-qa-checklist.md`**

```markdown
# Manual QA Checklist

## Setup
- [ ] Activate plugin; confirm `wp_gr_unlocks` table, `/uploads/gated-resources/.htaccess`,
      and `/uploads/gated-resources-previews/` exist.
- [ ] Settings save and persist (HubSpot, Turnstile, consent, unlock days).

## Authoring
- [ ] Create a Resource; upload a PDF via the meta box (async upload succeeds).
- [ ] Cover thumbnail generates from page 1 (or falls back to featured image / placeholder).
- [ ] Non-PDF and oversize uploads are rejected with a clear message.

## Gating + HubSpot
- [ ] Visiting a single resource while locked shows the form (with Turnstile).
- [ ] Submitting with Turnstile passing creates a HubSpot contact (check HubSpot).
- [ ] Consent ticked → consent recorded in HubSpot; unticked → no marketing consent.
- [ ] Invalid email / missing fields show inline validation.
- [ ] Honeypot-filled submission is blocked.
- [ ] After success, page reloads to the inline viewer + working download button.

## File protection
- [ ] Direct hit on the file's real `/uploads/gated-resources/...` URL is denied (403/blocked).
- [ ] `?gr_file=ID` returns 403 when no valid unlock cookie is present.
- [ ] `?gr_file=ID` streams the PDF when unlocked.

## Persistence
- [ ] After unlocking, other resources open without re-prompting (global unlock).
- [ ] Unlock persists across browser restart for 30 days (cookie + DB row).
- [ ] Manually expire a row → access prompts the form again.

## Responsive
- [ ] Grid is 3 cols desktop / 2 tablet (≤1024px) / 1 mobile (≤600px).
- [ ] Covers are portrait, not distorted.

## Lifecycle
- [ ] Deactivate clears the cron event.
- [ ] Uninstall drops the table, options, dirs, and resource posts.
```

- [ ] **Step 4: Commit**

```bash
git add README.md docs/manual-qa-checklist.md
git commit -m "docs: readme, nginx note, manual QA checklist"
```

---

## Task 20: Publish to GitHub (Browndog Agency, public repo `gated-resources`)

**Note:** This is an outward-facing action — confirm with the user before running, and confirm
the exact GitHub org/team slug for "Browndog Agency".

- [ ] **Step 1: Confirm `gh` auth + org**

Run: `gh auth status && gh repo list Browndog-Agency --limit 5`
Expected: authenticated; org slug confirmed (adjust if different).

- [ ] **Step 2: Create the public repo and push**

Run:
```bash
gh repo create Browndog-Agency/gated-resources --public --source=. --remote=origin --push
```
Expected: repo created, `main` pushed.

- [ ] **Step 3: Tag the first release**

Run:
```bash
git tag v0.1.0 && git push origin v0.1.0
gh release create v0.1.0 --title "v0.1.0" --notes "Initial release of the Gated Resources plugin."
```
Expected: release visible; the auto-updater can now detect future releases.

---

## Self-Review

**Spec coverage** — every spec section maps to a task:
- §3 Content model → Task 10 (CPT) + Task 12 (meta box).
- §4 Protected delivery → Task 5 (.htaccess/dirs) + Task 9 (storage + stream).
- §5 Thumbnail → Task 8.
- §6 Gate / 30-day unlock → Task 4 (table/token/cookie) + Task 5 (table create) + Task 13 (unlock on submit) + Task 14 (locked/unlocked render).
- §7 Form pipeline (nonce, honeypot, rate-limit, Turnstile, HubSpot) → Task 6 + Task 7 + Task 13.
- §8 Settings → Task 3.
- §9 Grid → Task 14 (archive/card/CSS) + Task 15 (shortcode) + Task 16 (CSS/JS).
- §10 Auto-update → Task 17.
- §11 Structure → all tasks; bootstrap Task 2.
- §12 Testing → Tasks 3,4,5,6,7,8,9,10,11,13,15 (unit) + Task 19 (suite + manual checklist).
- §13 Privacy/GDPR → consent (Task 7/13), minimal storage (Task 4), uninstall (Task 18).

**Placeholder scan** — no TBD/TODO; every code step contains real code; the one brand-colour
note ("confirm against live site") is an explicit, non-blocking build-time check, not a gap.

**Type/name consistency** — method/property names verified consistent across tasks:
`Settings::get`, `Gate::{generate_token,is_valid_token,is_unlocked,create_unlock,set_cookie,prune_expired}`,
`Turnstile::{verify,is_configured,site_key}`, `HubSpot::{build_payload,submit}`,
`Thumbnail::{generate,cover_url,imagick_available}`, `Protected_Files::{store,absolute_path,disposition,build_relative_path,stream,maybe_stream}`,
`PDF_Upload::{validate_file,max_bytes,handle}`, `Form::{process,validate,render,handle}`,
`Shortcode::{parse_atts,render}`, `CPT::{SLUG,register,register_post_type}`. Meta keys and the
`gr_settings` keys match the Conventions section throughout.
