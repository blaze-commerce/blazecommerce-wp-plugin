---
title: "Data Validation Feature"
description: "Comprehensive guide to the Data Validation feature for ensuring data integrity and security"
category: "features"
version: "1.0.0"
last_updated: "2025-07-15"
author: "Blaze Commerce Team"
tags: ["validation", "sanitization", "security", "data integrity", "typesense"]
related_docs: ["../api/data-validation-api.md", "../setup/installation-and-configuration.md"]
---

# Data Validation Feature

## Overview

The Data Validation feature provides comprehensive data validation and sanitization capabilities for the BlazeCommerce WordPress plugin. This feature ensures data integrity and security by validating and sanitizing data before it's sent to Typesense or processed within WordPress.

## Key Benefits

- **Enhanced Security**: Prevents malicious data from being processed or stored
- **Data Integrity**: Ensures data consistency and quality across all collections
- **Error Prevention**: Catches invalid data before it causes issues
- **Debugging Support**: Provides detailed logging for troubleshooting
- **Performance Optimization**: Reduces errors and failed requests to Typesense

## Features

### Automatic Data Validation

The feature automatically validates data for:

- **Product Data**: Names, descriptions, prices, URLs, and metadata
- **Taxonomy Data**: Category names, descriptions, and permalinks
- **Page Data**: Titles, content, and URLs
- **API Requests**: Query parameters and request arguments

### Sanitization Methods

- **Text Sanitization**: Removes harmful characters from text fields
- **HTML Sanitization**: Allows safe HTML while removing dangerous tags
- **Numeric Validation**: Ensures numeric values are within valid ranges
- **URL Validation**: Validates and sanitizes URLs
- **Integer Validation**: Validates integer values with min/max constraints

### TypesenseClient Integration

The feature integrates with the TypesenseClient to validate:

- API keys and connection parameters
- Host URLs and store IDs
- Request data before sending to Typesense

## Configuration

### Enabling Data Validation

1. Navigate to **WordPress Admin > BlazeCommerce > Settings**
2. Go to the **General** tab
3. Check **Enable Data Validation**
4. Save settings

### Debug Logging

To enable debug logging for validation operations:

1. Navigate to **WordPress Admin > BlazeCommerce > Settings**
2. Go to the **General** tab
3. Check **Enable Validation Debug Logging**
4. Save settings

Debug logs will be written to the WooCommerce logs and can be viewed at:
**WooCommerce > Status > Logs**

## Usage Examples

### Basic Usage

The Data Validation feature works automatically once enabled. No additional code is required for basic functionality.

### Custom Validation

You can add custom validation logic using the provided filters:

```php
// Add custom product validation
add_filter('blazecommerce/collection/product/typesense_data', 'my_custom_product_validation', 15, 3);
function my_custom_product_validation($data, $product_id, $product) {
    // Custom validation logic
    if (isset($data['custom_field'])) {
        $data['custom_field'] = sanitize_text_field($data['custom_field']);
    }
    return $data;
}
```

### Manual Validation

You can also use the validation methods directly in your code:

```php
$validator = \BlazeWooless\Features\DataValidator::get_instance();

// Validate text
$safe_text = $validator->sanitize_text($user_input);

// Validate numeric value
$price = $validator->validate_numeric($input_price, 0.01);

// Validate URL
$safe_url = $validator->validate_url($input_url);
```

## Validation Rules

### Text Fields

- Removes HTML tags and special characters
- Trims whitespace
- Prevents XSS attacks

### HTML Fields

- Allows safe HTML tags (p, br, strong, em, etc.)
- Removes dangerous tags (script, iframe, object, etc.)
- Sanitizes attributes

### Numeric Fields

- Ensures values are numeric
- Applies minimum and maximum constraints
- Converts to appropriate data type (int/float)

### URL Fields

- Validates URL format
- Ensures proper protocol (http/https)
- Removes dangerous parameters

## Performance Impact

The Data Validation feature is designed to have minimal performance impact:

- **Lightweight Processing**: Validation operations are optimized for speed
- **Conditional Execution**: Only runs when validation is enabled
- **Efficient Caching**: Validation results are cached where appropriate
- **Selective Validation**: Only validates data that needs validation

## Troubleshooting

### Common Issues

#### Validation Not Working

1. Check that **Enable Data Validation** is checked in settings
2. Verify that the DataValidator class is properly loaded
3. Check for PHP errors in the error log

#### Debug Logging Not Appearing

1. Ensure **Enable Validation Debug Logging** is enabled
2. Check WooCommerce logs at **WooCommerce > Status > Logs**
3. Look for logs with source "wooless-data-validation"

#### Performance Issues

1. Disable debug logging in production
2. Check for custom validation filters that may be slow
3. Monitor server resources during validation

### Debug Information

To get debug information about the validation system:

```php
$validator = \BlazeWooless\Features\DataValidator::get_instance();
$is_enabled = $validator->is_validation_enabled();
$is_debug = $validator->is_debug_enabled();

error_log("Validation enabled: " . ($is_enabled ? 'Yes' : 'No'));
error_log("Debug enabled: " . ($is_debug ? 'Yes' : 'No'));
```

## Best Practices

### Development

- Always enable debug logging during development
- Test validation with various data types and edge cases
- Use custom validation filters for specific business logic

### Production

- Disable debug logging in production for performance
- Monitor validation errors and adjust rules as needed
- Regularly review validation logs for security issues

### Security

- Never disable validation for user-generated content
- Regularly update validation rules based on security best practices
- Monitor for validation bypass attempts

## Integration with Other Features

### Typesense Sync

The Data Validation feature integrates seamlessly with Typesense sync operations:

- Validates data before sending to Typesense
- Prevents sync errors due to invalid data
- Improves search quality by ensuring clean data

### WooCommerce Integration

- Validates product data during save operations
- Sanitizes customer input in forms
- Ensures data consistency across WooCommerce and Typesense

### Third-Party Plugins

The validation system works with third-party plugins by:

- Providing filters for custom validation
- Maintaining compatibility with existing hooks
- Allowing selective validation for specific data types

## Related Documentation

- [Data Validation API Reference](../api/data-validation-api.md)
- [Installation and Configuration](../setup/installation-and-configuration.md)
- [Typesense Integration](../api/typesense-aliases-readme.md)
- [Troubleshooting Guide](../troubleshooting/common-issues.md)
