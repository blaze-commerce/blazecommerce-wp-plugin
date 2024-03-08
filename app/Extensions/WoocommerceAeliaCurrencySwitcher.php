<?php

namespace BlazeWooless\Extensions;

use BlazeWooless\Settings\RegionalSettings;

class WoocommerceAeliaCurrencySwitcher {
	private static $instance = null;

	private $price_manager = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Aelia Currency Switcher Prices Manager 
	 */
	public function price_manager() {
		return $this->price_manager;
	}

	public function currency_switcher() {
		return \WC_Aelia_CurrencySwitcher::instance();
	}

	public function get_enabled_currencies() {
		$enabled_currencies = $this->currency_switcher()->enabled_currencies();
		return $enabled_currencies;
	}
	public function get_currencies_from_sales() {
		$available_currencies = \Aelia\WC\CurrencySwitcher\WC_Aelia_Reporting_Manager::get_currencies_from_sales();

		return $available_currencies;
	}

	public function __construct() {
		if ( is_plugin_active( 'woocommerce-aelia-currencyswitcher/woocommerce-aelia-currencyswitcher.php' ) ) {
			// Set the price manager class of Aelia Currency Switcher
			$this->price_manager = \Aelia\WC\CurrencySwitcher\WC27\WC_Aelia_CurrencyPrices_Manager::instance();

			add_filter( 'blaze_wooless_product_data_for_typesense', array( $this, 'add_multicurrency_prices' ), 10, 2 );
			add_filter( 'blaze_wooless_cross_sell_data_for_typesense', array( $this, 'add_multicurrency_prices' ), 10, 2 );
			add_filter( 'blaze_wooless_additional_site_info', array( $this, 'add_multicurrency_site_info' ), 10, 1 );

			add_filter( 'graphql_RootQuery_fields', array( $this, 'modify_grapqhl_rootquery_cart_fields' ), 99999, 1 );

			add_filter( 'blaze_commerce_variation_multicurrency_prices', array( $this, 'variation_multicurrency_prices' ), 10, 2 );

			add_action( 'wp_footer', array( $this, 'add_currency_switcher_after_country_field' ), 50 );
		}
	}


	public function add_multicurrency_prices( $product_data, $product_id ) {
		$available_currencies = $this->get_enabled_currencies();

		if ( isset( $product_data['productType'] ) && $product_data['productType'] === 'pw-gift-card' ) {
			return $this->giftcard_multicurrency_prices( $product_data, $product_id, $available_currencies );
		}

		$regular_prices               = $this->price_manager()->get_product_regular_prices( $product_id );
		$product_data['regularPrice'] = $regular_prices;

		$sale_prices               = $this->price_manager()->get_product_sale_prices( $product_id );
		$product_data["salePrice"] = $sale_prices;


		if ( ! empty( $available_currencies ) ) {

			foreach ( $available_currencies as $currency ) {

				if ( ! isset( $product_data['price'][ $currency ] ) ) {
					$_sale_price                        = $product_data['salePrice'][ $currency ];
					$_regular_price                     = $product_data['regularPrice'][ $currency ];
					$product_data['price'][ $currency ] = ! empty( $_sale_price ) ? $_sale_price : $_regular_price;
				}

				$product_data['regularPrice'][ $currency ] = floatval( number_format( (float) $product_data['regularPrice'][ $currency ], 2 ) );
				$product_data['salePrice'][ $currency ]    = floatval( number_format( (float) $product_data['salePrice'][ $currency ], 2 ) );
				$product_data['price'][ $currency ]        = floatval( number_format( (float) $product_data['price'][ $currency ], 2 ) );

				unset( $converted_prices, $product, $converted_product, $_sale_price, $_regular_price );
			}
		}
		return $product_data;
	}

