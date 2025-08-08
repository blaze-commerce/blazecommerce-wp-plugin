<?php
/**
 * Registers the block using the metadata loaded from the `block.json` file.
 * Behind the scenes, it registers also all assets so they can be enqueued
 * through the block editor in the corresponding context.
 *
 * @see https://developer.wordpress.org/reference/functions/register_block_type/
 */

// Register block categories
add_filter('block_categories_all', 'blaze_commerce_register_block_categories', 10, 2);

function blaze_commerce_register_block_categories($categories, $post)
{
    // Add BlazeCommerce category at the beginning
    array_unshift($categories, array(
        'slug' => 'blazecommerce',
        'title' => __('BlazeCommerce', 'blaze-commerce'),
        'icon' => 'store',
    ));

    return $categories;
}

// Register blocks
add_action('init', 'blaze_commerce_register_blocks');

function blaze_commerce_register_blocks()
{
    // Register service features block
    register_block_type(__DIR__ . '/src/service-features');

    // Register kajal collection menu block
    register_block_type(__DIR__ . '/src/kajal-collection-menu');

    // Include stock status block functionality
    require_once __DIR__ . '/src/stock-status/stock-status-block.php';

    // Register stock status block
    register_block_type(__DIR__ . '/src/stock-status');

    // Include product description block functionality
    require_once __DIR__ . '/src/product-description/product-description-block.php';

    // Register product description block
    register_block_type(__DIR__ . '/src/product-description');

    // Include product detail block functionality
    require_once __DIR__ . '/src/product-detail/product-detail-block.php';

    // Register product detail block
    register_block_type(__DIR__ . '/src/product-detail');

    // Register blaze slideshow block
    register_block_type(__DIR__ . '/src/blaze-slideshow');
}

add_action('enqueue_block_editor_assets', 'extend_block_example_enqueue_block_editor_assets');

function extend_block_example_enqueue_block_editor_assets()
{
    // Enqueue our script
    wp_enqueue_script(
        'blaze-commerce-blocks-script',
        esc_url(plugins_url('/build/index.js', __FILE__)),
        array('wp-blocks', 'wp-i18n', 'wp-element', 'wp-editor'),
        '1.0.0',
        true // Enqueue the script in the footer.
    );

    wp_localize_script('wp-api-fetch', 'wpApiSettings', array(
        'root' => esc_url_raw(rest_url()),
        'nonce' => wp_create_nonce('wp_rest'),
    ));

    $menus = get_terms('nav_menu');

    $config = array(
        'menus' => $menus,
    );

    wp_localize_script('blaze-commerce-blocks-script', 'blaze_commerce_block_config', $config);
}

// Enqueue frontend assets
add_action('wp_enqueue_scripts', 'blaze_commerce_enqueue_frontend_assets');

function blaze_commerce_enqueue_frontend_assets()
{
    // Check if we have any Blaze Slideshow blocks on the page
    if (has_block('blaze-commerce/blaze-slideshow')) {
        wp_enqueue_script(
            'blaze-slideshow-frontend',
            plugins_url('/src/blaze-slideshow/frontend.js', __FILE__),
            array(),
            '1.0.0',
            true
        );
    }
}