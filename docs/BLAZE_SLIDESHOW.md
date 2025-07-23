# Blaze Slideshow Block Documentation

## Overview

The Blaze Slideshow block is a powerful, responsive carousel/slideshow component for WordPress Gutenberg editor. It allows you to create dynamic slideshows with customizable navigation, autoplay functionality, and responsive behavior. **Universal compatibility** - works with any WordPress theme and template, not limited to WooCommerce.

## Features

### Core Features

- **Universal Compatibility**: Works with any WordPress theme and template (not limited to WooCommerce)
- **Responsive Design**: Different slide counts for desktop, tablet, and mobile
- **Dynamic Content**: Use any WordPress blocks as slide content (25+ core blocks supported)
- **Touch/Swipe Support**: Mobile-friendly gesture navigation
- **Keyboard Navigation**: Arrow key support for accessibility
- **Infinite Loop**: Optional seamless looping
- **Performance Optimized**: Lightweight implementation with minimal dependencies

### Navigation Options

- **Arrow Navigation**: Previous/Next buttons with customizable colors
- **Dot Indicators**: Visual slide indicators with active state styling
- **Touch Gestures**: Swipe left/right on mobile devices
- **Keyboard Support**: Arrow keys for navigation

### Autoplay Features

- **Configurable Speed**: Set time between automatic transitions
- **Pause on Hover**: Automatically pause when user hovers over slideshow
- **Resume on Leave**: Continue autoplay when hover ends

## Configuration Options

### Responsive Settings

| Option                | Type   | Default | Range | Description                                 |
| --------------------- | ------ | ------- | ----- | ------------------------------------------- |
| `slidesToShowDesktop` | number | 3       | 1-6   | Number of slides visible on desktop screens |
| `slidesToShowTablet`  | number | 2       | 1-4   | Number of slides visible on tablet screens  |
| `slidesToShowMobile`  | number | 1       | 1-2   | Number of slides visible on mobile screens  |

### Navigation Settings

| Option         | Type    | Default | Description                        |
| -------------- | ------- | ------- | ---------------------------------- |
| `enableArrows` | boolean | true    | Show/hide arrow navigation buttons |
| `enableDots`   | boolean | true    | Show/hide dot indicators           |

### Autoplay Settings

| Option           | Type    | Default | Range      | Description                         |
| ---------------- | ------- | ------- | ---------- | ----------------------------------- |
| `enableAutoplay` | boolean | false   | -          | Enable automatic slide progression  |
| `autoplaySpeed`  | number  | 3000    | 1000-10000 | Time between slides in milliseconds |

### Advanced Settings

| Option           | Type    | Default | Range    | Description                        |
| ---------------- | ------- | ------- | -------- | ---------------------------------- |
| `infinite`       | boolean | true    | -        | Enable infinite loop scrolling     |
| `speed`          | number  | 500     | 100-2000 | Transition speed in milliseconds   |
| `slidesToScroll` | number  | 1       | 1-3      | Number of slides to scroll at once |

### Styling Options

| Option           | Type   | Default | Description                          |
| ---------------- | ------ | ------- | ------------------------------------ |
| `arrowColor`     | string | #333333 | Color for navigation arrows          |
| `dotColor`       | string | #cccccc | Color for inactive dot indicators    |
| `dotActiveColor` | string | #333333 | Color for active dot indicator       |
| `containerClass` | string | ""      | Additional CSS classes for container |

## Usage Guide

### Basic Setup

1. **Add the Block**

   - In the Gutenberg editor, click the "+" button
   - Search for "Blaze Slideshow" or find it in the "BlazeCommerce" category
   - Click to add the block to your page (works on any template - posts, pages, custom post types, etc.)

2. **Configure Settings**

   - Use the sidebar Inspector Controls to configure slideshow options
   - Set responsive slide counts for different screen sizes
   - Enable/disable navigation options as needed

3. **Add Content**
   - Each top-level block inside the slideshow becomes a slide
   - Use any WordPress blocks: images, text, buttons, groups, etc.
   - The block comes with 3 sample slides by default

### Advanced Configuration

#### Responsive Behavior

```
Desktop (≥1024px): Shows slidesToShowDesktop slides
Tablet (768-1023px): Shows slidesToShowTablet slides
Mobile (<768px): Shows slidesToShowMobile slides
```

#### Autoplay Configuration

- Enable autoplay in the sidebar settings
- Set autoplay speed (1-10 seconds)
- Slideshow pauses on hover and resumes when hover ends
- Autoplay stops when user manually navigates

