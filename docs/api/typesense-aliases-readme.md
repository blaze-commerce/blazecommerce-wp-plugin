---
title: "Typesense Collection Aliasing Implementation"
description: "Implementation guide for Typesense collection aliasing using blue-green deployment pattern to eliminate search downtime during syncs"
category: "api"
version: "1.0.0"
last_updated: "2025-01-09"
author: "Blaze Commerce Team"
tags: ["typesense", "aliasing", "blue-green", "deployment", "zero-downtime", "collections"]
related_docs: ["../features/country-specific-images.md", "../setup/installation-and-configuration.md"]
---

# Typesense Collection Aliasing Implementation

This document describes the implementation of Typesense collection aliasing in the Blaze Commerce WooCommerce plugin to eliminate search downtime during full product syncs using a blue-green deployment pattern.

## Overview

The new aliasing system replaces the current single-collection approach with a blue-green deployment system where:

- **Aliases** provide stable collection names for search operations
- **Blue-Green Collections** store the actual data using `_a` and `_b` suffixes
- **Zero downtime** during sync operations by switching aliases atomically
- **API Key Compatibility** works with scoped API keys that restrict collection names

## Key Features

### 1. Blue-Green Deployment Pattern

- **Alias Format**: `{type}-{site_url}` (e.g., `product-mystore-com`)
- **Collection Format**: `{type}-{site_url}-a` and `{type}-{site_url}-b` (e.g., `product-mystore-com-a`, `product-mystore-com-b`)
- **Environment Separation**: Automatic separation by site URL (dev/staging/production)
- **Rotation Logic**: Alternates between `-a` and `-b` collections for each sync

### 2. Stateless Implementation

- No local database tracking required
- State determined by querying Typesense API
- Automatic detection of active/inactive collections

### 3. Blue-Green Collection Management

- Maintains exactly two collections: `-a` and `-b`
- Active collection serves search traffic via alias
- Inactive collection is used for syncing new data
- Atomic alias switching ensures zero downtime

## Implementation Details

### Core Classes

#### 1. CollectionAliasManager (`app/Collections/CollectionAliasManager.php`)

Main class handling all alias operations:

- `get_alias_name($type)` - Generate alias name
- `get_collection_name($type, $suffix)` - Generate collection name with `-a` or `-b` suffix
- `get_current_collection($type)` - Get current live collection
- `get_inactive_collection($type)` - Get inactive collection for syncing
- `get_next_collection_suffix($type)` - Determine which suffix to use for next sync
- `update_alias($type, $target)` - Update alias pointer

#### 2. Enhanced BaseCollection (`app/Collections/BaseCollection.php`)

Extended with blue-green deployment support:

- `initialize_with_alias($schema)` - Create new collection using inactive suffix
- `complete_sync($new_collection)` - Update alias to point to new collection
- `get_inactive_collection_name()` - Get inactive collection name for syncing
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

## API Key Generation

The aliasing system requires specific API keys that have access to both the `_a` and `_b` collections as well as the aliases. You need to generate two types of API keys:

### 1. Private Key (Admin Operations)

The private key is used for administrative operations including creating, updating, and deleting collections and aliases. It requires full access to all collection variants.

**Example JSON for Private Key Generation:**

```json
{
  "description": "cart.mystore-com-private key",
  "actions": ["*"],
  "collections": [
    "site_info-cart.mystore-com-a",
    "menu-cart.mystore-com-a",
    "page-cart.mystore-com-a",
    "post-cart.mystore-com-a",
    "product-cart.mystore-com-a",
    "taxonomy-cart.mystore-com-a",
    "category-cart.mystore-com-a",
    "brand-cart.mystore-com-a",
    "tag-cart.mystore-com-a",
    "product-addon-option-cart.mystore-com-a",
    "navigation-cart.mystore-com-a",
    "site_info-cart.mystore-com-b",
    "menu-cart.mystore-com-b",
    "page-cart.mystore-com-b",
    "post-cart.mystore-com-b",
    "product-cart.mystore-com-b",
    "taxonomy-cart.mystore-com-b",
    "category-cart.mystore-com-b",
    "brand-cart.mystore-com-b",
    "tag-cart.mystore-com-b",
    "product-addon-option-cart.mystore-com-b",
    "navigation-cart.mystore-com-b"
  ]
}
```

