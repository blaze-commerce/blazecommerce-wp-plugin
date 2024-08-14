<?php

namespace BlazeWooless\Extensions;

class WoocommerceProductAddons {
	private static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		if ( is_plugin_active( 'woocommerce-product-addons/woocommerce-product-addons.php' ) ) {
			add_filter( 'blaze_wooless_additional_site_info', array( $this, 'add_general_product_addons_info' ), 10, 1 );
			add_filter( 'blaze_wooless_product_data_for_typesense', array( $this, 'sync_product_addons_data' ), 99, 3 );

		}
	}

	public function add_general_product_addons_info( $site_info ) {
		// we will get all available general product addons

		if ( class_exists( 'WC_Product_Addons_Groups' ) ) {

			$general_addons = \WC_Product_Addons_Groups::get_all_global_groups();
			$general_addons = blaze_woolese_array_camel_case_keys( $general_addons );
			foreach ( $general_addons as $index => $addon ) {
				foreach ( $addon as $key => $value ) {
					if ( $key === 'restrictToCategories' && count( $value ) > 0 ) {
						$general_addons[ $index ]['restrictToCategories'] = array_keys( $value );
					}
				}
			}

			$site_info['general_product_addons'] = $general_addons;
		}

		return $site_info;
	}

	public function sync_product_addons_data( $product_data, $product_id, $product ) {

		if ( class_exists( 'WC_Product_Addons_Product_Group' ) ) {

			$product_post = get_post( $product_id );
			$product_addons = blaze_woolese_array_camel_case_keys( \WC_Product_Addons_Product_Group::get_group( $product_post ) );

			$product_data['addons'] = $product_addons;
		}

		return $product_data;
	}
}