	public function add_multicurrency_site_info( $additional_settings ) {
		$additional_settings['is_multicurrency'] = 'yes';

		$cs_settings          = \Aelia\WC\CurrencySwitcher\WC_Aelia_CurrencySwitcher::settings();
		$available_currencies = \Aelia\WC\CurrencySwitcher\WC_Aelia_Reporting_Manager::get_currencies_from_sales();
		$default_currency     = $cs_settings->default_geoip_currency();

		$available_countries = RegionalSettings::get_selected_regions();
		// var_dump($available_countries); exit;
		// var_dump($available_currencies); exit;
		$aelia_currency_switcher_options = get_option( 'wc_aelia_currency_switcher', false );
		$country_currency_mappings       = $aelia_currency_switcher_options['currency_countries_mappings'];

		if ( ! empty( $country_currency_mappings ) ) {
			$currencies = array();
			foreach ( $country_currency_mappings as $currency => $data ) {
				if ( $intersected_countries = array_intersect( $data['countries'], $available_countries ) ) {
					$base_country = reset( $intersected_countries );
				} else {
					$base_country = $data['countries'][0];
				}

				$currencies[] = array(
					'countries' => $data['countries'],
					'baseCountry' => $base_country,
					'currency' => $currency,
					'symbol' => html_entity_decode( get_woocommerce_currency_symbol( $currency ) ),
					'symbolPosition' => $cs_settings->get_currency_symbol_position( $currency ),
					'thousandSeparator' => $cs_settings->get_currency_thousand_separator( $currency ),
					'decimalSeparator' => $cs_settings->get_currency_decimal_separator( $currency ),
					'precision' => $cs_settings->get_currency_decimals( $currency ),
					'priceFormat' => html_entity_decode( $this->get_currency_price_format( $currency ) ),
					'default' => $currency === $default_currency,
				);
			}

			$additional_settings['currencies'] = $currencies;
		}

		return $additional_settings;
	}

	public function get_currency_price_format( $currency ) {
		$currency_pos = \Aelia\WC\CurrencySwitcher\WC_Aelia_CurrencySwitcher::settings()->get_currency_symbol_position( $currency );
		$format       = '%1$s%2$s';

		switch ( $currency_pos ) {
			case 'left':
				$format = '%1$s%2$s';
				break;
			case 'right':
				$format = '%2$s%1$s';
				break;
			case 'left_space':
				$format = '%1$s&nbsp;%2$s';
				break;
			case 'right_space':
				$format = '%2$s&nbsp;%1$s';
				break;
			default:
				$format = '%1$s%2$s';
		}

		return apply_filters( 'woocommerce_price_format', $format, $currency_pos );
	}

	public function graphql_woocommerce_price( $return, $price, $args, $unformatted_price, $symbol ) {
		$convert_to       = $_POST['aelia_cs_currency'];
		$cs_settings      = \Aelia\WC\CurrencySwitcher\WC_Aelia_CurrencySwitcher::settings();
		$default_currency = $cs_settings->default_geoip_currency();

		if ( isset( $convert_to ) && $convert_to !== $default_currency ) {
			$converted_price = \Aelia\WC\CurrencySwitcher\WC_Aelia_CurrencySwitcher::instance()->convert( $unformatted_price, $default_currency, $convert_to );
			return $converted_price;
		}

		return $unformatted_price;
	}

	public function modify_grapqhl_rootquery_cart_fields( $fields ) {
		$fields['cart']['args']['currency'] = array(
			'type' => 'String',
			'description' => 'Current Currency',
		);
		return $fields;
	}

	public function graphql_resolve_field( $result, $source, $args, $context, $info, $type_name, $field_key, $field, $field_resolver ) {
		if ( 'rootquery' === strtolower( $type_name ) && 'cart' === $field_key ) {
			$_POST['aelia_cs_currency'] = $args['currency'];
		} else if ( 'shippingrate' === strtolower( $type_name ) && 'cost' === $field_key ) {
			$convert_to       = $_POST['aelia_cs_currency'];
			$cs_settings      = \Aelia\WC\CurrencySwitcher\WC_Aelia_CurrencySwitcher::settings();
			$default_currency = $cs_settings->default_geoip_currency();

			$converted_price = \Aelia\WC\CurrencySwitcher\WC_Aelia_CurrencySwitcher::instance()->convert( floatval( $result ), $default_currency, $convert_to );

			return $converted_price;
		}
		// else if ( 'cartitem' === strtolower( $type_name )  && 'total' === $field_key ) {
		//     $quantity = isset( $source['quantity'] ) ? $source['quantity'] : 0;
		//     $convert_to = $_POST['aelia_cs_currency'];

		//     $product = wc_get_product( $source['product_id'] );
		//     $converted_product = $this->price_manager()->convert_simple_product_prices( $product, $convert_to );

		//     return $converted_product->get_price() * $quantity;
		// }

		return $result;
	}

