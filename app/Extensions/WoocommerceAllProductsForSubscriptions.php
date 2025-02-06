<?php

namespace BlazeWooless\Extensions;

class WoocommerceAllProductsForSubscriptions {
	private static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		if ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'woocommerce-all-products-for-subscriptions/woocommerce-all-products-for-subscriptions.php' ) ) {
			add_filter( 'wooless_product_query_args', array( $this, 'modify_product_query_args' ), 10 );
		}
	}

	public function modify_product_query_args( array $args ) {
		$args['type'] = array_merge( $args['type'], [ 'subscription', 'variable-subscription' ] );
		return $args;
	}


}