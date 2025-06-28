<?php
/**
 * Collection Test Fixtures
 * 
 * Sample collection and alias data for testing
 * 
 * @package BlazeWooless
 * @subpackage Tests
 */

class BlazeCommerce_Collection_Fixtures {
	
	/**
	 * Get sample collection schema
	 * 
	 * @return array
	 */
	public static function get_product_collection_schema() {
		return array(
			'name' => 'product-test-site-a',
			'fields' => array(
				array(
					'name' => 'id',
					'type' => 'string',
				),
				array(
					'name' => 'name',
					'type' => 'string',
				),
				array(
					'name' => 'price',
					'type' => 'object',
				),
				array(
					'name' => 'updatedAt',
					'type' => 'int64',
				),
			),
			'default_sorting_field' => 'updatedAt',
			'enable_nested_fields' => true,
		);
	}
	
	/**
	 * Get sample alias configuration
	 * 
	 * @return array
	 */
	public static function get_alias_configuration() {
		return array(
			'product' => array(
				'alias_name' => 'product-test-site',
				'collection_a' => 'product-test-site-a',
				'collection_b' => 'product-test-site-b',
				'current_target' => 'product-test-site-a',
			),
			'taxonomy' => array(
				'alias_name' => 'taxonomy-test-site',
				'collection_a' => 'taxonomy-test-site-a',
				'collection_b' => 'taxonomy-test-site-b',
				'current_target' => 'taxonomy-test-site-b',
			),
		);
	}
	
	/**
	 * Get mock collection response
	 * 
	 * @return array
	 */
	public static function get_mock_collection_response() {
		return array(
			'collections' => array(
				array(
					'name' => 'product-test-site-a',
					'num_documents' => 150,
					'created_at' => time() - 3600,
				),
				array(
					'name' => 'product-test-site-b',
					'num_documents' => 0,
					'created_at' => time(),
				),
			),
		);
	}
	
	/**
	 * Get mock alias response
	 * 
	 * @return array
	 */
	public static function get_mock_alias_response() {
		return array(
			'aliases' => array(
				array(
					'name' => 'product-test-site',
					'collection_name' => 'product-test-site-a',
				),
				array(
					'name' => 'taxonomy-test-site',
					'collection_name' => 'taxonomy-test-site-b',
				),
			),
		);
	}
	
	/**
	 * Get sample import response
	 * 
	 * @return array
	 */
	public static function get_mock_import_response() {
		return array(
			array(
				'success' => true,
				'document' => array(
					'id' => '123',
					'name' => 'Test Product',
				),
			),
			array(
				'success' => true,
				'document' => array(
					'id' => '124',
					'name' => 'Test Variation',
				),
			),
		);
	}
	
	/**
	 * Get sample import error response
	 * 
	 * @return array
	 */
	public static function get_mock_import_error_response() {
		return array(
			array(
				'success' => false,
				'error' => 'Invalid price format',
				'document' => array(
					'id' => '125',
					'name' => 'Invalid Product',
				),
			),
		);
	}
	
	/**
	 * Get blue-green deployment test scenarios
	 * 
	 * @return array
	 */
	public static function get_blue_green_scenarios() {
		return array(
			'initial_sync' => array(
				'description' => 'First sync with no existing collections',
				'existing_collections' => array(),
				'existing_aliases' => array(),
				'expected_target' => 'product-test-site-a',
				'expected_alias' => 'product-test-site',
			),
			'second_sync' => array(
				'description' => 'Second sync with existing collection A',
				'existing_collections' => array( 'product-test-site-a' ),
				'existing_aliases' => array(
					'product-test-site' => 'product-test-site-a',
				),
				'expected_target' => 'product-test-site-b',
				'expected_alias' => 'product-test-site',
			),
			'third_sync' => array(
				'description' => 'Third sync switching back to collection A',
				'existing_collections' => array( 'product-test-site-a', 'product-test-site-b' ),
				'existing_aliases' => array(
					'product-test-site' => 'product-test-site-b',
				),
				'expected_target' => 'product-test-site-a',
				'expected_alias' => 'product-test-site',
			),
		);
	}
	
	/**
	 * Get error scenarios for testing
	 * 
	 * @return array
	 */
	public static function get_error_scenarios() {
		return array(
			'typesense_unavailable' => array(
				'description' => 'Typesense service unavailable',
				'error_type' => 'connection_error',
				'expected_behavior' => 'graceful_degradation',
			),
			'invalid_collection_name' => array(
				'description' => 'Invalid collection name format',
				'error_type' => 'validation_error',
				'expected_behavior' => 'error_message',
			),
			'alias_update_failure' => array(
				'description' => 'Alias update operation fails',
				'error_type' => 'api_error',
				'expected_behavior' => 'rollback_changes',
			),
		);
	}
}
