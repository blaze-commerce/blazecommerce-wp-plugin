<?php

namespace BlazeWooless\Extensions;

class WooDiscountRules {
    public static $instance = null;

    public static function get_instance()
    {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function __construct()
    {
        if ( is_plugin_active( 'woo-discount-rules/woo-discount-rules.php' ) ) {
            add_filter( 'blaze_wooless_product_data_for_typesense', array( $this, 'apply_discount_rules' ), 20, 2 );
            add_filter( 'blaze_wooless_cross_sell_data_for_typesense', array( $this, 'apply_discount_rules' ), 20, 2 );
        }
    }

    public function apply_discount_rules( $product_data, $product_id )
    {
        $product = \Wdr\App\Helpers\Woocommerce::getProduct($product_id);
        $sale_prices = $product_data["salePrice"];
        foreach ($sale_prices as $country => $price) {
            $product_regular_price = $product_data['regularPrice'][$country];
            $product->set_price($product_regular_price);
            $new_sale_price = \Wdr\App\Controllers\Admin\WDRAjax::$manage_discount->getProductSalePrice($product_regular_price, $product);
            $product_data["salePrice"][$country] = floatval(number_format((float) $new_sale_price, 2));
        }
        return $product_data;
    }
}