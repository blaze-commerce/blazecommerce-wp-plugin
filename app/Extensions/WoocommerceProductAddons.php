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
		if ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'woocommerce-product-addons/woocommerce-product-addons.php' ) ) {
			add_action( 'ts_before_product_upsert', array( $this, 'prepare_general_product_addons' ) );
			add_action( 'blaze_wooless_pre_sync_products', array( $this, 'prepare_general_product_addons' ) );
			add_filter( 'blaze_wooless_product_data_for_typesense', array( $this, 'sync_product_addons_data' ), 99, 3 );
			add_filter( 'woocommerce_add_cart_item_data', array( $this, 'woocommerce_add_cart_item_data' ), 99, 3 );
		}
	}

	public function prepare_general_product_addons() {
		$general_addons = get_transient( 'blaze_commerce_general_product_addons' );

		if ( $general_addons )
			return;

		if ( class_exists( 'WC_Product_Addons_Groups' ) ) {
			$general_addons = \WC_Product_Addons_Groups::get_all_global_groups();

			// sort the addons by priority
			usort( $general_addons, function ($a, $b) {
				return absint( $a['priority'] ) - absint( $b['priority'] );
			} );

			set_transient( 'blaze_commerce_general_product_addons', $general_addons, DAY_IN_SECONDS );
		}
	}

	public function get_product_addons() {
		$product_addons = get_transient( 'blaze_commerce_general_product_addons' );

		if ( ! $product_addons ) {
			$this->prepare_general_product_addons();
			$product_addons = get_transient( 'blaze_commerce_general_product_addons' );
		}

		return $product_addons;
	}

	public function sync_product_addons_data( $product_data, $product_id, $product ) {

		if ( class_exists( 'WC_Product_Addons_Product_Group' ) ) {

			$product_post = get_post( $product_id );
			$product_addons = blaze_woolese_array_camel_case_keys( \WC_Product_Addons_Product_Group::get_group( $product_post ) );

			if ( $product_addons['excludeGlobalAddOns'] === false ) {
				// get product category ids from $product_post
				$product_categories = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );

				$available_global_addons = [];

				$general_addons = $this->get_product_addons();

				foreach ( $general_addons as $addon ) {
					$restrict_to_categories = $addon['restrict_to_categories'];
					$restrict_keys = array_keys( $restrict_to_categories );

					if ( count( $restrict_to_categories ) === 0 || array_intersect( $product_categories, $restrict_keys ) ) {
						$available_global_addons = array_merge( $available_global_addons, $addon['fields'] );
					}
				}

				if ( ! empty( $available_global_addons ) ) {
					$product_addons['fields'] = array_merge( $product_addons['fields'], $available_global_addons );
				}
			}

			$product_data['addons'] = $product_addons['fields'];
		}

		return $product_data;
	}

	public function woocommerce_add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
		$post_data = ! empty( $cart_item_data['graphqlAddons'] ) ? $cart_item_data['graphqlAddons'] : null;
		if ( empty( $post_data ) ) {
			// Since the request is not from wpgraphql then we just return $cart_item_data and not modify it to avoid conflicts
			return $cart_item_data;
		}

		// Remove custom data
		unset( $cart_item_data['graphqlAddons'] );

		$product_addons = \WC_Product_Addons_Helper::get_product_addons( $product_id );

		if ( empty( $cart_item_data['addons'] ) ) {
			$cart_item_data['addons'] = array();
		}

		if ( is_array( $product_addons ) && ! empty( $product_addons ) ) {
			include_once WP_PLUGIN_DIR . '/woocommerce-product-addons/includes/fields/abstract-wc-product-addons-field.php';
			foreach ( $product_addons as $addon ) {
				if ( $addon['type'] === 'heading' )
					continue;

				$value = isset( $post_data[ 'addon-' . $addon['field_name'] ] ) ? $post_data[ 'addon-' . $addon['field_name'] ] : '';
				if ( is_array( $value ) ) {
					$value = array_map( 'stripslashes', $value );
				} else {
					$value = stripslashes( $value );
				}

				switch ( $addon['type'] ) {
					case 'checkbox':
					case 'radiobutton':
						include_once WP_PLUGIN_DIR . '/woocommerce-product-addons/includes/fields/class-wc-product-addons-field-list.php';
						$field = new \WC_Product_Addons_Field_List( $addon, $value );
						break;
					case 'custom':
					case 'custom_text':
					case 'custom_textarea':
					case 'custom_price':
					case 'input_multiplier':
						include_once WP_PLUGIN_DIR . '/woocommerce-product-addons/includes/fields/class-wc-product-addons-field-custom.php';
						$field = new \WC_Product_Addons_Field_Custom( $addon, $value );
						break;
					case 'select':
					case 'multiple_choice':
						include_once WP_PLUGIN_DIR . '/woocommerce-product-addons/includes/fields/class-wc-product-addons-field-select.php';
						$field = new \WC_Product_Addons_Field_Select( $addon, $value );
						break;
					case 'file_upload':
						include_once WP_PLUGIN_DIR . '/woocommerce-product-addons/includes/fields/class-wc-product-addons-field-file-upload.php';
						$field = new \WC_Product_Addons_Field_File_Upload( $addon, $value, $test );
						break;
					default:
						//skip the field if it is not supported to prevent errors
						continue;
				}

				$data = $field->get_cart_item_data();

				if ( is_wp_error( $data ) ) {

					if ( version_compare( WC_VERSION, '2.3.0', '<' ) ) {
						$this->add_error( $data->get_error_message() );
					} else {
						// Throw exception for add_to_cart to pickup
						throw new Exception( $data->get_error_message() );
					}
				} elseif ( $data ) {
					$cart_item_data['addons'] = array_merge( $cart_item_data['addons'], apply_filters( 'woocommerce_product_addon_cart_item_data', $data, $addon, $product_id, $post_data ) );
				}
			}
		}

		return $cart_item_data;
	}
}
