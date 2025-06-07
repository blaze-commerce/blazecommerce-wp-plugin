<?php

namespace BlazeWooless\Extensions\Gutenberg\Blocks;

class WooCommerceProductDetailsExtension {
	private static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_filter( 'render_block', array( $this, 'render_product_details_block' ), 10, 2 );
	}

	public function enqueue_block_editor_assets() {
		// Enqueue our script to extend WooCommerce Product Details block
		wp_enqueue_script(
			'woocommerce-product-details-extension',
			BLAZE_WOOLESS_PLUGIN_URL . 'assets/js/woocommerce-product-details-extension.js',
			array( 'wp-blocks', 'wp-i18n', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-hooks' ),
			BLAZE_WOOLESS_VERSION,
			true
		);
	}

	public function enqueue_frontend_assets() {
		// Enqueue CSS for frontend to hide short description when needed
		wp_enqueue_style(
			'woocommerce-product-details-extension',
			BLAZE_WOOLESS_PLUGIN_URL . 'assets/css/woocommerce-product-details-extension.css',
			array(),
			BLAZE_WOOLESS_VERSION
		);
	}

	public function render_product_details_block( $block_content, $block ) {
		// Only modify the WooCommerce Product Details block
		if ( $block['blockName'] !== 'woocommerce/product-details' ) {
			return $block_content;
		}

		// Check if showShortDescription attribute is set to false
		$show_short_description = isset( $block['attrs']['showShortDescription'] ) ? $block['attrs']['showShortDescription'] : true;

		// If short description should be hidden, add CSS class to hide it
		if ( ! $show_short_description ) {
			// Add CSS class to hide short description
			$block_content = $this->add_hide_class_to_content( $block_content );
		}

		return $block_content;
	}

	private function add_hide_class_to_content( $content ) {
		// Add CSS class to the block wrapper to hide short description
		// Look for the main block wrapper and add our CSS class

		// Find the main block wrapper div and add our class
		$content = preg_replace(
			'/(<div[^>]*class="[^"]*wp-block-woocommerce-product-details[^"]*"[^>]*>)/i',
			'$1<div class="hide-short-description">',
			$content
		);

		// Close the wrapper div at the end
		$content = $content . '</div>';

		return $content;
	}
}
