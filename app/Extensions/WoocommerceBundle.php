<?php

namespace BlazeWooless\Extensions;

use BlazeWooless\Woocommerce;

class WoocommerceBundle {
	private static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function is_plugin_active() {
		return function_exists( 'is_plugin_active' ) && is_plugin_active( 'woocommerce-product-bundles/woocommerce-product-bundles.php' );
	}

	public function __construct() {
		add_filter( 'blaze_wooless_additional_site_info', array( $this, 'additional_site_info' ), 10, 1 );
		if ( $this->is_plugin_active() ) {
			add_filter( 'blaze_wooless_product_for_typesense_fields', array( $this, 'fields' ), 10, 1 );
			add_filter( 'blaze_wooless_product_data_for_typesense', array( $this, 'data' ), 99, 3 );
			add_action( 'rest_api_init', array( $this, 'register_rest_endpoints' ) );
		}

	}

	public function additional_site_info( $additional_data ) {
		$additional_data['is_bundle_product_enabled'] = $this->is_plugin_active() ? 'true' : 'false';
		return $additional_data;
	}

	public function fields( $fields ) {
		$fields[] = array( 'name' => 'bundle', 'type' => 'object', 'optional' => true );

		// Bundle settings
		$fields[] = array( 'name' => 'bundle.settings', 'type' => 'object', 'optional' => true );
		$fields[] = array( 'name' => 'bundle.settings.layout', 'type' => 'string', 'optional' => true );
		$fields[] = array( 'name' => 'bundle.settings.formLocation', 'type' => 'string', 'optional' => true );
		$fields[] = array( 'name' => 'bundle.settings.minBundleSize', 'type' => 'int32', 'optional' => true );
		$fields[] = array( 'name' => 'bundle.settings.maxBundleSize', 'type' => 'int32', 'optional' => true );
		$fields[] = array( 'name' => 'bundle.settings.editInCart', 'type' => 'bool', 'optional' => true );

		// Bundle products
		$fields[] = array( 'name' => 'bundle.products', 'type' => 'object[]', 'optional' => true );
		$fields[] = array( 'name' => 'bundle.products.product', 'type' => 'object[]', 'optional' => true );
		$fields[] = array( 'name' => 'bundle.products.product.id', 'type' => 'int64[]', 'optional' => true );
		$fields[] = array( 'name' => 'bundle.products.product.stockStatus', 'type' => 'string[]', 'optional' => true );
		$fields[] = array( 'name' => 'bundle.products.product.bundleId', 'type' => 'int64[]', 'optional' => true );
		$fields[] = array( 'name' => 'bundle.products.product.image', 'type' => 'string[]', 'optional' => true );
		$fields[] = array( 'name' => 'bundle.products.product.name', 'type' => 'string[]', 'optional' => true );
		$fields[] = array( 'name' => 'bundle.products.product.slug', 'type' => 'string[]', 'optional' => true );
		$fields[] = array( 'name' => 'bundle.products.product.permalink', 'type' => 'string[]', 'optional' => true );
		$fields[] = array( 'name' => 'bundle.products.product.type', 'type' => 'string[]', 'optional' => true );
		$fields[] = array( 'name' => 'bundle.products.product.sku', 'type' => 'string[]', 'optional' => true );
		$fields[] = array( 'name' => 'bundle.products.product.description', 'type' => 'string[]', 'optional' => true );
		$fields[] = array( 'name' => 'bundle.products.product.shortDescription', 'type' => 'string[]', 'optional' => true );
		$fields[] = array( 'name' => 'bundle.products.product.stockQuantity', 'type' => 'int32[]', 'optional' => true );

		// Bundle product settings
		$fields[] = array( 'name' => 'bundle.products.settings', 'type' => 'object[]', 'optional' => true );
		$fields[] = array( 'name' => 'bundle.products.settings.minQuantity', 'type' => 'int32[]', 'optional' => true );
		$fields[] = array( 'name' => 'bundle.products.settings.maxQuantity', 'type' => 'int32[]', 'optional' => true );
		$fields[] = array( 'name' => 'bundle.products.settings.defaultQuantity', 'type' => 'int32[]', 'optional' => true );
		$fields[] = array( 'name' => 'bundle.products.settings.optional', 'type' => 'bool[]', 'optional' => true );
		$fields[] = array( 'name' => 'bundle.products.settings.shippedIndividually', 'type' => 'bool[]', 'optional' => true );
		$fields[] = array( 'name' => 'bundle.products.settings.pricedIndividually', 'type' => 'bool[]', 'optional' => true );
		$fields[] = array( 'name' => 'bundle.products.settings.discountPercent', 'type' => 'float[]', 'optional' => true );

		// Bundle product variations
		$fields[] = array( 'name' => 'bundle.products.variations', 'type' => 'object[]', 'optional' => true );

		// Bundle info
		$fields[] = array( 'name' => 'bundle.bundleInfo', 'type' => 'object', 'optional' => true );
		$fields[] = array( 'name' => 'bundle.bundleInfo.id', 'type' => 'int64', 'optional' => true );
		$fields[] = array( 'name' => 'bundle.bundleInfo.name', 'type' => 'string', 'optional' => true );
		$fields[] = array( 'name' => 'bundle.bundleInfo.slug', 'type' => 'string', 'optional' => true );
		$fields[] = array( 'name' => 'bundle.bundleInfo.permalink', 'type' => 'string', 'optional' => true );
		$fields[] = array( 'name' => 'bundle.bundleInfo.description', 'type' => 'string', 'optional' => true );
		$fields[] = array( 'name' => 'bundle.bundleInfo.shortDescription', 'type' => 'string', 'optional' => true );
		$fields[] = array( 'name' => 'bundle.bundleInfo.sku', 'type' => 'string', 'optional' => true );
		$fields[] = array( 'name' => 'bundle.bundleInfo.stockStatus', 'type' => 'string', 'optional' => true );
		$fields[] = array( 'name' => 'bundle.bundleInfo.stockQuantity', 'type' => 'int32', 'optional' => true );
		$fields[] = array( 'name' => 'bundle.bundleInfo.image', 'type' => 'string', 'optional' => true );
		$fields[] = array( 'name' => 'bundle.bundleInfo.productType', 'type' => 'string', 'optional' => true );

		// Bundle prices
		$currencies = Woocommerce::get_currencies();
		foreach ( $currencies as $currency ) {
			$fields[] = array( 'name' => 'bundle.minPrice.' . $currency, 'type' => 'float', 'optional' => true );
			$fields[] = array( 'name' => 'bundle.maxPrice.' . $currency, 'type' => 'float', 'optional' => true );
		}
		return $fields;
	}
	protected function set_data( $bundle_data, $bundled_item, $product ) {
		if ( $product->is_type( 'variable' ) ) {

			$bundle_fields_prefix = apply_filters( 'woocommerce_product_bundle_field_prefix', '', $bundled_item->get_id() );
			$variation_attributes = $bundled_item->get_product_variation_attributes();
			$variation_bundles    = array();

			foreach ( $variation_attributes as $variation_attribute_name => $variation_attribute_options ) {

				$variation_options = array();
				$variations        = $bundled_item->get_product_variations();
				$currency          = get_option( 'woocommerce_currency' );


				foreach ( (array) $variations as $variation ) {
					foreach ( $variation['attributes'] as $variation_key => $variation_value ) {

						$variation_id      = $variation['variation_id'];
						$variation_product = wc_get_product( $variation_id );

						$price                       = $variation_product->get_price();
						$convertedPrice[ $currency ] = $price;
						$convertedPrice              = apply_filters( 'blaze_wooless_convert_prices', $convertedPrice, $currency );

						$variation_options[ $variation_key ][ $variation_id ] = array(
							'label' => $variation_value,
							'price' => $convertedPrice,
							'description' => strip_tags( $variation['variation_description'] ) ?? null,
							'displayPrice' => boolval( $variation['display_price'] ),
							'stockStatus' => $variation_product->get_stock_status(),
							'stockQuantity' => $variation_product->get_stock_quantity(),
							'sku' => $variation_product->get_sku(),
							'id' => $variation_id,
						);
					}
				}

				foreach ( (array) $bundled_item->get_product_variation_attributes() as $label_name => $attribute_options ) {
					$variation_key = 'attribute_' . sanitize_title( $label_name );

					$id = $variation_key . '_' . $bundled_item->get_id();

					array_push( $variation_bundles, array(
						'prefix' => $bundle_fields_prefix,
						'id' => $id,
						'name' => 'bundle_' . $id,
						'label' => $label_name,
						'options' => $variation_options[ $variation_key ]
					) );
				}
			}

			$bundle_data['variations'] = $variation_bundles;

			// Add additional product data for grid display
			$bundle_data['product']['name']             = $product->get_name();
			$bundle_data['product']['slug']             = $product->get_slug();
			$bundle_data['product']['permalink']        = get_permalink( $product->get_id() );
			$bundle_data['product']['type']             = 'variable';
			$bundle_data['product']['status']           = $product->get_status();
			$bundle_data['product']['sku']              = $product->get_sku();
			$bundle_data['product']['description']      = $product->get_description();
			$bundle_data['product']['shortDescription'] = $product->get_short_description();

		} elseif ( $product->is_type( 'simple' ) ) {
			$currency                    = get_option( 'woocommerce_currency' );
			$price                       = $product->get_price();
			$convertedPrice[ $currency ] = $price;
			$convertedPrice              = apply_filters( 'blaze_wooless_convert_prices', $convertedPrice, $currency );

			$bundle_data['product']['price'] = $convertedPrice;
			// get product published status and link
			$bundle_data['product']['status']           = $product->get_status();
			$bundle_data['product']['link']             = get_permalink( $product->get_id() );
			$bundle_data['product']['name']             = $product->get_name();
			$bundle_data['product']['slug']             = $product->get_slug();
			$bundle_data['product']['type']             = 'simple';
			$bundle_data['product']['sku']              = $product->get_sku();
			$bundle_data['product']['description']      = $product->get_description();
			$bundle_data['product']['shortDescription'] = $product->get_short_description();
			$bundle_data['product']['stockStatus']      = $product->get_stock_status();
			$bundle_data['product']['stockQuantity']    = $product->get_stock_quantity();
		}

		// Clean the data by removing empty values
		return $this->remove_empty_values( $bundle_data );
	}

