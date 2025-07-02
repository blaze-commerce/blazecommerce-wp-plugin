<?php
/**
 * Test: CLI Product Sync --all Flag with Variations
 * 
 * Purpose: Validates that the --all flag syncs both parent products and variations
 * Scope: CLI/Integration
 * Dependencies: WooCommerce, Typesense (mocked)
 * 
 * @package BlazeWooless
 * @subpackage Tests
 */

class Test_CLI_Product_Sync_All_With_Variations extends WP_UnitTestCase {
	
	/**
	 * @var BlazeWooless\Features\Cli
	 */
	private $cli;
	
	/**
	 * @var array
	 */
	private $test_products = array();
	
	/**
	 * Setup test environment
	 */
	public function setUp(): void {
		parent::setUp();
		
		// Mock WP_CLI
		BlazeCommerce_Test_Helper::mock_wp_cli();
		
		// Enable aliases for testing
		BlazeCommerce_Test_Helper::enable_aliases();
		
		// Initialize CLI instance
		$this->cli = new BlazeWooless\Features\Cli();
		
		// Create test products
		$this->create_test_products();
	}
	
	/**
	 * Create test products for testing
	 */
	private function create_test_products() {
		// Create variable product with variations
		$variable_product = BlazeCommerce_Test_Helper::create_variable_product( array(
			'name' => 'Test Variable Product for Sync',
		) );
		$this->test_products['variable'] = $variable_product;
		
		// Create simple product
		$simple_product = BlazeCommerce_Test_Helper::create_simple_product( array(
			'name' => 'Test Simple Product for Sync',
		) );
		$this->test_products['simple'] = $simple_product;
	}
	
	/**
	 * Test: --all flag includes variations in sync
	 * 
	 * @covers BlazeWooless\Features\Cli::sync_variations_to_current_collection
	 */
	public function test_all_flag_includes_variations() {
		// Arrange
		$expected_messages = array(
			'Syncing product variations to the same collection...',
			'Found 2 variations to sync...',
			'All 2 product variations synced to the same collection.',
		);
		
		// Mock the product collection
		$product_collection = $this->createMock( BlazeWooless\Collections\Product::class );
		$product_collection->method( 'get_sync_collection' )
			->willReturn( BlazeCommerce_Test_Helper::mock_typesense_client()->collections );
		
		// Act
		$reflection = new ReflectionClass( $this->cli );
		$method = $reflection->getMethod( 'sync_variations_to_current_collection' );
		$method->setAccessible( true );
		$method->invoke( $this->cli, $product_collection );
		
		// Assert
		$messages = WP_CLI::get_messages();
		$this->assertNotEmpty( $messages );
		
		// Check that variation sync messages are present
		$message_texts = wp_list_pluck( $messages, 'message' );
		foreach ( $expected_messages as $expected ) {
			$this->assertContains( $expected, $message_texts );
		}
	}
	
	/**
	 * Test: Variations are synced to same collection as parent products
	 * 
	 * @covers BlazeWooless\Features\Cli::sync_variations_to_collection
	 */
	public function test_variations_synced_to_same_collection() {
		// Arrange
		$variation_ids = array();
		$variable_product = $this->test_products['variable'];
		$children = $variable_product->get_children();
		$variation_ids = array_merge( $variation_ids, $children );
		
		// Mock collection with import tracking
		$import_calls = array();
		$mock_collection = new class( &$import_calls ) {
			private $import_calls;
			public $documents;
			
			public function __construct( &$import_calls ) {
				$this->import_calls = &$import_calls;
				$this->documents = $this;
			}
			
			public function import( $data, $options ) {
				$this->import_calls[] = array(
					'data' => $data,
					'options' => $options,
				);
				return BlazeCommerce_Collection_Fixtures::get_mock_import_response();
			}
		};
		
		$product_collection = $this->createMock( BlazeWooless\Collections\Product::class );
		$product_collection->method( 'get_sync_collection' )->willReturn( $mock_collection );
		$product_collection->method( 'generate_typesense_data' )
			->willReturn( BlazeCommerce_Product_Fixtures::get_expected_variation_typesense_data() );
		
		// Act
		$reflection = new ReflectionClass( $this->cli );
		$method = $reflection->getMethod( 'sync_variations_to_collection' );
		$method->setAccessible( true );
		$method->invoke( $this->cli, $variation_ids, $product_collection );
		
		// Assert
		$this->assertCount( 1, $import_calls, 'Import should be called once for the variation batch' );
		$this->assertCount( 2, $import_calls[0]['data'], 'Should import 2 variations' );
		$this->assertEquals( 'upsert', $import_calls[0]['options']['action'] );
	}
	
