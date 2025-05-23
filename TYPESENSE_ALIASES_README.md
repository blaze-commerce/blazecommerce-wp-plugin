# Typesense Collection Aliasing Implementation

This document describes the implementation of Typesense collection aliasing in the Blaze Commerce WooCommerce plugin to eliminate search downtime during full product syncs.

## Overview

The new aliasing system replaces the current single-collection approach with a dual-collection system where:
- **Aliases** provide stable collection names for search operations
- **Timestamped collections** store the actual data
- **Zero downtime** during sync operations by switching aliases atomically

## Key Features

### 1. URL-Based Naming
- **Alias Format**: `{type}_{site_url}` (e.g., `product_mystore_com`)
- **Collection Format**: `{type}_{site_url}_{timestamp}` (e.g., `product_mystore_com_1714587349`)
- **Environment Separation**: Automatic separation by site URL (dev/staging/production)

### 2. Stateless Implementation
- No local database tracking required
- State determined by querying Typesense API
- Automatic detection of collection states

### 3. Intelligent Cleanup
- Keeps current live collection + 1 previous collection
- Automatic cleanup of interrupted sync collections
- Manual cleanup commands available

## Implementation Details

### Core Classes

#### 1. CollectionAliasManager (`app/Collections/CollectionAliasManager.php`)
Main class handling all alias operations:
- `get_alias_name($type)` - Generate alias name
- `get_collection_name($type, $timestamp)` - Generate collection name
- `get_current_collection($type)` - Get current live collection
- `update_alias($type, $target)` - Update alias pointer
- `cleanup_old_collections($type, $keep_count)` - Clean up old collections

#### 2. Enhanced BaseCollection (`app/Collections/BaseCollection.php`)
Extended with alias support:
- `initialize_with_alias($schema)` - Create new timestamped collection
- `complete_sync($new_collection)` - Update alias and cleanup
- `get_target_collection_name()` - Get correct collection for operations

#### 3. Enhanced TypesenseClient (`app/TypesenseClient.php`)
Added site URL functionality:
- `get_site_url()` - Get normalized site URL
- `normalize_site_url($url)` - Normalize URL for collection naming

### Updated Collection Classes

#### Product Collection (`app/Collections/Product.php`)
- `initialize()` - Enhanced with alias support
- `complete_product_sync()` - Complete sync and update alias

#### Taxonomy Collection (`app/Collections/Taxonomy.php`)
- `initialize()` - Enhanced with alias support
- `complete_taxonomy_sync()` - Complete sync and update alias

### CLI Commands

#### Enhanced Sync Commands
All sync commands now support the new alias system:
```bash
wp bc-sync product --all    # Uses aliases if enabled
wp bc-sync taxonomy --all   # Uses aliases if enabled
```

#### New Alias Management Commands
```bash
wp bc-sync alias --list                    # List all aliases
wp bc-sync alias --status                  # Show collection status
wp bc-sync alias --cleanup=product         # Clean up old collections
wp bc-sync alias --force-alias=product     # Force create alias
```

## Configuration

### Enable/Disable Aliases
The system can be controlled via WordPress filters:

```php
// Enable aliases (default)
add_filter('blazecommerce/use_collection_aliases', '__return_true');

// Disable aliases (fallback to legacy behavior)
add_filter('blazecommerce/use_collection_aliases', '__return_false');
```

### Backward Compatibility
- Legacy collections continue to work
- Gradual migration supported
- No breaking changes to existing functionality

## Sync Operation Flow

### 1. Initialize Sync
```
1. Check if aliases are enabled
2. Clean up any newer collections (interrupted syncs)
3. Create new timestamped collection
4. Store collection name for later use
```

### 2. Data Import
```
1. Import data to new timestamped collection
2. All operations target the new collection
3. Search continues using existing alias
```

### 3. Complete Sync
```
1. Update alias to point to new collection
2. Clean up old collections (keep 1 previous)
3. Return success status
```

## Testing

### Basic Functionality Test
```bash
php test-alias-implementation.php
```

### CLI Testing
```bash
# Check alias status
wp bc-sync alias --status

# Test product sync with aliases
wp bc-sync product --all

# Verify alias creation
wp bc-sync alias --list
```

### Manual Testing
1. Run a product sync
2. Verify search continues during sync
3. Check alias points to new collection
4. Verify old collections are cleaned up

## Monitoring

### Collection Status
```bash
wp bc-sync alias --status
```
Shows:
- Current alias targets
- All collections for each type
- Collections that can be cleaned up
- Newer collections (interrupted syncs)

### Typesense Dashboard
Monitor collections and aliases directly in Typesense dashboard:
- Collection sizes and document counts
- Alias mappings
- Collection timestamps

## Troubleshooting

### Common Issues

#### 1. Interrupted Syncs
**Symptoms**: Multiple newer collections exist
**Solution**: 
```bash
wp bc-sync alias --cleanup=product
```

#### 2. Missing Aliases
**Symptoms**: Search not working, no alias found
**Solution**:
```bash
wp bc-sync alias --force-alias=product
```

#### 3. Legacy Collections
**Symptoms**: Old naming format still in use
**Solution**: Run new sync to create alias-based collections

### Debug Information
Enable WordPress debug logging to see detailed sync information:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Migration Guide

### From Legacy to Alias System

1. **Enable aliases** (default behavior)
2. **Run sync commands** - will automatically create new collections
3. **Verify aliases** using `wp bc-sync alias --status`
4. **Clean up old collections** manually if needed

### Rollback to Legacy System
If needed, disable aliases:
```php
add_filter('blazecommerce/use_collection_aliases', '__return_false');
```

## Performance Considerations

### Benefits
- **Zero downtime** during syncs
- **Atomic alias switching** (milliseconds)
- **Parallel collection creation** possible

### Resource Usage
- **Temporary storage**: 2x collection size during sync
- **Cleanup frequency**: Configurable retention policy
- **Network overhead**: Minimal alias operations

## Security Considerations

- **URL normalization**: Prevents injection attacks
- **Collection isolation**: Environment separation by URL
- **Access control**: Inherits Typesense permissions

## Future Enhancements

### Planned Features
1. **Configurable retention policy**
2. **Automatic health checks**
3. **Sync progress monitoring**
4. **Collection size optimization**

### Extension Points
- Custom collection naming strategies
- Advanced cleanup policies
- Integration with monitoring systems
- Custom sync completion hooks
