<?php

namespace BlazeWooless\Extensions;

use BlazeWooless\Settings\RegionalSettings;

class WoocommerceAeliaCurrencySwitcher 
{
    private static $instance = null;

    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function __construct()
    {
        if ( is_plugin_active( 'woocommerce-aelia-currencyswitcher/woocommerce-aelia-currencyswitcher.php' ) ) {
            add_filter( 'blaze_wooless_product_data_for_typesense', array( $this, 'add_multicurrency_prices' ), 10, 2 );
            add_filter( 'blaze_wooless_additional_site_info', array( $this, 'add_multicurrency_site_info' ), 10, 1 );
        }
    }

    public function add_multicurrency_prices( $product_data, $product_id )
    {
        $available_currencies = \Aelia\WC\CurrencySwitcher\WC_Aelia_Reporting_Manager::get_currencies_from_sales();

        $regular_prices = \Aelia\WC\CurrencySwitcher\WC27\WC_Aelia_CurrencyPrices_Manager::instance()->get_product_regular_prices($product_id);
        $product_data["regularPrice"] = $regular_prices;

        $sale_prices = \Aelia\WC\CurrencySwitcher\WC27\WC_Aelia_CurrencyPrices_Manager::instance()->get_product_sale_prices($product_id);
        $product_data["salePrice"] = $sale_prices;

        foreach ($available_currencies as $currency => $value) {
            $converted_prices = array();
            if (! isset( $product_data['regularPrice'][ $currency ]) || ! isset( $product_data['salePrice'][ $currency ]) ) {
                $product = wc_get_product( $product_id );
                $converted_product = \Aelia\WC\CurrencySwitcher\WC27\WC_Aelia_CurrencyPrices_Manager::instance()->convert_simple_product_prices( $product, $currency );
                $converted_prices = array(
                    'regular_price' => $converted_product->get_regular_price(),
                    'sale_price' => $converted_product->get_sale_price(),
                );
            }

            if ( ! isset( $product_data['regularPrice'][ $currency ]) ) {
                $product_data['regularPrice'][ $currency ] = $converted_prices['regular_price'];
            }

            if ( ! isset( $product_data['salePrice'][ $currency ]) ) {
                $product_data['salePrice'][ $currency ] = $converted_prices['sale_price'];
            }

            if ( ! isset( $product_data['price'][ $currency ]) ) {
                $_sale_price = $product_data['salePrice'][ $currency ];
                $_regular_price = $product_data['regularPrice'][ $currency ];
                $product_data['price'][ $currency ] = !empty( $_sale_price ) ? $_sale_price : $_regular_price;
            }

            $product_data['regularPrice'][ $currency ] = number_format((float) $product_data['regularPrice'][ $currency ], 2);
            $product_data['salePrice'][ $currency ] =  number_format((float) $product_data['salePrice'][ $currency ], 2);
            $product_data['price'][ $currency ] =  number_format((float) $product_data['price'][ $currency ], 2);
        }

        return $product_data;
    }

    public function add_multicurrency_site_info( $additional_settings )
    {
        $additional_settings['is_multicurrency'] = 'yes';

        $cs_settings = \Aelia\WC\CurrencySwitcher\WC_Aelia_CurrencySwitcher::settings();
        $available_currencies = \Aelia\WC\CurrencySwitcher\WC_Aelia_Reporting_Manager::get_currencies_from_sales();
        $default_currency = $cs_settings->default_geoip_currency();

        $available_countries = RegionalSettings::get_selected_regions();
        // var_dump($available_countries); exit;
        // var_dump($available_currencies); exit;
        $aelia_currency_switcher_options = get_option('wc_aelia_currency_switcher', false);
        $country_currency_mappings = $aelia_currency_switcher_options['currency_countries_mappings'];
        $currencies = array();
        foreach ($country_currency_mappings as $currency => $data) {
            if ( $intersected_countries = array_intersect( $data['countries'], $available_countries ) ) {
                $base_country = reset($intersected_countries);
            } else {
                $base_country = $data['countries'][0];
            }

            $currencies[] = array(
                'countries' => $data['countries'],
                'baseCountry' => $base_country,
                'currency' => $currency,
                'symbol' => html_entity_decode(get_woocommerce_currency_symbol($currency)),
                'symbolPosition' => $cs_settings->get_currency_symbol_position($currency),
                'thousandSeparator' => $cs_settings->get_currency_thousand_separator($currency),
                'decimalSeparator' => $cs_settings->get_currency_decimal_separator($currency),
                'precision' => $cs_settings->get_currency_decimals($currency),
                'priceFormat' => html_entity_decode($this->get_currency_price_format($currency)),
                'default'   => $currency === $default_currency,
            );
        }

        $additional_settings['currencies'] = $currencies;

        return $additional_settings;
    }

    public function get_currency_price_format($currency) {
        $currency_pos = \Aelia\WC\CurrencySwitcher\WC_Aelia_CurrencySwitcher::settings()->get_currency_symbol_position($currency);
        $format = '%1$s%2$s';

        switch($currency_pos) {
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

        return apply_filters('woocommerce_price_format', $format, $currency_pos);
    }
}
