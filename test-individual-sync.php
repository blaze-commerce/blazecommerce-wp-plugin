<?php
/**
 * Test Individual Sync vs Sync All Behavior
 * 
 * This script helps diagnose why individual sync buttons result in 0 documents
 * while "Sync All" works correctly.
 */

// Simulate WordPress environment for testing
if (!defined('ABSPATH')) {
    echo "This script must be run within WordPress environment\n";
    exit;
}

require_once __DIR__ . '/app/Collections/Product.php';
require_once __DIR__ . '/app/Collections/Taxonomy.php';
require_once __DIR__ . '/app/TypesenseClient.php';

use BlazeWooless\Collections\Product;
use BlazeWooless\Collections\Taxonomy;
use BlazeWooless\TypesenseClient;

class IndividualSyncTester {
    
    public function __construct() {
        echo "=== Individual Sync Diagnostic Tool ===\n";
        echo "Testing individual sync methods vs Sync All behavior\n\n";
    }
    
    /**
     * Test individual product sync
     */
    public function test_individual_product_sync() {
        echo "1. Testing Individual Product Sync...\n";
        
        try {
            // Simulate the AJAX request parameters
            $_REQUEST['collection_name'] = 'products';
            $_REQUEST['page'] = 1;
            
            // Get initial collection state
            $initial_count = $this->get_collection_document_count('product');
            echo "   Initial product collection documents: $initial_count\n";
            
            // Run the product sync
            ob_start();
            Product::get_instance()->index_to_typesense();
            $output = ob_get_clean();
            
            echo "   Product sync output: " . substr($output, 0, 200) . "...\n";
            
            // Check final collection state
            $final_count = $this->get_collection_document_count('product');
            echo "   Final product collection documents: $final_count\n";
            
            if ($final_count > 0) {
                echo "   ✓ Product sync successful\n";
            } else {
                echo "   ✗ Product sync failed - 0 documents\n";
            }
            
        } catch (Exception $e) {
            echo "   ✗ Product sync error: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }
    
    /**
     * Test individual taxonomy sync
     */
    public function test_individual_taxonomy_sync() {
        echo "2. Testing Individual Taxonomy Sync...\n";
        
        try {
            // Simulate the AJAX request parameters
            $_REQUEST['collection_name'] = 'taxonomy';
            $_REQUEST['page'] = 1;
            $_REQUEST['batch_size'] = 5;
            $_REQUEST['imported_count'] = 0;
            $_REQUEST['total_imports'] = 0;
            
            // Get initial collection state
            $initial_count = $this->get_collection_document_count('taxonomy');
            echo "   Initial taxonomy collection documents: $initial_count\n";
            
            // Run the taxonomy sync
            ob_start();
            Taxonomy::get_instance()->index_to_typesense();
            $output = ob_get_clean();
            
            echo "   Taxonomy sync output: " . substr($output, 0, 200) . "...\n";
            
            // Check final collection state
            $final_count = $this->get_collection_document_count('taxonomy');
            echo "   Final taxonomy collection documents: $final_count\n";
            
            if ($final_count > 0) {
                echo "   ✓ Taxonomy sync successful\n";
            } else {
                echo "   ✗ Taxonomy sync failed - 0 documents\n";
            }
            
        } catch (Exception $e) {
            echo "   ✗ Taxonomy sync error: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }
    
    /**
     * Test the collection initialization process
     */
    public function test_collection_initialization() {
        echo "3. Testing Collection Initialization...\n";
        
        try {
            $store_id = bw_get_general_settings('store_id');
            echo "   Store ID: $store_id\n";
            
            // Test product collection initialization
            echo "   Testing product collection initialization...\n";
            $product_collection = Product::get_instance();
            
            // Check if collection exists before initialization
            $exists_before = $this->collection_exists("product-$store_id");
            echo "   Product collection exists before init: " . ($exists_before ? 'YES' : 'NO') . "\n";
            
            // Initialize collection
            $product_collection->initialize();
            
            // Check if collection exists after initialization
            $exists_after = $this->collection_exists("product-$store_id");
            echo "   Product collection exists after init: " . ($exists_after ? 'YES' : 'NO') . "\n";
            
            // Test taxonomy collection initialization
            echo "   Testing taxonomy collection initialization...\n";
            $taxonomy_collection = Taxonomy::get_instance();
            
            // Check if collection exists before initialization
            $exists_before = $this->collection_exists("taxonomy-$store_id");
            echo "   Taxonomy collection exists before init: " . ($exists_before ? 'YES' : 'NO') . "\n";
            
            // Initialize collection
            $taxonomy_collection->initialize();
            
            // Check if collection exists after initialization
            $exists_after = $this->collection_exists("taxonomy-$store_id");
            echo "   Taxonomy collection exists after init: " . ($exists_after ? 'YES' : 'NO') . "\n";
            
        } catch (Exception $e) {
            echo "   ✗ Collection initialization error: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }
    
    /**
     * Test the difference between individual sync and sync all
     */
    public function test_sync_all_simulation() {
        echo "4. Simulating Sync All Process...\n";
        
        try {
            // Simulate the sync all process step by step
            echo "   Step 1: Product sync (as in Sync All)...\n";
            $_REQUEST['collection_name'] = 'products';
            $_REQUEST['page'] = 1;
            
            ob_start();
            Product::get_instance()->index_to_typesense();
            $product_output = ob_get_clean();
            
            $product_count = $this->get_collection_document_count('product');
            echo "   Products synced: $product_count\n";
            
            echo "   Step 2: Taxonomy sync (as in Sync All)...\n";
            $_REQUEST['collection_name'] = 'taxonomy';
            $_REQUEST['page'] = 1;
            $_REQUEST['batch_size'] = 5;
            $_REQUEST['imported_count'] = 0;
            $_REQUEST['total_imports'] = 0;
            
            ob_start();
            Taxonomy::get_instance()->index_to_typesense();
            $taxonomy_output = ob_get_clean();
            
            $taxonomy_count = $this->get_collection_document_count('taxonomy');
            echo "   Taxonomies synced: $taxonomy_count\n";
            
            if ($product_count > 0 && $taxonomy_count > 0) {
                echo "   ✓ Sync All simulation successful\n";
            } else {
                echo "   ✗ Sync All simulation failed\n";
            }
            
        } catch (Exception $e) {
            echo "   ✗ Sync All simulation error: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }
    
    /**
     * Check if a collection exists
     */
    private function collection_exists($collection_name) {
        try {
            $client = TypesenseClient::get_instance()->client();
            $collection = $client->collections[$collection_name]->retrieve();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get document count for a collection
     */
    private function get_collection_document_count($collection_type) {
        try {
            $store_id = bw_get_general_settings('store_id');
            $collection_name = "$collection_type-$store_id";
            
            $client = TypesenseClient::get_instance()->client();
            $search_result = $client->collections[$collection_name]->documents->search([
                'q' => '*',
                'per_page' => 1
            ]);
            
            return $search_result['found'] ?? 0;
        } catch (Exception $e) {
            echo "   Error getting document count: " . $e->getMessage() . "\n";
            return 0;
        }
    }
    
    /**
     * Check WordPress data availability
     */
    public function check_wordpress_data() {
        echo "5. Checking WordPress Data Availability...\n";
        
        // Check products
        $products = get_posts([
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => 5,
            'fields' => 'ids'
        ]);
        echo "   Published products in WordPress: " . count($products) . "\n";
        
        // Check taxonomies
        $taxonomies = get_taxonomies([], 'names');
        $product_categories = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'number' => 5
        ]);
        echo "   Product categories in WordPress: " . count($product_categories) . "\n";
        
        // Check if system is enabled
        $system_enabled = boolval(bw_get_general_settings('enable_system'));
        echo "   BlazeCommerce system enabled: " . ($system_enabled ? 'YES' : 'NO') . "\n";
        
        // Check Typesense connection
        try {
            $can_connect = TypesenseClient::get_instance()->can_connect();
            echo "   Typesense connection: " . ($can_connect ? 'OK' : 'FAILED') . "\n";
        } catch (Exception $e) {
            echo "   Typesense connection: ERROR - " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }
    
    /**
     * Run all tests
     */
    public function run_all_tests() {
        $this->check_wordpress_data();
        $this->test_collection_initialization();
        $this->test_individual_product_sync();
        $this->test_individual_taxonomy_sync();
        $this->test_sync_all_simulation();
        
        echo "=== Test Complete ===\n";
        echo "If individual syncs show 0 documents but Sync All works,\n";
        echo "the issue is likely in the JavaScript/AJAX handling or\n";
        echo "collection initialization timing.\n";
    }
}

// Run the tests if this file is executed directly
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    $tester = new IndividualSyncTester();
    $tester->run_all_tests();
}
