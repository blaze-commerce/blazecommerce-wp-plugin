<?php
if ( !class_exists( 'Blaze_Wooless_Woocommerce_Prices_By_Country_Compatibility' ) ) {
    class Blaze_Wooless_Woocommerce_Prices_By_Country_Compatibility {
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
            if ( is_plugin_active( 'woocommerce-prices-by-country/woocommerce-prices-by-country.php' ) ) {
                add_filter( 'blaze_wooless_product_data_for_typesense', array( $this, 'add_multicurrency_prices' ), 10, 2 );
                // add_filter( 'blaze_wooless_additional_site_info', array( $this, 'add_multicurrency_site_info' ), 10, 1 );
            }
        }

        public function add_multicurrency_prices( $product_data, $product_id )
        {
            if ($product_id == 24) {
                $namespace = Aelia\WC\PricesByCountry\WC_Aelia_Prices_By_Country::get_wc_namespace();
                $class = '\\Aelia\\WC\\PricesByCountry\\' . $namespace . '\PricingManager';
                $pricing_manager = $class::Instance();
                $product_price_by_country = $pricing_manager->get_product_prices_by_country(24);
                $regions = Aelia\WC\PricesByCountry\WC_Aelia_Prices_By_Country::settings()->get_regions();
                foreach ( $product_price_by_country as $region_id => $region) {
                    $current_region = $regions[$region_id];
                    $current_country = $current_region['region_name'];
                    foreach($GLOBALS['woocommerce-prices-by-country']->enabled_currencies() as $currency) {
                        // TODO: add currency switcher and map to correct currency
                        $product_data["price"][$current_country] = $region[$currency]['regular_price'];
                        $product_data['regularPrice'][$current_country] = $region[$currency]['regular_price'];
                        $product_data['salePrice'][$current_country] = $region[$currency]['sale_price'];
                    }
                }
                var_dump($product_data); exit;
            }
            
            return $product_data;
        }

        public function add_multicurrency_site_info( $additional_settings )
        {
            $additional_settings['is_multicurrency'] = 'yes';

            $currencies = $additional_settings['regional_data'];

            $other_currencies = array_reduce( WCPBC_Pricing_Zones::get_zones(), function( $carry, $zone ) {
                $carry[] = array(
                    'country' => array_values( $zone->get_countries() )[0],
                    'currency' => $zone->get_currency(),
                );
                return $carry;
            }, array() );

            $currencies = array_merge( $currencies, $other_currencies );

            $additional_settings['regional_data'] = $currencies;

            return $additional_settings;
        }
    }

    Blaze_Wooless_Woocommerce_Prices_By_Country_Compatibility::get_instance();
}
