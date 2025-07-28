<?php
/**
 * Test: Kajal Collection Menu Block
 *
 * Purpose: Validates the Kajal Collection Menu Gutenberg block functionality
 * Scope: Unit/Integration
 * Dependencies: WordPress, Gutenberg
 *
 * @package BlazeWooless
 * @subpackage Tests
 */

class Test_Kajal_Collection_Menu_Block extends WP_UnitTestCase {

    /**
     * Setup test environment
     */
    public function setUp(): void {
        parent::setUp();
        
        // Ensure blocks are registered
        if (function_exists('blaze_commerce_register_blocks')) {
            blaze_commerce_register_blocks();
        }
    }

    /**
     * Test: Block registration
     *
     * @covers blaze_commerce_register_blocks
     */
    public function test_block_registration() {
        // Arrange
        $expected_block_name = 'blaze-commerce/kajal-collection-menu';

        // Act
        $registered_blocks = WP_Block_Type_Registry::get_instance()->get_all_registered();

        // Assert
        $this->assertArrayHasKey($expected_block_name, $registered_blocks);
        $this->assertInstanceOf('WP_Block_Type', $registered_blocks[$expected_block_name]);
    }

    /**
     * Test: Block attributes schema
     *
     * @covers WP_Block_Type::attributes
     */
    public function test_block_attributes_schema() {
        // Arrange
        $block_name = 'blaze-commerce/kajal-collection-menu';
        $registry = WP_Block_Type_Registry::get_instance();

        // Act
        $block_type = $registry->get_registered($block_name);

        // Assert
        $this->assertNotNull($block_type);
        $this->assertIsArray($block_type->attributes);
        
        // Test required attributes exist
        $required_attributes = [
            'title',
            'titleClass',
            'showUnderline',
            'underlineClass',
            'showBadge',
            'badgeText',
            'badgeClass',
            'containerClass',
            'menuItemClass',
            'menuItems'
        ];

        foreach ($required_attributes as $attribute) {
            $this->assertArrayHasKey($attribute, $block_type->attributes);
        }
    }

    /**
     * Test: Default attribute values
     *
     * @covers WP_Block_Type::attributes
     */
    public function test_default_attribute_values() {
        // Arrange
        $block_name = 'blaze-commerce/kajal-collection-menu';
        $registry = WP_Block_Type_Registry::get_instance();
        $block_type = $registry->get_registered($block_name);

        // Act & Assert
        $this->assertEquals('Choose By Type', $block_type->attributes['title']['default']);
        $this->assertTrue($block_type->attributes['showUnderline']['default']);
        $this->assertTrue($block_type->attributes['showBadge']['default']);
        $this->assertEquals('NEW IN!', $block_type->attributes['badgeText']['default']);
        $this->assertIsArray($block_type->attributes['menuItems']['default']);
        $this->assertCount(4, $block_type->attributes['menuItems']['default']);
    }

    /**
     * Test: Menu item structure
     *
     * @covers WP_Block_Type::attributes
     */
    public function test_menu_item_structure() {
        // Arrange
        $block_name = 'blaze-commerce/kajal-collection-menu';
        $registry = WP_Block_Type_Registry::get_instance();
        $block_type = $registry->get_registered($block_name);
        $default_items = $block_type->attributes['menuItems']['default'];

        // Act
        $first_item = $default_items[0];

        // Assert
        $required_item_properties = ['id', 'text', 'link', 'linkType', 'target'];
        foreach ($required_item_properties as $property) {
            $this->assertArrayHasKey($property, $first_item);
        }

        $this->assertEquals('url', $first_item['linkType']);
        $this->assertEquals('_self', $first_item['target']);
    }

    /**
     * Test: Block category assignment
     *
     * @covers WP_Block_Type::category
     */
    public function test_block_category() {
        // Arrange
        $block_name = 'blaze-commerce/kajal-collection-menu';
        $registry = WP_Block_Type_Registry::get_instance();

        // Act
        $block_type = $registry->get_registered($block_name);

        // Assert
        $this->assertEquals('woocommerce-product-elements', $block_type->category);
    }

