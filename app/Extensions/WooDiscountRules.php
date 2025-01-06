<?php

namespace BlazeWooless\Extensions;

use BlazeWooless\Woocommerce;

class WooDiscountRules {
	private static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function is_plugin_active() {
		return function_exists( 'is_plugin_active' ) && is_plugin_active( 'woo-discount-rules/woo-discount-rules.php' );
	}


	public function __construct() {
		if ( $this->is_plugin_active() ) {
			add_filter( 'blaze_wooless_product_for_typesense_fields', array( $this, 'set_fields' ), 99, 1 );
			add_action( 'ts_before_product_upsert', array( $this, 'prepare_discount_data' ) );
			add_action( 'blaze_wooless_pre_sync_products', array( $this, 'prepare_discount_data' ) );
			add_filter( 'blaze_wooless_product_data_for_typesense', array( $this, 'sync_product_data' ), 99, 3 );
		}
	}

	/**
	 * Set collection fields for gift card products
	 * @param array $fields
	 * @return array
	 */
	public function set_fields( $fields ) {
		$fields[] = array( 'name' => 'metaData.discountRule', 'type' => 'object', 'optional' => true );
		$fields[] = array( 'name' => 'metaData.discountRule.filters', 'type' => 'string', 'optional' => true );
		$fields[] = array( 'name' => 'metaData.discountRule.bulk_adjustments', 'type' => 'string', 'optional' => true );
		$fields[] = array( 'name' => 'metaData.discountRule.advanced_discount_message', 'type' => 'string', 'optional' => true );

		return $fields;
	}

	public function prepare_discount_data() {
		if ( ! class_exists( '\Wdr\App\Helpers\Rule' ) )
			return;

		$rule_helper = new \Wdr\App\Helpers\Rule();
		$rules = $rule_helper->getAllRules( [] );

		// filter rules to get only active rules by checking property enabled is true
		$rules = array_filter( $rules, function ($rule) {
			return boolval( $rule->rule->enabled ) === true;
		} );


		do_action(
			"inspect",
			[ 
				"prepare_discount_data_ver_2",
				[ 
					"rules" => $rules,
				]
			]
		);

		$rules = array_map( function ($rule) {
			$new_rule = new \stdClass();
			$new_rule->filters = $rule->rule->filters;
			$new_rule->bulk_adjustments = $rule->rule->bulk_adjustments;
			$new_rule->advanced_discount_message = $rule->rule->advanced_discount_message;
			return $new_rule;
		}, $rules );

		do_action(
			"inspect",
			[ 
				"prepare_discount_data",
				[ 
					"rules" => $rules,
				]
			]
		);

		set_transient( 'blaze_commerce_discount_data', $rules, 15 * MINUTE_IN_SECONDS );
	}

	public function sync_product_data( $product_data, $product_id, $product ) {

		if ( class_exists( '\Wdr\App\Helpers\Rule' ) ) {

			$is_applied = true;

			$product_categories = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
			$discount_rules = get_transient( 'blaze_commerce_discount_data' );

			foreach ( $discount_rules as $rule ) {

				if ( $rule->type === "product_category" ) {
					if ( $rule->method === "in_list" && ! array_intersect( $product_categories, $rule->value ) ) {
						$is_applied = false;
					} elseif ( $rule->method === "not_in_list" && array_intersect( $product_categories, $rule->value ) ) {
						$is_applied = false;
					}
				}
			}

			if ( $is_applied ) {

				$product_data['metaData']['discountRule'] = [ 
					'filters' => $rule->filters,
					'bulk_adjustments' => $rule->bulk_adjustments,
					'advanced_discount_message' => $rule->advanced_discount_message,
				];
			}

		}
		return $product_data;
	}

}