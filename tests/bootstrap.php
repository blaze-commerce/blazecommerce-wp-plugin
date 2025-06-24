<?php
/**
 * Test Bootstrap
 * 
 * Sets up the testing environment for BlazeCommerce WordPress Plugin
 * 
 * @package BlazeWooless
 * @subpackage Tests
 */

// Define test environment
define( 'BLAZE_COMMERCE_TESTING', true );

// WordPress test environment
$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

// Load WordPress test functions
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested
 */
function _manually_load_plugin() {
	// Load WooCommerce first
	if ( file_exists( WP_PLUGIN_DIR . '/woocommerce/woocommerce.php' ) ) {
		require_once WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';
	}
	
	// Load our plugin
	require dirname( dirname( __FILE__ ) ) . '/blaze-wooless.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment
require $_tests_dir . '/includes/bootstrap.php';

// Load test helpers
require_once __DIR__ . '/helpers/test-helper.php';
require_once __DIR__ . '/fixtures/product-fixtures.php';
require_once __DIR__ . '/fixtures/collection-fixtures.php';
