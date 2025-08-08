# WooCommerce Product Details Extension

Extension to add checkbox control to the existing WooCommerce Product Details block.

## Features

- ✅ Adds "Show Short Description" checkbox to WooCommerce Product Details block
- ✅ Control available in sidebar Inspector Controls
- ✅ Works in both editor and frontend
- ✅ Does not interfere with existing block functionality

## How to Use

1. Open Gutenberg editor
2. Add WooCommerce Product Details block (from WooCommerce Product Elements category)
3. Select the block
4. In the sidebar, open "Display Options" panel
5. Use "Show Short Description" checkbox to show/hide short description

## Files Involved

### PHP Backend

- `app/Extensions/Gutenberg/Blocks/WooCommerceProductDetailsExtension.php`
  - Main class for the extension
  - Handles asset enqueuing and block rendering

### JavaScript

- `assets/js/woocommerce-product-details-extension.js`
  - Adds `showShortDescription` attribute to block
  - Adds ToggleControl in Inspector Controls
  - Adds CSS class to hide short description

### CSS

- `assets/css/woocommerce-product-details-extension.css`
  - Styling to hide short description
  - Works in both editor and frontend

## Technical Implementation

### WordPress Hooks Used

- `blocks.registerBlockType` - Adds new attribute
- `editor.BlockEdit` - Adds control in editor
- `editor.BlockListBlock` - Adds CSS class in editor
- `render_block` - Modifies output on frontend

### Added Attributes

```javascript
showShortDescription: {
    type: 'boolean',
    default: true,
}
```

### CSS Classes

- `.hide-short-description` - Class added when short description is hidden

## Compatibility

- ✅ WordPress 5.0+
- ✅ WooCommerce 5.0+
- ✅ Gutenberg Block Editor
- ✅ Frontend and Backend

## Troubleshooting

### Short Description Still Appears

1. Make sure "Show Short Description" checkbox is unchecked
2. Clear cache if using caching plugins
3. Check if there are custom CSS that override styling

### Control Not Appearing

1. Make sure you're using the original WooCommerce Product Details block
2. Make sure the extension is activated
3. Refresh the editor page

### Styling Not Working

1. Make sure CSS file is properly enqueued
2. Check browser console for errors
3. Make sure there are no conflicts with theme CSS
