# Gutenberg Block Configuration Guide

## Overview

This comprehensive guide covers how to add configurations and settings to WordPress Gutenberg blocks. Based on modern WordPress development practices (2024), this document serves as a reference for implementing block settings, inspector controls, and attributes.

## Table of Contents

1. [Block Configuration Fundamentals](#block-configuration-fundamentals)
2. [Block Attributes Schema](#block-attributes-schema)
3. [Inspector Controls](#inspector-controls)
4. [Block Settings Groups](#block-settings-groups)
5. [Modern Implementation Patterns](#modern-implementation-patterns)
6. [Best Practices](#best-practices)
7. [Common Components](#common-components)
8. [Examples](#examples)

## Block Configuration Fundamentals

### Core Concepts

Gutenberg block configurations involve three main components:

1. **Attributes** - Define the data structure and schema
2. **Edit Function** - Handles the editor interface and controls
3. **Save Function** - Defines how data is saved to post content

### File Structure

```
block-name/
├── block.json          # Block metadata and configuration
├── edit.js            # Editor interface and controls
├── save.js            # Frontend output
├── style.css          # Frontend styles
└── editor.css         # Editor-only styles
```

## Block Attributes Schema

### Defining Attributes in block.json

```json
{
  "attributes": {
    "textContent": {
      "type": "string",
      "default": "Default text"
    },
    "showIcon": {
      "type": "boolean",
      "default": false
    },
    "alignment": {
      "type": "string",
      "default": "left",
      "enum": ["left", "center", "right"]
    },
    "backgroundColor": {
      "type": "string"
    },
    "items": {
      "type": "array",
      "default": []
    },
    "settings": {
      "type": "object",
      "default": {}
    }
  }
}
```

### Attribute Types

- **string** - Text values
- **boolean** - True/false values
- **number** - Numeric values
- **integer** - Whole numbers
- **array** - Lists of values
- **object** - Complex data structures

### Advanced Attribute Configuration

```json
{
  "attributes": {
    "customField": {
      "type": "string",
      "source": "attribute",
      "selector": "img",
      "attribute": "src"
    },
    "richContent": {
      "type": "string",
      "source": "html",
      "selector": ".content"
    }
  }
}
```

## Inspector Controls

### Basic Inspector Controls Setup

```javascript
import { 
  useBlockProps, 
  InspectorControls 
} from '@wordpress/block-editor';

import {
  PanelBody,
  TextControl,
  ToggleControl,
  SelectControl,
  RangeControl
} from '@wordpress/components';

export default function Edit({ attributes, setAttributes }) {
  const blockProps = useBlockProps();

  return (
    <>
      <InspectorControls>
        <PanelBody title="Block Settings" initialOpen={true}>
          <TextControl
            label="Custom Text"
            value={attributes.customText}
            onChange={(value) => setAttributes({ customText: value })}
          />
          
          <ToggleControl
            label="Show Icon"
            checked={attributes.showIcon}
            onChange={(value) => setAttributes({ showIcon: value })}
          />
          
          <SelectControl
            label="Alignment"
            value={attributes.alignment}
            options={[
              { label: 'Left', value: 'left' },
              { label: 'Center', value: 'center' },
              { label: 'Right', value: 'right' }
            ]}
            onChange={(value) => setAttributes({ alignment: value })}
          />
        </PanelBody>
      </InspectorControls>
      
      <div {...blockProps}>
        {/* Block content */}
      </div>
    </>
  );
}
```

### Multiple Panels

```javascript
<InspectorControls>
  <PanelBody title="Content Settings" initialOpen={true}>
    {/* Content-related controls */}
  </PanelBody>
  
  <PanelBody title="Display Options" initialOpen={false}>
    {/* Display-related controls */}
  </PanelBody>
  
  <PanelBody title="Advanced Settings" initialOpen={false}>
    {/* Advanced controls */}
  </PanelBody>
</InspectorControls>
```

## Block Settings Groups

### Modern Approach (WordPress 6.2+)

WordPress provides predefined settings groups that integrate with the block editor's native panels:

#### Available Groups

- **styles** - General styling options
- **color** - Color settings
- **typography** - Font and text settings
- **dimensions** - Spacing, width, height
- **border** - Border and radius settings
- **advanced** - Advanced/miscellaneous settings

#### Using Settings Groups

```javascript
// Method 1: Using block.json supports
{
  "supports": {
    "spacing": {
      "padding": true,
      "margin": true
    },
    "color": {
      "background": true,
      "text": true
    },
    "typography": {
      "fontSize": true,
      "lineHeight": true
    }
  }
}

// Method 2: Using InspectorControls groups
<InspectorControls group="dimensions">
  <RangeControl
    label="Custom Spacing"
    value={attributes.customSpacing}
    onChange={(value) => setAttributes({ customSpacing: value })}
    min={0}
    max={100}
  />
</InspectorControls>

<InspectorControls group="border">
  <BorderBoxControl
    label="Custom Border"
    value={attributes.customBorder}
    onChange={(value) => setAttributes({ customBorder: value })}
  />
</InspectorControls>
```

### Advanced Controls Component

```javascript
import { InspectorAdvancedControls } from '@wordpress/block-editor';

// Alternative to group="advanced"
<InspectorAdvancedControls>
  <TextControl
    label="CSS Class"
    value={attributes.className}
    onChange={(value) => setAttributes({ className: value })}
  />
</InspectorAdvancedControls>
```

## Modern Implementation Patterns

### Using Hooks and Modern React Patterns

```javascript
import { useState, useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';

export default function Edit({ attributes, setAttributes, clientId }) {
  const [isLoading, setIsLoading] = useState(false);
  
  // Access block editor data
  const { isSelected } = useSelect((select) => ({
    isSelected: select('core/block-editor').isBlockSelected(clientId)
  }));
  
  // Handle complex state updates
  const updateSettings = (newSettings) => {
    setAttributes({
      settings: {
        ...attributes.settings,
        ...newSettings
      }
    });
  };
  
  return (
    <>
      <InspectorControls>
        <PanelBody title="Dynamic Settings">
          {/* Controls that depend on state */}
        </PanelBody>
      </InspectorControls>
      
      <div {...useBlockProps()}>
        {/* Block content */}
      </div>
    </>
  );
}
```

### Conditional Controls

```javascript
<InspectorControls>
  <PanelBody title="Settings">
    <ToggleControl
      label="Enable Advanced Options"
      checked={attributes.enableAdvanced}
      onChange={(value) => setAttributes({ enableAdvanced: value })}
    />
    
    {attributes.enableAdvanced && (
      <>
        <TextControl
          label="Advanced Setting"
          value={attributes.advancedSetting}
          onChange={(value) => setAttributes({ advancedSetting: value })}
        />
        
        <RangeControl
          label="Advanced Range"
          value={attributes.advancedRange}
          onChange={(value) => setAttributes({ advancedRange: value })}
          min={0}
          max={100}
        />
      </>
    )}
  </PanelBody>
</InspectorControls>
```

## Best Practices

### 1. Attribute Naming

- Use descriptive, camelCase names
- Prefix custom attributes to avoid conflicts
- Group related attributes logically

```javascript
// Good
{
  "customTextContent": "string",
  "displayShowIcon": "boolean",
  "layoutAlignment": "string"
}

// Better - with prefixing
{
  "myBlockTextContent": "string",
  "myBlockShowIcon": "boolean", 
  "myBlockAlignment": "string"
}
```

### 2. Default Values

Always provide sensible defaults:

```json
{
  "attributes": {
    "title": {
      "type": "string",
      "default": "Enter title here..."
    },
    "isVisible": {
      "type": "boolean", 
      "default": true
    }
  }
}
```

### 3. Validation and Sanitization

```javascript
const updateTitle = (newTitle) => {
  // Sanitize input
  const sanitizedTitle = newTitle.trim().substring(0, 100);
  setAttributes({ title: sanitizedTitle });
};
```

### 4. Performance Considerations

- Use `initialOpen={false}` for secondary panels
- Implement lazy loading for complex controls
- Debounce frequent updates

```javascript
import { useDebouncedCallback } from 'use-debounce';

const debouncedUpdate = useDebouncedCallback(
  (value) => setAttributes({ searchTerm: value }),
  300
);
```

### 5. Accessibility

- Provide proper labels for all controls
- Use semantic HTML
- Ensure keyboard navigation works

```javascript
<TextControl
  label="Image Alt Text"
  value={attributes.altText}
  onChange={(value) => setAttributes({ altText: value })}
  help="Describe the image for screen readers"
/>
```

## Common Components

### Text Controls

```javascript
import {
  TextControl,
  TextareaControl,
  RichText
} from '@wordpress/components';

// Simple text input
<TextControl
  label="Title"
  value={attributes.title}
  onChange={(value) => setAttributes({ title: value })}
/>

// Multi-line text
<TextareaControl
  label="Description"
  value={attributes.description}
  onChange={(value) => setAttributes({ description: value })}
  rows={4}
/>

// Rich text editor
<RichText
  tagName="h2"
  value={attributes.heading}
  onChange={(value) => setAttributes({ heading: value })}
  placeholder="Enter heading..."
  allowedFormats={['core/bold', 'core/italic']}
/>
```

### Selection Controls

```javascript
import {
  SelectControl,
  RadioControl,
  CheckboxControl,
  ToggleControl
} from '@wordpress/components';

// Dropdown selection
<SelectControl
  label="Size"
  value={attributes.size}
  options={[
    { label: 'Small', value: 'small' },
    { label: 'Medium', value: 'medium' },
    { label: 'Large', value: 'large' }
  ]}
  onChange={(value) => setAttributes({ size: value })}
/>

// Radio buttons
<RadioControl
  label="Layout"
  selected={attributes.layout}
  options={[
    { label: 'Grid', value: 'grid' },
    { label: 'List', value: 'list' }
  ]}
  onChange={(value) => setAttributes({ layout: value })}
/>

// Toggle switch
<ToggleControl
  label="Show Border"
  checked={attributes.showBorder}
  onChange={(value) => setAttributes({ showBorder: value })}
/>
```

### Range and Number Controls

```javascript
import {
  RangeControl,
  __experimentalNumberControl as NumberControl
} from '@wordpress/components';

// Range slider
<RangeControl
  label="Opacity"
  value={attributes.opacity}
  onChange={(value) => setAttributes({ opacity: value })}
  min={0}
  max={100}
  step={5}
/>

// Number input
<NumberControl
  label="Columns"
  value={attributes.columns}
  onChange={(value) => setAttributes({ columns: parseInt(value) })}
  min={1}
  max={6}
/>
```

### Color Controls

```javascript
import { ColorPalette, ColorPicker } from '@wordpress/components';
import { useSelect } from '@wordpress/data';

// Color palette
const colors = useSelect((select) => 
  select('core/block-editor').getSettings().colors
);

<ColorPalette
  colors={colors}
  value={attributes.backgroundColor}
  onChange={(value) => setAttributes({ backgroundColor: value })}
/>

// Advanced color picker
<ColorPicker
  color={attributes.textColor}
  onChange={(value) => setAttributes({ textColor: value })}
/>
```

## Examples

### Complete Block Configuration Example

```javascript
// edit.js
import { 
  useBlockProps, 
  InspectorControls,
  RichText 
} from '@wordpress/block-editor';

import {
  PanelBody,
  TextControl,
  ToggleControl,
  SelectControl,
  RangeControl,
  ColorPalette
} from '@wordpress/components';

export default function Edit({ attributes, setAttributes }) {
  const {
    title,
    content,
    showIcon,
    alignment,
    fontSize,
    textColor,
    backgroundColor
  } = attributes;

  return (
    <>
      <InspectorControls>
        <PanelBody title="Content Settings" initialOpen={true}>
          <ToggleControl
            label="Show Icon"
            checked={showIcon}
            onChange={(value) => setAttributes({ showIcon: value })}
          />
          
          <SelectControl
            label="Text Alignment"
            value={alignment}
            options={[
              { label: 'Left', value: 'left' },
              { label: 'Center', value: 'center' },
              { label: 'Right', value: 'right' }
            ]}
            onChange={(value) => setAttributes({ alignment: value })}
          />
        </PanelBody>
        
        <PanelBody title="Typography" initialOpen={false}>
          <RangeControl
            label="Font Size"
            value={fontSize}
            onChange={(value) => setAttributes({ fontSize: value })}
            min={12}
            max={48}
          />
        </PanelBody>
      </InspectorControls>
      
      <InspectorControls group="color">
        <ColorPalette
          label="Text Color"
          value={textColor}
          onChange={(value) => setAttributes({ textColor: value })}
        />
        
        <ColorPalette
          label="Background Color"
          value={backgroundColor}
          onChange={(value) => setAttributes({ backgroundColor: value })}
        />
      </InspectorControls>
      
      <div 
        {...useBlockProps({
          style: {
            textAlign: alignment,
            fontSize: fontSize + 'px',
            color: textColor,
            backgroundColor: backgroundColor
          }
        })}
      >
        {showIcon && <span className="icon">🎯</span>}
        
        <RichText
          tagName="h3"
          value={title}
          onChange={(value) => setAttributes({ title: value })}
          placeholder="Enter title..."
        />
        
        <RichText
          tagName="p"
          value={content}
          onChange={(value) => setAttributes({ content: value })}
          placeholder="Enter content..."
        />
      </div>
    </>
  );
}
```

### Corresponding block.json

```json
{
  "apiVersion": 3,
  "name": "my-plugin/configured-block",
  "title": "Configured Block",
  "category": "text",
  "icon": "admin-settings",
  "description": "A block with comprehensive configuration options",
  "keywords": ["settings", "configuration", "custom"],
  "attributes": {
    "title": {
      "type": "string",
      "default": "Sample Title"
    },
    "content": {
      "type": "string",
      "default": "Sample content..."
    },
    "showIcon": {
      "type": "boolean",
      "default": true
    },
    "alignment": {
      "type": "string",
      "default": "left"
    },
    "fontSize": {
      "type": "number",
      "default": 16
    },
    "textColor": {
      "type": "string"
    },
    "backgroundColor": {
      "type": "string"
    }
  },
  "supports": {
    "html": false,
    "spacing": {
      "padding": true,
      "margin": true
    }
  },
  "editorScript": "file:./index.js",
  "editorStyle": "file:./index.css",
  "style": "file:./style-index.css"
}
```

## Conclusion

This guide provides a comprehensive foundation for implementing Gutenberg block configurations. The modern approach emphasizes:

1. **Declarative configuration** through block.json
2. **Organized inspector controls** with proper grouping
3. **Responsive and accessible** user interfaces
4. **Performance-conscious** implementation patterns
5. **Maintainable and scalable** code structure

For implementation in the BlazeCommerce plugin, these patterns can be adapted to create sophisticated block configurations that integrate seamlessly with WordPress's native block editor experience.

## References

- [WordPress Block Editor Handbook](https://developer.wordpress.org/block-editor/)
- [Block API Reference](https://developer.wordpress.org/block-editor/reference-guides/block-api/)
- [Inspector Controls Documentation](https://developer.wordpress.org/block-editor/how-to-guides/block-tutorial/block-controls-toolbar-and-sidebar/)
- [WordPress Components Library](https://developer.wordpress.org/block-editor/reference-guides/components/)
