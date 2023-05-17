<?php
if ( !class_exists( 'Blaze_Wooless_Woocommerce_Price_Based_On_Country_Compatibility' ) ) {
    class Blaze_Wooless_Woocommerce_Price_Based_On_Country_Compatibility {
        private static $instance = null;
        protected $currency_country_map = array(
            "AUD" => 'AU',
            "NZD" => 'NZ',
            "USD" => 'US',
        );

        public static function get_instance()
        {
            if (self::$instance === null) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        public function __construct()
        {
            if ( is_plugin_active( 'woocommerce-product-price-based-on-countries/woocommerce-product-price-based-on-countries.php' ) ) {
                add_filter( 'blaze_wooless_product_data_for_typesense', array( $this, 'add_multicurrency_prices' ), 10, 2 );
                add_filter( 'blaze_wooless_additional_site_info', array( $this, 'add_multicurrency_site_info' ), 10, 1 );
            }
        }

        public function add_multicurrency_prices( $product_data, $product_id )
        {
            foreach ( WCPBC_Pricing_Zones::get_zones() as $zone ) {
                $currency = $zone->get_currency();
                if ( isset( $product_data["price"] ) && !isset( $product_data["price"][ $currency ] ) ) {
                    $product_data["price"][ $currency ] = floatval($zone->get_exchange_rate_price_by_post( $product_id, '_price' ));
                }
                if ( isset( $product_data["regularPrice"] ) && !isset( $product_data["regularPrice"][ $currency ] ) ) {
                    $product_data["regularPrice"][ $currency ] = floatval($zone->get_exchange_rate_price_by_post( $product_id, '_regular_price' ));
                }
                if ( isset( $product_data["salePrice"] ) && !isset( $product_data["salePrice"][ $currency ] ) ) {
                    $product_data["salePrice"][ $currency ] = floatval($zone->get_exchange_rate_price_by_post( $product_id, '_sale_price' ));
                }
            }
            return $product_data;
        }

        public function add_multicurrency_site_info( $additional_settings )
        {
            $additional_settings['is_multicurrency'] = 'yes';

            $currencies = array();

            $site_currency = get_woocommerce_currency();
            $currencies[] = array(
                'country' => $this->currency_country_map[ $site_currency ],
                'currency' => $site_currency,
            );

            $other_currencies = array_map( function( $zone ) {
                return array(
                    'country' => array_values( $zone->get_countries() )[0],
                    'currency' => $zone->get_currency(),
                );
            }, WCPBC_Pricing_Zones::get_zones() );

            $currencies = array_merge( $currencies, $other_currencies );

            $additional_settings['multicurrency_data'] = json_encode( array_values( $currencies ) );

            return $additional_settings;
        }
    }

    Blaze_Wooless_Woocommerce_Price_Based_On_Country_Compatibility::get_instance();
}
