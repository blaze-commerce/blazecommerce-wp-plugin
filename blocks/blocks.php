<?php
/**
 * Registers the block using the metadata loaded from the `block.json` file.
 * Behind the scenes, it registers also all assets so they can be enqueued
 * through the block editor in the corresponding context.
 *
 * @see https://developer.wordpress.org/reference/functions/register_block_type/
 */
// function blaze_commerce_menu_init() {
// 	var_dump(get_post(70)); exit;
// }
// add_action( 'init', 'blaze_commerce_menu_init' );

add_action( 'enqueue_block_editor_assets', 'extend_block_example_enqueue_block_editor_assets' );

function extend_block_example_enqueue_block_editor_assets() {
    // Enqueue our script
    wp_enqueue_script(
        'extend-block-example-js',
        esc_url( plugins_url( '/build/index.js', __FILE__ ) ),
        array( 'wp-blocks', 'wp-i18n', 'wp-element', 'wp-editor' ),
        '1.0.0',
        true // Enqueue the script in the footer.
    );
}