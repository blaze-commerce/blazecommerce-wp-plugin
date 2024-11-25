<?php

namespace BlazeWooless\Extensions;

class NiWooCommerceProductVariationsTable {
	private static $instance = null;
	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		if ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'ni-woocommerce-product-variations-table/ni-woocommerce-product-variations-table.php' ) ) {

			add_filter( 'blaze_wooless_product_data_for_typesense', array( $this, 'save_table_to_description' ), 10, 3 );
		}
	}

	public function get_variation_table( $product ) {

		if ( class_exists( 'Ni_wooCommerce_After_Single_Product_Summary' ) ) {
			ob_start();
			setup_postdata( $product->get_id() );

			$table = new \Ni_wooCommerce_After_Single_Product_Summary();
			$table->ni_woocommerce_after_single_product_summary();
			return ob_get_clean();
		}

		return '';
	}


	public function save_table_to_description( $product_data, $product_id, $product ) {

		$tableHtml                   = $this->get_variation_table( $product );
		$product_data['description'] = $tableHtml . $product_data['description'];

		return $product_data;
	}
}
