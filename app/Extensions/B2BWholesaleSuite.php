<?php

namespace BlazeWooless\Extensions;

use BlazeWooless\Settings\RegionalSettings;
use BlazeWooless\Woocommerce;

class B2BWholesaleSuite {
    private static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

    public function is_plugin_active() {
		return function_exists( 'is_plugin_active' ) && is_plugin_active( 'b2b-wholesale-suite/b2b-wholesale-suite.php' );
	}

    public function __construct() {
		if ( $this->is_plugin_active() ) {
			add_action( 'rest_api_init', array( $this, 'register_rest_endpoints' ) );
			add_filter( 'blaze_commerce_taxonomy_meta_data', array( $this, 'blaze_commerce_taxonomy_meta_data' ), 10, 2 );
			add_filter( 'blaze_wooless_product_data_for_typesense', array( $this, 'add_wholesale_product_metadata' ), 50, 2 );
		}
	}

    public function register_rest_endpoints() {
		register_rest_route(
			'wooless-wc/v1',
			'/wholesale-price',
			array(
				'methods' => 'GET',
				'callback' => array( $this, 'get_wholesale_price' ),
				'args' => array(
					'product_id' => array(
						'required' => true,
					),
				),
			)
		);

	}

    public function get_wholesale_price( \WP_REST_Request $request ) {
		try {
			$product_id = $request->get_param( 'product_id' );
			$user_id = $request->get_param( 'user_id' );
			$product    = wc_get_product( $product_id );
            wp_set_current_user($user_id);

            // var_dump(wp_get_current_user()->user_login ); exit;
            $regular_price = $product->get_regular_price();
            // var_dump(\B2bwhs_Dynamic_Rules::b2bwhs_dynamic_rule_fixed_price($regular_price, $product)); exit;
            var_dump($product->get_regular_price()); exit;

			$data = $product;

			$response = new \WP_REST_Response( $data );

			// Add a custom status code
			$response->set_status( 201 );
		} catch (\Exception $e) {
			$response = new \WP_REST_Response( array(
				'error' => $e->getMessage()
			) );
			$response->set_status( 400 );
		}

		return $response;
	}

	public function blaze_commerce_taxonomy_meta_data( $meta_data, $term_id ) {
		$excluded_roles = [];
		$enabled_for_guest = esc_html( get_term_meta( $term_id, 'b2bwhs_group_0', true ) );

		if ( ! $enabled_for_guest ) {
			$excluded_roles[] = "Guest";
		} 
		
		$groups = get_posts(
			array(
				'post_type'   => 'b2bwhs_group',
				'post_status' => 'publish',
				'numberposts' => -1,
			)
		);

		foreach ( $groups as $group ) {
			// retrieve the existing value(s) for this meta field.
			$enabled_for_group = esc_html( get_term_meta( $term_id, 'b2bwhs_group_' . $group->ID, true ) );

			if ( ! $enabled_for_group ) {
				$excluded_roles[] = esc_html( $group->post_title );
			}
		}

		if ( count($excluded_roles) !== count($groups) + 1 ) {
			$meta_data[] = array( 'name' => 'hide_for_roles', 'value' => $excluded_roles );
		}

		return $meta_data;
	}

	public function add_wholesale_product_metadata( $product_data, $product_id ) {
		$visibility = get_post_meta( $product_id, 'b2bwhs_product_visibility_override', true );
		$product_data['metaData']['b2b_wholesale_visibility'] = $visibility;

		if ( "manual" === $visibility ) {
			$excluded_roles = [];
			$enabled_for_guest = esc_html( get_post_meta( $product_id, 'b2bwhs_group_0', true ) );

			if ( ! $enabled_for_guest ) {
				$excluded_roles[] = "Guest";
			}

			$groups = get_posts(
				array(
					'post_type'   => 'b2bwhs_group',
					'post_status' => 'publish',
					'numberposts' => -1,
				)
			);

			foreach ( $groups as $group ) {
				// retrieve the existing value(s) for this meta field.
				$enabled_for_product = esc_html( get_post_meta( $product_id, 'b2bwhs_group_' . $group->ID, true ) );
	
				if ( ! $enabled_for_product ) {
					$excluded_roles[] = esc_html( $group->post_title );
				}
			}

			$product_data['metaData']['hide_for_roles'] = $visibility;
		}

		return $product_data;
	}
}