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

		add_filter( 'blazecommerce/settings/tax_rates', array( $this, 'tax_rates' ), 10, 1 );
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

	public function tax_rates( $tax_rates ) {
		$tax_rates = $this->get_tax_rates_array();

		return $tax_rates;
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




















