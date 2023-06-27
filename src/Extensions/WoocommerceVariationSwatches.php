<?php

namespace BlazeWooless\Extensions;

class WoocommerceVariationSwatches
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
        if ( is_plugin_active( 'woo-variation-swatches/woo-variation-swatches.php' ) ) {
            add_filter( 'blaze_wooless_product_attribute_for_typesense', array( $this, 'add_swatches_data' ), 10, 2 );
        }
    }


    public function add_swatches_data($attribute_to_register, $attribute)
    {
        return $attribute_to_register;
    }
}