### 2. Public Key (Search Operations)

The public key is used for frontend search operations and only needs read access to the aliases (not the individual `_a` and `_b` collections).

**Example JSON for Public Key Generation:**

```json
{
  "description": "cart.mystore-com-public key",
  "actions": ["documents:search"],
  "collections": [
    "site_info-cart.mystore-com",
    "menu-cart.mystore-com",
    "page-cart.mystore-com",
    "post-cart.mystore-com",
    "product-cart.mystore-com",
    "taxonomy-cart.mystore-com",
    "category-cart.mystore-com",
    "brand-cart.mystore-com",
    "tag-cart.mystore-com",
    "product-addon-option-cart.mystore-com",
    "navigation-cart.mystore-com"
  ]
}
```

### Key Generation Steps

1. **Replace the site URL**: Change `cart.mystore-com` to your actual normalized site URL (dots replaced with hyphens)
2. **Generate Private Key**: Use the private key JSON via Typesense API or dashboard
3. **Generate Public Key**: Use the public key JSON via Typesense API or dashboard
4. **Configure Plugin**: Add both keys to your WordPress configuration

### Important Notes

- **Site URL Format**: Use the same normalized format as your collections (e.g., `cart.mystore-com` for `cart.mystore.com`)
- **Collection Coverage**: Ensure all collection types your site uses are included in both keys
- **Security**: Keep the private key secure and only use it for admin operations
- **Frontend Usage**: Only use the public key for frontend search operations

## Sync Operation Flow (Blue-Green Deployment)

### 1. Initialize Sync

```
1. Check if aliases are enabled
2. Determine inactive collection (opposite of current active)
3. Delete inactive collection if it exists (cleanup)
4. Create new collection with inactive suffix (-a or -b)
5. Store collection name for later use
```

### 2. Data Import

```
1. Import data to inactive collection
2. All sync operations target the inactive collection
3. Search continues using existing alias (active collection)
4. Zero downtime during entire sync process
```

### 3. Complete Sync (Atomic Switch)

```
1. Update alias to point to newly synced collection
2. Previous active collection becomes inactive
3. Ready for next sync cycle
4. No cleanup needed - maintains both collections
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
4. Verify both `_a` and `_b` collections exist
5. Run another sync to test rotation

## Monitoring

### Collection Status

```bash
wp bc-sync alias --status
```

Shows:

- Current alias targets
- Active and inactive collections for each type
- Blue-green deployment status
- Collection rotation state

### Typesense Dashboard

Monitor collections and aliases directly in Typesense dashboard:

- Collection sizes and document counts
- Alias mappings
- Blue-green collection pairs (`-a` and `-b`)

## Troubleshooting

### Common Issues

#### 1. Interrupted Syncs

**Symptoms**: Inactive collection exists but sync failed
**Solution**: Simply run sync again - it will clean up and recreate the inactive collection

```bash
wp bc-sync product --all
```

#### 2. Missing Aliases

**Symptoms**: Search not working, no alias found
**Solution**:

```bash
wp bc-sync alias --force-alias=product
```

#### 3. Both Collections Missing

**Symptoms**: Neither `-a` nor `-b` collection exists
**Solution**: Run new sync to create the blue-green collection pair

#### 4. Legacy Collections

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

- **Permanent storage**: 2x collection size (maintains both `-a` and `-b`)
- **API key compatibility**: Works with scoped API keys
- **Network overhead**: Minimal alias operations
- **Sync efficiency**: No cleanup operations needed

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
