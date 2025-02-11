<?php

namespace BlazeWooless\Extensions;

class WoocommerceSubscription {
	private static $instance = null;

	private static $post_type = [ 'subscription', 'variable-subscription', 'subscription_variation' ];


	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		if ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'woocommerce-subscriptions/woocommerce-subscriptions.php' ) ) {
			add_filter( 'wooless_product_query_args', array( $this, 'modify_product_query_args' ), 10 );
			add_filter( 'blaze_wooless_product_for_typesense_fields', array( $this, 'set_fields' ), 10, 1 );
			add_filter( 'blaze_wooless_product_data_for_typesense', array( $this, 'sync_product_data' ), 99, 3 );
		}
	}

	public function modify_product_query_args( array $args ) {
		$args['type'] = array_merge( $args['type'], self::$post_type );
		return $args;
	}

	public function set_fields( $fields ) {

		$fields[] = [ 'name' => 'metaData.subscriptions', 'type' => 'object[]', 'optional' => true ];
		$fields[] = [ 'name' => 'metaData.subscriptions.length', 'type' => 'int64[]', 'optional' => true ];
		$fields[] = [ 'name' => 'metaData.subscriptions.period', 'type' => 'string[]', 'optional' => true ];
		$fields[] = [ 'name' => 'metaData.subscriptions.period_interval', 'type' => 'int64[]', 'optional' => true ];
		$fields[] = [ 'name' => 'metaData.subscriptions.sign_up_fee', 'type' => 'float[]', 'optional' => true ];
		$fields[] = [ 'name' => 'metaData.subscriptions.trial_period', 'type' => 'string[]', 'optional' => true ];
		$fields[] = [ 'name' => 'metaData.subscriptions.trial_length', 'type' => 'int64[]', 'optional' => true ];

		return $fields;
	}

	public function sync_product_data( $product_data, $product_id, $product ) {


		if ( $product->is_type( [ 'subscription_variation', 'subscription' ] ) ) {

			$product_data['metaData']['subscriptions'] = [ 
				'length' => $product->get_meta( '_subscription_length' ),
				'period' => $product->get_meta( '_subscription_period' ),
				'period_interval' => $product->get_meta( '_subscription_period_interval' ),
				'sign_up_fee' => $product->get_meta( '_subscription_sign_up_fee' ),
				'trial_period' => $product->get_meta( '_subscription_trial_period' ),
				'trial_length' => $product->get_meta( '_subscription_trial_length' ),
			];

		}

		return $product_data;
	}


}