	public function get_bundled_items( $bundle ) {

		if ( ! $bundle->is_type( 'bundle' ) ) {
			return array();
		}

		$bundled_items_data = array();

		$bundled_items = $bundle->get_bundled_items();

		foreach ( $bundled_items as $bundled_item ) {
			$product = $bundled_item->get_product();

			$image     = $product->get_image_id();
			$image_src = wp_get_attachment_image_src( $image, 'full' );

			$data = array(
				'product' => array(
					'id' => $product->get_id(),
					'stockStatus' => $product->get_stock_status(),
					'bundleId' => $bundled_item->get_id(),
					'image' => $image_src[0] ?? null
				),
				'settings' => array(
					'minQuantity' => $bundled_item->get_quantity( 'min' ),
					'maxQuantity' => $bundled_item->get_quantity( 'max' ),
					'defaultQuantity' => $bundled_item->get_quantity( 'default' ),
					'optional' => $bundled_item->is_optional(),
					'shippedIndividually' => $bundled_item->is_shipped_individually(),
					'pricedIndividually' => $bundled_item->is_priced_individually(),
					'discountPercent' => $bundled_item->get_discount(),
					'productVisible' => $bundled_item->is_visible(),
					'priceVisible' => $bundled_item->is_price_visible(),
					'overrideTitle' => $bundled_item->has_title_override(),
					'title' => $bundled_item->get_title(),
					'description' => strip_tags( $bundled_item->get_description() ),
					'hideThumbnail' => $bundled_item->is_thumbnail_visible(),
				)
			);

			array_push( $bundled_items_data, $this->set_data( $data, $bundled_item, $product ) );

		}
		return $bundled_items_data;
	}

