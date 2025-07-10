<?php

namespace BlazeWooless\Extensions;

class WoocommerceProductLabel {
	private static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		if ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'woocommerce-advanced-product-labels/woocommerce-advanced-product-labels.php' ) ) {
			add_filter( 'blaze_wooless_product_data_for_typesense', array( $this, 'product_label_html' ), 10, 2 );
			add_action( 'blaze_get_advance_custom_labels_html', array( \WAPL_Global_Labels::class, 'global_label_hook' ), 15 );
		}
	}

	public function product_label_html( $product_data, $product_id ) {
		if ( ! empty( $product_data ) && $product_id ) {
			ob_start();
			setup_postdata( $product_id );
			wc_load_cart(); // Ensure the cart object is loaded before calling any method from product stock status condition because it is using wc cart which is not yet initialize
			do_action( 'blaze_get_advance_custom_labels_html' );
			$label_html = ob_get_clean();
			$product_data['metaData']['productLabel'] = htmlspecialchars( $label_html, ENT_QUOTES, 'UTF-8' );
		}

		return $product_data;
	}
}
