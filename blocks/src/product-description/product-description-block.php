<?php
/**
 * Product Description Block Server-side Functionality
 *
 * This file contains the render callback and related functionality
 * for the Product Description Gutenberg block.
 *
 * @package BlazeCommerce
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render callback for the product description block.
 *
 * @param array $attributes Block attributes.
 * @param string $content Block content.
 * @param WP_Block $block Block instance.
 * @return string Block HTML.
 */
if ( ! function_exists( 'blaze_commerce_render_product_description_block' ) ) {
	function blaze_commerce_render_product_description_block( $attributes, $content, $block ) {
		$product_id = isset( $attributes['productId'] ) ? intval( $attributes['productId'] ) : 0;

		// If no product ID is set, try to get current product
		if ( ! $product_id ) {
			global $post;
			if ( $post && $post->post_type === 'product' ) {
				$product_id = $post->ID;
			}
		}

		if ( ! $product_id ) {
			return '<div class="blaze-product-description-block"><p>' . __( 'No product selected.', 'blaze-commerce' ) . '</p></div>';
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return '<div class="blaze-product-description-block"><p>' . __( 'Product not found.', 'blaze-commerce' ) . '</p></div>';
		}

		$description = $product->get_description();
		if ( empty( $description ) ) {
			return '<div class="blaze-product-description-block"><p>' . __( 'No description available for this product.', 'blaze-commerce' ) . '</p></div>';
		}

		$output = '<div class="blaze-product-description-block">';
		$output .= '<div class="blaze-product-description">';
		$output .= do_shortcode( wpautop( $description ) );
		$output .= '</div>';
		$output .= '</div>';

		return $output;
	}
}

/**
 * Get product description with fallback handling.
 *
 * @param int $product_id Product ID.
 * @return string Product description or empty string.
 */
if ( ! function_exists( 'blaze_commerce_get_product_description' ) ) {
	function blaze_commerce_get_product_description( $product_id ) {
		if ( ! $product_id ) {
			return '';
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return '';
		}

		return $product->get_description();
	}
}

/**
 * Check if product has description.
 *
 * @param int $product_id Product ID.
 * @return bool True if product has description, false otherwise.
 */
if ( ! function_exists( 'blaze_commerce_product_has_description' ) ) {
	function blaze_commerce_product_has_description( $product_id ) {
		$description = blaze_commerce_get_product_description( $product_id );
		return ! empty( $description );
	}
}
