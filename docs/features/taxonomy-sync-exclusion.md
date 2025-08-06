# Taxonomy Sync Exclusion Feature

## Overview

The Taxonomy Sync Exclusion feature allows administrators to exclude specific WooCommerce product categories from being synced to the Typesense search index. This provides granular control over which categories appear in search results and can help optimize search performance by excluding irrelevant or internal categories.

## Features

- **Custom Meta Field**: Adds a checkbox to the WooCommerce category edit page
- **Batch Sync Filtering**: Excludes marked categories during bulk taxonomy sync operations
- **Individual Term Handling**: Properly handles exclusion when individual categories are edited
- **Automatic Cleanup**: Removes excluded categories from Typesense when they are marked for exclusion
- **Re-inclusion Support**: Automatically syncs categories back to Typesense when exclusion is removed

## Implementation Details

### Files Modified/Created

1. **`app/Features/TaxonomySyncExclusion.php`** - Main feature class
2. **`app/BlazeWooless.php`** - Added feature registration
3. **`app/Collections/Taxonomy.php`** - Added exclusion logic to sync process
4. **`test/test-taxonomy-sync-exclusion.php`** - Test file for validation

### Database Schema

The feature uses WordPress term meta to store exclusion settings:

- **Meta Key**: `blaze_exclude_from_typesense_sync`
- **Meta Value**: `'1'` (excluded) or `'0'` (included)
- **Storage**: WordPress `termmeta` table

### Hooks and Filters

#### Actions
- `product_cat_edit_form_fields` - Adds the exclusion checkbox to category edit page
- `edited_product_cat` - Saves the exclusion setting when category is updated

#### Filters
- `blazecommerce_taxonomy_sync_terms` - Filters terms before batch data preparation

## User Interface

### Category Edit Page

The feature adds a new field to the WooCommerce product category edit page:

```
Typesense Sync
☐ Exclude from Typesense sync
Check this box to exclude this category from being synced to Typesense search index.
```

### Location
The checkbox appears in the category edit form alongside other category settings.

## Usage Instructions

### Excluding a Category

1. Navigate to **Products > Categories** in WordPress admin
2. Click **Edit** on the category you want to exclude
3. Scroll down to find the "Typesense Sync" section
4. Check the box labeled "Exclude from Typesense sync"
5. Click **Update** to save the changes

### Including a Previously Excluded Category

1. Navigate to the excluded category's edit page
2. Uncheck the "Exclude from Typesense sync" checkbox
3. Click **Update** to save the changes
4. The category will be automatically synced back to Typesense

## Technical Implementation

### Sync Process Flow

1. **Bulk Sync**: During taxonomy sync operations, the `blazecommerce_taxonomy_sync_terms` filter is applied to remove excluded terms before batch processing
2. **Individual Updates**: When a category is edited, the system checks the exclusion status and either removes or updates the term in Typesense
3. **Cleanup**: Excluded terms are automatically removed from the Typesense index
4. **Re-inclusion**: Previously excluded terms are automatically re-synced when exclusion is removed

### Code Examples

#### Filtering Terms During Sync
```php
// Applied in app/Collections/Taxonomy.php
$filtered_terms = apply_filters( 'blazecommerce_taxonomy_sync_terms', $term_query->terms );
$taxonomy_datas = $this->prepare_batch_data( $filtered_terms );
```

#### Exclusion Check
```php
// Check if term is excluded
$exclude_from_sync = get_term_meta( $term_id, 'blaze_exclude_from_typesense_sync', true );
if ( $exclude_from_sync === '1' ) {
    // Handle exclusion logic
}
```

## Performance Considerations

- **Reduced Index Size**: Excluding unnecessary categories reduces the Typesense index size
- **Faster Sync**: Fewer terms to process during bulk sync operations
- **Optimized Search**: Cleaner search results without irrelevant categories

## Compatibility

- **WordPress**: 5.0+
- **WooCommerce**: 3.0+
- **PHP**: 7.4+
- **Typesense**: Compatible with existing collection structure

## Testing

### Manual Testing

1. Create a test product category
2. Mark it for exclusion using the checkbox
3. Run a taxonomy sync and verify the category is not included
4. Uncheck the exclusion and verify the category is re-synced

### Automated Testing

Run the test file to validate functionality:
```bash
# Access via WordPress admin
Tools > Test Taxonomy Sync Exclusion
```

## Troubleshooting

### Common Issues

1. **Checkbox not appearing**: Ensure the feature is properly registered in `app/BlazeWooless.php`
2. **Categories still syncing**: Check that the filter is properly applied in the taxonomy collection
3. **Categories not removed**: Verify Typesense connection and permissions

### Debug Information

Enable WordPress debug mode to see detailed logging:
```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
```

## Future Enhancements

- **Bulk Actions**: Add bulk exclusion/inclusion options to the categories list page
- **Taxonomy Support**: Extend to other taxonomies beyond product categories
- **Advanced Filtering**: Add more granular exclusion criteria
- **Analytics**: Track which categories are excluded and their impact on search

## Related Features

- [Country Specific Images](country-specific-images.md)
- [Category Banner](../app/Features/CategoryBanner.php)
- [Typesense Collections](../app/Collections/Taxonomy.php)