	public function reformat_prices( $prices, $currency ) {
		$prices = apply_filters( 'blaze_wooless_convert_prices', $prices, $currency );
		return array_map( [ Woocommerce::class, 'format_price' ], $prices );
	}

	public function get_bundled_data( $product ) {

		if ( ! $product->is_type( 'bundle' ) ) {
			return array();
		}

		$currency = get_option( 'woocommerce_currency' );

		$bundle_products = $this->get_bundled_items( $product );

		$minPrice = $maxPrice = array();

		$minPrice[ $currency ] = $product->get_bundle_price( 'min', true );
		$maxPrice[ $currency ] = $product->get_bundle_price( 'max', true );

		// Get product image
		$image_id  = $product->get_image_id();
		$image_url = null;
		if ( $image_id ) {
			$image_src = wp_get_attachment_image_src( $image_id, 'full' );
			$image_url = $image_src ? $image_src[0] : null;
		}

		$data = array(
			'settings' => array(
				'layout' => $product->get_layout(),
				'formLocation' => $product->get_add_to_cart_form_location(),
				'minBundleSize' => $product->get_min_bundle_size(),
				'maxBundleSize' => $product->get_max_bundle_size(),
				'editInCart' => $product->get_editable_in_cart(),
			),
			'products' => $bundle_products,
			'minPrice' => $this->reformat_prices( $minPrice, $currency ),
			'maxPrice' => $this->reformat_prices( $maxPrice, $currency ),
			'bundleInfo' => array(
				'id' => $product->get_id(),
				'name' => $product->get_name(),
				'slug' => $product->get_slug(),
				'permalink' => get_permalink( $product->get_id() ),
				'description' => $product->get_description(),
				'shortDescription' => $product->get_short_description(),
				'sku' => $product->get_sku(),
				'stockStatus' => $product->get_stock_status(),
				'stockQuantity' => $product->get_stock_quantity(),
				'image' => $image_url,
				'productType' => 'bundle',
			),
		);

		// Clean the data by removing empty values
		$data = $this->remove_empty_values( $data );

		return apply_filters( 'blaze_wooless_product_bundle_data', $data, $product );
	}

