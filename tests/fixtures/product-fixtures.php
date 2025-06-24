<?php
/**
 * Product Test Fixtures
 * 
 * Sample product data for testing
 * 
 * @package BlazeWooless
 * @subpackage Tests
 */

class BlazeCommerce_Product_Fixtures {
	
	/**
	 * Get sample variable product data
	 * 
	 * @return array
	 */
	public static function get_variable_product_data() {
		return array(
			'name' => 'Test Variable Product',
			'type' => 'variable',
			'status' => 'publish',
			'regular_price' => '',
			'attributes' => array(
				'size' => array(
					'name' => 'Size',
					'options' => array( 'Small', 'Medium', 'Large' ),
					'variation' => true,
				),
				'color' => array(
					'name' => 'Color',
					'options' => array( 'Red', 'Blue', 'Green' ),
					'variation' => true,
				),
			),
			'variations' => array(
				array(
					'attributes' => array( 'size' => 'Small', 'color' => 'Red' ),
					'regular_price' => '15.00',
					'sale_price' => '12.00',
					'stock_quantity' => 10,
				),
				array(
					'attributes' => array( 'size' => 'Medium', 'color' => 'Blue' ),
					'regular_price' => '20.00',
					'sale_price' => '',
					'stock_quantity' => 5,
				),
				array(
					'attributes' => array( 'size' => 'Large', 'color' => 'Green' ),
					'regular_price' => '25.00',
					'sale_price' => '22.00',
					'stock_quantity' => 3,
				),
			),
		);
	}
	
	/**
	 * Get sample simple product data
	 * 
	 * @return array
	 */
	public static function get_simple_product_data() {
		return array(
			'name' => 'Test Simple Product',
			'type' => 'simple',
			'status' => 'publish',
			'regular_price' => '10.00',
			'sale_price' => '8.00',
			'stock_quantity' => 100,
			'description' => 'A simple test product for testing purposes.',
			'short_description' => 'Test product',
		);
	}
	
	/**
	 * Get sample bundle product data
	 * 
	 * @return array
	 */
	public static function get_bundle_product_data() {
		return array(
			'name' => 'Test Bundle Product',
			'type' => 'bundle',
			'status' => 'publish',
			'regular_price' => '50.00',
			'sale_price' => '45.00',
			'description' => 'A bundle test product for testing purposes.',
		);
	}
	
	/**
	 * Get expected Typesense data structure for variable product
	 * 
	 * @return array
	 */
	public static function get_expected_variable_product_typesense_data() {
		return array(
			'id' => 'PRODUCT_ID',
			'name' => 'Test Variable Product',
			'productType' => 'variable',
			'status' => 'publish',
			'price' => array(
				'GBP' => 1200, // Lowest variation price in cents
			),
			'regularPrice' => array(
				'GBP' => 1500, // Lowest variation regular price in cents
			),
			'salePrice' => array(
				'GBP' => 1200, // Lowest variation sale price in cents
			),
			'updatedAt' => 'TIMESTAMP',
		);
	}
	
	/**
	 * Get expected Typesense data structure for simple product
	 * 
	 * @return array
	 */
	public static function get_expected_simple_product_typesense_data() {
		return array(
			'id' => 'PRODUCT_ID',
			'name' => 'Test Simple Product',
			'productType' => 'simple',
			'status' => 'publish',
			'price' => array(
				'GBP' => 800, // Sale price in cents
			),
			'regularPrice' => array(
				'GBP' => 1000, // Regular price in cents
			),
			'salePrice' => array(
				'GBP' => 800, // Sale price in cents
			),
			'updatedAt' => 'TIMESTAMP',
		);
	}
	
	/**
	 * Get expected Typesense data structure for product variation
	 * 
	 * @return array
	 */
	public static function get_expected_variation_typesense_data() {
		return array(
			'id' => 'VARIATION_ID',
			'name' => 'Test Variable Product - Small, Red',
			'productType' => 'variation',
			'status' => 'publish',
			'parentId' => 'PARENT_PRODUCT_ID',
			'price' => array(
				'GBP' => 1200, // Sale price in cents
			),
			'regularPrice' => array(
				'GBP' => 1500, // Regular price in cents
			),
			'salePrice' => array(
				'GBP' => 1200, // Sale price in cents
			),
			'attributes' => array(
				'size' => 'Small',
				'color' => 'Red',
			),
			'updatedAt' => 'TIMESTAMP',
		);
	}
	
	/**
	 * Get sample products with NaN price scenarios
	 * 
	 * @return array
	 */
	public static function get_nan_price_scenarios() {
		return array(
			'empty_price' => array(
				'name' => 'Product with Empty Price',
				'regular_price' => '',
				'sale_price' => '',
			),
			'null_price' => array(
				'name' => 'Product with Null Price',
				'regular_price' => null,
				'sale_price' => null,
			),
			'invalid_price' => array(
				'name' => 'Product with Invalid Price',
				'regular_price' => 'invalid',
				'sale_price' => 'invalid',
			),
		);
	}
}
