<?php
/**
 * Test Bootstrap
 *
 * Sets up the testing environment for BlazeCommerce WordPress Plugin
 *
 * @package BlazeWooless
 * @subpackage Tests
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define test environment constants
define( 'BLAZE_COMMERCE_TESTING', true );
define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );

// Enhanced error reporting for tests
error_reporting( E_ALL );
ini_set( 'display_errors', 1 );
ini_set( 'log_errors', 1 );

// WordPress test environment setup
$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

// Verify WordPress test environment
if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "WordPress test environment not found at: $_tests_dir\n";
	echo "Please ensure WordPress test environment is properly installed.\n";
	exit( 1 );
}

// WordPress core directory
$_core_dir = getenv( 'WP_CORE_DIR' );
if ( ! $_core_dir ) {
	$_core_dir = '/tmp/wordpress/';
}

// Define WordPress plugin directory
if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
	define( 'WP_PLUGIN_DIR', $_core_dir . 'wp-content/plugins' );
}

// Load WordPress test functions
require_once $_tests_dir . '/includes/functions.php';

/**
 * Enhanced plugin loading with error handling
 */
function _manually_load_plugin() {
	// Ensure WooCommerce is available
	$woocommerce_path = WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';
	if ( file_exists( $woocommerce_path ) ) {
		require_once $woocommerce_path;
		echo "WooCommerce loaded successfully\n";
	} else {
		echo "Warning: WooCommerce not found at: $woocommerce_path\n";
		echo "Available plugins:\n";
		if ( is_dir( WP_PLUGIN_DIR ) ) {
			$plugins = scandir( WP_PLUGIN_DIR );
			foreach ( $plugins as $plugin ) {
				if ( $plugin !== '.' && $plugin !== '..' ) {
					echo "  - $plugin\n";
				}
			}
		}
	}

	// Load our plugin
	$plugin_path = dirname( dirname( __FILE__ ) ) . '/blaze-wooless.php';
	if ( file_exists( $plugin_path ) ) {
		require $plugin_path;
		echo "BlazeCommerce plugin loaded successfully\n";
	} else {
		echo "Warning: BlazeCommerce plugin not found at: $plugin_path\n";
	}
}

// Hook plugin loading
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Additional WordPress setup
tests_add_filter( 'init', function() {
	// Ensure WooCommerce is properly initialized
	if ( class_exists( 'WooCommerce' ) ) {
		echo "WooCommerce class available\n";
	} else {
		echo "Warning: WooCommerce class not available\n";
	}
} );

// Start up the WP testing environment
require $_tests_dir . '/includes/bootstrap.php';

// Load test helpers
require_once __DIR__ . '/helpers/test-helper.php';
require_once __DIR__ . '/fixtures/product-fixtures.php';
require_once __DIR__ . '/fixtures/collection-fixtures.php';
