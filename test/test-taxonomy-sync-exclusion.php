<?php
/**
 * Test file for Taxonomy Sync Exclusion feature
 * 
 * This file tests the functionality of excluding WooCommerce categories
 * from Typesense sync based on the custom meta field.
 * 
 * @package BlazeWooless
 * @subpackage Tests
 */

// Only run in development/debug mode
if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
	return;
}

/**
 * Test the taxonomy sync exclusion functionality
 */
function test_taxonomy_sync_exclusion() {
	echo "<h2>Testing Taxonomy Sync Exclusion Feature</h2>\n";
	
	// Test 1: Check if the feature class exists
	echo "<h3>Test 1: Feature Class Existence</h3>\n";
	if ( class_exists( 'BlazeWooless\\Features\\TaxonomySyncExclusion' ) ) {
		echo "✅ TaxonomySyncExclusion class exists\n";
	} else {
		echo "❌ TaxonomySyncExclusion class not found\n";
		return;
	}
	
	// Test 2: Check if the feature is registered
	echo "<h3>Test 2: Feature Registration</h3>\n";
	$instance = BlazeWooless\Features\TaxonomySyncExclusion::get_instance();
	if ( $instance ) {
		echo "✅ TaxonomySyncExclusion instance created successfully\n";
	} else {
		echo "❌ Failed to create TaxonomySyncExclusion instance\n";
	}
	
	// Test 3: Check if hooks are registered
	echo "<h3>Test 3: Hook Registration</h3>\n";
	
	// Check if the edit form hook is registered
	if ( has_action( 'product_cat_edit_form_fields' ) ) {
		echo "✅ product_cat_edit_form_fields hook is registered\n";
	} else {
		echo "❌ product_cat_edit_form_fields hook not found\n";
	}
	
	// Check if the save hook is registered
	if ( has_action( 'edited_product_cat' ) ) {
		echo "✅ edited_product_cat hook is registered\n";
	} else {
		echo "❌ edited_product_cat hook not found\n";
	}
	
	// Check if the filter hook is registered
	if ( has_filter( 'blazecommerce_taxonomy_sync_terms' ) ) {
		echo "✅ blazecommerce_taxonomy_sync_terms filter is registered\n";
	} else {
		echo "❌ blazecommerce_taxonomy_sync_terms filter not found\n";
	}
	
	// Test 4: Test the exclusion filter functionality
	echo "<h3>Test 4: Exclusion Filter Functionality</h3>\n";
	test_exclusion_filter();
	
	echo "<h3>Test Summary</h3>\n";
	echo "Taxonomy Sync Exclusion feature tests completed.\n";
	echo "Check the results above to ensure all tests pass.\n";
}

/**
 * Test the exclusion filter with mock data
 */
function test_exclusion_filter() {
	// Create mock terms for testing
	$mock_terms = array();
	
	// Mock term 1 - not excluded
	$term1 = new stdClass();
	$term1->term_id = 999991;
	$term1->name = 'Test Category 1';
	$term1->slug = 'test-category-1';
	$mock_terms[] = $term1;
	
	// Mock term 2 - excluded (we'll simulate this)
	$term2 = new stdClass();
	$term2->term_id = 999992;
	$term2->name = 'Test Category 2';
	$term2->slug = 'test-category-2';
	$mock_terms[] = $term2;
	
	// Simulate exclusion meta for term 2
	// Note: In a real test, we'd create actual terms and set meta
	// For this test, we'll just verify the filter exists and can be called
	
	echo "Mock terms created: " . count( $mock_terms ) . "\n";
	
	// Test the filter
	$filtered_terms = apply_filters( 'blazecommerce_taxonomy_sync_terms', $mock_terms );
	
	if ( is_array( $filtered_terms ) ) {
		echo "✅ Filter executed successfully\n";
		echo "Original terms: " . count( $mock_terms ) . "\n";
		echo "Filtered terms: " . count( $filtered_terms ) . "\n";
	} else {
		echo "❌ Filter execution failed\n";
	}
}

/**
 * Display test results in admin
 */
function display_taxonomy_sync_exclusion_test() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	
	echo '<div class="wrap">';
	echo '<h1>Taxonomy Sync Exclusion Test</h1>';
	echo '<div style="background: #fff; padding: 20px; border: 1px solid #ccc; font-family: monospace; white-space: pre-line;">';
	
	ob_start();
	test_taxonomy_sync_exclusion();
	$output = ob_get_clean();
	
	echo esc_html( $output );
	echo '</div>';
	echo '</div>';
}

// Add admin menu for testing (only in debug mode)
if ( is_admin() ) {
	add_action( 'admin_menu', function() {
		add_submenu_page(
			'tools.php',
			'Test Taxonomy Sync Exclusion',
			'Test Taxonomy Sync Exclusion',
			'manage_options',
			'test-taxonomy-sync-exclusion',
			'display_taxonomy_sync_exclusion_test'
		);
	});
}

// Run basic test on plugin load (only in debug mode)
add_action( 'init', function() {
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		// Run test via WP-CLI if available
		test_taxonomy_sync_exclusion();
	}
}, 999 );
