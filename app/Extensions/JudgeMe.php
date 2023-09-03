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

                add_action('blaze_wooless_generate_product_data', array( $this, 'generate_product_data' ), 10, 1);

                add_filter('blaze_wooless_product_data_for_typesense', array( $this, 'get_product_reviews_data' ), 10, 2);

                add_filter('blaze_wooless_cross_sell_data_for_typesense', array( $this, 'get_cross_sell_reviews_data' ), 10, 2);
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

                    $result = wp_remote_get( self::$API_URL . self::$PRODUCTS_ENDPOINT . $PRODUCT_PARAMETERS );
        
                    $response = json_decode( wp_remote_retrieve_body($result), true );

                    if (empty($response['products'])) {
                        $finished = true;
                        continue;
                    }

                    foreach($response['products'] as $products) {
                        $products_batch[] = $products;
                    }

                    unset($response);

                    // Increment the page number
                    $page++;
                }
            }

            $product_reviews = $this->generate_product_reviews($products_batch);

            update_option('judgeme_product_reviews', $product_reviews);
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

        public function generate_product_reviews($products) {
            $SHOP_DOMAIN = $this->reformat_url( bw_get_general_settings( 'shop_domain' ) );
            $product_reviews = array();

            if(!empty($products)) {
                foreach($products as $product) {
                    $product_ids[] = $product['external_id'];
                }

                $REVIEWS_WIDGETS_PARAMETERS = 'review_widget_product_ids=' . implode(",", $product_ids);
    
                $result = wp_remote_get( self::$WIDGET_URL . $SHOP_DOMAIN . "?" . $REVIEWS_WIDGETS_PARAMETERS );
    
                $response = json_decode( wp_remote_retrieve_body($result), true );

                foreach($products as $product) {
                    foreach($response['review_widgets'] as $key=>$value) {
                        if($product['external_id'] === $key) {
                            $average_rating = $this->get_reviews_average_rating($value);
                            $rating_count = $this->get_reviews_rating_count($value);
                            $product_reviews[$product['handle']] = array(
                                'average' => (float)$average_rating[1],
                                'count' => (int)$rating_count[1],
                            );
                        }
                    }
                }
            }

            unset($response);

            return $product_reviews;
        }

        public function get_product_reviews_data($product_data, $product_id) {
            $reviews = get_option('judgeme_product_reviews');
                
            if(!empty($reviews[$product_data['slug']])) {
                $product_data['judgemeReviews'] = $reviews[$product_data['slug']];
            }

            unset($reviews);

            return $product_data;
        }

        public function get_cross_sell_reviews_data($product_data) {
            $reviews = get_option('judgeme_product_reviews');
            $product = array();

            foreach($product_data as $product) {
                if(!empty($reviews[$product['slug']])) {
                    $product['judgemeReviews'] = $reviews[$product['slug']];
                }
            }

            unset($product_data);
            unset($reviews);

            return $product;
        }

        public function get_reviews_average_rating($html) {
            $re = "/data-average-rating='(.*?)'/m";
            preg_match_all($re, $html, $matches, PREG_SET_ORDER, 0);
            
            return $matches[0];
        }

        public function get_reviews_rating_count($html) {
            $re = "/data-number-of-reviews='(.*?)'/m";
            preg_match_all($re, $html, $matches, PREG_SET_ORDER, 0);

            return $matches[0];
        }
    }
