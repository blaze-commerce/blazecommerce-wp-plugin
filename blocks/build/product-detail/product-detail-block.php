<?php
/**
 * Product Detail Block Server-side Functionality
 *
 * This file contains the render callback and related functionality
 * for the Product Detail Gutenberg block.
 *
 * @package BlazeCommerce
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render callback for the product detail block.
 *
 * @param array $attributes Block attributes.
 * @param string $content Block content.
 * @param WP_Block $block Block instance.
 * @return string Block HTML.
 */
if ( ! function_exists( 'blaze_commerce_render_product_detail_block' ) ) {
	function blaze_commerce_render_product_detail_block( $attributes, $content, $block ) {
		$product_id = isset( $attributes['productId'] ) ? intval( $attributes['productId'] ) : 0;

		// If no product ID is set, try to get current product
		if ( ! $product_id ) {
			global $post;
			if ( $post && $post->post_type === 'product' ) {
				$product_id = $post->ID;
			}
		}

		if ( ! $product_id ) {
			return '<div class="blaze-product-detail-block"><p>' . __( 'No product selected.', 'blaze-commerce' ) . '</p></div>';
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return '<div class="blaze-product-detail-block"><p>' . __( 'Product not found.', 'blaze-commerce' ) . '</p></div>';
		}

		// Extract attributes with defaults
		$show_short_description = isset( $attributes['showShortDescription'] ) ? $attributes['showShortDescription'] : true;
		$show_sku = isset( $attributes['showSku'] ) ? $attributes['showSku'] : true;
		$show_price = isset( $attributes['showPrice'] ) ? $attributes['showPrice'] : true;
		$show_stock_status = isset( $attributes['showStockStatus'] ) ? $attributes['showStockStatus'] : true;
		$show_stock_quantity = isset( $attributes['showStockQuantity'] ) ? $attributes['showStockQuantity'] : false;
		$show_categories = isset( $attributes['showCategories'] ) ? $attributes['showCategories'] : true;
		$show_tags = isset( $attributes['showTags'] ) ? $attributes['showTags'] : false;

		// Style attributes
		$text_color = isset( $attributes['textColor'] ) ? $attributes['textColor'] : '#333333';
		$font_size = isset( $attributes['fontSize'] ) ? intval( $attributes['fontSize'] ) : 16;
		$font_weight = isset( $attributes['fontWeight'] ) ? $attributes['fontWeight'] : 'normal';
		$line_height = isset( $attributes['lineHeight'] ) ? floatval( $attributes['lineHeight'] ) : 1.5;
		$alignment = isset( $attributes['alignment'] ) ? $attributes['alignment'] : 'left';

		$short_description_color = isset( $attributes['shortDescriptionColor'] ) ? $attributes['shortDescriptionColor'] : '#666666';
		$short_description_font_size = isset( $attributes['shortDescriptionFontSize'] ) ? intval( $attributes['shortDescriptionFontSize'] ) : 14;

		$price_color = isset( $attributes['priceColor'] ) ? $attributes['priceColor'] : '#e74c3c';
		$price_font_size = isset( $attributes['priceFontSize'] ) ? intval( $attributes['priceFontSize'] ) : 18;
		$price_font_weight = isset( $attributes['priceFontWeight'] ) ? $attributes['priceFontWeight'] : 'bold';

		$output = '<div class="blaze-product-detail-block" style="text-align: ' . esc_attr( $alignment ) . ';">';
		$output .= '<div class="blaze-product-detail">';

		// Product title
		$title_style = sprintf(
			'color: %s; font-size: %dpx; font-weight: bold; margin: 0 0 1rem 0;',
			esc_attr( $text_color ),
			$font_size + 4
		);
		$output .= '<h3 style="' . $title_style . '">' . esc_html( $product->get_name() ) . '</h3>';

		// Short description
		if ( $show_short_description && $product->get_short_description() ) {
			$short_desc_style = sprintf(
				'color: %s; font-size: %dpx; line-height: %s; margin-bottom: 1rem;',
				esc_attr( $short_description_color ),
				$short_description_font_size,
				$line_height
			);
			$output .= '<div class="product-short-description" style="' . $short_desc_style . '">';
			$output .= wpautop( $product->get_short_description() );
			$output .= '</div>';
		}

		$base_style = sprintf(
			'color: %s; font-size: %dpx; font-weight: %s; line-height: %s; margin: 0.5rem 0;',
			esc_attr( $text_color ),
			$font_size,
			esc_attr( $font_weight ),
			$line_height
		);

		// SKU
		if ( $show_sku && $product->get_sku() ) {
			$output .= '<p style="' . $base_style . '">';
			$output .= '<strong>' . __( 'SKU:', 'blaze-commerce' ) . '</strong> ' . esc_html( $product->get_sku() );
			$output .= '</p>';
		}

		// Price
		if ( $show_price ) {
			$price_style = sprintf(
				'color: %s; font-size: %dpx; font-weight: %s; margin: 0.5rem 0;',
				esc_attr( $price_color ),
				$price_font_size,
				esc_attr( $price_font_weight )
			);
			$output .= '<p class="product-price" style="' . $price_style . '">';
			$output .= $product->get_price_html();
			$output .= '</p>';
		}

		// Stock status
		if ( $show_stock_status ) {
			$stock_data = blaze_commerce_get_product_stock_data( $product );
			$output .= '<p style="' . $base_style . '">';
			$output .= '<strong>' . __( 'Stock Status:', 'blaze-commerce' ) . '</strong> ' . esc_html( $stock_data['text'] );

			// Show stock quantity if enabled and in stock
			if ( $show_stock_quantity && $stock_data['status'] === 'instock' && $stock_data['quantity'] ) {
				$output .= ' (' . intval( $stock_data['quantity'] ) . ' ' . __( 'available', 'blaze-commerce' ) . ')';
			}
			$output .= '</p>';
		}

		// Categories
		if ( $show_categories ) {
			$categories = blaze_commerce_get_product_categories( $product_id );
			if ( ! empty( $categories ) ) {
				$output .= '<p style="' . $base_style . '">';
				$output .= '<strong>' . __( 'Categories:', 'blaze-commerce' ) . '</strong> ' . esc_html( implode( ', ', $categories ) );
				$output .= '</p>';
			}
		}

		// Tags
		if ( $show_tags ) {
			$tags = blaze_commerce_get_product_tags( $product_id );
			if ( ! empty( $tags ) ) {
				$output .= '<p style="' . $base_style . '">';
				$output .= '<strong>' . __( 'Tags:', 'blaze-commerce' ) . '</strong> ' . esc_html( implode( ', ', $tags ) );
				$output .= '</p>';
			}
		}

		$output .= '</div>';
		$output .= '</div>';

		return $output;
	}
}

