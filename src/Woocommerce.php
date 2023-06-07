<?php

namespace BlazeWooless;

use BlazeWooless\Collections\Product;

class Woocommerce
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
        add_action( 'woocommerce_order_status_changed', array( $this, 'on_order_status_changed' ), 10, 4);
        add_action('woocommerce_new_product', array( $this, 'on_product_save' ), 10, 2);
        add_action('woocommerce_update_product', array( $this, 'on_product_save' ), 10, 2);
        add_action('woocommerce_checkout_update_order_meta', array( $this, 'on_checkout_update_order_meta' ), 10, 2);
    }

    public function on_order_status_changed($order_id, $old_status, $new_status, $order)
    {
        if ($new_status === 'completed' || $new_status === 'processing' || $new_status === 'cancelled' || $new_status === 'refunded') {
            // Get the items in the order
            $items = $order->get_items();
    
            // Loop through each item and update the corresponding product in Typesense
            foreach ($items as $item) {
                $product_id = $item->get_product_id();
                $wc_product = wc_get_product($product_id);
    
                if ($wc_product->get_status() == 'publish') {
                    try {
                        $document_data = Product::get_instance()->generate_typesense_data( $wc_product );
                        Product::get_instance()->update( strval($product_id), $document_data );
                        do_action('ts_product_update', $product_id, $wc_product);
                    } catch (\Exception $e) {
                        error_log("Error updating product in Typesense during checkout: " . $e->getMessage());
                    }
                }
            }
        }
    }

    // Function to update the product in Typesense when its metadata is updated in WooCommerce
    public function on_product_save($product_id, $wc_product)
    {
        // Creating global variable so that this function only runs once if product id is exactly equal to product id
        global $bwl_previous_product_id;
        if ($bwl_previous_product_id === $product_id) {
            // Check if the product is published before updating typesense data
            if ($wc_product->get_status() == 'publish') {
                try {
                    $document_data = Product::get_instance()->generate_typesense_data( $wc_product );
                    Product::get_instance()->update( strval($product_id), $document_data );
                    do_action('ts_product_update', $product_id, $wc_product);
                } catch (\Exception $e) {
                    error_log("Error updating product in Typesense: " . $e->getMessage());
                }
            }
        }
        // Setting the variable in memory so that we can use this later for checking
        $bwl_previous_product_id = $product_id;
    }

    public function on_checkout_update_order_meta($order_id, $data)
    {
        // Get the order object
        $order = wc_get_order($order_id);

        // Get the items in the order
        $items = $order->get_items();

        // Loop through each item and update the corresponding product in Typesense
        foreach ($items as $item) {
            $product_id = $item->get_product_id();
            $wc_product = wc_get_product($product_id);

            if ($wc_product->get_status() == 'publish') {
                try {
                    $document_data = Product::get_instance()->generate_typesense_data( $wc_product );
                    Product::get_instance()->update( strval($product_id), $document_data );
                    do_action('ts_product_update', $product_id, $wc_product);
                } catch (\Exception $e) {
                    error_log("Error updating product in Typesense during checkout: " . $e->getMessage());
                }
            }
        }
    }
}
