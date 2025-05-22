<?php
/**
 * Registers the block using the metadata loaded from the `block.json` file.
 * Behind the scenes, it registers also all assets so they can be enqueued
 * through the block editor in the corresponding context.
 *
 * @see https://developer.wordpress.org/reference/functions/register_block_type/
 */

// Register blocks
add_action( 'init', 'blaze_commerce_register_blocks' );

function blaze_commerce_register_blocks() {
	// Register service features block
	register_block_type( __DIR__ . '/src/service-features' );

	// Register stock status block
	register_block_type( __DIR__ . '/src/stock-status', array(
		'render_callback' => 'blaze_commerce_render_stock_status_block',
		'attributes' => array(
			'align' => array(
				'type' => 'string',
				'default' => 'left',
			),
			'showQuantity' => array(
				'type' => 'boolean',
				'default' => false,
			),
			'className' => array(
				'type' => 'string',
			),
		),
	) );
}

/**
 * Render callback for the stock status block.
 *
 * @param array $attributes Block attributes.
 * @return string Block HTML.
 */
function blaze_commerce_render_stock_status_block( $attributes ) {
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

	// Get stock status label
	$status_label = '';
	switch ( $stock_status ) {
		case 'instock':
			$status_label = __( 'In stock', 'blaze-commerce' );
			$status_class = 'in-stock';
			break;
		case 'outofstock':
			$status_label = __( 'Out of stock', 'blaze-commerce' );
			$status_class = 'out-of-stock';
			break;
		case 'onbackorder':
			$status_label = __( 'On backorder', 'blaze-commerce' );
			$status_class = 'on-backorder';
			break;
		default:
			$status_label = __( 'Unknown', 'blaze-commerce' );
			$status_class = '';
	}

	// Build the HTML
	$html = '<div class="stock-status ' . esc_attr( $align_class . $custom_class ) . '">';
	$html .= '<div class="stock-status-container">';
	$html .= '<div class="stock-status-indicator ' . esc_attr( $status_class ) . '">';
	$html .= '<span class="stock-status-text">' . esc_html( $status_label ) . '</span>';

	// Add quantity if enabled and product is in stock
	if ( $show_quantity && $stock_status === 'instock' && $stock_quantity !== null ) {
		$html .= '<span class="stock-quantity">' . esc_html__( 'Quantity:', 'blaze-commerce' ) . ' ' . esc_html( $stock_quantity ) . '</span>';
	}

	$html .= '</div>'; // Close stock-status-indicator
	$html .= '</div>'; // Close stock-status-container
	$html .= '</div>'; // Close stock-status

	return $html;
}

add_action( 'enqueue_block_editor_assets', 'extend_block_example_enqueue_block_editor_assets' );

function extend_block_example_enqueue_block_editor_assets() {
	// Enqueue our script
	wp_enqueue_script(
		'blaze-commerce-blocks-script',
		esc_url( plugins_url( '/build/index.js', __FILE__ ) ),
		array( 'wp-blocks', 'wp-i18n', 'wp-element', 'wp-editor' ),
		'1.0.0',
		true // Enqueue the script in the footer.
	);

	$menus = get_terms( 'nav_menu' );

	$config = array(
		'menus' => $menus,
	);

	wp_localize_script( 'blaze-commerce-blocks-script', 'blaze_commerce_block_config', $config );
}