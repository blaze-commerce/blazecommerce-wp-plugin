<?php

namespace BlazeWooless\Features;

class Tax {
	private static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		add_filter( 'blaze_commerce_variation_data', array( $this, 'add_price_with_tax_meta_data' ), 10, 3 );
		add_filter( 'blaze_wooless_cross_sell_data_for_typesense', array( $this, 'add_price_with_tax_meta_data' ), 10, 3 );
		add_filter( 'blaze_wooless_product_data_for_typesense', array( $this, 'add_price_with_tax_meta_data' ), 10, 3 );

		add_filter( 'blaze_wooless_additional_site_info', array( $this, 'add_tax_settings_to_site_info' ), 10, 1 );
	}

	public function add_price_with_tax_meta_data( $product_data, $product_id, $product ) {
		$currency = get_option( 'woocommerce_currency' );
		if ( ! isset( $product_data['metaData'] ) ) {
			$product_data['metaData'] = array();
		}

		$args = array(
			'qty' => 1,
			'price' => $product->get_price(),
		);

		if ( function_exists( 'wc_get_price_including_tax' ) ) { // WC 3.0+
			$price_with_tax = wc_get_price_including_tax( $product, $args );
		} else { // WC < 3.0
			$price_with_tax = $product->get_price_including_tax();
		}

		$product_data['metaData']['priceWithTax'] = array(
			$currency => (float) number_format( empty( $price_with_tax ) ? 0 : $price_with_tax, 4, '.', '' ),
		);
		return $product_data;
	}

	public function add_tax_settings_to_site_info( $additional_settings ) {
		$tax_settings = array(
			'prices_include_tax' => get_option( 'woocommerce_prices_include_tax' ),
			'tax_based_on'       => get_option( 'woocommerce_tax_based_on' ),
			'shipping_tax_class' => get_option( 'woocommerce_shipping_tax_class' ),
			'tax_round_at_subtotal' => get_option( 'woocommerce_tax_round_at_subtotal' ),
			'tax_classes'        => array_filter( array_map( 'trim', explode( "\n", get_option( 'woocommerce_tax_classes' ) ) ) ),
			'tax_display_shop' => get_option( 'woocommerce_tax_display_shop' ),
			'tax_display_cart' => get_option( 'woocommerce_tax_display_cart' ),
			'price_display_suffix' => get_option( 'woocommerce_price_display_suffix' ),
			'tax_total_display' => get_option( 'woocommerce_tax_total_display' ),
		);

		$additional_settings['woocommerce_tax_settings'] = $tax_settings;
		$additional_settings['woocommerce_tax_rates'] = $this->get_tax_rates_array();

		return $additional_settings;
	}

	public function get_tax_rates_array() {
		$tax_rates = [];
		$tax_classes = \WC_Tax::get_tax_classes();

		$tax_rates['Standard'] = $this->get_tax_rate_array( 'Standard' );

		foreach ( $tax_classes as $class ) {
			$tax_rates[$class] = $this->get_tax_rate_array( $class );
		}

		return $tax_rates;
	}

	public function get_tax_rate_array( $rate_name ) {
		$tax_rates = \WC_Tax::get_rates_for_tax_class( $rate_name );
		$tax_rates = array_map( function ( $rate ) {
			return (array)$rate;
		}, $tax_rates );

		return array_values( $tax_rates );
	}
}




