    /**
     * Test: Block supports configuration
     *
     * @covers WP_Block_Type::supports
     */
    public function test_block_supports() {
        // Arrange
        $block_name = 'blaze-commerce/kajal-collection-menu';
        $registry = WP_Block_Type_Registry::get_instance();

        // Act
        $block_type = $registry->get_registered($block_name);

        // Assert
        $this->assertIsArray($block_type->supports);
        $this->assertArrayHasKey('html', $block_type->supports);
        $this->assertFalse($block_type->supports['html']);
    }

    /**
     * Test: Block render output structure
     *
     * @covers render_block
     */
    public function test_block_render_output() {
        // Arrange
        $attributes = [
            'title' => 'Test Menu',
            'showUnderline' => true,
            'showBadge' => true,
            'badgeText' => 'TEST',
            'menuItems' => [
                [
                    'id' => 'test-1',
                    'text' => 'Test Item',
                    'link' => 'https://example.com',
                    'linkType' => 'url',
                    'target' => '_self'
                ]
            ]
        ];

        $block = [
            'blockName' => 'blaze-commerce/kajal-collection-menu',
            'attrs' => $attributes,
            'innerHTML' => '',
            'innerContent' => []
        ];

        // Act
        $output = render_block($block);

        // Assert
        $this->assertStringContainsString('kajal-collection-menu', $output);
        $this->assertStringContainsString('Test Menu', $output);
        $this->assertStringContainsString('TEST', $output);
        $this->assertStringContainsString('Test Item', $output);
        $this->assertStringContainsString('href="https://example.com"', $output);
    }

    /**
     * Test: Anchor link rendering
     *
     * @covers render_block
     */
    public function test_anchor_link_rendering() {
        // Arrange
        $attributes = [
            'title' => 'Anchor Menu',
            'showBadge' => false,
            'menuItems' => [
                [
                    'id' => 'anchor-1',
                    'text' => 'Section 1',
                    'link' => 'section-1',
                    'linkType' => 'anchor',
                    'target' => '_self'
                ]
            ]
        ];

        $block = [
            'blockName' => 'blaze-commerce/kajal-collection-menu',
            'attrs' => $attributes,
            'innerHTML' => '',
            'innerContent' => []
        ];

        // Act
        $output = render_block($block);

        // Assert
        $this->assertStringContainsString('href="#section-1"', $output);
        $this->assertStringContainsString('Section 1', $output);
    }

    /**
     * Test: External link target attribute
     *
     * @covers render_block
     */
    public function test_external_link_target() {
        // Arrange
        $attributes = [
            'title' => 'External Menu',
            'showBadge' => false,
            'menuItems' => [
                [
                    'id' => 'external-1',
                    'text' => 'External Link',
                    'link' => 'https://external.com',
                    'linkType' => 'url',
                    'target' => '_blank'
                ]
            ]
        ];

        $block = [
            'blockName' => 'blaze-commerce/kajal-collection-menu',
            'attrs' => $attributes,
            'innerHTML' => '',
            'innerContent' => []
        ];

        // Act
        $output = render_block($block);

        // Assert
        $this->assertStringContainsString('target="_blank"', $output);
        $this->assertStringContainsString('rel="noopener noreferrer"', $output);
    }

    /**
     * Test: CSS class application
     *
     * @covers render_block
     */
    public function test_css_class_application() {
        // Arrange
        $attributes = [
            'title' => 'Styled Menu',
            'titleClass' => 'custom-title-class',
            'containerClass' => 'custom-container-class',
            'menuItemClass' => 'custom-item-class',
            'showBadge' => false,
            'menuItems' => [
                [
                    'id' => 'styled-1',
                    'text' => 'Styled Item',
                    'link' => '#',
                    'linkType' => 'url',
                    'target' => '_self'
                ]
            ]
        ];

        $block = [
            'blockName' => 'blaze-commerce/kajal-collection-menu',
            'attrs' => $attributes,
            'innerHTML' => '',
            'innerContent' => []
        ];

        // Act
        $output = render_block($block);

        // Assert
        $this->assertStringContainsString('custom-title-class', $output);
        $this->assertStringContainsString('custom-container-class', $output);
        $this->assertStringContainsString('custom-item-class', $output);
    }

    /**
     * Cleanup test environment
     */
    public function tearDown(): void {
        parent::tearDown();
    }
}
