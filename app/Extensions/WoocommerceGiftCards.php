<?php

namespace BlazeWooless\Extensions;

class WoocommerceGiftCards 
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
        if ( is_plugin_active( 'pw-gift-cards/pw-gift-cards.php' ) ) {
            add_filter( 'blaze_wooless_product_data_for_typesense', array( $this, 'woocommerce_giftcard_price' ), 10, 2 );
        }
    }

	public function woocommerce_giftcard_price( $product_data, $product_id ) {
        if($product_data['productType'] === 'pw-gift-card') {
			$currency = get_option('woocommerce_currency');

            $product_data['price'] = [
				$currency => floatval(get_post_meta($product_id, '_price', true))
			];
			$product_data['regularPrice'] = [
				$currency => floatval(get_post_meta($product_id, '_price', true))
			];
        }

		return $this->giftcard_multicurrency_prices( $product_data, $product_id );
	}

    public function giftcard_multicurrency_prices( $product_data, $product_id ) {
        if( is_plugin_active( 'woocommerce-aelia-currencyswitcher/woocommerce-aelia-currencyswitcher.php' ) ) {
            return apply_filters('blaze_commerce_giftcard_multicurrency_prices', $product_data, $product_id);
    }

        return $product_data;
    }
}
