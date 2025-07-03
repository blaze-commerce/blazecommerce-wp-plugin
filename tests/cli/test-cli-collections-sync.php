<?php
/**
 * Test: CLI Collections Sync Command
 * 
 * Purpose: Validates that the collections command syncs all collections in the correct order
 * Scope: CLI/Integration
 * Dependencies: WooCommerce, Typesense (mocked)
 * 
 * @package BlazeWooless
 * @subpackage Tests
 */

class Test_CLI_Collections_Sync extends WP_UnitTestCase {
	
	/**
	 * @var BlazeWooless\Features\Cli
	 */
	private $cli;
	
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
	}
	
	/**
	 * Test: collections command requires --all flag
	 * 
	 * @covers BlazeWooless\Features\Cli::collections
	 */
	public function test_collections_command_requires_all_flag() {
		// Arrange
		$args = array();
		$assoc_args = array(); // No --all flag
		
		// Act
		WP_CLI::clear_messages();
		$this->cli->collections( $args, $assoc_args );
		
		// Assert
		$messages = WP_CLI::get_messages();
		$error_messages = array_filter( $messages, function( $msg ) {
			return $msg['type'] === 'error';
		} );
		
		$this->assertNotEmpty( $error_messages );
		$error_texts = wp_list_pluck( $error_messages, 'message' );
		$this->assertContains( 'Please specify --all to sync all collections.', $error_texts );
	}
	
	/**
	 * Test: collections command with --all flag starts sync process
	 * 
	 * @covers BlazeWooless\Features\Cli::collections
	 */
	public function test_collections_command_with_all_flag() {
		// Arrange
		$args = array();
		$assoc_args = array( 'all' => true );
		
		// Mock collection instances
		$this->mock_collection_instances();
		
		// Act
		WP_CLI::clear_messages();
		
		// We expect this to call WP_CLI::halt(0), so we'll catch that
		$this->expectException( 'WP_CLI\ExitException' );
		$this->cli->collections( $args, $assoc_args );
	}
	
	/**
	 * Test: get_collections_configuration returns correct order
	 * 
	 * @covers BlazeWooless\Features\Cli::get_collections_configuration
	 */
	public function test_get_collections_configuration_order() {
		// Arrange
		$reflection = new ReflectionClass( $this->cli );
		$method = $reflection->getMethod( 'get_collections_configuration' );
		$method->setAccessible( true );
		
		// Act
		$config = $method->invoke( $this->cli );
		
		// Assert
		$expected_order = array( 'site_info', 'products', 'taxonomy', 'menu', 'page_and_post', 'navigation' );
		$actual_order = array_keys( $config );
		
		$this->assertEquals( $expected_order, $actual_order );
		
		// Verify each collection has required keys
		foreach ( $config as $collection_type => $collection_info ) {
			$this->assertArrayHasKey( 'class', $collection_info );
			$this->assertArrayHasKey( 'name', $collection_info );
			$this->assertTrue( class_exists( $collection_info['class'] ) );
		}
	}
	
	/**
	 * Test: execute_collection_sync handles unknown sync type
	 *
	 * @covers BlazeWooless\Features\Cli::execute_collection_sync
	 */
	public function test_execute_collection_sync_unknown_type() {
		// Arrange
		$reflection = new ReflectionClass( $this->cli );
		$method = $reflection->getMethod( 'execute_collection_sync' );
		$method->setAccessible( true );

		$collection_type = 'unknown_type';
		$collection_info = array(
			'class' => 'BlazeWooless\Collections\SiteInfo', // Use existing class
			'name' => 'Unknown Collection',
			'sync_type' => 'unknown_sync_type'
		);

		// Act & Assert
		$this->expectException( 'Exception' );
		$this->expectExceptionMessage( 'Unknown sync type: unknown_sync_type' );
		$method->invoke( $this->cli, $collection_type, $collection_info );
	}
	
	/**
	 * Test: display_collection_header formats correctly
	 *
	 * @covers BlazeWooless\Features\Cli::display_collection_header
	 */
	public function test_display_collection_header() {
		// Arrange
		$reflection = new ReflectionClass( $this->cli );
		$method = $reflection->getMethod( 'display_collection_header' );
		$method->setAccessible( true );

		$collection_name = 'Test Collection';

		// Act
		WP_CLI::clear_messages();
		$method->invoke( $this->cli, $collection_name );

		// Assert
		$messages = WP_CLI::get_messages();
		$line_messages = array_filter( $messages, function( $msg ) {
			return $msg['type'] === 'line';
		} );

		$this->assertNotEmpty( $line_messages );
		$line_texts = wp_list_pluck( $line_messages, 'message' );

		// Check for header formatting
		$this->assertContains( 'Syncing Test Collection collection...', $line_texts );
		$this->assertTrue( in_array( str_repeat( "=", 50 ), $line_texts ) );
	}

	/**
	 * Test: execute_batch_processing_loop handles safety limits
	 *
	 * @covers BlazeWooless\Features\Cli::execute_batch_processing_loop
	 */
	public function test_execute_batch_processing_loop_safety_limit() {
		// Arrange
		$reflection = new ReflectionClass( $this->cli );
		$method = $reflection->getMethod( 'execute_batch_processing_loop' );
		$method->setAccessible( true );

		$mock_collection = $this->createMock( 'BlazeWooless\Collections\SiteInfo' );
		$mock_collection->method( 'prepare_batch_data' )->willReturn( array() );
		$mock_collection->method( 'import_prepared_batch' )->willReturn( array() );

		// Data retriever that always returns data (would cause infinite loop without safety)
		$data_retriever = function( $page, $batch_size ) {
			return array( 'dummy_data' ); // Always return data
		};

		// Act
		WP_CLI::clear_messages();
		$result = $method->invoke( $this->cli, $mock_collection, 50, $data_retriever, 'site_info' );

		// Assert
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'imported_count', $result );
		$this->assertArrayHasKey( 'total_imports', $result );

		// Check that warning was issued for hitting iteration limit
		$messages = WP_CLI::get_messages();
		$warning_messages = array_filter( $messages, function( $msg ) {
			return $msg['type'] === 'warning';
		} );

		$this->assertNotEmpty( $warning_messages );
	}
	
	/**
	 * Test: configuration validation catches invalid settings
	 *
	 * @covers BlazeWooless\Features\Cli::validate_collections_configuration
	 */
	public function test_validate_collections_configuration_invalid_class() {
		// Arrange
		$reflection = new ReflectionClass( $this->cli );
		$method = $reflection->getMethod( 'validate_collections_configuration' );
		$method->setAccessible( true );

		$invalid_config = array(
			'test_collection' => array(
				'class' => 'NonExistentClass',
				'name' => 'Test Collection',
				'sync_type' => 'single_batch'
			)
		);

		// Act & Assert
		$this->expectException( 'Exception' );
		$this->expectExceptionMessage( "Collection class 'NonExistentClass' does not exist" );
		$method->invoke( $this->cli, $invalid_config );
	}
	
	/**
	 * Test: configuration validation catches invalid sync type
	 *
	 * @covers BlazeWooless\Features\Cli::validate_collections_configuration
	 */
	public function test_validate_collections_configuration_invalid_sync_type() {
		// Arrange
		$reflection = new ReflectionClass( $this->cli );
		$method = $reflection->getMethod( 'validate_collections_configuration' );
		$method->setAccessible( true );

		$invalid_config = array(
			'test_collection' => array(
				'class' => 'BlazeWooless\Collections\SiteInfo',
				'name' => 'Test Collection',
				'sync_type' => 'invalid_sync_type'
			)
		);

		// Act & Assert
		$this->expectException( 'Exception' );
		$this->expectExceptionMessage( "Invalid sync_type 'invalid_sync_type'" );
		$method->invoke( $this->cli, $invalid_config );
	}
	
	/**
	 * Test: configuration validation catches missing required method
	 *
	 * @covers BlazeWooless\Features\Cli::validate_collections_configuration
	 */
	public function test_validate_collections_configuration_missing_method() {
		// Arrange
		$reflection = new ReflectionClass( $this->cli );
		$method = $reflection->getMethod( 'validate_collections_configuration' );
		$method->setAccessible( true );

		$invalid_config = array(
			'test_collection' => array(
				'class' => 'BlazeWooless\Collections\SiteInfo',
				'name' => 'Test Collection',
				'sync_type' => 'batch_with_ids',
				'id_method' => 'non_existent_method'
			)
		);

		// Act & Assert
		$this->expectException( 'Exception' );
		$this->expectExceptionMessage( "Method 'non_existent_method' does not exist" );
		$method->invoke( $this->cli, $invalid_config );
	}
	
	/**
	 * Test: adaptive safety limits return correct values
	 *
	 * @covers BlazeWooless\Features\Cli::get_safety_limit
	 */
	public function test_get_safety_limit_adaptive_values() {
		// Arrange
		$reflection = new ReflectionClass( $this->cli );
		$method = $reflection->getMethod( 'get_safety_limit' );
		$method->setAccessible( true );

		// Act & Assert
		$this->assertEquals( 2000, $method->invoke( $this->cli, 'products' ) );
		$this->assertEquals( 800, $method->invoke( $this->cli, 'taxonomy' ) );
		$this->assertEquals( 1500, $method->invoke( $this->cli, 'page_and_post' ) );
		$this->assertEquals( 200, $method->invoke( $this->cli, 'navigation' ) );
		$this->assertEquals( 100, $method->invoke( $this->cli, 'menu' ) );
		$this->assertEquals( 50, $method->invoke( $this->cli, 'site_info' ) );
		$this->assertEquals( 1000, $method->invoke( $this->cli, 'unknown_type' ) ); // Default
	}
	
	/**
	 * Test: progress percentage calculation
	 *
	 * @covers BlazeWooless\Features\Cli::calculate_progress_percentage
	 */
	public function test_calculate_progress_percentage() {
		// Arrange
		$reflection = new ReflectionClass( $this->cli );
		$method = $reflection->getMethod( 'calculate_progress_percentage' );
		$method->setAccessible( true );

		// Act & Assert
		$this->assertEquals( 50.0, $method->invoke( $this->cli, 50, 100 ) );
		$this->assertEquals( 25.0, $method->invoke( $this->cli, 25, 100 ) );
		$this->assertEquals( 100.0, $method->invoke( $this->cli, 150, 100 ) ); // Capped at 100
		$this->assertEquals( 0.0, $method->invoke( $this->cli, 50, 0 ) ); // Division by zero protection
	}
	
	/**
	 * Test: memory-based garbage collection trigger
	 *
	 * @covers BlazeWooless\Features\Cli::should_trigger_gc
	 */
	public function test_should_trigger_gc_memory_threshold() {
		// Arrange
		$reflection = new ReflectionClass( $this->cli );
		$method = $reflection->getMethod( 'should_trigger_gc' );
		$method->setAccessible( true );

		// Act
		$result = $method->invoke( $this->cli );

		// Assert
		$this->assertIsBool( $result );
		// Note: The actual result depends on current memory usage, so we just verify it returns a boolean
	}
	
	/**
	 * Test: collections sync with filter disabled logging
	 *
	 * @covers BlazeWooless\Features\Cli::execute_collection_sync
	 */
	public function test_collections_sync_with_filter_disabled() {
		// Arrange
		$reflection = new ReflectionClass( $this->cli );
		$method = $reflection->getMethod( 'execute_collection_sync' );
		$method->setAccessible( true );

		$collection_type = 'taxonomy';
		$collection_info = array(
			'class' => 'BlazeWooless\Collections\Taxonomy',
			'name' => 'Taxonomy',
			'sync_type' => 'batch_with_query',
			'query_method' => 'get_query_args',
			'filter_key' => 'blazecommerce/settings/sync/taxonomies'
		);

		// Mock the filter to return false (disabled)
		add_filter( 'blazecommerce/settings/sync/taxonomies', '__return_false' );

		// Act
		WP_CLI::clear_messages();
		$result = $method->invoke( $this->cli, $collection_type, $collection_info );

		// Assert
		$this->assertEquals( 'Taxonomy', $result['collection'] );
		$this->assertEquals( 0, $result['total_imports'] );
		$this->assertEquals( 0, $result['successful_imports'] );
		$this->assertTrue( $result['skipped'] );
		$this->assertEquals( 'disabled_by_filter', $result['reason'] );

		// Check warning message was logged
		$messages = WP_CLI::get_messages();
		$warning_messages = array_filter( $messages, function( $msg ) {
			return $msg['type'] === 'warning';
		} );
		$this->assertNotEmpty( $warning_messages );

		// Cleanup
		remove_filter( 'blazecommerce/settings/sync/taxonomies', '__return_false' );
	}
	
	/**
	 * Test: collections sync partial failure handling
	 *
	 * @covers BlazeWooless\Features\Cli::collections
	 */
	public function test_collections_sync_partial_failure() {
		// Arrange
		$args = array();
		$assoc_args = array( 'all' => true );

		// Mock one collection to fail and others to succeed
		$this->mock_collection_instances_with_failure();

		// Act
		WP_CLI::clear_messages();

		// We expect this to call WP_CLI::halt(0), so we'll catch that
		$this->expectException( 'WP_CLI\ExitException' );
		$this->cli->collections( $args, $assoc_args );
	}
	
	/**
	 * Test: estimate total iterations for different collection types
	 *
	 * @covers BlazeWooless\Features\Cli::estimate_total_iterations
	 */
	public function test_estimate_total_iterations() {
		// Arrange
		$reflection = new ReflectionClass( $this->cli );
		$method = $reflection->getMethod( 'estimate_total_iterations' );
		$method->setAccessible( true );

		// Act & Assert
		$this->assertEquals( 200, $method->invoke( $this->cli, 'products' ) );
		$this->assertEquals( 50, $method->invoke( $this->cli, 'taxonomy' ) );
		$this->assertEquals( 100, $method->invoke( $this->cli, 'page_and_post' ) );
		$this->assertEquals( 10, $method->invoke( $this->cli, 'navigation' ) );
		$this->assertEquals( 5, $method->invoke( $this->cli, 'menu' ) );
		$this->assertEquals( 1, $method->invoke( $this->cli, 'site_info' ) );
		$this->assertEquals( 0, $method->invoke( $this->cli, 'unknown_type' ) ); // Default
	}
	
	/**
	 * Test: enhanced summary with skip counts
	 *
	 * @covers BlazeWooless\Features\Cli::display_collections_summary
	 */
	public function test_display_collections_summary_with_skips() {
		// Arrange
		$reflection = new ReflectionClass( $this->cli );
		$method = $reflection->getMethod( 'display_collections_summary' );
		$method->setAccessible( true );

		$collection_results = array(
			array( 'collection' => 'Products', 'total_imports' => 100, 'successful_imports' => 95 ),
			array( 'collection' => 'Taxonomy', 'skipped' => true, 'reason' => 'disabled_by_filter', 'total_imports' => 0, 'successful_imports' => 0 ),
			array( 'collection' => 'Menu', 'error' => 'Connection failed', 'total_imports' => 0, 'successful_imports' => 0 )
		);

		// Act
		WP_CLI::clear_messages();
		$method->invoke( $this->cli, $collection_results, microtime( true ), 100, 95 );

		// Assert
		$messages = WP_CLI::get_messages();
		$line_messages = wp_list_pluck( array_filter( $messages, function( $msg ) {
			return $msg['type'] === 'line';
		} ), 'message' );

		// Check for skip indicator and success/error counts
		$summary_text = implode( '\n', $line_messages );
		$this->assertStringContains( 'SKIPPED', $summary_text );
		$this->assertStringContains( 'Successful: 1', $summary_text );
		$this->assertStringContains( 'Failed: 1', $summary_text );
		$this->assertStringContains( 'Skipped: 1', $summary_text );
	}
	
	/**
	 * Mock collection instances for testing
	 */
	private function mock_collection_instances() {
		// Mock each collection class to return a mock instance
		$collection_classes = array(
			'BlazeWooless\Collections\SiteInfo',
			'BlazeWooless\Collections\Product',
			'BlazeWooless\Collections\Taxonomy',
			'BlazeWooless\Collections\Menu',
			'BlazeWooless\Collections\Page',
			'BlazeWooless\Collections\Navigation'
		);
		
		foreach ( $collection_classes as $class ) {
			if ( class_exists( $class ) ) {
				$mock = $this->createMock( $class );
				$mock->method( 'initialize' )->willReturn( true );
				$mock->method( 'prepare_batch_data' )->willReturn( array() );
				$mock->method( 'import_prepared_batch' )->willReturn( array() );
				$mock->method( 'get_inactive_collection_name' )->willReturn( 'test-collection-name' );
				$mock->method( 'collection_name' )->willReturn( 'test-collection-name' );
			}
		}
	}
	
	/**
	 * Mock collection instances with one failing for testing partial failure
	 */
	private function mock_collection_instances_with_failure() {
		// Mock each collection class to return a mock instance
		$collection_classes = array(
			'BlazeWooless\Collections\SiteInfo',
			'BlazeWooless\Collections\Product',
			'BlazeWooless\Collections\Taxonomy',
			'BlazeWooless\Collections\Menu',
			'BlazeWooless\Collections\Page',
			'BlazeWooless\Collections\Navigation'
		);
		
		foreach ( $collection_classes as $class ) {
			if ( class_exists( $class ) ) {
				$mock = $this->createMock( $class );
				
				// Make Product collection fail for testing
				if ( $class === 'BlazeWooless\Collections\Product' ) {
					$mock->method( 'initialize' )->will( $this->throwException( new \Exception( 'Mock product sync failure' ) ) );
				} else {
					$mock->method( 'initialize' )->willReturn( true );
					$mock->method( 'prepare_batch_data' )->willReturn( array( 'test_data' ) );
					$mock->method( 'import_prepared_batch' )->willReturn( array( 'success' ) );
				}
				
				$mock->method( 'get_inactive_collection_name' )->willReturn( 'test-collection-name' );
				$mock->method( 'collection_name' )->willReturn( 'test-collection-name' );
			}
		}
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
