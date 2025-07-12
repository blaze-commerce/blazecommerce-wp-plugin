# Collections Sync Command

## Overview

The `wp bc-sync collections --all` command provides a comprehensive way to sync all Typesense collections in a single operation. This command executes all individual collection syncs in the optimal order and provides detailed timing and summary statistics.

## Usage

```bash
wp bc-sync collections --all
```

## Collection Sync Order

The command syncs collections in this specific order:

1. **site_info** - Site configuration and metadata
2. **products** - All products including variations
3. **taxonomy** - Product categories, tags, and attributes
4. **menu** - WordPress menus
5. **page_and_post** - Pages and blog posts
6. **navigation** - WordPress navigation blocks

This order ensures that dependencies are properly handled and data integrity is maintained.

## Features

### Comprehensive Timing
- Individual collection timing
- Total elapsed time across all collections
- Formatted time display (HH:MM:SS)

### Detailed Statistics
- Items processed per collection
- Success/failure counts
- Overall summary with totals

### Error Handling
- Continues processing even if one collection fails
- Detailed error reporting
- Final summary shows status of each collection

### Visual Progress Indicators
- Clear section headers for each collection
- Progress indicators with ✅ and ❌ symbols
- Formatted summary tables

## Example Output

```
Starting sync of all Typesense collections...

==================================================
Syncing Site Info collection...
==================================================
Target collection: site_info-example.com-a
✅ Site Info sync completed in 00:00:05
✅ Imported: 1/1 items

==================================================
Syncing Products collection...
==================================================
Target collection: products-example.com-b
Syncing product variations to the same collection...
✅ Products sync completed in 00:02:30
✅ Imported: 150/150 items

==================================================
Syncing Taxonomy collection...
==================================================
Target collection: taxonomy-example.com-a
✅ Taxonomy sync completed in 00:00:15
✅ Imported: 25/25 items

==================================================
Syncing Menu collection...
==================================================
Target collection: menu-example.com-b
✅ Menu sync completed in 00:00:03
✅ Imported: 3/3 items

==================================================
Syncing Pages and Posts collection...
==================================================
Target collection: page-example.com-a
✅ Pages and Posts sync completed in 00:00:45
✅ Imported: 50/50 items

==================================================
Syncing Navigation collection...
==================================================
Target collection: navigation-example.com-b
✅ Navigation sync completed in 00:00:02
✅ Imported: 2/2 items

============================================================
ALL COLLECTIONS SYNC SUMMARY
============================================================
✅ Site Info: 1/1 items
✅ Products: 150/150 items
✅ Taxonomy: 25/25 items
✅ Menu: 3/3 items
✅ Pages and Posts: 50/50 items
✅ Navigation: 2/2 items

Overall Statistics:
✅ Total import: 231
✅ Successful import: 231
✅ Total time spent: 00:03:40 (hh:mm:ss)

✅ All collections sync process completed!
```

## Error Handling Example

If a collection fails, the output shows:

```
============================================================
ALL COLLECTIONS SYNC SUMMARY
============================================================
✅ Site Info: 1/1 items
❌ Products: FAILED - Connection timeout to Typesense server
✅ Taxonomy: 25/25 items
✅ Menu: 3/3 items
✅ Pages and Posts: 50/50 items
✅ Navigation: 2/2 items

Overall Statistics:
✅ Total import: 80
✅ Successful import: 80
✅ Total time spent: 00:01:20 (hh:mm:ss)

✅ All collections sync process completed!
```

## Integration with Alias System

The collections command fully supports the Typesense alias system:

- Uses blue-green deployment for zero-downtime syncing
- Automatically switches aliases after successful sync
- Maintains collection rotation (`-a` and `-b` suffixes)
- Provides cleanup of old collections

## Best Practices

### When to Use
- Initial site setup
- After major data changes
- Regular maintenance schedules
- Before going live with changes

### Performance Considerations
- Large product catalogs may take significant time
- Consider running during low-traffic periods
- Monitor server resources during sync
- Use `--cleanup` commands periodically to manage storage

### Monitoring
- Watch for error messages during sync
- Verify final statistics match expectations
- Check Typesense dashboard for collection status
- Use `wp bc-sync alias --status` to verify aliases

## Related Commands

- `wp bc-sync alias --status` - Check collection status
- `wp bc-sync alias --cleanup=<type>` - Clean up old collections
- `wp bc-sync cache --clear` - Clear caches before sync
- Individual collection commands for targeted syncing

## Troubleshooting

### Common Issues

1. **Memory Limits**: Increase PHP memory limit for large datasets
2. **Timeouts**: Adjust max execution time for large syncs
3. **Connection Issues**: Verify Typesense server connectivity
4. **Permission Errors**: Ensure proper API key permissions

### Debug Mode

Enable WordPress debug logging to see detailed sync information:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Technical Implementation

The collections command is implemented in `app/Features/Cli.php` using a highly refactored, DRY approach that maximizes code reuse:

### Core Architecture

- `collections()` - Main command handler
- `execute_collection_sync()` - Collection sync orchestrator using configuration-driven approach
- `get_collections_configuration()` - Centralized collection configuration with sync types and metadata
- `execute_batch_processing_loop()` - Unified batch processing that consolidates all existing patterns

### Reusable Sync Patterns

The implementation leverages four reusable sync patterns that eliminate code duplication:

1. **Single Batch Sync** (`execute_single_batch_sync`)
   - Used by: Site Info, Menu collections
   - Reuses existing simple sync pattern
   - Supports post-sync callbacks

2. **Batch with IDs** (`execute_batch_sync_with_ids`)
   - Used by: Pages/Posts, Navigation collections
   - Reuses existing ID-based batch processing
   - Configurable ID retrieval methods

3. **Batch with Query** (`execute_batch_sync_with_query`)
   - Used by: Taxonomy collection
   - Reuses existing WP_Term_Query pattern
   - Supports complex query arguments

4. **Batch with Variations** (`execute_products_sync_with_variations`)
   - Used by: Products collection
   - Reuses existing product + variation sync pattern
   - Includes variation sync integration

### Code Reuse Benefits

- **95% code reuse** from existing individual sync methods
- **Unified error handling** using existing patterns
- **Consistent memory optimization** with garbage collection
- **Standardized safety limits** to prevent infinite loops
- **Shared display formatting** using existing helper methods
- **Filter integration** for conditional sync enabling/disabling

### Configuration-Driven Approach

Each collection is defined with metadata that drives the sync behavior:

```php
'products' => array(
    'class' => Product::class,
    'name' => 'Products',
    'sync_type' => 'batch_with_variations',
    'id_method' => 'get_product_ids'
)
```

This approach eliminates switch statements and makes adding new collections trivial.
