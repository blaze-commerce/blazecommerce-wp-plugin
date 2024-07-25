<?php

namespace BlazeWooless\Features;

use BlazeWooless\Woocommerce;

class Cli {
	private static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			add_action( 'cli_init', array( $this, 'init' ) );
		}
	}

	public function init() {
		\WP_CLI::add_command( 'bc auto-assign-parent-categories', array( $this, 'auto_assign_parent_categories' ) );
	}

	/**
	 * Sample command handler.
	 *
	 * ## EXAMPLES
	 *
	 *     wp bc auto-assign-parent-categories --product-i={the_product_id}
	 *     wp bc auto-assign-parent-categories --page=1
	 * 
	 * ## EXAMPLES IN GRIDPANE
	 *     	gp wp cart.ezywiper-bc-v1.blz.onl bc auto-assign-parent-categories --product-id=216175
	 *		gp wp cart.ezywiper-bc-v1.blz.onl bc auto-assign-parent-categories --page=1
	 *
	 * @when after_wp_load
	 */
	public function auto_assign_parent_categories( $args, $assoc_args ) {
		$assoc_args = wp_parse_args(
			$assoc_args,
			array(
				'batch-size' => 30,
				'page' => 1,
				'product-id' => 0
			)
		);

		if ( ! empty( $assoc_args['product-id'] ) ) {
			$product = wc_get_product( $assoc_args['product-id'] );
			if ( $product ) {
				Woocommerce::get_instance()->auto_assign_parent_categories( $product );
			}
			exit();
		}

		$args = [ 
			'limit' => $assoc_args['batch-size'],
			'page' => $assoc_args['page'],
		];

		$products = wc_get_products( $args );
		if ( ! empty( $products ) ) {
			foreach ( $products as $product ) {
				Woocommerce::get_instance()->auto_assign_parent_categories( $product );
			}
		} else {
			echo 'No products found with the following query ';
			print_r( $assoc_args );
		}
	}
}