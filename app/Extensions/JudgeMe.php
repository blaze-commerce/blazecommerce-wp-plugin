<?php

namespace BlazeWooless\Extensions;

class JudgeMe 
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
            if ( is_plugin_active( 'judgeme-product-reviews-woocommerce/judgeme.php' ) ) {
                add_filter( 'blaze_wooless_additional_site_info', array( $this, 'add_review_config_to_site_info' ), 10, 2 );

                add_filter('blaze_wooless_generate_product_data', array( $this, 'generate_product_data' ), 10, 2);
            }
        }

        public function add_review_config_to_site_info( $additional_settings )
        {
            if ( $html_miracle = get_option( 'judgeme_widget_html_miracle' ) ) {
                $additional_settings['judgeme_widget_html_miracle'] = $html_miracle;
            }
            
            if ( $setting = get_option( 'judgeme_widget_settings' ) ) {
                $additional_settings['judgeme_widget_settings'] = $setting;
            }

            return $additional_settings;
        }

        public function generate_product_data() {
            $products_endpoint = '/products/?';
            $api_url = 'https://judge.me/api/v1';
            $site_url = $this->reformat_url( get_site_url() );
            $base_url = '';
            if($site_url === 'stg-premiumvape-wooless.s1.blz.onl') {
                $base_url = 'premiumvape.co.nz';
            }

            if( $api_key = get_option('judgeme_shop_token') ) {
                $finished = false;
                $page = 1;

                $products_batch = array();

                while (!$finished) {
                    $product_parameters = http_build_query( array(
                        'api_token' => $api_key,
                        'shop_domain' => $base_url,
                        'page' => $page,
                        'per_page' => 100,
                    ) );

                    $products = wp_remote_get( $api_url . $products_endpoint . $product_parameters );
        
                    $response = json_decode( wp_remote_retrieve_body($products), true );

                    if (empty($response['products'])) {
                        $finished = true;
                        continue;
                    }

                    foreach($response['products'] as $product_data) {
                        $products_batch[] = $product_data;
                    }

                    unset($response);

                    // Increment the page number
                    $page++;
                }

                return $products_batch;
            }
        }

        public function reformat_url( $url ) {
            $disallowed = array('http://', 'https://');
            foreach( $disallowed as $d ) {
               if( strpos($url, $d) === 0 ) {
                  $removed_http = str_replace($d, '', $url);
                  if( strpos($url, 'www.') === 0 ) {
                     return str_replace('www.', '', $removed_http);
                  }
                  return $removed_http;
               }
            }
            return $url;
        }
    }
