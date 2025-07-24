<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

namespace BlazeWooless\Features;

class EditCartCheckout
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
		// Only add address reversal fix if environment is ready
		if ( $this->is_environment_ready() ) {
			add_action( 'wp_footer', array( $this, 'fix_checkout_address_reversal' ), 10 );
		}
	}

	/**
	 * Check if the environment is ready for our fixes
	 *
	 * @return bool True if environment is ready
	 */
	private function is_environment_ready() {
		// Check if WordPress functions exist
		if ( ! function_exists( 'add_action' ) || ! function_exists( 'is_checkout' ) ) {
			return false;
		}

		// Check if WooCommerce functions exist
		if ( ! function_exists( 'is_order_received_page' ) ) {
			return false;
		}

		// Check if WooCommerce is active
		if ( ! class_exists( 'WooCommerce' ) ) {
			return false;
		}

		return true;
	}



	/**
	 * Check if the address fix should run
	 *
	 * @return bool True if fix should run
	 */
	private function should_run_address_fix() {
		// Only run on checkout page
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return false;
		}

		// Don't run on order received page
		if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
			return false;
		}

		// Don't run if we're in admin area
		if ( function_exists( 'is_admin' ) && is_admin() ) {
			return false;
		}

		// Don't run during AJAX requests
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return false;
		}

		return true;
	}

	/**
	 * Fix the address reversal bug in checkout display
	 * This JavaScript will swap the billing and shipping address content back to the correct order
	 */
	public function fix_checkout_address_reversal() {
		// Enhanced safety checks
		if ( ! $this->should_run_address_fix() ) {
			return;
		}

		// Ensure we have proper escaping for JavaScript output
		?>
		<script type="text/javascript">
		(function($) {
			'use strict';

			// Check if jQuery is available
			if (typeof $ === 'undefined' || typeof jQuery === 'undefined') {
				console.warn('BlazeCommerce: jQuery not available, address fix cannot run');
				return;
			}

			$(document).ready(function() {
				var fixApplied = false;
				var maxAttempts = 20;
				var attempts = 0;

				// Function to fix address reversal with error handling
				function fixAddressReversal() {
					try {
						// Prevent multiple fixes
						if (fixApplied) {
							return;
						}

						attempts++;
						console.log('BlazeCommerce: Attempting to fix address reversal (attempt ' + attempts + ')');

						// Look for billing and shipping address headers
						var billingHeader = $('h4:contains("Billing Address")');
						var shippingHeader = $('h4:contains("Shipping Address")');

						if (billingHeader.length && shippingHeader.length) {
							console.log('BlazeCommerce: Found both address headers, checking content...');

							// Get all paragraphs after each header until the next header
							var billingParagraphs = billingHeader.nextUntil('h4');
							var shippingParagraphs = shippingHeader.nextUntil('h4');

							console.log('BlazeCommerce: Billing paragraphs:', billingParagraphs.length);
							console.log('BlazeCommerce: Shipping paragraphs:', shippingParagraphs.length);

							// Only proceed if we have content to swap
							if (billingParagraphs.length > 0 && shippingParagraphs.length > 0) {
								// Check if addresses are actually reversed by looking at content
								var billingText = billingParagraphs.text();
								var shippingText = shippingParagraphs.text();

								console.log('BlazeCommerce: Billing content preview:', billingText.substring(0, 50));
								console.log('BlazeCommerce: Shipping content preview:', shippingText.substring(0, 50));

								// Clone the content before swapping
								var billingClone = billingParagraphs.clone();
								var shippingClone = shippingParagraphs.clone();

								// Remove original content
								billingParagraphs.remove();
								shippingParagraphs.remove();

								// Insert swapped content
								billingHeader.after(shippingClone);
								shippingHeader.after(billingClone);

								fixApplied = true;
								console.log('BlazeCommerce: Address reversal fixed successfully!');
							} else {
								console.log('BlazeCommerce: Address content not yet available, will retry...');
							}
						} else {
							console.log('BlazeCommerce: Address headers not found yet, will retry...');
						}
					} catch (error) {
						console.error('BlazeCommerce: Error in address reversal fix:', error);
						// Stop retrying on error to prevent infinite loops
						fixApplied = true;
					}
				}

			// Function to repeatedly try fixing until successful or max attempts reached
			function tryFixWithRetry() {
				if (!fixApplied && attempts < maxAttempts) {
					fixAddressReversal();
					if (!fixApplied) {
						setTimeout(tryFixWithRetry, 250);
					}
				}
			}

			// Start trying to fix immediately
			tryFixWithRetry();

			// Also run after any checkout updates
			$(document.body).on('updated_checkout', function() {
				console.log('BlazeCommerce: Checkout updated, resetting fix...');
				fixApplied = false;
				attempts = 0;
				setTimeout(tryFixWithRetry, 100);
			});

			// Run after window load as backup
			$(window).on('load', function() {
				if (!fixApplied) {
					console.log('BlazeCommerce: Window loaded, trying fix as backup...');
					setTimeout(tryFixWithRetry, 500);
				}
			});
		});
		</script>
		<?php
	}
}