	function add_currency_switcher_after_country_field() {
		if ( \is_checkout() && ! \is_wc_endpoint_url( 'order-received' ) ) {
			$currency_switcher_options = get_option( 'wc_aelia_currency_switcher' );
			$enabled_currencies        = $currency_switcher_options['enabled_currencies'];
			$site_currency             = \get_woocommerce_currency();

			$opposing_currency = reset( array_diff( $enabled_currencies, array( $site_currency ) ) );

			$switch_to_currency_text  = apply_filters( 'blaze_commerce_checkout_switch_currency_text', 'Switch to ' . $opposing_currency, $opposing_currency, $site_currency );
			$switch_currency_template = '<p class="checkout-switch-currency" style="margin-top: -15px; margin-bottom: 8px;" data-currency="%1$s"><a style="cursor: pointer;">' . esc_html__( $switch_to_currency_text ) . '</a></p>';
			?>
			<script type="text/javascript">
				(function ($) {
					$(document).ready(function () {
						var currency_switch = $('<?php echo sprintf( $switch_currency_template, $opposing_currency ) ?>');
						currency_switch.on('click', function (e) {
							e.preventDefault();

							var currency = $(this).data('currency');
							document.cookie = "aelia_cs_selected_currency=" + currency + "; path=/; domain=<?php echo COOKIE_DOMAIN ?>";
							window.location.reload();
						});
						$('#billing_country').closest('.row').after(currency_switch)
						$('#shipping_country').closest('.row').after(currency_switch.clone(true, true))
					});
				})(jQuery);
			</script>
			<?php
		}
	}

	public function giftcard_multicurrency_prices( $product_data, $product_id, $available_currencies ) {
		if ( ! empty( $available_currencies ) ) {
			foreach ( $available_currencies as $currency => $value ) {
				$product_data['regularPrice'][ $currency ] = floatval( number_format( (float) $product_data['variations'][0]['regularPrice'][ $currency ], 2 ) );
				$product_data['price'][ $currency ]        = floatval( number_format( (float) $product_data['variations'][0]['price'][ $currency ], 2 ) );
			}
		}

		return $product_data;
	}

	public function variation_multicurrency_prices( $variations_data, $variation_id ) {
		$available_currencies = \Aelia\WC\CurrencySwitcher\WC_Aelia_Reporting_Manager::get_currencies_from_sales();

		$variation_regular_prices        = $this->price_manager()->get_variation_regular_prices( $variation_id );
		$variations_data['regularPrice'] = $variation_regular_prices;

		$variation_sale_prices        = $this->price_manager()->get_variation_sale_prices( $variation_id );
		$variations_data["salePrice"] = $variation_sale_prices;

		if ( ! empty( $available_currencies ) ) {
			foreach ( $available_currencies as $currency => $value ) {
				$converted_variation_prices = array();
				if ( ! isset( $variations_data['regularPrice'][ $currency ] ) || ! isset( $variations_data['salePrice'][ $currency ] ) ) {
					$variation_obj              = wc_get_product( $variation_id );
					$converted_variation        = $this->price_manager()->convert_variation_product_prices( $variation_obj, $currency );
					$converted_variation_prices = array(
						'regular_price' => $converted_variation->get_regular_price(),
						'sale_price' => $converted_variation->get_sale_price(),
					);
				}

				if ( ! isset( $variations_data['regularPrice'][ $currency ] ) ) {
					$variations_data['regularPrice'][ $currency ] = $converted_variation_prices['regular_price'];
				}

				if ( ! isset( $variations_data['salePrice'][ $currency ] ) ) {
					$variations_data['salePrice'][ $currency ] = $converted_variation_prices['sale_price'];
				}

				if ( ! isset( $variations_data['price'][ $currency ] ) ) {
					$_variation_sale_price                 = $variations_data['salePrice'][ $currency ];
					$_variation_regular_price              = $variations_data['regularPrice'][ $currency ];
					$variations_data['price'][ $currency ] = ! empty( $_variation_sale_price ) ? $_variation_sale_price : $_variation_regular_price;
				}

				$variations_data['regularPrice'][ $currency ] = floatval( number_format( (float) $variations_data['regularPrice'][ $currency ], 2 ) );
				$variations_data['salePrice'][ $currency ]    = floatval( number_format( (float) $variations_data['salePrice'][ $currency ], 2 ) );
				$variations_data['price'][ $currency ]        = floatval( number_format( (float) $variations_data['price'][ $currency ], 2 ) );

				unset( $converted_variation_prices, $variation_obj, $converted_variation, $_variation_sale_price, $_variation_regular_price );
			}
		}

		return $variations_data;
	}
}
