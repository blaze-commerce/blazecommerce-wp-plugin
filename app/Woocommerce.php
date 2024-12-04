<?php

namespace BlazeWooless;

use BlazeWooless\Collections\Product;

class Woocommerce {
	private static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		add_action( 'woocommerce_order_status_changed', array( $this, 'on_order_status_changed' ), 10, 4 );
		add_action( 'woocommerce_new_product', array( $this, 'on_product_save' ), 10, 2 );
		add_action( 'woocommerce_update_product', array( $this, 'on_product_save' ), 10, 2 );

		add_action( 'woocommerce_trash_product', array( $this, 'on_product_trash_or_untrash' ), 10, 1 );
		add_action( 'trashed_post', array( $this, 'on_product_trash_or_untrash' ), 10, 1 );
		add_action( 'untrashed_post', array( $this, 'on_product_trash_or_untrash' ), 10, 1 );
		add_action( 'before_delete_post', array( $this, 'before_delete_product' ) );

		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'on_checkout_update_order_meta' ), 10, 2 );
		add_action( 'woocommerce_after_product_ordering', array( $this, 'product_reordering' ), 10, 2 );

		add_action( 'woocommerce_get_checkout_url', array( $this, 'append_cart_in_checkout_url' ) );

		add_action( 'ts_product_update', array( $this, 'update_typesense_variation' ), 10, 2 );
		add_action( 'wooless_variation_update', array( $this, 'variation_update' ), 10, 1 );

		add_filter( 'blaze_wooless_product_data_for_typesense', array( $this, 'update_variable_product_price' ), 999, 3 );

		// We set the priority to 1 so that this will be the first to be executed as we will mimic how woo is adding product to cart but via graphql
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 1, 4 );
	}

	/**
	 * Delete typesense product when user permanently deletes a product
	 * 
	 * @param mixed $post_id
	 * @return void
	 */
	public function before_delete_product( $post_id ) {
		$post_type = get_post_type( $post_id );
		if ( $post_type === 'product' || $post_type === 'product_variation' ) {
			$product = wc_get_product( $post_id );
			if ( $product ) {
				try {
					Product::get_instance()->collection()->documents[ $post_id ]->delete();
					do_action( 'ts_product_update', $product->get_id(), $product );
				} catch (\Exception $e) {
					$logger  = wc_get_logger();
					$context = array( 'source' => 'wooless-product-delete' );
					$logger->debug( 'TS Product Delete Exception: ' . $e->getMessage(), $context );
				}
			}
		}
	}


	public function add_cart_item_data( $cart_item_data, $product_id, $variation_id, $quantity ) {
		$enable_system = boolval( bw_get_general_settings( 'enable_system' ) );

		if ( ! $enable_system ) {
			return $cart_item_data;
		}

		$post_data = ! empty( $cart_item_data['woolessGraphqlRequest'] ) ? $cart_item_data['woolessGraphqlRequest'] : null;
		if ( empty( $post_data ) ) {
			// Since the request is not from our wpgraphql request then we just return $cart_item_data and not modify it to avoid conflicts
			return $cart_item_data;
		}

		/**
		 * We modify and merge global post request to our graphql request so that we mimic how a normal add to cart request is done in woocommerce.
		 * Normally add to cart request is a post request and this is the reason why we had to set the priority to 1 when we hook in woocommerce_add_cart_item_data
		 */
		$_POST = array_merge( $_POST, $post_data );
		return $cart_item_data;
	}


	public function append_cart_in_checkout_url( $checkout_url ) {

		$enable_system = boolval( bw_get_general_settings( 'enable_system' ) );

		if ( $enable_system && strpos( $checkout_url, 'https://cart.' ) === false ) {
			$checkout_url = str_replace( 'https://', 'https://cart.', $checkout_url );
		}
		return $checkout_url;
	}

	public function product_reordering( $product_id, $menu_orders ) {

		$menu_orders_for_import = array();
		if ( ! empty( $menu_orders ) ) {
			foreach ( $menu_orders as $product_id => $menu_order ) {
				$menu_orders_for_import[] = array(
					'id' => (string) $product_id,
					'menuOrder' => intval( $menu_order )
				);
			}
			$response = Product::get_instance()->collection()->documents->import( $menu_orders_for_import, array(
				'action' => 'update'
			) );

			$logger  = wc_get_logger();
			$context = array( 'source' => 'wooless-product-menu-ordering' );
			$logger->debug( 'TS Product import response : ' . print_r( $response, 1 ), $context );
		}
	}

	public function on_order_status_changed( $order_id, $old_status, $new_status, $order ) {
		$enable_system = boolval( bw_get_general_settings( 'enable_system' ) );

		if ( ! $enable_system ) {
			return;
		}

		if ( $new_status === 'completed' || $new_status === 'processing' || $new_status === 'cancelled' || $new_status === 'refunded' ) {
			// Get the items in the order
			$items = $order->get_items();

			// Loop through each item and update the corresponding product in Typesense
			foreach ( $items as $item ) {
				$product_id = $item->get_product_id();
				$wc_product = wc_get_product( $product_id );

				if ( $wc_product->get_status() == 'publish' ) {
					try {
						$document_data = Product::get_instance()->generate_typesense_data( $wc_product );
						Product::get_instance()->update( strval( $product_id ), $document_data );
						do_action( 'ts_product_update', $product_id, $wc_product );
					} catch (\Exception $e) {
						error_log( "Error updating product in Typesense during checkout: " . $e->getMessage() );
					}
				}
			}
		}
	}

	public function on_product_trash_or_untrash( $product_id ) {
		$enable_system = boolval( bw_get_general_settings( 'enable_system' ) );

		if ( ! $enable_system ) {
			return;
		}

		$wc_product = wc_get_product( $product_id );
		if ( $wc_product ) {
			$this->on_product_save( $product_id, $wc_product );
		}

	}

	// Function to update the product in Typesense when its metadata is updated in WooCommerce
	public function on_product_save( $product_id, $wc_product ) {
		$enable_system = boolval( bw_get_general_settings( 'enable_system' ) );

		if ( ! $enable_system ) {
			return;
		}

		try {
			do_action( 'ts_before_product_upsert', $wc_product );
			$document_data = Product::get_instance()->generate_typesense_data( $wc_product );
			Product::get_instance()->upsert( $document_data );
			do_action( 'ts_product_update', $product_id, $wc_product );
		} catch (\Exception $e) {
			$logger  = wc_get_logger();
			$context = array( 'source' => 'wooless-product-update' );

			$logger->debug( 'TS Product Update Exception: ' . $e->getMessage(), $context );
			error_log( "Error updating product in Typesense: " . $e->getMessage() );
		}
	}

	public function update_typesense_variation( $product_id, $wc_product ) {

		if ( $wc_product && $wc_product->is_type( 'variable' ) ) {
			$variation_ids = $wc_product->get_children();
			$event_time    = WC()->call_function( 'time' ) + 1;
			as_schedule_single_action( $event_time, 'wooless_variation_update', array( $variation_ids ), 'blaze-wooless', true, 1 );

		}
	}

	public function variation_update( $variation_ids ) {
		try {
			$typsense_product = Product::get_instance();
			$variations_data  = array();
			foreach ( $variation_ids as $variation_id ) {
				$wc_variation = wc_get_product( $variation_id );

				if ( $wc_variation ) {
					$variations_data[] = $typsense_product->generate_typesense_data( $wc_variation );
				}
			}

			$import = $typsense_product->collection()->documents->import( $variations_data, array(
				'action' => 'upsert'
			) );

			$logger  = wc_get_logger();
			$context = array( 'source' => 'wooless-variations-success-import' );
			$logger->debug( print_r( $import, 1 ), $context );
		} catch (\Exception $e) {
			$logger  = wc_get_logger();
			$context = array( 'source' => 'wooless-variations-import' );
			$logger->debug( 'TS Variations Import Exception: ' . $e->getMessage(), $context );
		}
	}

	public function on_checkout_update_order_meta( $order_id, $data ) {

		$enable_system = boolval( bw_get_general_settings( 'enable_system' ) );

		if ( ! $enable_system ) {
			return;
		}

		// Get the order object
		$order = wc_get_order( $order_id );

		// Get the items in the order
		$items = $order->get_items();

		// Loop through each item and update the corresponding product in Typesense
		foreach ( $items as $item ) {
			$product_id = $item->get_product_id();
			$wc_product = wc_get_product( $product_id );
			try {
				$document_data = Product::get_instance()->generate_typesense_data( $wc_product );
				Product::get_instance()->update( strval( $product_id ), $document_data );
				do_action( 'ts_product_update', $product_id, $wc_product );
			} catch (\Exception $e) {
				error_log( "Error updating product in Typesense during checkout: " . $e->getMessage() );
			}

		}
	}

	/**
	 * Use for making sure that we are setting a valid float type on prices so that typesense will accept it
	 */
	public static function format_price( $price ) {
		return (float) number_format( empty( $price ) ? 0 : $price, 4, '.', '' );
	}

	public static function get_currencies() {
		$base_currency = get_woocommerce_currency();
		return apply_filters( 'blaze_wooless_currencies', array(
			$base_currency => $base_currency
		) );
	}

	/**
	 * Update variable product price based on variations
	 * This function is used to update the price of a variable product based on the variations
	 * Since the price of a variable product is not stored in the product itself, we need to get the price from the variations
	 * Hooked to blaze_wooless_get_variation_prices filter, priority 999
	 * Task : https://app.clickup.com/t/86eprwe91
	 * @since   1.5.0
	 * @param   array $product_data
	 * @param   int $product_id
	 * @param   \WC_Product $product
	 * @return  array
	 */
	public function update_variable_product_price( $product_data, $product_id, $product ) {

		if ( $product->get_type() == 'variable' ) {

			try {

				// get variations
				$variations = $product->get_variation_prices( true );

				// find the lowest price among the variations
				$prices = $variations['price'];
				if ( ! empty( $prices ) ) {
					$min_price         = min( $prices );
					$lowest_product_id = array_search( $min_price, $prices );

					if ( $lowest_product_id ) {
						$lowest_product = wc_get_product( $lowest_product_id );
						$variation_data = Product::get_instance()->generate_typesense_data( $lowest_product );

						$variation_data               = apply_filters( 'blaze_wooless_get_variation_prices', $variation_data, $lowest_product_id, $lowest_product );
						$product_data['price']        = $variation_data['price'];
						$product_data['regularPrice'] = $variation_data['regularPrice'];
						$product_data['salePrice']    = $variation_data['salePrice'];
					} else {
						throw new \Exception( 'No variations found for product ' . $product_id );
					}
				}
			} catch (\Exception $e) {
				$logger  = wc_get_logger();
				$context = array( 'source' => 'wooless-variable-product-price' );
				$logger->debug( 'TS Variable Product Price Exception: ' . $e->getMessage(), $context );
			}
		}

		return $product_data;
	}
}
