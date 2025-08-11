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
        array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-hooks', 'wp-compose', 'wp-editor' ),
        '1.0.1',
        true
    );

    wp_enqueue_style(
        'blaze-commerce-global-block-config-style',
        BLAZE_COMMERCE_PLUGIN_URL . 'assets/css/global-block-config.css',
        array(),
        '1.0.1'
    );
}