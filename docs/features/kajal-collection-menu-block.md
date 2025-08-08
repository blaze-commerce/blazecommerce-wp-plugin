# Kajal Collection Menu Block

## Overview

The Kajal Collection Menu block is a customizable Gutenberg block that displays a collection menu with a title, optional badge, and menu items. This block is perfect for creating navigation menus, product category lists, or any organized list of links.

## Features

- **Customizable Title**: Set a main title for your menu
- **Optional Underline**: Add a decorative underline below the title
- **Badge Support**: Display a badge (like "NEW IN!") below the title
- **Dynamic Menu Items**: Add, edit, and remove menu items
- **Link Types**: Support for both URL links and anchor links
- **Target Options**: Open links in same window or new window
- **Tailwind CSS Integration**: Full support for Tailwind CSS classes
- **Responsive Design**: Mobile-friendly layout

## Block Configuration

### Basic Settings

#### Title Settings
- **Title**: The main heading text for your menu
- **Show Underline**: Toggle to show/hide decorative underline
- **Title CSS Classes**: Add custom Tailwind CSS classes for styling

#### Badge Settings
- **Show Badge**: Toggle to show/hide the badge
- **Badge Text**: Text to display in the badge (e.g., "NEW IN!", "FEATURED")
- **Badge CSS Classes**: Add custom Tailwind CSS classes for badge styling

### Menu Items

#### Adding Menu Items
1. Click "Add Menu Item" button in the sidebar
2. Configure the item properties:
   - **Text**: Display text for the menu item
   - **Link Type**: Choose between "URL" or "Anchor"
   - **Link/Anchor**: Enter the URL or anchor name
   - **Target**: Choose to open in same window or new window

#### Managing Menu Items
- **Select Item**: Use the dropdown to select which item to edit
- **Remove Item**: Click the trash icon to delete an item
- **Reorder**: Items appear in the order they were added

### Styling Options

#### Container Styling
- **Container CSS Classes**: Add Tailwind classes for the main container
- **Example**: `p-6 bg-white shadow-lg rounded-lg`

#### Title Styling
- **Title CSS Classes**: Style the main title
- **Example**: `text-2xl font-bold text-gray-800 mb-4`

#### Underline Styling
- **Underline CSS Classes**: Style the decorative underline
- **Example**: `border-b-2 border-yellow-500 w-full mb-4`

#### Badge Styling
- **Badge CSS Classes**: Style the badge element
- **Example**: `border border-yellow-500 text-yellow-600 px-4 py-2 rounded-full text-sm font-medium`

#### Menu Item Styling
- **Menu Item CSS Classes**: Style individual menu items
- **Example**: `bg-gray-100 hover:bg-gray-200 px-4 py-3 rounded-lg transition-colors`

## Usage Examples

### Basic Collection Menu
```html
<!-- Default styling similar to the screenshot -->
<div class="kajal-collection-menu">
  <div class="kajal-collection-menu-container p-6">
    <div class="kajal-menu-title-section">
      <h2 class="kajal-menu-title text-2xl font-serif text-gray-800">Choose By Type</h2>
      <div class="kajal-menu-underline bg-gradient-to-r from-yellow-600 to-yellow-500 h-0.5 w-full mb-4"></div>
    </div>
    
    <div class="kajal-menu-badge border border-yellow-600 text-yellow-600 px-4 py-2 rounded-full text-sm font-medium mb-6">
      NEW IN!
    </div>
    
    <div class="kajal-menu-items space-y-3">
      <a href="#" class="kajal-menu-item block bg-gray-100 hover:bg-gray-200 px-4 py-3 rounded-lg">
        DIVYA - Diamond Mangalsutra
      </a>
      <a href="#" class="kajal-menu-item block bg-gray-100 hover:bg-gray-200 px-4 py-3 rounded-lg">
        Mangalsutra 3.0 - Kismet
      </a>
      <!-- More items... -->
    </div>
  </div>
</div>
```

### Product Category Menu
```json
{
  "title": "Shop by Category",
  "showBadge": true,
  "badgeText": "FEATURED",
  "menuItems": [
    {
      "text": "Necklaces",
      "link": "/category/necklaces",
      "linkType": "url",
      "target": "_self"
    },
    {
      "text": "Earrings", 
      "link": "/category/earrings",
      "linkType": "url",
      "target": "_self"
    }
  ]
}
```

### Anchor Navigation Menu
```json
{
  "title": "Page Sections",
  "showBadge": false,
  "menuItems": [
    {
      "text": "About Us",
      "link": "about-section",
      "linkType": "anchor",
      "target": "_self"
    },
    {
      "text": "Services",
      "link": "services-section", 
      "linkType": "anchor",
      "target": "_self"
    }
  ]
}
```

## Default Styling

The block comes with default styling that matches the design shown in the reference screenshot:

- **Title**: Large serif font with elegant styling
- **Underline**: Golden gradient line below title
- **Badge**: Outlined badge with golden border
- **Menu Items**: Gray background with hover effects and rounded corners
- **Layout**: Vertical stacking with proper spacing

## Customization Tips

### Color Schemes
- **Golden Theme**: Use `border-yellow-500`, `text-yellow-600`, `bg-yellow-50`
- **Blue Theme**: Use `border-blue-500`, `text-blue-600`, `bg-blue-50`
- **Dark Theme**: Use `bg-gray-800`, `text-white`, `border-gray-600`

### Typography
- **Serif Titles**: Add `font-serif` for elegant headings
- **Sans-serif Items**: Use `font-sans` for clean menu items
- **Font Weights**: Combine `font-light`, `font-medium`, `font-bold`

### Spacing
- **Compact**: Use `space-y-1`, `px-2 py-1`
- **Standard**: Use `space-y-3`, `px-4 py-3`
- **Spacious**: Use `space-y-6`, `px-6 py-4`

## Accessibility

The block includes several accessibility features:

- **Semantic HTML**: Uses proper heading and link elements
- **Keyboard Navigation**: All links are keyboard accessible
- **Focus Indicators**: Clear focus states for keyboard users
- **Screen Reader Support**: Proper text content and link descriptions

## Browser Support

- **Modern Browsers**: Full support in Chrome, Firefox, Safari, Edge
- **Mobile Devices**: Responsive design works on all screen sizes
- **Tailwind CSS**: Requires Tailwind CSS for styling to work properly

## Technical Details

- **Block Name**: `blaze-commerce/kajal-collection-menu`
- **Category**: `woocommerce-product-elements`
- **Supports**: HTML editing disabled for security
- **Dependencies**: WordPress 5.0+, Gutenberg editor

## Troubleshooting

### Common Issues

1. **Styling not applied**: Ensure Tailwind CSS is loaded on your site
2. **Links not working**: Check that URLs are properly formatted
3. **Block not appearing**: Verify the block is registered and built properly

### Debug Steps

1. Check browser console for JavaScript errors
2. Verify block registration in WordPress admin
3. Ensure build process completed successfully
4. Test with default styling first, then add custom classes
