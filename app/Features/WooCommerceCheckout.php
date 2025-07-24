<?php

namespace BlazeWooless\Features;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WooCommerceCheckout
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
		// Only initialize if WooCommerce is active and required functions exist
		if ( $this->is_woocommerce_available() ) {
			add_filter( 'woocommerce_checkout_fields', array( $this, 'modify_checkout_fields' ), 10, 1 );
		}
	}

	/**
	 * Check if WooCommerce is available and required functions exist
	 *
	 * @return bool True if WooCommerce is available
	 */
	private function is_woocommerce_available() {
		// Check if WooCommerce class exists
		if ( ! class_exists( 'WooCommerce' ) ) {
			return false;
		}

		// Check if WooCommerce is active
		if ( ! function_exists( 'WC' ) ) {
			return false;
		}

		// Check if required WordPress functions exist
		if ( ! function_exists( 'add_filter' ) ) {
			return false;
		}

		// Check if WooCommerce version is compatible (3.0+)
		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '3.0', '<' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Modify WooCommerce checkout fields to fix incorrect labels
	 *
	 * @param array $fields The checkout fields
	 * @return array Modified checkout fields
	 */
	public function modify_checkout_fields( $fields ) {
		// Ensure $fields is an array to prevent fatal errors
		if ( ! is_array( $fields ) ) {
			return $fields;
		}

		try {
			// Fix the checkbox label for billing address same as shipping address
			if ( isset( $fields['billing']['billing_address_same_as_shipping']['label'] ) ) {
				$fields['billing']['billing_address_same_as_shipping']['label'] = 'Billing address same as shipping address';
			}

			// Alternative approach: Check for any field that might have the incorrect label
			foreach ( $fields as $field_group => $group_fields ) {
				if ( ! is_array( $group_fields ) ) {
					continue;
				}

				foreach ( $group_fields as $field_key => $field_data ) {
					if ( ! is_array( $field_data ) || ! isset( $field_data['label'] ) ) {
						continue;
					}

					// Check for the incorrect label and fix it
					if ( $field_data['label'] === 'Shipping address same as billing address' ) {
						$fields[$field_group][$field_key]['label'] = 'Billing address same as shipping address';
					}
				}
			}

		} catch ( Exception $e ) {
			// Log error if logging is available, but don't break the checkout
			if ( function_exists( 'error_log' ) ) {
				error_log( 'BlazeCommerce WooCommerceCheckout Error: ' . $e->getMessage() );
			}
		}

		return $fields;
	}
}