	/**
	 * Recursively remove empty values from an array
	 *
	 * @param array $array The array to clean and avoid typesense error if field is optional
	 * @return array The cleaned array
	 */
	protected function remove_empty_values( $array ) {
		foreach ( $array as $key => $value ) {
			// If value is an array, recursively clean it
			if ( is_array( $value ) ) {
				$array[ $key ] = $this->remove_empty_values( $value );

				// If the array is now empty, remove it
				if ( empty( $array[ $key ] ) ) {
					unset( $array[ $key ] );
				}
			} else {
				// Remove empty strings, null values, and empty arrays
				if ( $value === '' || $value === null || ( is_array( $value ) && empty( $value ) ) ) {
					unset( $array[ $key ] );
				}
			}
		}

		return $array;
	}

	public function data( $product_data, $product_id, $product ) {

		if ( ! $product->is_type( 'bundle' ) ) {
			return $product_data;
		}
		$currency = get_option( 'woocommerce_currency' );

		$bundle_data = $this->get_bundled_data( $product );

		// Clean the bundle data by removing empty values
		$bundle_data = $this->remove_empty_values( $bundle_data );

		$product_data['bundle'] = $bundle_data;

		if ( $product_data['price'][ $currency ] === 0 && $product_data['regularPrice'][ $currency ] === 0 ) {
			$product_data['price'][ $currency ]        = $product_data['bundle']['minPrice'][ $currency ];
			$product_data['regularPrice'][ $currency ] = $product_data['bundle']['minPrice'][ $currency ];
			$product_data['salePrice'][ $currency ]    = $product_data['bundle']['minPrice'][ $currency ];
		}

		return $product_data;
	}

	public function register_rest_endpoints() {
		register_rest_route(
			'wooless-wc/v1',
			'/check-bundle-data',
			array(
				'methods' => 'GET',
				'callback' => array( $this, 'check_bundle_data' ),
				'args' => array(
					'product_id' => array(
						'required' => true,
					),
				),
			)
		);
	}

	public function check_bundle_data( \WP_REST_Request $request ) {
		try {
			$product_id = $request->get_param( 'product_id' );
			$product    = wc_get_product( $product_id );

			if ( ! is_a( $product, 'WC_Product_Bundle' ) ) {
				throw new \Exception( 'Product is not a bundle' );
			}

			$data = $this->get_bundled_data( $product );

			// Clean the data by removing empty values
			$data = $this->remove_empty_values( $data );

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

}