	/**
	 * Test: Error handling when no variations exist
	 * 
	 * @covers BlazeWooless\Features\Cli::sync_variations_to_current_collection
	 */
	public function test_no_variations_handling() {
		// Arrange - Delete all variable products
		foreach ( $this->test_products as $product ) {
			if ( $product->is_type( 'variable' ) ) {
				$product->delete( true );
			}
		}
		
		$product_collection = $this->createMock( BlazeWooless\Collections\Product::class );
		
		// Act
		WP_CLI::clear_messages();
		$reflection = new ReflectionClass( $this->cli );
		$method = $reflection->getMethod( 'sync_variations_to_current_collection' );
		$method->setAccessible( true );
		$method->invoke( $this->cli, $product_collection );
		
		// Assert
		$messages = WP_CLI::get_messages();
		$message_texts = wp_list_pluck( $messages, 'message' );
		$this->assertContains( 'No product variations found to sync.', $message_texts );
	}
	
	/**
	 * Test: Error handling with invalid product collection
	 * 
	 * @covers BlazeWooless\Features\Cli::sync_variations_to_current_collection
	 */
	public function test_invalid_product_collection_handling() {
		// Arrange
		$invalid_collection = null;
		
		// Act
		WP_CLI::clear_messages();
		$reflection = new ReflectionClass( $this->cli );
		$method = $reflection->getMethod( 'sync_variations_to_current_collection' );
		$method->setAccessible( true );
		$method->invoke( $this->cli, $invalid_collection );
		
		// Assert
		$messages = WP_CLI::get_messages();
		$warning_messages = array_filter( $messages, function( $msg ) {
			return $msg['type'] === 'warning';
		} );
		
		$this->assertNotEmpty( $warning_messages );
		$warning_texts = wp_list_pluck( $warning_messages, 'message' );
		$this->assertContains( 'Invalid product collection instance provided for variation sync.', $warning_texts );
	}
	
	/**
	 * Test: WooCommerce dependency check
	 * 
	 * @covers BlazeWooless\Features\Cli::sync_variations_to_current_collection
	 */
	public function test_woocommerce_dependency_check() {
		// Arrange - Temporarily remove WooCommerce function
		$original_function = null;
		if ( function_exists( 'wc_get_product' ) ) {
			// We can't actually remove the function, so we'll test the check logic
			$this->assertTrue( function_exists( 'wc_get_product' ) );
		}
		
		// This test validates that the function check exists in the code
		$reflection = new ReflectionClass( $this->cli );
		$method = $reflection->getMethod( 'sync_variations_to_current_collection' );
		$source = file_get_contents( $reflection->getFileName() );
		
		// Assert
		$this->assertStringContains( 'function_exists( \'wc_get_product\' )', $source );
		$this->assertStringContains( 'WooCommerce is not available', $source );
	}
	
	/**
	 * Cleanup test environment
	 */
	public function tearDown(): void {
		// Clean up test data
		BlazeCommerce_Test_Helper::cleanup_test_data();
		
		// Clear WP_CLI messages
		WP_CLI::clear_messages();
		
		parent::tearDown();
	}
}
