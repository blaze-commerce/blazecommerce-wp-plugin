<?php
/**
 * Test Helper Functions
 * 
 * Common helper functions for testing BlazeCommerce functionality
 * 
 * @package BlazeWooless
 * @subpackage Tests
 */

class BlazeCommerce_Test_Helper {
	
	/**
	 * Create a test product with variations
	 * 
	 * @param array $args Product arguments
	 * @return WC_Product_Variable
	 */
	public static function create_variable_product( $args = array() ) {
		$defaults = array(
			'name' => 'Test Variable Product',
			'regular_price' => '20.00',
			'status' => 'publish',
		);
		
		$args = wp_parse_args( $args, $defaults );
		
		$product = new WC_Product_Variable();
		$product->set_name( $args['name'] );
		$product->set_status( $args['status'] );
		$product->save();
		
		// Create variations
		$variation_1 = new WC_Product_Variation();
		$variation_1->set_parent_id( $product->get_id() );
		$variation_1->set_regular_price( '15.00' );
		$variation_1->set_attributes( array( 'size' => 'small' ) );
		$variation_1->set_status( 'publish' );
		$variation_1->save();
		
		$variation_2 = new WC_Product_Variation();
		$variation_2->set_parent_id( $product->get_id() );
		$variation_2->set_regular_price( '25.00' );
		$variation_2->set_attributes( array( 'size' => 'large' ) );
		$variation_2->set_status( 'publish' );
		$variation_2->save();
		
		return $product;
	}
	
	/**
	 * Create a simple test product
	 * 
	 * @param array $args Product arguments
	 * @return WC_Product_Simple
	 */
	public static function create_simple_product( $args = array() ) {
		$defaults = array(
			'name' => 'Test Simple Product',
			'regular_price' => '10.00',
			'status' => 'publish',
		);
		
		$args = wp_parse_args( $args, $defaults );
		
		$product = new WC_Product_Simple();
		$product->set_name( $args['name'] );
		$product->set_regular_price( $args['regular_price'] );
		$product->set_status( $args['status'] );
		$product->save();
		
		return $product;
	}
	
	/**
	 * Mock Typesense client for testing
	 * 
	 * @return object Mock client
	 */
	public static function mock_typesense_client() {
		return new class {
			public $collections;
			public $aliases;
			
			public function __construct() {
				$this->collections = new class {
					public function retrieve() {
						return array( 'collections' => array() );
					}
				};
				
				$this->aliases = new class {
					public function upsert( $name, $mapping ) {
						return array( 'success' => true );
					}
				};
			}
		};
	}
	
	/**
	 * Clean up test data
	 */
	public static function cleanup_test_data() {
		// Delete test products
		$products = wc_get_products( array(
			'name' => 'Test%',
			'limit' => -1,
		) );
		
		foreach ( $products as $product ) {
			$product->delete( true );
		}
	}
	
	/**
	 * Enable aliases for testing
	 */
	public static function enable_aliases() {
		add_filter( 'blazecommerce/use_collection_aliases', '__return_true' );
	}
	
	/**
	 * Disable aliases for testing
	 */
	public static function disable_aliases() {
		add_filter( 'blazecommerce/use_collection_aliases', '__return_false' );
	}
	
	/**
	 * Mock WP_CLI for testing CLI commands
	 */
	public static function mock_wp_cli() {
		if ( ! class_exists( 'WP_CLI' ) ) {
			class WP_CLI {
				public static $messages = array();
				
				public static function line( $message ) {
					self::$messages[] = array( 'type' => 'line', 'message' => $message );
				}
				
				public static function success( $message ) {
					self::$messages[] = array( 'type' => 'success', 'message' => $message );
				}
				
				public static function warning( $message ) {
					self::$messages[] = array( 'type' => 'warning', 'message' => $message );
				}
				
				public static function error( $message ) {
					self::$messages[] = array( 'type' => 'error', 'message' => $message );
				}
				
				public static function halt( $code ) {
					// Do nothing in tests
				}
				
				public static function get_messages() {
					return self::$messages;
				}
				
				public static function clear_messages() {
					self::$messages = array();
				}
			}
		}
	}
}
