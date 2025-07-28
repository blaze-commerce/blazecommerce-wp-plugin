# Kajal Collection Menu Block

A customizable Gutenberg block for creating collection menus with titles, badges, and menu items.

## Features

- **Customizable Title**: Set main title with optional decorative underline
- **Badge Support**: Display badges like "NEW IN!" or "FEATURED"
- **Dynamic Menu Items**: Add, edit, and remove menu items
- **Link Types**: Support for URL and anchor links
- **Styling**: Full Tailwind CSS integration
- **Responsive**: Mobile-friendly design
- **Accessible**: Keyboard navigation and screen reader support

## Block Structure

```
kajal-collection-menu/
├── block.json          # Block configuration and attributes
├── index.js           # Block registration
├── edit.js            # Editor component
├── save.js            # Frontend render component
├── editor.scss        # Editor styles
├── style.scss         # Frontend styles
└── README.md          # This file
```

## Attributes

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `title` | string | "Choose By Type" | Main title text |
| `titleClass` | string | "" | CSS classes for title |
| `showUnderline` | boolean | true | Show decorative underline |
| `underlineClass` | string | "" | CSS classes for underline |
| `showBadge` | boolean | true | Show badge element |
| `badgeText` | string | "NEW IN!" | Badge text content |
| `badgeClass` | string | "" | CSS classes for badge |
| `containerClass` | string | "" | CSS classes for container |
| `menuItemClass` | string | "" | CSS classes for menu items |
| `menuItems` | array | [...] | Array of menu item objects |

### Menu Item Object Structure

```javascript
{
  id: "item-1",           // Unique identifier
  text: "Menu Item",      // Display text
  link: "#",              // URL or anchor
  linkType: "url",        // "url" or "anchor"
  target: "_self"         // "_self" or "_blank"
}
```

## Usage Examples

### Basic Implementation

```javascript
// Block attributes
{
  "title": "Choose By Type",
  "showUnderline": true,
  "showBadge": true,
  "badgeText": "NEW IN!",
  "menuItems": [
    {
      "id": "item-1",
      "text": "DIVYA - Diamond Mangalsutra",
      "link": "/category/divya-diamond",
      "linkType": "url",
      "target": "_self"
    }
  ]
}
```

### Styled with Tailwind CSS

```javascript
// Custom styling
{
  "containerClass": "p-6 bg-white shadow-lg rounded-lg",
  "titleClass": "text-2xl font-serif text-gray-800 mb-4",
  "underlineClass": "border-b-2 border-yellow-500 w-full mb-4",
  "badgeClass": "border border-yellow-500 text-yellow-600 px-4 py-2 rounded-full",
  "menuItemClass": "bg-gray-100 hover:bg-gray-200 px-4 py-3 rounded-lg"
}
```

## Default Styling

The block includes default styles that match the reference design:

- **Title**: Large serif font with elegant styling
- **Underline**: Golden gradient line
- **Badge**: Outlined with golden border
- **Menu Items**: Gray background with hover effects

## Development

### Building the Block

```bash
cd blocks
npm run build
```

### File Structure

- `edit.js`: Contains the editor interface with sidebar controls
- `save.js`: Renders the frontend output
- `editor.scss`: Styles for the editor interface
- `style.scss`: Styles for the frontend display

### Key Components

1. **Inspector Controls**: Sidebar configuration panel
2. **Menu Item Management**: Add/edit/remove functionality
3. **Link Type Handling**: URL vs anchor link logic
4. **CSS Class Integration**: Tailwind CSS support

## Testing

Run the unit tests:

```bash
# From plugin root
phpunit tests/unit/test-kajal-collection-menu-block.php
```

Test coverage includes:
- Block registration
- Attribute validation
- Render output
- Link handling
- CSS class application

## Browser Support

- Modern browsers (Chrome, Firefox, Safari, Edge)
- Mobile devices
- Requires Tailwind CSS for styling

## Accessibility

- Semantic HTML structure
- Keyboard navigation support
- Screen reader compatibility
- Focus indicators
- Proper link attributes

## Contributing

1. Follow WordPress coding standards
2. Include unit tests for new features
3. Update documentation
4. Test across different browsers
5. Ensure accessibility compliance

## Related Documentation

- [Feature Documentation](../../../docs/features/kajal-collection-menu-block.md)
- [WordPress Block Development](https://developer.wordpress.org/block-editor/)
- [Tailwind CSS Documentation](https://tailwindcss.com/docs)
