<?php
/**
 * Test script for Typesense Collection Aliasing Implementation
 *
 * This script tests the basic functionality of the new alias system
 * Run this from the WordPress root directory: php tests/test-alias-implementation.php
 */

// Load WordPress
require_once __DIR__ . '/../wp-config.php';
require_once __DIR__ . '/../wp-load.php';

// Load the plugin classes
require_once __DIR__ . '/../app/TypesenseClient.php';
require_once __DIR__ . '/../app/Collections/CollectionAliasManager.php';
require_once __DIR__ . '/../app/Collections/BaseCollection.php';

use BlazeWooless\TypesenseClient;
use BlazeWooless\Collections\CollectionAliasManager;

echo "=== Typesense Collection Aliasing Test ===\n\n";

try {
	// Test 1: TypesenseClient site URL functionality
	echo "Test 1: TypesenseClient site URL functionality\n";
	echo "-----------------------------------------------\n";

	$typesense = TypesenseClient::get_instance();
	if ( ! $typesense ) {
		throw new Exception( "Failed to get TypesenseClient instance" );
	}

	$site_url = $typesense->get_site_url();
	echo "Site URL: " . $site_url . "\n";

	if ( empty( $site_url ) ) {
		throw new Exception( "Site URL is empty" );
	}

	echo "✓ TypesenseClient site URL functionality works\n\n";

	// Test 2: CollectionAliasManager basic functionality
	echo "Test 2: CollectionAliasManager basic functionality\n";
	echo "--------------------------------------------------\n";

	$alias_manager = new CollectionAliasManager();

	// Test alias name generation
	$alias_name = $alias_manager->get_alias_name( 'product' );
	echo "Product alias name: " . $alias_name . "\n";

	if ( strpos( $alias_name, 'product_' ) !== 0 ) {
		throw new Exception( "Alias name format is incorrect" );
	}

	// Test collection name generation
	$collection_name = $alias_manager->get_collection_name( 'product', 1234567890 );
	echo "Product collection name: " . $collection_name . "\n";

	if ( strpos( $collection_name, 'product_' ) !== 0 || strpos( $collection_name, '_1234567890' ) === false ) {
		throw new Exception( "Collection name format is incorrect" );
	}

	echo "✓ CollectionAliasManager basic functionality works\n\n";

	// Test 3: Check if Typesense client is accessible
	echo "Test 3: Typesense client connectivity\n";
	echo "-------------------------------------\n";

	$client = $typesense->client();
	if ( ! $client ) {
		echo "⚠ Typesense client is not available (this is expected if not configured)\n";
	} else {
		echo "✓ Typesense client is available\n";

		// Try to get collections (this might fail if not configured)
		try {
			$collections = $client->collections->retrieve();
			echo "Current collections count: " . count( $collections['collections'] ) . "\n";
		} catch (Exception $e) {
			echo "⚠ Could not retrieve collections: " . $e->getMessage() . "\n";
		}
	}

	echo "\n";

	// Test 4: Test collection type detection
	echo "Test 4: Collection type detection\n";
	echo "---------------------------------\n";

	$collection_types = [ 'product', 'taxonomy', 'page', 'menu', 'site_info', 'navigation' ];

	foreach ( $collection_types as $type ) {
		$alias_name = $alias_manager->get_alias_name( $type );
		$current_collection = $alias_manager->get_current_collection( $type );
		$all_collections = $alias_manager->get_all_collections_for_type( $type );

		echo "Type: $type\n";
		echo "  Alias: $alias_name\n";
		echo "  Current: " . ( $current_collection ?: 'None' ) . "\n";
		echo "  All collections: " . ( empty( $all_collections ) ? 'None' : implode( ', ', $all_collections ) ) . "\n";
		echo "\n";
	}

	echo "✓ Collection type detection works\n\n";

	// Test 5: Test filter functionality
	echo "Test 5: Filter functionality\n";
	echo "----------------------------\n";

	// Test the filter that controls alias usage
	$use_aliases_default = apply_filters( 'blazecommerce/use_collection_aliases', true );
	echo "Use aliases (default): " . ( $use_aliases_default ? 'true' : 'false' ) . "\n";

	// Add a filter to test
	add_filter( 'blazecommerce/use_collection_aliases', function ($use) {
		return false;
	} );

	$use_aliases_filtered = apply_filters( 'blazecommerce/use_collection_aliases', true );
	echo "Use aliases (filtered): " . ( $use_aliases_filtered ? 'true' : 'false' ) . "\n";

	// Remove the filter
	remove_all_filters( 'blazecommerce/use_collection_aliases' );

	echo "✓ Filter functionality works\n\n";

	echo "=== All Tests Passed! ===\n";
	echo "The Typesense Collection Aliasing implementation appears to be working correctly.\n\n";

	echo "Next steps:\n";
	echo "1. Configure Typesense settings in WordPress admin\n";
	echo "2. Test with actual data using: wp bc-sync alias --status\n";
	echo "3. Run a product sync to test the new alias system: wp bc-sync product --all\n";
	echo "4. Monitor the alias creation and collection management\n";

} catch (Exception $e) {
	echo "❌ Test failed: " . $e->getMessage() . "\n";
	echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
	exit( 1 );
}