#### Custom Styling

- Add custom CSS classes via the `containerClass` option
- Customize arrow and dot colors using the color pickers
- Override styles using CSS custom properties:
  - `--slides-desktop`: Number of desktop slides
  - `--slides-tablet`: Number of tablet slides
  - `--slides-mobile`: Number of mobile slides
  - `--transition-speed`: Transition duration
  - `--dot-color`: Inactive dot color
  - `--dot-active-color`: Active dot color

## CSS Custom Properties

The slideshow uses CSS custom properties for dynamic styling:

```css
.wp-block-blaze-commerce-blaze-slideshow {
	--slides-desktop: 3;
	--slides-tablet: 2;
	--slides-mobile: 1;
	--transition-speed: 500ms;
	--dot-color: #cccccc;
	--dot-active-color: #333333;
}
```

## JavaScript API

The frontend JavaScript creates a `BlazeSlideshow` class for each slideshow instance:

### Methods

- `nextSlide()`: Move to next slide
- `prevSlide()`: Move to previous slide
- `goToSlide(index)`: Jump to specific slide
- `startAutoplay()`: Start automatic progression
- `pauseAutoplay()`: Pause automatic progression
- `destroy()`: Clean up event listeners

### Events

The slideshow responds to:

- Touch/swipe gestures on mobile
- Keyboard arrow keys
- Mouse hover (for autoplay pause/resume)
- Window resize (for responsive updates)

## Accessibility

### Keyboard Navigation

- **Left Arrow**: Previous slide
- **Right Arrow**: Next slide
- **Tab**: Navigate through dots and arrows
- **Enter/Space**: Activate focused navigation element

### Screen Reader Support

- Arrow buttons have appropriate ARIA labels
- Dot indicators show current position
- Focus management for keyboard users

### Visual Indicators

- High contrast focus outlines
- Clear active state for dots
- Disabled state for arrows when appropriate

## Performance Considerations

### Optimization Features

- Lazy loading of slide content
- Efficient CSS transforms for animations
- Debounced resize handling
- Minimal DOM manipulation

### Best Practices

- Limit number of slides for better performance
- Optimize images used in slides
- Use appropriate autoplay speeds (3-5 seconds recommended)
- Test on mobile devices for touch responsiveness

## Troubleshooting

### Common Issues

**Slideshow not working**

- Ensure JavaScript is enabled
- Check browser console for errors
- Verify block is properly registered

**Responsive behavior not working**

- Check CSS custom properties are set
- Verify media queries are not overridden
- Test on actual devices, not just browser resize

**Touch gestures not working**

- Ensure touch events are not prevented by other scripts
- Check for conflicting touch handlers
- Test on actual touch devices

**Autoplay not working**

- Verify autoplay is enabled in settings
- Check if user has interacted with slideshow (some browsers require user interaction)
- Ensure autoplay speed is reasonable (not too fast/slow)

### Debug Mode

Add this CSS to enable debug mode:

```css
.wp-block-blaze-commerce-blaze-slideshow {
	border: 2px solid red !important;
}
.blaze-slideshow-track > * {
	border: 1px solid blue !important;
}
```

## Browser Support

- **Modern Browsers**: Full support (Chrome 60+, Firefox 55+, Safari 12+, Edge 79+)
- **Legacy Browsers**: Graceful degradation (basic functionality without animations)
- **Mobile Browsers**: Full touch support on iOS Safari 12+ and Android Chrome 60+

## Examples

### Basic Image Slideshow

```html
<!-- Each group becomes a slide -->
<div class="wp-block-group">
	<img src="image1.jpg" alt="Slide 1" />
	<h3>Slide Title 1</h3>
</div>
<div class="wp-block-group">
	<img src="image2.jpg" alt="Slide 2" />
	<h3>Slide Title 2</h3>
</div>
```

### Product Showcase

```html
<!-- Using WooCommerce blocks as slides -->
<div class="wp-block-blaze-commerce-product-detail"></div>
<div class="wp-block-blaze-commerce-product-description"></div>
<div class="wp-block-blaze-commerce-service-features"></div>
```

### Custom Content Slides

```html
<!-- Mixed content slides -->
<div class="wp-block-group">
	<h2>Welcome</h2>
	<p>Introduction text...</p>
	<div class="wp-block-buttons">
		<a class="wp-block-button__link">Learn More</a>
	</div>
</div>
```
