<?php
/**
 * Registers the block using the metadata loaded from the `block.json` file.
 * Behind the scenes, it registers also all assets so they can be enqueued
 * through the block editor in the corresponding context.
 *
 * @see https://developer.wordpress.org/reference/functions/register_block_type/
 */

add_action( 'enqueue_block_editor_assets', 'extend_block_example_enqueue_block_editor_assets' );
add_action( 'enqueue_block_editor_assets', 'blaze_commerce_enqueue_global_block_config' );
add_action( 'rest_api_init', 'blaze_commerce_register_regions_endpoint' );

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

/**
 * Enqueue global block configuration assets
 */
function blaze_commerce_enqueue_global_block_config() {
    wp_enqueue_script(
        'blaze-commerce-global-block-config',
        BLAZE_COMMERCE_PLUGIN_URL . 'assets/js/global-block-config.js',
        array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-hooks', 'wp-compose', 'wp-editor', 'wp-api-fetch' ),
        '1.1.0',
        true
    );

    wp_enqueue_style(
        'blaze-commerce-global-block-config-style',
        BLAZE_COMMERCE_PLUGIN_URL . 'assets/css/global-block-config.css',
        array(),
        '1.1.0'
    );
}

/**
 * Register REST API endpoint for regions
 */
function blaze_commerce_register_regions_endpoint() {
    register_rest_route('wp/v2', '/blaze-commerce/regions', array(
        'methods' => 'GET',
        'callback' => 'blaze_commerce_get_regions',
        'permission_callback' => function() {
            return current_user_can('edit_posts');
        }
    ));
}

/**
 * Get available regions from PageMetaFields extension
 */
function blaze_commerce_get_regions() {
    // Check if PageMetaFields extension is available
    if (!class_exists('BlazeWooless\Extensions\PageMetaFields')) {
        return new WP_Error('extension_not_found', 'PageMetaFields extension not available', array('status' => 404));
    }

    $page_meta_fields = \BlazeWooless\Extensions\PageMetaFields::get_instance();
    $available_regions = $page_meta_fields->get_available_regions();

    // Transform regions data for frontend consumption
    $regions = array();
    foreach ($available_regions as $code => $data) {
        $regions[] = array(
            'code' => $code,
            'label' => $data['label'],
            'currency' => $data['currency'],
            'country_code' => $data['country_code'],
            'country_name' => $data['country_name']
        );
    }

    return rest_ensure_response($regions);
}

