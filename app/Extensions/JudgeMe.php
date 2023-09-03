<?php

namespace BlazeWooless\Extensions;

class JudgeMe 
    {
        private static $instance = null;
        public static $API_URL = 'https://judge.me/api/v1';
        public static $WIDGET_URL = 'https://cache.judge.me/widgets/woocommerce/';
        public static $PRODUCTS_ENDPOINT = '/products/?';

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

                add_filter('blaze_wooless_generate_product_reviews_widgets', array( $this, 'generate_product_reviews_widgets' ), 10, 2);
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
            $SHOP_DOMAIN = $this->reformat_url( bw_get_general_settings( 'shop_domain' ) );
            $products_batch = array();

            if( $this->get_api_key() ) {
                $finished = false;
                $page = 1;

                while (!$finished) {
                    $params = array(
                        'api_token' => $this->get_api_key(),
                        'shop_domain' => $SHOP_DOMAIN,
                        'page' => $page,
                        'per_page' => 100,
                    );

                    $PRODUCT_PARAMETERS = http_build_query( $params );

                    $products = wp_remote_get( self::$API_URL . self::$PRODUCTS_ENDPOINT . $PRODUCT_PARAMETERS );
        
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
            }

            return $products_batch;
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

        public function get_api_key() {
            return get_option('judgeme_shop_token');
        }

        public function generate_product_reviews_widgets() {
            $SHOP_DOMAIN = $this->reformat_url( bw_get_general_settings( 'shop_domain' ) );
            $products = $this->generate_product_data();

            $product_items = array();

            $product_ids = array();

            $widget = array();

            if(!empty($products)) {
                foreach($products as $product) {
                    $product_items[] = array(
                        'product_external_id' => $product['external_id'],
                        'product_handle' => $product['handle'],
                    );
                }

                foreach($product_items as $product_id) {
                    $product_ids[] = $product_id['product_external_id'];
                }

                $REVIEWS_WIDGETS_PARAMETERS = 'review_widget_product_ids=' . implode(",", $product_ids);
    
                $result = wp_remote_get( self::$WIDGET_URL . $SHOP_DOMAIN . "?" . $REVIEWS_WIDGETS_PARAMETERS );
    
                $response = json_decode( wp_remote_retrieve_body($result), true );

                foreach($product_items as $product_item) {
                    foreach($response['review_widgets'] as $key=>$value) {
                        if($product_item['product_external_id'] === $key) {
                            $widget[] = array(
                                'slug' => $product_item['product_handle'],
                                'widget' => $value,
                            );
                        }
                    }
                }

                return $widget;
            }
            
            return null;
        }
    }
