---
title: "Data Validation API Reference"
description: "Complete API reference for the Data Validation feature with methods, parameters, and examples"
category: "api"
version: "1.0.0"
last_updated: "2025-07-15"
author: "Blaze Commerce Team"
tags: ["api", "validation", "sanitization", "security", "data integrity"]
related_docs: ["../features/data-validation-feature.md"]
---

# Data Validation API Reference

## Overview

The Data Validation API provides a comprehensive set of methods for validating and sanitizing data throughout the BlazeCommerce WordPress plugin. This API helps ensure data integrity and security before sending data to Typesense or processing it within WordPress.

## Namespace

```php
namespace BlazeWooless\Features;
```

## Class

```php
DataValidator
```

## Methods

### `sanitize_text($text)`

**Description**: Sanitizes text input to remove potentially harmful content.

**Parameters**:
- `$text` (string): Text to sanitize

**Returns**: string - Sanitized text

**Example**:
```php
$validator = DataValidator::get_instance();
$safe_text = $validator->sanitize_text($user_input);
```

### `sanitize_html($html)`

**Description**: Sanitizes HTML content while allowing safe HTML tags.

**Parameters**:
- `$html` (string): HTML content to sanitize

**Returns**: string - Sanitized HTML

**Example**:
```php
$validator = DataValidator::get_instance();
$safe_html = $validator->sanitize_html($product_description);
```

### `validate_numeric($value, $min = 0, $max = null)`

**Description**: Validates and sanitizes numeric values within specified range.

**Parameters**:
- `$value` (mixed): Value to validate
- `$min` (float): Minimum allowed value (default: 0)
- `$max` (float): Maximum allowed value (default: null - no maximum)

**Returns**: float|int - Validated numeric value

**Example**:
```php
$validator = DataValidator::get_instance();
$price = $validator->validate_numeric($input_price, 0.01);
```

### `validate_integer($value, $min = 0, $max = null)`

**Description**: Validates and sanitizes integer values within specified range.

**Parameters**:
- `$value` (mixed): Value to validate
- `$min` (int): Minimum allowed value (default: 0)
- `$max` (int): Maximum allowed value (default: null - no maximum)

**Returns**: int - Validated integer value

**Example**:
```php
$validator = DataValidator::get_instance();
$quantity = $validator->validate_integer($input_quantity, 1, 100);
```

### `validate_url($url)`

**Description**: Validates and sanitizes URL.

**Parameters**:
- `$url` (string): URL to validate

**Returns**: string - Validated URL

**Example**:
```php
$validator = DataValidator::get_instance();
$safe_url = $validator->validate_url($input_url);
```

### `is_validation_enabled()`

**Description**: Checks if validation is enabled in settings.

**Parameters**: None

**Returns**: bool - Whether validation is enabled

**Example**:
```php
$validator = DataValidator::get_instance();
if ($validator->is_validation_enabled()) {
    // Perform validation
}
```

### `is_debug_enabled()`

**Description**: Checks if validation debug logging is enabled in settings.

**Parameters**: None

**Returns**: bool - Whether validation debug is enabled

**Example**:
```php
$validator = DataValidator::get_instance();
if ($validator->is_debug_enabled()) {
    $validator->log_validation('Custom validation', $context);
}
```

### `log_validation($message, $context = null)`

**Description**: Logs validation operation for debugging.

**Parameters**:
- `$message` (string): Log message
- `$context` (mixed): Additional context (default: null)

**Returns**: void

**Example**:
```php
$validator = DataValidator::get_instance();
$validator->log_validation('Product validation complete', $product_id);
```

## Filters

### `blazecommerce/collection/product/typesense_data`

**Description**: Filter to validate product data before sending to Typesense.

**Parameters**:
- `$data` (array): Product data
- `$product_id` (int): Product ID
- `$product` (object): Product object

**Example**:
```php
add_filter('blazecommerce/collection/product/typesense_data', 'my_custom_product_validation', 15, 3);
function my_custom_product_validation($data, $product_id, $product) {
    // Custom validation logic
    return $data;
}
```

### `blazecommerce/collection/taxonomy/typesense_data`

**Description**: Filter to validate taxonomy data before sending to Typesense.

**Parameters**:
- `$data` (array): Taxonomy data
- `$term_id` (int): Term ID
- `$term` (object): Term object

**Example**:
```php
add_filter('blazecommerce/collection/taxonomy/typesense_data', 'my_custom_taxonomy_validation', 15, 3);
function my_custom_taxonomy_validation($data, $term_id, $term) {
    // Custom validation logic
    return $data;
}
```

### `blazecommerce/collection/page/typesense_data`

**Description**: Filter to validate page data before sending to Typesense.

**Parameters**:
- `$data` (array): Page data
- `$page_id` (int): Page ID
- `$page` (object): Page object

**Example**:
```php
add_filter('blazecommerce/collection/page/typesense_data', 'my_custom_page_validation', 15, 3);
function my_custom_page_validation($data, $page_id, $page) {
    // Custom validation logic
    return $data;
}
```

### `blaze_wooless_api_request_args`

**Description**: Filter to validate API request arguments.

**Parameters**:
- `$args` (array): Request arguments
- `$endpoint` (string): API endpoint

**Example**:
```php
add_filter('blaze_wooless_api_request_args', 'my_custom_api_validation', 15, 2);
function my_custom_api_validation($args, $endpoint) {
    // Custom validation logic
    return $args;
}
```

## Settings

The Data Validation feature adds the following settings to the General Settings page:

- **Enable Data Validation**: Enable data validation and sanitization before sending to Typesense
- **Enable Validation Debug Logging**: Log data validation operations for debugging purposes

## Integration with TypesenseClient

The Data Validation API is integrated with the TypesenseClient class to validate connection parameters and API requests:

```php
// Example of TypesenseClient using DataValidator
$validation = $typesense_client->validate_connection_params($api_key, $host, $store_id);
if ($validation['status'] !== 'valid') {
    // Handle validation errors
}
```

## Error Handling

The Data Validation API provides detailed error messages when validation fails:

```php
// Example of validation error handling
$validator = DataValidator::get_instance();
try {
    $price = $validator->validate_numeric($input_price, 0.01);
} catch (Exception $e) {
    // Handle validation error
    error_log('Validation error: ' . $e->getMessage());
}
```

## Changelog

### Version 1.0.0 (2025-07-15)
- Initial API release
- Added validation methods for text, HTML, numeric values, integers, and URLs
- Added integration with TypesenseClient
- Added settings for enabling/disabling validation and debug logging
