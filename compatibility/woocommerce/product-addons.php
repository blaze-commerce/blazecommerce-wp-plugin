<?php
if ( !class_exists( 'Blaze_Wooless_Product_Addons_Compatibility' ) ) {
    class Blaze_Wooless_Product_Addons_Compatibility {
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
            if ( is_plugin_active( 'woocommerce-product-addons/woocommerce-product-addons.php' ) ) {
                add_filter( 'blaze_wooless_product_data_for_typesense', array( $this, 'add_addons_to_product_schema' ), 10, 2 );
            }
        }

        public function add_addons_to_product_schema( $product_data, $product_id )
        {
            $product_data['addons'] = $this->recompile_addons_data($product_id);
            return $product_data;
        }


        public function recompile_addons_data($product_id)
        {
            $addons = array();
            if ( function_exists( 'get_product_addons' ) ) {
                $addons = get_product_addons($product_id, false);

                foreach ($addons as $key => $addon) {
                    foreach ($addon['options'] as $option_key => $option) {
                        // label_slug
                        $addons[$key]['options'][$option_key]['label_slug'] = sanitize_title($option['label']);
                        // field_name
                        $addons[$key]['options'][$option_key]['field_name'] = 'addon-' . sanitize_title($addon['field-name']);
                    }
                }
            }
            return json_encode($addons);
        }
    }

    Blaze_Wooless_Product_Addons_Compatibility::get_instance();
}
