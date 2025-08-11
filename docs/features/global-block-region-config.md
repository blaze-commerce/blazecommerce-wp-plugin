# Global Block Region Configuration

## Overview

The Global Block Region Configuration feature adds a multiselect region control to the Advanced panel of all Gutenberg blocks. This allows content creators to specify which regions each block should be displayed in using checkboxes for available regions from Aelia Currency Switcher configuration.

## Features

- **Global Implementation**: Automatically adds region configuration to ALL Gutenberg blocks
- **Advanced Panel Integration**: Places the regions control in the standard "Advanced" section
- **Multiselect Interface**: Checkbox-based selection for multiple regions
- **Aelia Integration**: Automatically pulls regions from Aelia Currency Switcher configuration
- **REST API Backend**: Efficient region data loading via WordPress REST API with global caching
- **Metadata Storage**: Saves region data as block attributes and HTML data attributes
- **Frontend Integration**: Adds `data-blaze-regions` attribute to block HTML for frontend use

## How It Works

### 1. Block Attribute Addition

The system automatically adds a `blazeCommerceRegions` attribute to all blocks:

```javascript
settings.attributes.blazeCommerceRegions = {
    type: 'array',
    default: []
};
```

### 2. REST API Endpoint

A REST API endpoint provides available regions from Aelia Currency Switcher:

```php
register_rest_route('wp/v2', '/blaze-commerce/regions', array(
    'methods' => 'GET',
    'callback' => 'blaze_commerce_get_regions',
    'permission_callback' => function() {
        return current_user_can('edit_posts');
    }
));
```

### 3. Inspector Control

Checkbox controls are added to the Advanced panel of every block:

```javascript
availableRegions.map(region =>
    createElement(CheckboxControl, {
        key: region.code,
        label: region.label,
        checked: selectedRegions.includes(region.code),
        onChange: () => toggleRegion(region.code)
    })
)
```

### 4. Frontend Output

When regions are selected, they're saved as a comma-separated data attribute in the block's HTML:

```html
<div data-blaze-regions="US,CA" class="wp-block-paragraph">
    Block content here...
</div>
```

## User Interface

### Location
- **Panel**: Advanced (in Block Inspector sidebar)
- **Position**: Bottom of Advanced panel
- **Label**: "Region"
- **Type**: Text input field

### Usage Instructions
1. Select any Gutenberg block in the editor
2. Open the Block Inspector sidebar (right panel)
3. Expand the "Advanced" section
4. Find the "Region" field at the bottom
5. Enter the desired region code (e.g., "US", "AU", "UK")
6. Save the post/page

## Technical Implementation

### Files Created/Modified

**PHP Files:**
- `blocks/blocks.php` - Added enqueue function for global block config
- `blaze-wooless.php` - Included test file for debugging

**JavaScript Files:**
- `assets/js/global-block-config.js` - Main implementation
- Uses WordPress hooks: `blocks.registerBlockType`, `editor.BlockEdit`, `blocks.getSaveContent.extraProps`

**CSS Files:**
- `assets/css/global-block-config.css` - Styling for the region control

**Test Files:**
- `test/test-global-block-config.php` - Automated testing for the feature

### WordPress Hooks Used

1. **`blocks.registerBlockType`** - Adds region attribute to all blocks
2. **`editor.BlockEdit`** - Adds region control to Advanced panel
3. **`blocks.getSaveContent.extraProps`** - Saves region as HTML data attribute

### Dependencies

- `wp-blocks` - Block registration functionality
- `wp-element` - React elements (createElement, Fragment)
- `wp-components` - UI components (TextControl)
- `wp-block-editor` - Block editor components (InspectorAdvancedControls)
- `wp-hooks` - WordPress filter system
- `wp-compose` - Higher-order components
- `wp-api-fetch` - REST API requests

## Performance Optimization

### Global Caching System

The implementation uses a sophisticated caching system to prevent performance issues:

- **Single API Request**: Regions are fetched only once per editor session, regardless of block count
- **Shared Cache**: All blocks share the same regions data via global cache
- **Promise Reuse**: Multiple blocks loading simultaneously share the same API request
- **Immediate Loading**: Cached data is available instantly for subsequent blocks

