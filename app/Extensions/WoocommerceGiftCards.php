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

			$product_price = $product->get_price();

			// re-initialize $product if price is 0
			if ( $product_price == 0 ) {

				// get the lowest price from its variations
				$variations = $product->get_available_variations();
				$lowest_price = 0;
				foreach ( $variations as $variation ) {
					$variation_price = $variation['display_price'];
					if ( $lowest_price == 0 || $variation_price < $lowest_price ) {
						$lowest_price = $variation_price;
					}
				}

				$product_price = $lowest_price;
			}

			$price = apply_filters( 'blaze_wooless_calculated_converted_single_price', $product_price );

			$product_data['price'] = $price;
			$product_data['regularPrice'] = $price;
			$product_data['salePrice'] = $price;
		}

		return $product_data;
	}
}
