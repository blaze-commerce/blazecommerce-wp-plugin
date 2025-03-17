<?php

namespace BlazeWooless\Extensions;

class WoocommerceProducts {
	private static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'add_custom_fields' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_meta' ) );
		add_filter( 'blaze_wooless_product_for_typesense_fields', array( $this, 'set_fields' ), 10, 1 );
		add_filter( 'blaze_wooless_product_data_for_typesense', array( $this, 'sync_product_data' ), 99, 3 );
		add_filter( 'display_post_states', array( $this, 'add_best_seller_post_state' ), 10, 2 );
	}

	/**
	 * Add custom product meta fields
	 * @return void
	 */
	public function add_custom_fields() {
		$override_best_seller = bw_get_general_settings( 'enable_override_best_seller' );

		if ( empty( $override_best_seller ) )
			return;

		woocommerce_wp_checkbox(
			array(
				'id' => '_set_as_best_seller',
				'label' => __( 'Best seller', 'blaze' ),
				'description' => __( 'Set this product as a best seller', 'blaze' ),
			)
		);
	}
	public function save_product_meta() {
		$product = wc_get_product( get_the_ID() );
		$set_as_best_seller = isset( $_POST['_set_as_best_seller'] ) ? 'yes' : 'no';
		$product->update_meta_data( '_set_as_best_seller', $set_as_best_seller );
		$product->save();
	}

	/**
	 * Add "Best Seller" post state to product titles
	 * @param array $post_states
	 * @param WP_Post $post
	 * @return array
	 */
	public function add_best_seller_post_state( $post_states, $post ) {
		if ( 'product' === get_post_type( $post->ID ) ) {
			$product = wc_get_product( $post->ID );
			if ( 'yes' === $product->get_meta( '_set_as_best_seller' ) ) {
				$post_states['best-seller'] = __( 'Best Seller', 'blaze' );
			}
		}
		return $post_states;
	}

	public function set_fields( $fields ) {
		$override_best_seller = bw_get_general_settings( 'enable_override_best_seller' );

		if ( ! empty( $override_best_seller ) ) {
			$fields[] = [ 'name' => 'metaData.bestSeller', 'type' => 'bool', 'optional' => true ];
		}

		return $fields;
	}

	public function sync_product_data( $product_data, $product_id, $product ) {
		$override_best_seller = bw_get_general_settings( 'enable_override_best_seller' );

		if ( ! empty( $override_best_seller ) ) {
			$product_data['metaData']['bestSeller'] = 'yes' === $product->get_meta( '_set_as_best_seller' );
		}

		// check if product type is an external product
		if ( 'external' === $product->get_type() ) {
			$product_data['metaData']['external'] = true;
			$product_data['metaData']['externalUrl'] = $product->get_product_url();
			$product_data['metaData']['buttonText'] = $product->get_button_text();
		}

		return $product_data;
	}
}