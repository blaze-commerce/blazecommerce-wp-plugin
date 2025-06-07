<?php

namespace BlazeWooless\Extensions\Gutenberg\Blocks;

class ProductBlocks {
	private static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		add_action( 'init', array( $this, 'register_blocks' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
	}

	public function register_blocks() {
		// Register Product Description Block
		register_block_type( 'blaze-commerce/product-description', array(
			'render_callback' => array( $this, 'render_product_description_block' ),
			'attributes' => array(
				'productId' => array(
					'type' => 'number',
					'default' => 0,
				),
			),
		) );

		// Register Product Detail Block
		register_block_type( 'blaze-commerce/product-detail', array(
			'render_callback' => array( $this, 'render_product_detail_block' ),
			'attributes' => array(
				'productId' => array(
					'type' => 'number',
					'default' => 0,
				),
				'showShortDescription' => array(
					'type' => 'boolean',
					'default' => true,
				),
				'showSku' => array(
					'type' => 'boolean',
					'default' => true,
				),
				'showPrice' => array(
					'type' => 'boolean',
					'default' => true,
				),
				'showStockStatus' => array(
					'type' => 'boolean',
					'default' => true,
				),
				'showStockQuantity' => array(
					'type' => 'boolean',
					'default' => false,
				),
				'showCategories' => array(
					'type' => 'boolean',
					'default' => true,
				),
				'showTags' => array(
					'type' => 'boolean',
					'default' => false,
				),
				'textColor' => array(
					'type' => 'string',
					'default' => '#333333',
				),
				'fontSize' => array(
					'type' => 'number',
					'default' => 16,
				),
				'fontWeight' => array(
					'type' => 'string',
					'default' => 'normal',
				),
				'lineHeight' => array(
					'type' => 'number',
					'default' => 1.5,
				),
				'alignment' => array(
					'type' => 'string',
					'default' => 'left',
				),
				'shortDescriptionColor' => array(
					'type' => 'string',
					'default' => '#666666',
				),
				'shortDescriptionFontSize' => array(
					'type' => 'number',
					'default' => 14,
				),
				'priceColor' => array(
					'type' => 'string',
					'default' => '#e74c3c',
				),
				'priceFontSize' => array(
					'type' => 'number',
					'default' => 18,
				),
				'priceFontWeight' => array(
					'type' => 'string',
					'default' => 'bold',
				),
			),
		) );
	}

	public function enqueue_editor_assets() {
		// Enqueue WooCommerce REST API settings for blocks
		wp_localize_script( 'wp-api-fetch', 'wpApiSettings', array(
			'root' => esc_url_raw( rest_url() ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
		) );
	}

	public function render_product_description_block( $attributes, $content ) {
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

	public function render_product_detail_block( $attributes, $content ) {
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
			$stock_status = $product->get_stock_status();
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

			$output .= '<p style="' . $base_style . '">';
			$output .= '<strong>' . __( 'Stock Status:', 'blaze-commerce' ) . '</strong> ' . esc_html( $stock_text );

			// Show stock quantity if enabled and in stock
			if ( $show_stock_quantity && $stock_status === 'instock' && $product->get_stock_quantity() ) {
				$output .= ' (' . intval( $product->get_stock_quantity() ) . ' ' . __( 'available', 'blaze-commerce' ) . ')';
			}
			$output .= '</p>';
		}

		// Categories
		if ( $show_categories ) {
			$categories = get_the_terms( $product_id, 'product_cat' );
			if ( $categories && ! is_wp_error( $categories ) ) {
				$category_names = array_map( function ($cat) {
					return $cat->name;
				}, $categories );
				$output .= '<p style="' . $base_style . '">';
				$output .= '<strong>' . __( 'Categories:', 'blaze-commerce' ) . '</strong> ' . esc_html( implode( ', ', $category_names ) );
				$output .= '</p>';
			}
		}

		// Tags
		if ( $show_tags ) {
			$tags = get_the_terms( $product_id, 'product_tag' );
			if ( $tags && ! is_wp_error( $tags ) ) {
				$tag_names = array_map( function ($tag) {
					return $tag->name;
				}, $tags );
				$output .= '<p style="' . $base_style . '">';
				$output .= '<strong>' . __( 'Tags:', 'blaze-commerce' ) . '</strong> ' . esc_html( implode( ', ', $tag_names ) );
				$output .= '</p>';
			}
		}

		$output .= '</div>';
		$output .= '</div>';

		return $output;
	}
}
