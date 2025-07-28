# Blaze Commerce Gutenberg Blocks

This directory contains custom Gutenberg blocks for the Blaze Commerce plugin.

## Available Blocks

### 1. Blaze Slideshow Block (`blaze-commerce/blaze-slideshow`)

A responsive slideshow/carousel block with customizable navigation and autoplay options. **Universal compatibility** - works with any WordPress theme and template, not limited to WooCommerce.

#### Features:

- **Universal Compatibility**: Works with any WordPress theme and template (not limited to WooCommerce)
- **Responsive Design**: Configure different number of slides for desktop, tablet, and mobile
- **Navigation Options**: Enable/disable arrow navigation and dot indicators
- **Autoplay Support**: Optional autoplay with customizable speed
- **Touch/Swipe Support**: Mobile-friendly touch gestures
- **Infinite Loop**: Optional infinite scrolling
- **Customizable Styling**: Arrow colors, dot colors, and CSS classes
- **Dynamic Content**: Use any WordPress blocks as slide content (25+ core blocks supported)
- **Performance Optimized**: Lightweight and efficient implementation

#### Configuration Options:

- `slidesToShowDesktop` (number): Number of slides visible on desktop (1-6, default: 3)
- `slidesToShowTablet` (number): Number of slides visible on tablet (1-4, default: 2)
- `slidesToShowMobile` (number): Number of slides visible on mobile (1-2, default: 1)
- `enableArrows` (boolean): Show/hide arrow navigation (default: true)
- `enableDots` (boolean): Show/hide dot indicators (default: true)
- `enableAutoplay` (boolean): Enable automatic slide progression (default: false)
- `autoplaySpeed` (number): Time between slides in milliseconds (1000-10000, default: 3000)
- `infinite` (boolean): Enable infinite loop (default: true)
- `speed` (number): Transition speed in milliseconds (100-2000, default: 500)
- `slidesToScroll` (number): Number of slides to scroll at once (1-3, default: 1)
- `arrowColor` (string): Color for navigation arrows (default: #333333)
- `dotColor` (string): Color for inactive dots (default: #cccccc)
- `dotActiveColor` (string): Color for active dot (default: #333333)
- `containerClass` (string): Additional CSS classes for container

#### Usage:

1. Add the "Blaze Slideshow" block to your page (found in "BlazeCommerce" category)
2. Configure slideshow settings in the sidebar
3. Add content blocks inside the slideshow (each top-level block becomes a slide)
4. Use any of 25+ supported WordPress core blocks for slide content
5. Customize styling and behavior options
6. Preview responsive behavior in the editor
7. Works on any WordPress template - posts, pages, custom post types, etc.

### 2. Product Description Block (`blaze-commerce/product-description`)

Displays WooCommerce product description with extensive styling options.

#### Features:

- **Product Selection**: Choose any WooCommerce product from a dropdown
- **Auto-detection**: Automatically selects current product when editing a product page
- **Title Options**: Show/hide title with customizable text, color, font size, and weight
- **Text Styling**: Full control over text color, font size, weight, line height, and text transformation
- **Alignment**: Left, center, right, or justify text alignment
- **Rich Content**: Supports HTML content and shortcodes in product descriptions

#### Attributes:

- `productId` (number): ID of the product to display
- `showTitle` (boolean): Whether to show the title
- `titleText` (string): Custom title text
- `titleColor` (string): Title color
- `titleFontSize` (number): Title font size in pixels
- `titleFontWeight` (string): Title font weight
- `textColor` (string): Description text color
- `fontSize` (number): Description font size in pixels
- `fontWeight` (string): Description font weight
- `lineHeight` (number): Line height multiplier
- `textTransform` (string): Text transformation (none, uppercase, lowercase, capitalize)
- `alignment` (string): Text alignment

### 2. Product Detail Block (`blaze-commerce/product-detail`)

Displays comprehensive WooCommerce product details with checkbox controls for each element.

#### Features:

- **Product Selection**: Choose any WooCommerce product from a dropdown
- **Auto-detection**: Automatically selects current product when editing a product page
- **Flexible Display Options**: Toggle visibility of each product detail element
- **Short Description Control**: Checkbox to show/hide product short description
- **Stock Information**: Display stock status with optional stock quantity
- **Styling Controls**: Separate styling options for different elements
- **Category & Tag Support**: Display product categories and tags

#### Display Options (Checkboxes):

- ✅ **Show Short Description**: Display product short description with custom styling
- ✅ **Show SKU**: Display product SKU
- ✅ **Show Price**: Display product price with custom styling
- ✅ **Show Stock Status**: Display stock status (In Stock, Out of Stock, On Backorder)
- ✅ **Show Stock Quantity**: Show available quantity when product is in stock
- ✅ **Show Categories**: Display product categories
- ✅ **Show Tags**: Display product tags

#### Styling Options:

- **General Styling**: Text color, font size, weight, line height, alignment
- **Short Description Styling**: Separate color and font size controls
- **Price Styling**: Custom color, font size, and weight for prices

#### Attributes:

- `productId` (number): ID of the product to display
- `showShortDescription` (boolean): Show/hide short description
- `showSku` (boolean): Show/hide SKU
- `showPrice` (boolean): Show/hide price
- `showStockStatus` (boolean): Show/hide stock status
- `showStockQuantity` (boolean): Show/hide stock quantity
- `showCategories` (boolean): Show/hide categories
- `showTags` (boolean): Show/hide tags
- Plus styling attributes for colors, fonts, and alignment

## Installation & Usage

### Prerequisites

- WordPress with Gutenberg editor
- WooCommerce plugin installed and activated
- Blaze Commerce plugin installed

### Development Setup

1. Navigate to the blocks directory:

```bash
cd blocks
```

2. Install dependencies:

```bash
npm install
```

3. Build the blocks:

```bash
npm run build
```

4. For development with hot reload:

```bash
npm run start
```

### Using the Blocks

1. **In the WordPress Editor**:

   - Open any post, page, or template in the Gutenberg editor
   - Click the "+" button to add a new block
   - Search for "BlazeCommerce" or find the blocks in the "BlazeCommerce" category
   - Select any of the available blocks: Blaze Slideshow, Product Description, Product Detail, Service Features, or Product Stock Status

2. **Configuration**:

   - Use the sidebar controls to select a product
   - Customize display options using the checkboxes
   - Adjust styling options in the sidebar panels
   - Preview changes in real-time

3. **Auto-detection**:
   - When editing a WooCommerce product page, the blocks will automatically detect and select the current product

## Technical Details

### File Structure

```
blocks/
├── src/
│   ├── blaze-slideshow/
│   │   ├── block.json          # Block metadata
│   │   ├── edit.js            # Editor component
│   │   ├── save.js            # Save component
│   │   ├── index.js           # Block registration
│   │   ├── editor.scss        # Editor styles
│   │   ├── style.scss         # Frontend styles
│   │   └── frontend.js        # Frontend JavaScript
│   ├── product-description/
│   │   ├── block.json          # Block metadata
│   │   ├── edit.js            # Editor component
│   │   ├── save.js            # Save component
│   │   ├── index.js           # Block registration
│   │   ├── editor.css         # Editor styles
│   │   └── style.css          # Frontend styles
│   ├── product-detail/
│   │   ├── block.json          # Block metadata
│   │   ├── edit.js            # Editor component
│   │   ├── save.js            # Save component
│   │   ├── index.js           # Block registration
│   │   ├── editor.css         # Editor styles
│   │   └── style.css          # Frontend styles
│   ├── service-features/
│   │   ├── block.json          # Block metadata
│   │   ├── edit.js            # Editor component
│   │   ├── save.js            # Save component
│   │   ├── index.js           # Block registration
│   │   ├── editor.scss        # Editor styles
│   │   └── style.scss         # Frontend styles
│   ├── stock-status/
│   │   ├── block.json          # Block metadata
│   │   ├── edit.js            # Editor component
│   │   ├── save.js            # Save component
│   │   ├── index.js           # Block registration
│   │   └── style.scss         # Frontend styles
│   └── index.js               # Main entry point
├── build/                     # Compiled blocks
└── blocks.php                 # PHP registration
```

### Backend Integration

- PHP classes in `app/Extensions/Gutenberg/Blocks/ProductBlocks.php`
- Server-side rendering for frontend display
- Integration with WooCommerce product data
- Automatic fallback for missing products

### API Integration

- Uses WordPress REST API for product data
- Fallback to WooCommerce REST API if needed
- Real-time product selection and preview
- Automatic current product detection

## Customization

### Adding New Styling Options

1. Add new attributes to `block.json`
2. Add controls to the `edit.js` component
3. Update the save function and PHP renderer
4. Add corresponding CSS if needed

### Extending Product Data

1. Modify the API calls in `edit.js`
2. Update the PHP rendering functions
3. Add new display options as needed

## Troubleshooting

### Common Issues

1. **Products not loading**: Check WooCommerce REST API permissions
2. **Styling not applied**: Ensure CSS files are properly enqueued
3. **Block not appearing**: Verify block category registration
4. **Auto-detection not working**: Check if editing a product post type

### Debug Mode

Enable WordPress debug mode to see detailed error messages:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Contributing

When contributing to these blocks:

1. Follow WordPress coding standards
2. Test with different product types
3. Ensure responsive design
4. Add proper error handling
5. Update documentation as needed
