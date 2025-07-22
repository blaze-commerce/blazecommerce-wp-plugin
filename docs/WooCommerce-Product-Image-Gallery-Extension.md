# WooCommerce Product Image Gallery Extension

Extension to add "Vertical Style" checkbox control to the existing WooCommerce Product Image Gallery block.

## Features

- ✅ Adds "Vertical Style" checkbox to WooCommerce Product Image Gallery block
- ✅ Control available in sidebar Inspector Controls
- ✅ Attribute value is saved and persisted
- ✅ Does not interfere with existing block functionality
- ✅ No visual changes to editor or frontend (as requested)

## How to Use

1. Open Gutenberg editor
2. Add WooCommerce Product Image Gallery block (from WooCommerce Product Elements category)
3. Select the block
4. In the sidebar, open "Style Options" panel
5. Use "Vertical Style" checkbox to enable/disable vertical style
6. The checkbox value is saved with the block

## Files Involved

### PHP Backend

- `app/Extensions/Gutenberg/Blocks/WooCommerceProductImageGalleryExtension.php`
  - Main class for the extension
  - Handles asset enqueuing and block rendering
  - Adds CSS class when vertical style is enabled

### JavaScript

- `assets/js/woocommerce-product-image-gallery-extension.js`
  - Adds `verticalStyle` attribute to block
  - Adds ToggleControl in Inspector Controls
  - Adds CSS class for styling purposes

### Registration

- `app/BlazeWooless.php` (line 132)
  - Extension is registered in the extensions array

## Technical Implementation

### WordPress Hooks Used

- `blocks.registerBlockType` - Adds new attribute
- `editor.BlockEdit` - Adds control in editor
- `editor.BlockListBlock` - Adds CSS class in editor
- `render_block` - Modifies output on frontend

### Added Attributes

```javascript
verticalStyle: {
    type: 'boolean',
    default: false,
}
```

### CSS Classes

- `.vertical-style` - Class added when vertical style is enabled

## Compatibility

- ✅ WordPress 5.0+
- ✅ WooCommerce 5.0+
- ✅ Gutenberg Block Editor
- ✅ Frontend and Backend

## Troubleshooting

### Control Not Appearing

1. Make sure you're using the original WooCommerce Product Image Gallery block
2. Make sure the extension is activated
3. Refresh the editor page
4. Check browser console for JavaScript errors

### Attribute Not Saving

1. Make sure the block is properly saved
2. Check if there are any JavaScript errors in console
3. Verify the extension is properly loaded

### Extension Not Loading

1. Make sure the extension is registered in `app/BlazeWooless.php`
2. Check if the JavaScript file is properly enqueued
3. Verify file paths are correct

## Development Notes

- The extension follows the same pattern as WooCommerceProductDetailsExtension
- No visual changes are implemented as per requirements
- The checkbox value is properly saved and can be used for future styling implementations
- CSS class is added to both editor and frontend for consistency