### Cache Management

```javascript
// Cache structure
regionsCache = {
    data: null,        // Cached regions array
    loading: false,    // Loading state flag
    error: null,       // Error state
    promise: null      // Shared promise for concurrent requests
}

// Clear cache if needed (available in browser console)
window.blazeCommerceClearRegionsCache();
```

### Performance Benefits

- **Eliminates N+1 Problem**: No matter how many blocks (10, 100, or 1000), only 1 API request is made
- **Faster Loading**: Subsequent blocks load instantly using cached data
- **Reduced Server Load**: Minimal impact on WordPress REST API
- **Better User Experience**: No loading delays after the first block

## Block Exclusions

The following core blocks are excluded from region configuration:

- `core/freeform` - Classic editor block
- `core/html` - Custom HTML block  
- `core/shortcode` - Shortcode block

These blocks are excluded because they typically contain raw content that shouldn't have region-specific behavior.

## Frontend Integration

### Data Attribute

When a region is specified, the block HTML includes:

```html
data-blaze-region="[region-value]"
```

### JavaScript Access

Frontend JavaScript can access the region data:

```javascript
// Get all blocks with region data
const regionBlocks = document.querySelectorAll('[data-blaze-region]');

// Get specific region blocks
const usBlocks = document.querySelectorAll('[data-blaze-region="US"]');

// Process region-specific blocks
regionBlocks.forEach(block => {
    const region = block.getAttribute('data-blaze-region');
    // Handle region-specific logic
});
```

### CSS Targeting

CSS can target blocks by region:

```css
/* Hide blocks for specific regions */
[data-blaze-region="US"] {
    display: none;
}

/* Style blocks differently by region */
[data-blaze-region="AU"] {
    border-left: 3px solid #00a86b;
}
```

## Testing

### Automated Tests

The feature includes automated tests in `test/test-global-block-config.php`:

- **Script Enqueue Test**: Verifies the JavaScript file is properly enqueued
- **File Existence Test**: Confirms all required files exist and are readable
- **Integration Test**: Checks WordPress hook integration

### Manual Testing

1. **Block Editor Test**:
   - Create a new post/page
   - Add any Gutenberg block
   - Check Advanced panel for Region field
   - Enter a region value and save

2. **Frontend Test**:
   - View the published post/page
   - Inspect HTML to verify `data-blaze-region` attribute
   - Confirm region value is correctly saved

3. **Multiple Blocks Test**:
   - Add multiple blocks with different regions
   - Verify each block saves its region independently
   - Check that empty regions don't add data attributes

## Troubleshooting

### Common Issues

**Region field not appearing:**
- Check if JavaScript file is loading (browser console)
- Verify WordPress dependencies are available
- Ensure block editor assets are properly enqueued

**Region data not saving:**
- Check browser console for JavaScript errors
- Verify block attributes are being set correctly
- Confirm WordPress save functionality is working

**Frontend data attribute missing:**
- Ensure region field has a value (not empty)
- Check if block is being rendered correctly
- Verify save props filter is working

### Debug Information

Enable WordPress debug mode to see test results:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Check debug logs for test results and any error messages.

## Future Enhancements

Potential improvements for future versions:

1. **Dropdown Selection**: Replace text field with region dropdown
2. **Multiple Regions**: Support for multiple region selection
3. **Region Validation**: Validate against available regions
4. **Visual Indicators**: Show region badges in block editor
5. **Bulk Operations**: Bulk assign regions to multiple blocks
6. **Integration**: Connect with Aelia Currency Switcher regions

## Conclusion

The Global Block Region Configuration provides a simple, effective way to add region-specific functionality to all Gutenberg blocks. The implementation follows WordPress best practices and integrates seamlessly with the block editor interface.

The feature is designed to be:
- **Non-intrusive**: Only adds a simple text field
- **Performant**: Minimal impact on editor performance
- **Extensible**: Easy to enhance with additional functionality
- **Compatible**: Works with all existing blocks and themes
