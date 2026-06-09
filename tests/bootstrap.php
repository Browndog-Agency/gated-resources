<?php
require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once dirname( __DIR__ ) . '/includes/autoload.php';
require_once dirname( __DIR__ ) . '/tests/class-gr-testcase.php';

// Constants the plugin classes reference, stubbed for unit tests.
if ( ! defined( 'GR_VERSION' ) ) { define( 'GR_VERSION', 'test' ); }
if ( ! defined( 'GR_DIR' ) ) { define( 'GR_DIR', dirname( __DIR__ ) . '/' ); }
if ( ! defined( 'GR_URL' ) ) { define( 'GR_URL', 'https://example.test/wp-content/plugins/gated-resources/' ); }
if ( ! defined( 'DAY_IN_SECONDS' ) ) { define( 'DAY_IN_SECONDS', 86400 ); }
