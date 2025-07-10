---
title: "Export/Import Settings Feature"
description: "Backup and restore all Blaze Commerce plugin settings in JSON format for migration and configuration management"
category: "features"
version: "1.0.0"
last_updated: "2025-01-09"
author: "Blaze Commerce Team"
tags: ["export", "import", "settings", "backup", "migration", "configuration"]
related_docs: ["country-specific-images.md", "../setup/installation-and-configuration.md"]
---

# Export/Import Settings Feature

## Overview

The Export/Import Settings feature allows you to backup and restore all Blaze Commerce plugin settings in JSON format. This feature is useful for:

- Creating backups before making configuration changes
- Migrating settings between different WordPress installations
- Sharing configuration setups between team members
- Restoring settings after plugin reinstallation

## Location

The Export/Import feature is available in the WordPress admin under:
**Blaze Commerce > Setting > Export/Import** tab

This tab appears after the "Synonym Settings" tab as requested.

## Features

### Export Settings
- Downloads all plugin settings as a JSON file
- Includes metadata (plugin version, export date, site URL, WordPress version)
- File naming format: `blaze-commerce-settings-YYYY-MM-DD-HH-MM-SS.json`

### Import Settings
- Upload a JSON file to restore settings
- Validates file format and content
- Shows success/error messages
- Provides detailed feedback on import results

## Included Settings

The export/import feature includes the following option keys:

### Main Settings
- `wooless_general_settings_options` - General plugin settings
- `wooless_regional_settings_options` - Regional/currency settings
- `wooless_product_filters_settings_options` - Product filter configurations
- `wooless_settings_product_page_options` - Product page settings
- `wooless_settings_category_page_options` - Category page settings
- `wooless_header_settings_options` - Header settings
- `wooless_footer_settings_options` - Footer settings
- `wooless_homepage_settings_options` - Homepage settings
- `wooless_synonym_settings_options` - Synonym settings
- `wooless_synonyms` - Synonym data

### Additional Content Settings
- `blaze_wooless_product_filters_content` - Product filter content
- `blaze_wooless_homepage_layout` - Homepage layout configuration
- `free_shipping_threshold` - Free shipping threshold settings

### Extension Settings
- `wooless_custom_jwt_secret_key` - JWT authentication key
- `judgeme_widget_html_miracle` - JudgeMe widget HTML
- `judgeme_widget_settings` - JudgeMe widget settings
- `judgeme_shop_token` - JudgeMe shop token
- `blaze_commerce_judgeme_product_reviews` - JudgeMe review data
- `blaze_commerce_yotpo_product_reviews` - Yotpo review data
- `yotpo_settings` - Yotpo configuration
- `nipv_setting_option` - Product variations table settings
- `wcact_settings` - WooCommerce auto category thumbnails

## Usage Instructions

### Exporting Settings

1. Navigate to **Blaze Commerce > Setting**
2. Click on the **Export/Import** tab
3. In the Export section, click **Export Settings**
4. The browser will download a JSON file with all your settings

### Importing Settings

1. Navigate to **Blaze Commerce > Setting**
2. Click on the **Export/Import** tab
3. In the Import section:
   - Click **Choose File** and select your JSON export file
   - Click **Import Settings**
4. Review the success/error messages
5. Click **Save Settings** to apply the changes

## Security Features

- CSRF protection with WordPress nonces
- User capability checks (requires `manage_options`)
- File type validation (only JSON files accepted)
- JSON format validation
- Sanitized error messages

## Technical Implementation

### Files Created
- `app/Settings/ExportImportSettings.php` - Main settings class
- `views/export-import-settings.php` - Admin interface template

### Files Modified
- `app/BlazeWooless.php` - Added ExportImportSettings to registered settings

### AJAX Endpoints
- `wp_ajax_blaze_export_settings` - Handles export requests
- Form submission handles import via WordPress settings API

## Troubleshooting

### Common Issues

1. **Export not working**
   - Check browser console for JavaScript errors
   - Verify user has `manage_options` capability

2. **Import fails**
   - Ensure file is valid JSON format
   - Check file was exported from Blaze Commerce
   - Verify file size is reasonable (not corrupted)

3. **Settings not applying**
   - Click "Save Settings" after import
   - Check for error messages in admin notices
   - Verify imported data matches expected format

### Error Messages

- "Security check failed" - User lacks permissions or nonce expired
- "Please upload a JSON file" - Wrong file type selected
- "Invalid JSON file format" - File is corrupted or not valid JSON
- "No settings were imported" - File doesn't contain recognized settings

## Best Practices

1. **Always export before importing** to create a backup
2. **Test imports on staging sites** before production
3. **Review settings after import** to ensure everything is correct
4. **Keep export files secure** as they may contain sensitive configuration data
5. **Use descriptive filenames** when saving exports for future reference

## Future Enhancements

Potential improvements for future versions:
- Selective export/import (choose specific settings)
- Import preview (show what will be changed)
- Automatic backups before import
- Settings comparison tool
- Bulk operations for multiple sites
