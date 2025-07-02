<?php
/**
 * Simple test file for Export/Import Settings functionality
 * 
 * This file can be used to test the export/import feature manually
 * Run this file in a WordPress environment to test the functionality
 */

// Ensure this is run in WordPress context
if ( ! defined( 'ABSPATH' ) ) {
	die( 'This file must be run in WordPress context' );
}

// Include the ExportImportSettings class
require_once BLAZE_COMMERCE_PLUGIN_DIR . 'app/Settings/ExportImportSettings.php';

use BlazeWooless\Settings\ExportImportSettings;

/**
 * Test the export functionality
 */
function test_export_settings() {
	echo "<h2>Testing Export Settings</h2>\n";
	
	$export_import = ExportImportSettings::get_instance();
	$export_data = $export_import->export_settings();
	
	if ( ! empty( $export_data ) ) {
		echo "<p style='color: green;'>✓ Export successful!</p>\n";
		echo "<p>Exported " . count( $export_data ) . " items (including metadata)</p>\n";
		
		// Check if metadata is present
		if ( isset( $export_data['_export_metadata'] ) ) {
			echo "<p style='color: green;'>✓ Export metadata is present</p>\n";
			echo "<pre>" . print_r( $export_data['_export_metadata'], true ) . "</pre>\n";
		} else {
			echo "<p style='color: red;'>✗ Export metadata is missing</p>\n";
		}
		
		// Show which settings were exported
		$settings_count = 0;
		foreach ( $export_data as $key => $value ) {
			if ( $key !== '_export_metadata' && $value !== false ) {
				$settings_count++;
			}
		}
		echo "<p>Found {$settings_count} actual settings with data</p>\n";
		
	} else {
		echo "<p style='color: red;'>✗ Export failed or returned empty data</p>\n";
	}
	
	return $export_data;
}

/**
 * Test the import functionality
 */
function test_import_settings( $test_data ) {
	echo "<h2>Testing Import Settings</h2>\n";
	
	$export_import = ExportImportSettings::get_instance();
	
	// Test with valid data
	$result = $export_import->import_settings( $test_data );
	
	if ( $result['success'] ) {
		echo "<p style='color: green;'>✓ Import successful!</p>\n";
		echo "<p>" . $result['message'] . "</p>\n";
		
		if ( ! empty( $result['errors'] ) ) {
			echo "<p style='color: orange;'>Warnings:</p>\n";
			foreach ( $result['errors'] as $error ) {
				echo "<p style='color: orange;'>- {$error}</p>\n";
			}
		}
	} else {
		echo "<p style='color: red;'>✗ Import failed!</p>\n";
		echo "<p>" . $result['message'] . "</p>\n";
		
		if ( ! empty( $result['errors'] ) ) {
			echo "<p>Errors:</p>\n";
			foreach ( $result['errors'] as $error ) {
				echo "<p style='color: red;'>- {$error}</p>\n";
			}
		}
	}
	
	// Test with invalid data
	echo "<h3>Testing with invalid data</h3>\n";
	$invalid_result = $export_import->import_settings( array( 'invalid' => 'data' ) );
	
	if ( ! $invalid_result['success'] ) {
		echo "<p style='color: green;'>✓ Correctly rejected invalid data</p>\n";
		echo "<p>" . $invalid_result['message'] . "</p>\n";
	} else {
		echo "<p style='color: red;'>✗ Should have rejected invalid data</p>\n";
	}
}

/**
 * Test the settings keys
 */
function test_settings_keys() {
	echo "<h2>Testing Settings Keys</h2>\n";
	
	$export_import = ExportImportSettings::get_instance();
	$reflection = new ReflectionClass( $export_import );
	$method = $reflection->getMethod( 'get_all_settings_keys' );
	$method->setAccessible( true );
	$keys = $method->invoke( $export_import );
	
	echo "<p>Found " . count( $keys ) . " settings keys to export/import:</p>\n";
	echo "<ul>\n";
	foreach ( $keys as $key ) {
		$value = get_option( $key );
		$has_data = ( $value !== false && ! empty( $value ) );
		$status = $has_data ? "✓" : "○";
		$color = $has_data ? "green" : "gray";
		echo "<li style='color: {$color};'>{$status} {$key}</li>\n";
	}
	echo "</ul>\n";
	
	return $keys;
}

/**
 * Run all tests
 */
function run_export_import_tests() {
	echo "<h1>Blaze Commerce Export/Import Settings Tests</h1>\n";
	echo "<style>body { font-family: Arial, sans-serif; margin: 20px; }</style>\n";
	
	// Test 1: Check settings keys
	$keys = test_settings_keys();
	
	// Test 2: Export settings
	$export_data = test_export_settings();
	
	// Test 3: Import settings (using the exported data)
	if ( ! empty( $export_data ) ) {
		test_import_settings( $export_data );
	} else {
		echo "<p style='color: red;'>Skipping import test due to export failure</p>\n";
	}
	
	echo "<h2>Test Summary</h2>\n";
	echo "<p>Tests completed. Check the results above for any issues.</p>\n";
	echo "<p><strong>Note:</strong> This is a basic functionality test. For full testing, use the WordPress admin interface.</p>\n";
}

// Only run tests if this file is accessed directly (not included)
if ( basename( $_SERVER['PHP_SELF'] ) === basename( __FILE__ ) ) {
	// Check if we're in WordPress admin and user has proper permissions
	if ( is_admin() && current_user_can( 'manage_options' ) ) {
		run_export_import_tests();
	} else {
		echo "<p style='color: red;'>This test must be run by an administrator in the WordPress admin area.</p>\n";
	}
}
?>