/**
 * Get product stock data.
 *
 * @param WC_Product $product Product object.
 * @return array Array containing stock status, text, and quantity.
 */
if ( ! function_exists( 'blaze_commerce_get_product_stock_data' ) ) {
	function blaze_commerce_get_product_stock_data( $product ) {
		$stock_status = $product->get_stock_status();
		$stock_quantity = $product->get_stock_quantity();

		$stock_text = '';
		switch ( $stock_status ) {
			case 'instock':
				$stock_text = __( 'In Stock', 'blaze-commerce' );
				break;
			case 'outofstock':
				$stock_text = __( 'Out of Stock', 'blaze-commerce' );
				break;
			case 'onbackorder':
				$stock_text = __( 'On Backorder', 'blaze-commerce' );
				break;
			default:
				$stock_text = $stock_status;
		}

		return array(
			'status' => $stock_status,
			'text' => $stock_text,
			'quantity' => $stock_quantity,
		);
	}
}

/**
 * Get product categories.
 *
 * @param int $product_id Product ID.
 * @return array Array of category names.
 */
if ( ! function_exists( 'blaze_commerce_get_product_categories' ) ) {
	function blaze_commerce_get_product_categories( $product_id ) {
		$categories = get_the_terms( $product_id, 'product_cat' );
		if ( $categories && ! is_wp_error( $categories ) ) {
			return array_map( function ($cat) {
				return $cat->name;
			}, $categories );
		}
		return array();
	}
}

/**
 * Get product tags.
 *
 * @param int $product_id Product ID.
 * @return array Array of tag names.
 */
if ( ! function_exists( 'blaze_commerce_get_product_tags' ) ) {
	function blaze_commerce_get_product_tags( $product_id ) {
		$tags = get_the_terms( $product_id, 'product_tag' );
		if ( $tags && ! is_wp_error( $tags ) ) {
			return array_map( function ($tag) {
				return $tag->name;
			}, $tags );
		}
		return array();
	}
}
