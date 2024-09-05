<?php

namespace BlazeWooless\Extensions;

class WoocommerceProductAddons {
	private static $instance = null;
	private $general_addons = [];

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		if ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'woocommerce-product-addons/woocommerce-product-addons.php' ) ) {
			add_action( 'ts_before_product_upsert', array( $this, 'prepare_general_product_addons' ) );
			add_action( 'blaze_wooless_pre_sync_products', array( $this, 'prepare_general_product_addons' ) );
			add_filter( 'blaze_wooless_product_data_for_typesense', array( $this, 'sync_product_addons_data' ), 99, 3 );
		}
	}

	public function prepare_general_product_addons() {
		// we will get all available general product addons

		if ( class_exists( 'WC_Product_Addons_Groups' ) ) {
			$this->general_addons = \WC_Product_Addons_Groups::get_all_global_groups();
		}
	}

	public function sync_product_addons_data( $product_data, $product_id, $product ) {

		if ( class_exists( 'WC_Product_Addons_Product_Group' ) ) {

			$product_post = get_post( $product_id );
			$product_addons = blaze_woolese_array_camel_case_keys( \WC_Product_Addons_Product_Group::get_group( $product_post ) );

			if ( $product_addons['excludeGlobalAddOns'] === false ) {
				// get product category ids from $product_post
				$product_categories = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );

				$available_global_addons = [];

				foreach ( $this->general_addons as $addon ) {
					$restrict_to_categories = $addon['restrict_to_categories'];
					if ( count( $restrict_to_categories ) === 0 || array_intersect( $product_categories, $restrict_to_categories ) ) {
						$available_global_addons += $addon['fields'];
					}
				}

				if ( ! empty( $available_global_addons ) ) {
					$product_addons['fields'] += $available_global_addons;
				}
			}

			$product_data['addons'] = $product_addons['fields'];
		}

		return $product_data;
	}
}