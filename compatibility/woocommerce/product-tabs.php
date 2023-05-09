<?php
if ( !class_exists( 'Blaze_Wooless_Product_Tabs_Compatibility' ) ) {
    class Blaze_Wooless_Product_Tabs_Compatibility {
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
            add_filter( 'wooless_product_tabs', array( $this, 'add_additional_tabs' ), 10, 2 );
        }

        public function add_additional_tabs( $additional_tabs, $product_id )
        {
            $product_additional_tabs = get_post_meta($product_id, '_additional_tabs', true);

            if (!empty($product_additional_tabs)) {
                foreach ($product_additional_tabs as $tab) {
                    $additional_tabs[] = array(
                        'title' => $tab['tab_title'],
                        'content' => $tab['tab_content'],
                    );
                }
            }
            return $additional_tabs;
        }
    }

    Blaze_Wooless_Product_Tabs_Compatibility::get_instance();
}
