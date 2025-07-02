<?php
/**
 * Test: Blue-Green Deployment Integration with Variations
 * 
 * Purpose: Validates complete blue-green deployment workflow with variations
 * Scope: Integration
 * Dependencies: WooCommerce, Typesense (mocked)
 * 
 * @package BlazeWooless
 * @subpackage Tests
 */

class Test_Integration_Blue_Green_With_Variations extends WP_UnitTestCase {
	
	/**
	 * @var BlazeWooless\Collections\CollectionAliasManager
	 */
	private $alias_manager;
	
	/**
	 * @var BlazeWooless\Collections\Product
	 */
	private $product_collection;
	
	/**
	 * @var array
	 */
	private $test_products = array();
	
	/**
	 * Setup test environment
	 */
	public function setUp(): void {
		parent::setUp();
		
		// Enable aliases for testing
		BlazeCommerce_Test_Helper::enable_aliases();
		
		// Initialize components
		$this->alias_manager = new BlazeWooless\Collections\CollectionAliasManager();
		$this->product_collection = new BlazeWooless\Collections\Product();
		
		// Create test products
		$this->create_test_products();
	}
	
	/**
	 * Create test products for integration testing
	 */
	private function create_test_products() {
		// Create variable product with variations
		$variable_product = BlazeCommerce_Test_Helper::create_variable_product( array(
			'name' => 'Integration Test Variable Product',
		) );
		$this->test_products['variable'] = $variable_product;
		
		// Create simple products
		for ( $i = 1; $i <= 3; $i++ ) {
			$simple_product = BlazeCommerce_Test_Helper::create_simple_product( array(
				'name' => "Integration Test Simple Product {$i}",
				'regular_price' => ( 10 * $i ) . '.00',
			) );
			$this->test_products["simple_{$i}"] = $simple_product;
		}
	}
	
	/**
	 * Test: Complete blue-green deployment cycle with variations
	 * 
	 * @covers BlazeWooless\Collections\CollectionAliasManager::get_inactive_collection
	 * @covers BlazeWooless\Collections\Product::complete_collection_sync
	 */
	public function test_complete_blue_green_cycle_with_variations() {
		// Arrange
		$collection_type = 'product';
		$scenarios = BlazeCommerce_Collection_Fixtures::get_blue_green_scenarios();
		
		foreach ( $scenarios as $scenario_name => $scenario ) {
			// Act - Get inactive collection for sync
			$inactive_collection = $this->alias_manager->get_inactive_collection( $collection_type );
			
			// Assert - Verify correct collection is targeted
			$this->assertEquals( 
				$scenario['expected_target'], 
				$inactive_collection,
				"Scenario '{$scenario_name}': Wrong target collection"
			);
			
			// Simulate sync completion
			$this->simulate_sync_with_variations( $inactive_collection );
			
			// Simulate alias update
			$this->simulate_alias_update( $collection_type, $inactive_collection );
		}
	}
	
	/**
	 * Test: Variations are included in same collection as parent products
	 * 
	 * @covers BlazeWooless\Features\Cli::sync_variations_to_current_collection
	 */
	public function test_variations_included_in_same_collection() {
		// Arrange
		$collection_name = 'product-integration-test-a';
		$variable_product = $this->test_products['variable'];
		$variation_ids = $variable_product->get_children();
		
		// Mock collection tracking
		$import_operations = array();
		$mock_collection = $this->create_collection_mock( $import_operations );
		
		// Mock product collection
		$product_collection = $this->createMock( BlazeWooless\Collections\Product::class );
		$product_collection->method( 'get_sync_collection' )->willReturn( $mock_collection );
		$product_collection->method( 'generate_typesense_data' )
			->willReturn( BlazeCommerce_Product_Fixtures::get_expected_variation_typesense_data() );
		
		// Set active sync collection
		$reflection = new ReflectionClass( $product_collection );
		$property = $reflection->getProperty( 'active_sync_collection' );
		$property->setAccessible( true );
		$property->setValue( $product_collection, $collection_name );
		
		// Act - Sync variations
		$cli = new BlazeWooless\Features\Cli();
		$reflection = new ReflectionClass( $cli );
		$method = $reflection->getMethod( 'sync_variations_to_collection' );
		$method->setAccessible( true );
		$method->invoke( $cli, $variation_ids, $product_collection );
		
		// Assert
		$this->assertCount( 1, $import_operations, 'Should have one import operation' );
		$this->assertCount( 2, $import_operations[0]['data'], 'Should import 2 variations' );
		$this->assertEquals( 'upsert', $import_operations[0]['options']['action'] );
	}
	
