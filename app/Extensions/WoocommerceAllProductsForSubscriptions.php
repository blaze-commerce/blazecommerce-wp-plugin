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
			add_filter( 'blaze_wooless_product_for_typesense_fields', array( $this, 'set_fields' ), 10, 1 );
			add_filter( 'blaze_wooless_product_data_for_typesense', array( $this, 'sync_product_data' ), 99, 3 );
		}
	}

	public function set_fields( $fields ) {
		$fields[] = [ 'name' => 'metaData.subscriptionsATT', 'type' => 'object', 'optional' => true ];
		$fields[] = [ 'name' => 'metaData.subscriptionsATT.has_scheme', 'type' => 'boolean', 'optional' => true ];
		$fields[] = [ 'name' => 'metaData.subscriptionsATT.schemes', 'type' => 'object[]', 'optional' => true ];
		$fields[] = [ 'name' => 'metaData.subscriptionsATT.schemes.period', 'type' => 'string[]', 'optional' => true ];
		$fields[] = [ 'name' => 'metaData.subscriptionsATT.schemes.interval', 'type' => 'int64[]', 'optional' => true ];
		$fields[] = [ 'name' => 'metaData.subscriptionsATT.schemes.length', 'type' => 'int64[]', 'optional' => true ];
		$fields[] = [ 'name' => 'metaData.subscriptionsATT.schemes.trial_period', 'type' => 'string[]', 'optional' => true ];
		$fields[] = [ 'name' => 'metaData.subscriptionsATT.schemes.trial_length', 'type' => 'int64[]', 'optional' => true ];
		$fields[] = [ 'name' => 'metaData.subscriptionsATT.schemes.pricing_model', 'type' => 'string[]', 'optional' => true ];
		$fields[] = [ 'name' => 'metaData.subscriptionsATT.schemes.discount', 'type' => 'int64[]', 'optional' => true ];
		$fields[] = [ 'name' => 'metaData.subscriptionsATT.schemes.sync_date', 'type' => 'int64[]', 'optional' => true ];
		$fields[] = [ 'name' => 'metaData.subscriptionsATT.schemes.context', 'type' => 'string[]', 'optional' => true ];
		$fields[] = [ 'name' => 'metaData.subscriptionsATT.schemes.id', 'type' => 'string[]', 'optional' => true ];
		$fields[] = [ 'name' => 'metaData.subscriptionsATT.schemes.key', 'type' => 'string[]', 'optional' => true ];
		$fields[] = [ 'name' => 'metaData.subscriptionsATT.schemes.is_synced', 'type' => 'boolean[]', 'optional' => true ];

		return $fields;
	}

	public function sync_product_data( $product_data, $product_id, $product ) {
		$subscription_schemes = \WCS_ATT_Product_Schemes::get_subscription_schemes( $product );

		$schemes = array_map( function ($scheme) {
			return $scheme->get_data();
		}, $subscription_schemes );

		$product_data['metaData']['subscriptionsATT'] = [ 
			'has_scheme' => \WCS_ATT_Product_Schemes::has_subscription_schemes( $product, 'local' ),
			'schemes' => $schemes,
		];

		return $product_data;
	}
}