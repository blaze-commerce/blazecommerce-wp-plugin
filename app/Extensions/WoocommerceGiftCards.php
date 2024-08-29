<?php

namespace BlazeWooless\Extensions;

use BlazeWooless\TypesenseClient;

class WoocommerceGiftCards {
	private static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		if ( is_plugin_active( 'pw-gift-cards/pw-gift-cards.php' ) ) {
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

		if ( is_a( $product, 'WC_Product_PW_Gift_Card' ) ) {

			$price = apply_filters( 'blaze_wooless_calculated_converted_single_price', $product->get_price() );

			$product_data['price'] = $price;
			$product_data['regularPrice'] = $price;
			$product_data['salePrice'] = $price;
		}

		return $product_data;
	}
}
