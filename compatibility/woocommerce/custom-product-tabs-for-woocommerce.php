<?php
if ( !class_exists( 'Blaze_Wooless_Custom_Product_Tabs_For_Woocommerce_Compatibility' ) ) {
    class Blaze_Wooless_Custom_Product_Tabs_For_Woocommerce_Compatibility {
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
            if ( is_plugin_active( 'yikes-inc-easy-custom-woocommerce-product-tabs/yikes-inc-easy-custom-woocommerce-product-tabs.php' ) ) {
                // var_dump('activated'); exit;
                add_filter( 'wooless_product_tabs', array( $this, 'add_additional_tabs' ), 10, 2 );
            }
        }

        public function add_additional_tabs( $additional_tabs, $product_id )
        {
            // Source of this code can be found on /wp-content/plugins/yikes-inc-easy-custom-woocommerce-product-tabs/yikes-inc-easy-custom-woocommerce-product-tabs.php
            $product_tabs = maybe_unserialize( get_post_meta( $product_id, 'yikes_woo_products_tabs' , true ) );

			if ( is_array( $product_tabs ) && ! empty( $product_tabs ) ) {

				// Setup priorty to loop over, and render tabs in proper order
				$i = 25; 

				foreach ( $product_tabs as $tab ) {

					// Do not show tabs with empty titles on the front end
					if ( empty( $tab['title'] ) ) {
						continue;
					}

                    $additional_tabs[] = array(
                        'title' => $tab['title'],
                        'content' => $tab['content'],
                    );
				}
			}
            return $additional_tabs;
        }
    }

    Blaze_Wooless_Custom_Product_Tabs_For_Woocommerce_Compatibility::get_instance();
}
