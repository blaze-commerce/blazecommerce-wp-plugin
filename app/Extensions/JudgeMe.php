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
    }