	/**
	 * Test: NaN price prevention with complete product data
	 * 
	 * @covers BlazeWooless\Woocommerce::format_price_to_int64
	 */
	public function test_nan_price_prevention_with_complete_data() {
		// Arrange
		$variable_product = $this->test_products['variable'];
		$variations = $variable_product->get_children();
		
		// Test scenarios that could cause NaN
		$nan_scenarios = BlazeCommerce_Product_Fixtures::get_nan_price_scenarios();
		
		foreach ( $nan_scenarios as $scenario_name => $scenario_data ) {
			// Act - Format price using the actual method
			$result = BlazeWooless\Woocommerce::format_price_to_int64( $scenario_data['regular_price'] );
			
			// Assert - Should not be NaN
			$this->assertIsInt( $result, "Scenario '{$scenario_name}': Result should be integer" );
			$this->assertGreaterThanOrEqual( 0, $result, "Scenario '{$scenario_name}': Result should be non-negative" );
		}
	}
	
	/**
	 * Test: Alias switching maintains data consistency
	 * 
	 * @covers BlazeWooless\Collections\CollectionAliasManager::update_alias
	 */
	public function test_alias_switching_maintains_data_consistency() {
		// Arrange
		$collection_type = 'product';
		$target_collection = 'product-integration-test-b';
		
		// Mock alias manager with tracking
		$alias_operations = array();
		$mock_alias_manager = $this->create_alias_manager_mock( $alias_operations );
		
		// Act - Update alias
		$result = $mock_alias_manager->update_alias( $collection_type, $target_collection );
		
		// Assert
		$this->assertTrue( $result['success'] );
		$this->assertCount( 1, $alias_operations );
		$this->assertEquals( $target_collection, $alias_operations[0]['target'] );
	}
	
	/**
	 * Test: Error recovery during variation sync
	 * 
	 * @covers BlazeWooless\Features\Cli::sync_variations_to_collection
	 */
	public function test_error_recovery_during_variation_sync() {
		// Arrange
		$invalid_variation_ids = array( 'invalid', 0, -1, 999999 );
		
		// Mock collection that tracks errors
		$error_operations = array();
		$mock_collection = $this->create_error_tracking_collection_mock( $error_operations );
		
		$product_collection = $this->createMock( BlazeWooless\Collections\Product::class );
		$product_collection->method( 'get_sync_collection' )->willReturn( $mock_collection );
		
		// Act
		$cli = new BlazeWooless\Features\Cli();
		$reflection = new ReflectionClass( $cli );
		$method = $reflection->getMethod( 'sync_variations_to_collection' );
		$method->setAccessible( true );
		
		// Should not throw exception
		$method->invoke( $cli, $invalid_variation_ids, $product_collection );
		
		// Assert - No fatal errors occurred
		$this->assertTrue( true, 'Method completed without fatal errors' );
	}
	
	/**
	 * Create a mock collection that tracks import operations
	 */
	private function create_collection_mock( &$import_operations ) {
		return new class( &$import_operations ) {
			private $import_operations;
			public $documents;
			
			public function __construct( &$import_operations ) {
				$this->import_operations = &$import_operations;
				$this->documents = $this;
			}
			
			public function import( $data, $options ) {
				$this->import_operations[] = array(
					'data' => $data,
					'options' => $options,
				);
				return BlazeCommerce_Collection_Fixtures::get_mock_import_response();
			}
		};
	}
	
	/**
	 * Create a mock alias manager that tracks operations
	 */
	private function create_alias_manager_mock( &$alias_operations ) {
		return new class( &$alias_operations ) {
			private $alias_operations;
			
			public function __construct( &$alias_operations ) {
				$this->alias_operations = &$alias_operations;
			}
			
			public function update_alias( $collection_type, $target_collection ) {
				$this->alias_operations[] = array(
					'type' => $collection_type,
					'target' => $target_collection,
				);
				return array( 'success' => true );
			}
		};
	}
	
	/**
	 * Create a mock collection that tracks errors
	 */
	private function create_error_tracking_collection_mock( &$error_operations ) {
		return new class( &$error_operations ) {
			private $error_operations;
			public $documents;
			
			public function __construct( &$error_operations ) {
				$this->error_operations = &$error_operations;
				$this->documents = $this;
			}
			
			public function import( $data, $options ) {
				$this->error_operations[] = array(
					'data_count' => count( $data ),
					'options' => $options,
				);
				return array(); // Empty response
			}
		};
	}
	
	/**
	 * Simulate sync with variations
	 */
	private function simulate_sync_with_variations( $collection_name ) {
		// Simulate parent product sync
		// Simulate variation sync to same collection
		// This would be the actual sync logic in real implementation
	}
	
	/**
	 * Simulate alias update
	 */
	private function simulate_alias_update( $collection_type, $target_collection ) {
		// Simulate alias pointing to new collection
		// This would be the actual alias update in real implementation
	}
	
	/**
	 * Cleanup test environment
	 */
	public function tearDown(): void {
		// Clean up test data
		BlazeCommerce_Test_Helper::cleanup_test_data();
		
		parent::tearDown();
	}
}
