<?php

namespace BlazeWooless\Features;
use BlazeWooless\Woocommerce;

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

		add_filter( 'blazecommerce/collection/product/typesense_fields', array( $this, 'add_price_with_tax_meta_data_fields' ), 10, 1 );
		add_filter( 'blazecommerce/collection/product/typesense_data', array( $this, 'add_price_with_tax_meta_data' ), 10, 3 );

		add_filter( 'blazecommerce/settings/tax_rates', array( $this, 'tax_rates' ), 10, 1 );
	}

	public function add_price_with_tax_meta_data_fields( $fields ) {
		$fields[] = [ 'name' => 'metaData.pricesByLocation', 'type' => 'object', 'optional' => true ];
		$fields[] = [ 'name' => 'metaData.pricesByLocation.with_tax', 'type' => 'object', 'optional' => true ];
		$fields[] = [ 'name' => 'metaData.pricesByLocation.with_tax.regularPrice', 'type' => 'object', 'optional' => true ];
		$fields[] = [ 'name' => 'metaData.pricesByLocation.with_tax.salePrice', 'type' => 'object', 'optional' => true ];
		$fields[] = [ 'name' => 'metaData.pricesByLocation.without_tax', 'type' => 'object', 'optional' => true ];
		$fields[] = [ 'name' => 'metaData.pricesByLocation.without_tax.regularPrice', 'type' => 'object', 'optional' => true ];
		$fields[] = [ 'name' => 'metaData.pricesByLocation.without_tax.salePrice', 'type' => 'object', 'optional' => true ];

		// Bundle prices
		$currencies = Woocommerce::get_currencies();
		foreach ( $currencies as $currency ) {
			$fields[] = array( 'name' => 'metaData.pricesByLocation.with_tax.regularPrice.' . $currency, 'type' => 'int64', 'optional' => true );
			$fields[] = array( 'name' => 'metaData.pricesByLocation.with_tax.salePrice.' . $currency, 'type' => 'int64', 'optional' => true );
			$fields[] = array( 'name' => 'metaData.pricesByLocation.without_tax.regularPrice.' . $currency, 'type' => 'int64', 'optional' => true );
			$fields[] = array( 'name' => 'metaData.pricesByLocation.without_tax.salePrice.' . $currency, 'type' => 'int64', 'optional' => true );
		}

		return $fields;
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
			$currency => Woocommerce::format_price_to_int64( $price_with_tax ),
		);

		$tax_rates          = $this->get_tax_rate_array( 'Standard' );
		$prices_by_location = [];
		$is_tax_inclusive   = get_option( 'woocommerce_prices_include_tax' );

		foreach ( $tax_rates as $rate ) {
			$country = $rate['tax_rate_country'] ?? '*';
			$state   = $rate['tax_rate_state'] ?? '*';

			$regular_price = $product->get_regular_price();
			$sale_price    = $product->get_sale_price();

			// If it has tax
			if ( floatval( $rate['tax_rate'] ) > 0 && $is_tax_inclusive === 'yes' ) {
				$final_regular_price = \wc_get_price_including_tax( $product, array( 'price' => $regular_price ) );
				$final_sale_price    = \wc_get_price_including_tax( $product, array( 'price' => $sale_price ) );

				$prices_by_location['with_tax']['locations'][]  = array( 'country' => $country, 'state' => $state );
				$prices_by_location['with_tax']['regularPrice'] = apply_filters( 'blazecommerce/product/metaData/price_by_location/with_tax/regular_price', array(
					$currency => Woocommerce::format_price_to_int64( $final_regular_price ),
				), $currency );
				$prices_by_location['with_tax']['salePrice']    = apply_filters( 'blazecommerce/product/metaData/price_by_location/with_tax/sale_price', array(
					$currency => Woocommerce::format_price_to_int64( $final_sale_price ),
				), $currency);
			} else {
				$final_regular_price = \wc_get_price_excluding_tax( $product, array( 'price' => $regular_price ) );
				$final_sale_price    = \wc_get_price_excluding_tax( $product, array( 'price' => $sale_price ) );

				$prices_by_location['without_tax']['locations'][]  = array( 'country' => $country, 'state' => $state );
				$prices_by_location['without_tax']['regularPrice'] = apply_filters( 'blazecommerce/product/metaData/price_by_location/without_tax/regular_price', array(
					$currency => Woocommerce::format_price_to_int64( $final_regular_price ),
				), $currency );
				$prices_by_location['without_tax']['salePrice']    = apply_filters( 'blazecommerce/product/metaData/price_by_location/without_tax/sale_price', array(
					$currency => Woocommerce::format_price_to_int64( $final_sale_price ),
				), $currency);
			}
		}

		$product_data['metaData']['pricesByLocation'] = $prices_by_location;

		return $product_data;
	}

	public function tax_rates( $tax_rates ) {
		$tax_rates = $this->get_tax_rates_array();

		return $tax_rates;
	}

	public function get_tax_rates_array() {
		$tax_rates   = [];
		$tax_classes = \WC_Tax::get_tax_classes();

		$tax_rates['Standard'] = $this->get_tax_rate_array( 'Standard' );

		foreach ( $tax_classes as $class ) {
			$tax_rates[ $class ] = $this->get_tax_rate_array( $class );
		}

		return $tax_rates;
	}

	public function get_tax_rate_array( $rate_name ) {
		$tax_rates = \WC_Tax::get_rates_for_tax_class( $rate_name );
		$tax_rates = array_map( function ($rate) {
			return (array) $rate;
		}, $tax_rates );

		return array_values( $tax_rates );
	}
}




















