<?php

namespace BlazeWooless\Extensions;

class WoocommerceGiftCards {
	private static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		if ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'pw-gift-cards/pw-gift-cards.php' ) ) {
			add_filter( 'blaze_wooless_additional_site_info', array( $this, 'giftcard_email_content' ), 10, 1 );
			add_filter( 'wooless_product_query_args', array( $this, 'giftcard_product_query_args' ), 10, 1 );
			add_filter( 'blaze_wooless_product_data_for_typesense', array( $this, 'sync_gift_card_data' ), 99, 3 );
			add_filter( 'blaze_wooless_product_data_for_typesense', array( $this, 'set_meta_data' ), 99, 3 );
		}
	}

	public function giftcard_email_content( $additional_settings ) {
		if ( $email_header_logo = get_option( 'woocommerce_email_header_image' ) ) {
			$additional_settings['email_header_logo'] = $email_header_logo;
		}

		if ( $email_footer_text = get_option( 'woocommerce_email_footer_text' ) ) {
			$email_footer_text = apply_filters( 'woocommerce_email_footer_text', $email_footer_text );
			$additional_settings['email_footer_text'] = wpautop( $email_footer_text );
		}

		return $additional_settings;
	}

	public function giftcard_product_query_args( array $args ) {
		if (
			defined( 'PWGC_PRODUCT_TYPE_SLUG' ) &&
			array_key_exists( 'type', $args ) &&
			is_array( $args['type'] )
		) {
			$args['type'][] = \PWGC_PRODUCT_TYPE_SLUG;
		}

		return $args;

	}

	public function sync_gift_card_data( $product_data, $product_id, $product ) {

		if ( $product_data['productType'] === 'pw-gift-card' ) {

			$variation_prices = $product->get_variation_prices();

			// find the lowest price and exclude 0
			$prices = array_filter( $variation_prices['price'], function ($price) {
				return $price > 0;
			} );

			$product_price = min( $prices );

			// re-initialize $product if price is 0
			if ( $product_price == 0 ) {

				$allowed_custom_amounts = boolval( get_post_meta( $product_id, '_pwgc_custom_amount_allowed', true ) );

				if ( ! empty( $allowed_custom_amounts ) ) {
					$product_price = get_post_meta( $product_id, '_pwgc_custom_amount_min', true );
				}
			}

			$price = apply_filters( 'blaze_wooless_calculated_converted_single_price', $product_price );

			$product_data['price'] = $price;
			$product_data['regularPrice'] = $price;
			$product_data['salePrice'] = $price;
		}

		return $product_data;
	}

	/**
	 * Set metadata for gift card products
	 * @param array $product_data
	 * @param integer $product_id
	 * @param \WC_Product $product
	 * @return array
	 */
	public function set_meta_data( $product_data, $product_id, $product ) {

		if ( $product->is_type( 'pw-gift-card' ) ) {

			$currency = get_woocommerce_currency();

			$allowed_custom_amounts = boolval( get_post_meta( $product_id, '_pwgc_custom_amount_allowed', true ) );

			if ( $allowed_custom_amounts ) {
				$min_price = (string) get_post_meta( $product_id, '_pwgc_custom_amount_min', true );
				$max_price = (string) get_post_meta( $product_id, '_pwgc_custom_amount_max', true );
			} else {
				$variation_prices = array_filter( $product->get_variation_prices(), function ($price) {
					return $price > 0;
				} );

				$min_price = (string) min( $variation_prices['price'] );
				$max_price = (string) max( $variation_prices['price'] );
			}

			// later we need to check if include tax is enabled or multicurrency is enabled

			$product_data['metaData']['giftCard'] = [ 
				'allowCustomAmount' => $allowed_custom_amounts,
				'min' => [ 
					$currency => $min_price
				],
				'max' => [ 
					$currency => $max_price
				],
			];
		}

		return $product_data;
	}
}
