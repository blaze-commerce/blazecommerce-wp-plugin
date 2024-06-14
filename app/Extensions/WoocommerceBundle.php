<?php

namespace BlazeWooless\Extensions;

class WoocommerceBundle {
	private static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		if ( is_plugin_active( 'woocommerce-product-bundles/woocommerce-product-bundles.php' ) ) {
			add_filter( 'blaze_wooless_product_for_typesense_fields', array( $this, 'fields' ), 10, 1 );
			add_filter( 'blaze_wooless_product_data_for_typesense', array( $this, 'data' ), 10, 3 );
		}
	}

	public function fields( $fields ) {
		$fields[] = array( 'name' => 'bundle', 'type' => 'object', 'optional' => true );
		return $fields;
	}

	public function get_bundled_items( $bundle ) {

		if ( ! $bundle->is_type( 'bundle' ) ) {
			return array();
		}

		$bundled_items_data = array();

		$bundled_items = $bundle->get_bundled_items();
		foreach ( $bundled_items as $bundled_item ) {
			$product = $bundled_item->get_product();
			array_push( $bundled_items_data, array(
				'product' => array(
					'id' => $product->get_id(),
				),
				'settings' => array(
					'minQuantity' => $bundled_item->get_quantity( 'min' ),
					'maxQuantity' => $bundled_item->get_quantity( 'max' ),
					'defaultQuantity' => $bundled_item->get_quantity( 'default' ),
					'optional' => $bundled_item->is_optional(),
					'shippedIndividually' => $bundled_item->is_shipped_individually(),
					'pricedIndividually' => $bundled_item->is_priced_individually(),
					'discountPercent' => $bundled_item->get_discount(),
					'productVisible' => $bundled_item->is_visible(),
					'priceVisible' => $bundled_item->is_price_visible(),
					'overrideTitle' => $bundled_item->has_title_override(),
					'title' => $bundled_item->get_title(),
					'description' => $bundled_item->get_description(),
					'hideThumbnail' => $bundled_item->is_thumbnail_visible()
				)
			) );

		}
		return $bundled_items_data;
	}

	public function get_bundled_data( $product ) {

		if ( ! $product->is_type( 'bundle' ) ) {
			return array();
		}

		$data = array(
			'settings' => array(
				'layout' => $product->get_layout(),
				'formLocation' => $product->get_add_to_cart_form_location(),
				'minBundleSize' => $product->get_min_bundle_size(),
				'maxBundleSize' => $product->get_max_bundle_size(),
				'editInCart' => $product->get_editable_in_cart(),
			),
			'products' => $this->get_bundled_items( $product )
		);
		return apply_filters( 'blaze_wooless_product_bundle_data', $data, $product );
	}

	public function data( $product_data, $product_id, $product ) {
		if ( ! $product->is_type( 'bundle' ) ) {
			return $product_data;
		}

		$product_data['bundle'] = $this->get_bundled_data( $product );

		return $product_data;
	}

}
