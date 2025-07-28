<?php
/**
 * Stock Status Block Server-side Functionality
 *
 * This file contains the render callback and related functionality
 * for the Product Stock Status Gutenberg block.
 *
 * @package BlazeCommerce
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render callback for the stock status block.
 *
 * @param array $attributes Block attributes.
 * @param string $content Block content.
 * @param WP_Block $block Block instance.
 * @return string Block HTML.
 */
if ( ! function_exists( 'blaze_commerce_render_stock_status_block' ) ) {
	function blaze_commerce_render_stock_status_block( $attributes, $content, $block ) {
		global $product;

		// Get the current product if we're on a product page
		if ( ! $product ) {
			$product = wc_get_product( get_the_ID() );
		}

		// If we still don't have a product, return a message
		if ( ! $product ) {
			return '<div class="stock-status-error">' . __( 'No product found.', 'blaze-commerce' ) . '</div>';
		}

		// Get stock status and quantity
		$stock_status = $product->get_stock_status();
		$stock_quantity = $product->get_stock_quantity();

		// Get alignment class
		$align_class = isset( $attributes['align'] ) ? 'align-' . $attributes['align'] : 'align-left';

		// Get custom class if set
		$custom_class = isset( $attributes['className'] ) ? ' ' . $attributes['className'] : '';

		// Determine if we should show quantity
		$show_quantity = isset( $attributes['showQuantity'] ) ? $attributes['showQuantity'] : false;

		// Get stock status label and class
		$status_data = blaze_commerce_get_stock_status_data( $stock_status );

		// Build the HTML
		$html = '<div class="stock-status ' . esc_attr( $align_class . $custom_class ) . '">';
		$html .= '<div class="stock-status-container">';
		$html .= '<div class="stock-status-indicator ' . esc_attr( $status_data['class'] ) . '">';
		$html .= '<span class="stock-status-text">' . esc_html( $status_data['label'] ) . '</span>';

		// Add quantity if enabled and product is in stock
		if ( $show_quantity && $stock_status === 'instock' && $stock_quantity !== null ) {
			$html .= '<span class="stock-quantity">' . esc_html__( 'Quantity:', 'blaze-commerce' ) . ' ' . esc_html( $stock_quantity ) . '</span>';
		}

		$html .= '</div>'; // Close stock-status-indicator
		$html .= '</div>'; // Close stock-status-container
		$html .= '</div>'; // Close stock-status

		return $html;
	}
}

/**
 * Get stock status data (label and CSS class).
 *
 * @param string $stock_status The stock status.
 * @return array Array containing 'label' and 'class' keys.
 */
if ( ! function_exists( 'blaze_commerce_get_stock_status_data' ) ) {
	function blaze_commerce_get_stock_status_data( $stock_status ) {
		$status_data = array(
			'label' => '',
			'class' => '',
		);

		switch ( $stock_status ) {
			case 'instock':
				$status_data['label'] = __( 'In stock', 'blaze-commerce' );
				$status_data['class'] = 'in-stock';
				break;
			case 'outofstock':
				$status_data['label'] = __( 'Out of stock', 'blaze-commerce' );
				$status_data['class'] = 'out-of-stock';
				break;
			case 'onbackorder':
				$status_data['label'] = __( 'On backorder', 'blaze-commerce' );
				$status_data['class'] = 'on-backorder';
				break;
			default:
				$status_data['label'] = __( 'Unknown', 'blaze-commerce' );
				$status_data['class'] = '';
		}

		/**
		 * Filter the stock status data.
		 *
		 * @param array $status_data Array containing 'label' and 'class' keys.
		 * @param string $stock_status The original stock status.
		 */
		return apply_filters( 'blaze_commerce_stock_status_data', $status_data, $stock_status );
	}
}

/**
 * Check if WooCommerce is active and product exists.
 *
 * @param int $product_id Product ID to check.
 * @return bool True if product exists and WooCommerce is active.
 */
if ( ! function_exists( 'blaze_commerce_is_valid_product' ) ) {
	function blaze_commerce_is_valid_product( $product_id = null ) {
		// Check if WooCommerce is active
		if ( ! class_exists( 'WooCommerce' ) ) {
			return false;
		}

		// If no product ID provided, try to get current product
		if ( ! $product_id ) {
			global $product;
			if ( ! $product ) {
				$product = wc_get_product( get_the_ID() );
			}
			return $product instanceof WC_Product;
		}

		// Check specific product
		$product = wc_get_product( $product_id );
		return $product instanceof WC_Product;
	}
}
