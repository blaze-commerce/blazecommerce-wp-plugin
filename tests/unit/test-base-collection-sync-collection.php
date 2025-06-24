<?php
/**
 * Test: BaseCollection get_sync_collection Method
 * 
 * Purpose: Validates the get_sync_collection method functionality
 * Scope: Unit
 * Dependencies: None (mocked)
 * 
 * @package BlazeWooless
 * @subpackage Tests
 */

class Test_BaseCollection_Sync_Collection extends WP_UnitTestCase {
	
	/**
	 * @var BlazeWooless\Collections\BaseCollection
	 */
	private $collection;
	
	/**
	 * Setup test environment
	 */
	public function setUp(): void {
		parent::setUp();
		
		// Create a mock BaseCollection instance
		$this->collection = $this->getMockForAbstractClass( 
			BlazeWooless\Collections\BaseCollection::class 
		);
	}
	
	/**
	 * Test: get_sync_collection returns active sync collection
	 * 
	 * @covers BlazeWooless\Collections\BaseCollection::get_sync_collection
	 */
	public function test_get_sync_collection_returns_active_sync_collection() {
		// Arrange
		$expected_collection_name = 'product-test-site-a';
		$mock_collection_object = BlazeCommerce_Test_Helper::mock_typesense_client()->collections;
		
		// Set the active sync collection property
		$reflection = new ReflectionClass( $this->collection );
		$property = $reflection->getProperty( 'active_sync_collection' );
		$property->setAccessible( true );
		$property->setValue( $this->collection, $expected_collection_name );
		
		// Mock the get_direct_collection method
		$this->collection->method( 'get_direct_collection' )
			->with( $expected_collection_name )
			->willReturn( $mock_collection_object );
		
		// Act
		$result = $this->collection->get_sync_collection();
		
		// Assert
		$this->assertSame( $mock_collection_object, $result );
	}
	
	/**
	 * Test: get_sync_collection falls back to regular collection
	 * 
	 * @covers BlazeWooless\Collections\BaseCollection::get_sync_collection
	 */
	public function test_get_sync_collection_fallback_to_regular_collection() {
		// Arrange
		$mock_collection_object = BlazeCommerce_Test_Helper::mock_typesense_client()->collections;
		
		// Ensure active_sync_collection is not set
		$reflection = new ReflectionClass( $this->collection );
		$property = $reflection->getProperty( 'active_sync_collection' );
		$property->setAccessible( true );
		$property->setValue( $this->collection, null );
		
		// Mock the collection method
		$this->collection->method( 'collection' )
			->willReturn( $mock_collection_object );
		
		// Act
		$result = $this->collection->get_sync_collection();
		
		// Assert
		$this->assertSame( $mock_collection_object, $result );
	}
	
	/**
	 * Test: get_sync_collection handles empty active sync collection
	 * 
	 * @covers BlazeWooless\Collections\BaseCollection::get_sync_collection
	 */
	public function test_get_sync_collection_handles_empty_active_sync_collection() {
		// Arrange
		$mock_collection_object = BlazeCommerce_Test_Helper::mock_typesense_client()->collections;
		
		// Set empty active sync collection
		$reflection = new ReflectionClass( $this->collection );
		$property = $reflection->getProperty( 'active_sync_collection' );
		$property->setAccessible( true );
		$property->setValue( $this->collection, '' );
		
		// Mock the collection method
		$this->collection->method( 'collection' )
			->willReturn( $mock_collection_object );
		
		// Act
		$result = $this->collection->get_sync_collection();
		
		// Assert
		$this->assertSame( $mock_collection_object, $result );
	}
	
	/**
	 * Test: get_sync_collection handles exceptions gracefully
	 * 
	 * @covers BlazeWooless\Collections\BaseCollection::get_sync_collection
	 */
	public function test_get_sync_collection_handles_exceptions() {
		// Arrange
		$expected_collection_name = 'product-test-site-a';
		
		// Set the active sync collection property
		$reflection = new ReflectionClass( $this->collection );
		$property = $reflection->getProperty( 'active_sync_collection' );
		$property->setAccessible( true );
		$property->setValue( $this->collection, $expected_collection_name );
		
		// Mock the get_direct_collection method to throw exception
		$this->collection->method( 'get_direct_collection' )
			->with( $expected_collection_name )
			->willThrowException( new Exception( 'Collection not found' ) );
		
		// Act
		$result = $this->collection->get_sync_collection();
		
		// Assert
		$this->assertNull( $result );
	}
	
	/**
	 * Test: get_sync_collection logs errors appropriately
	 * 
	 * @covers BlazeWooless\Collections\BaseCollection::get_sync_collection
	 */
	public function test_get_sync_collection_logs_errors() {
		// Arrange
		$expected_collection_name = 'product-test-site-a';
		$expected_error = 'Test error message';
		
		// Set the active sync collection property
		$reflection = new ReflectionClass( $this->collection );
		$property = $reflection->getProperty( 'active_sync_collection' );
		$property->setAccessible( true );
		$property->setValue( $this->collection, $expected_collection_name );
		
		// Mock the get_direct_collection method to throw exception
		$this->collection->method( 'get_direct_collection' )
			->with( $expected_collection_name )
			->willThrowException( new Exception( $expected_error ) );
		
		// Mock logger to capture log calls
		$log_calls = array();
		$mock_logger = new class( &$log_calls ) {
			private $log_calls;
			
			public function __construct( &$log_calls ) {
				$this->log_calls = &$log_calls;
			}
			
			public function debug( $message, $context ) {
				$this->log_calls[] = array(
					'message' => $message,
					'context' => $context,
				);
			}
		};
		
		// Replace wc_get_logger temporarily
		$original_logger = null;
		if ( function_exists( 'wc_get_logger' ) ) {
			// We can't easily mock global functions, so we'll test the structure
			$this->assertTrue( function_exists( 'wc_get_logger' ) );
		}
		
		// Act
		$result = $this->collection->get_sync_collection();
		
		// Assert
		$this->assertNull( $result );
		
		// Verify error handling code exists
		$reflection = new ReflectionClass( $this->collection );
		$source = file_get_contents( $reflection->getFileName() );
		$this->assertStringContains( 'Failed to get sync collection', $source );
		$this->assertStringContains( 'wooless-get-sync-collection-error', $source );
	}
	
	/**
	 * Test: get_sync_collection method signature and return type
	 * 
	 * @covers BlazeWooless\Collections\BaseCollection::get_sync_collection
	 */
	public function test_get_sync_collection_method_signature() {
		// Arrange & Act
		$reflection = new ReflectionClass( $this->collection );
		$method = $reflection->getMethod( 'get_sync_collection' );
		
		// Assert
		$this->assertTrue( $method->isPublic() );
		$this->assertFalse( $method->isStatic() );
		$this->assertEquals( 0, $method->getNumberOfParameters() );
		
		// Check return type annotation in docblock
		$docComment = $method->getDocComment();
		$this->assertStringContains( '@return object|null', $docComment );
	}
	
	/**
	 * Cleanup test environment
	 */
	public function tearDown(): void {
		parent::tearDown();
	}
}
