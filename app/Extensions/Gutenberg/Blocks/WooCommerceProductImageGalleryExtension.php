<?php

namespace BlazeWooless\Extensions\Gutenberg\Blocks;

class WooCommerceProductImageGalleryExtension
{
    private static $instance = null;

    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function __construct()
    {
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_editor_assets'));
        add_filter('render_block', array($this, 'render_product_image_gallery_block'), 10, 2);
    }

    public function enqueue_block_editor_assets()
    {
        // Enqueue our script to extend WooCommerce Product Image Gallery block
        wp_enqueue_script(
            'woocommerce-product-image-gallery-extension',
            BLAZE_WOOLESS_PLUGIN_URL . 'assets/js/woocommerce-product-image-gallery-extension.js',
            array('wp-blocks', 'wp-i18n', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-hooks'),
            BLAZE_WOOLESS_VERSION,
            true
        );
    }

    public function render_product_image_gallery_block($block_content, $block)
    {
        // Only modify the WooCommerce Product Image Gallery block
        if ($block['blockName'] !== 'woocommerce/product-image-gallery') {
            return $block_content;
        }

        // Check if verticalStyle attribute is set to true
        $vertical_style = isset($block['attrs']['verticalStyle']) ? $block['attrs']['verticalStyle'] : false;

        // If vertical style is enabled, add CSS class
        if ($vertical_style) {
            // Add CSS class to the block wrapper for vertical style
            $block_content = $this->add_vertical_style_class_to_content($block_content);
        }

        return $block_content;
    }

    private function add_vertical_style_class_to_content($content)
    {
        // Add CSS class to the block wrapper for vertical style
        // Look for the main block wrapper and add our CSS class

        // Find the main block wrapper div and add our class
        $content = preg_replace(
            '/(<div[^>]*class="[^"]*wp-block-woocommerce-product-image-gallery[^"]*"[^>]*>)/i',
            '$1<div class="vertical-style">',
            $content
        );

        // Close the wrapper div at the end
        $content = $content . '</div>';

        return $content;
    }
}
