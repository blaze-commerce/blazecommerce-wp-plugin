<?php

namespace BlazeWooless\Extensions;

class WoocommerceAfterpay {
	private static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		if ( is_plugin_active( 'afterpay-gateway-for-woocommerce/afterpay-gateway-for-woocommerce.php' ) ) {
			add_filter( 'blaze_wooless_additional_site_info', array( $this, 'woocommerce_is_afterpay_enabled' ), 10, 1 );
		}
	}

	public function woocommerce_is_afterpay_enabled( $additional_settings ) {
		if ( $woocommerce_afterpay_settings = get_option( 'woocommerce_afterpay_settings' ) ) {
			if ( $woocommerce_afterpay_settings['enabled'] === 'yes' ) {
				$additional_settings['woocommerce_is_afterpay_enabled'] = "true";
			}
		}

		return $additional_settings;
	}
}
