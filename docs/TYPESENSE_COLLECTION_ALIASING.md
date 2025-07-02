# Typesense Collection Aliasing System

A comprehensive guide to the Typesense collection aliasing system in the Blaze Commerce WordPress plugin, designed to provide zero-downtime search during data synchronization operations.

## Table of Contents

1. [Introduction](#introduction)
2. [How It Works](#how-it-works)
3. [Configuration](#configuration)
4. [CLI Commands](#cli-commands)
5. [API Key Configuration](#api-key-configuration)
6. [Common Operations](#common-operations)
7. [Troubleshooting](#troubleshooting)
8. [Best Practices](#best-practices)
9. [Migration Guide](#migration-guide)

## Introduction

### What is the Collection Aliasing System?

The Typesense collection aliasing system is a blue-green deployment pattern that eliminates search downtime during data synchronization. Instead of updating collections in-place (which would cause search interruptions), the system maintains two versions of each collection and switches between them atomically.

### Key Benefits

- **Zero Downtime**: Search operations continue uninterrupted during syncs
- **Atomic Switching**: Alias updates happen in milliseconds
- **Environment Separation**: Automatic isolation by site URL (dev/staging/production)
- **API Key Compatibility**: Works with scoped API keys that restrict collection access
- **Rollback Capability**: Easy rollback to previous collection version if needed

### System Architecture

The aliasing system uses three key components:

1. **Aliases**: Stable collection names used for search operations (`product-mystore-com`)
2. **Blue Collections**: Active data collections with `-a` suffix (`product-mystore-com-a`)
3. **Green Collections**: Standby data collections with `-b` suffix (`product-mystore-com-b`)

## How It Works

### Collection Naming Pattern

- **Alias Format**: `{type}-{site_url}` (e.g., `product-mystore-com`)
- **Collection Format**: `{type}-{site_url}-{suffix}` (e.g., `product-mystore-com-a`, `product-mystore-com-b`)
- **Supported Types**: `product`, `taxonomy`, `page`, `menu`, `site_info`, `navigation`

### Blue-Green Deployment Flow

1. **Sync Initialization**

   - System determines which collection is currently active (pointed to by alias)
   - Identifies the inactive collection (opposite suffix: if active is `-a`, inactive is `-b`)
   - Cleans up the inactive collection if it exists

2. **Data Synchronization**

   - All new data is synced to the inactive collection
   - Search operations continue using the active collection via the alias
   - No interruption to frontend functionality

3. **Atomic Switch**

   - Once sync is complete, the alias is updated to point to the newly synced collection
   - The switch happens in milliseconds
   - The previously active collection becomes the new inactive collection

4. **Cleanup**
   - The old collection remains available for rollback if needed
   - Can be cleaned up manually or automatically in future syncs

### Stateless Implementation

The system is designed to be stateless:

- No local database tracking required
- State determined by querying Typesense API
- Automatic detection of active/inactive collections
- Resilient to interruptions and failures

## Configuration

### Enable/Disable Aliasing System

The aliasing system is **enabled by default** but can be controlled via WordPress filters:

```php
// Enable aliases (default behavior)
add_filter('blazecommerce/use_collection_aliases', '__return_true');

// Disable aliases (fallback to legacy behavior)
add_filter('blazecommerce/use_collection_aliases', '__return_false');
```

### Adding the Filter to Your Theme

Add the filter to your theme's `functions.php` file or a custom plugin:

```php
// In your theme's functions.php or custom plugin
function enable_blazecommerce_aliases() {
    return true;
}
add_filter('blazecommerce/use_collection_aliases', 'enable_blazecommerce_aliases');
```

### Environment-Specific Configuration

You can configure different behaviors for different environments:

```php
// Enable aliases only in production
function conditional_alias_usage() {
    return wp_get_environment_type() === 'production';
}
add_filter('blazecommerce/use_collection_aliases', 'conditional_alias_usage');
```

### Backward Compatibility

- Legacy collections continue to work without modification
- Gradual migration is supported
- No breaking changes to existing functionality
- System automatically falls back to legacy naming when aliases are disabled

## CLI Commands

### Basic Sync Commands

All existing sync commands automatically use the aliasing system when enabled:

```bash
# Sync all products using aliases
wp bc-sync product --all

# Sync taxonomies using aliases
wp bc-sync taxonomy --all

# Sync pages and posts using aliases
wp bc-sync page_and_post --all

# Sync menus using aliases
wp bc-sync menu --all

# Sync navigation using aliases
wp bc-sync navigation --all
```

### Alias Management Commands

The plugin provides dedicated commands for managing aliases:

#### List All Aliases

```bash
wp bc-sync alias --list
```

Shows all aliases and their target collections:

```
Alias: product-mystore-com -> product-mystore-com-a
Alias: taxonomy-mystore-com -> taxonomy-mystore-com-b
Alias: page-mystore-com -> page-mystore-com-a
```

#### Check Alias Status

```bash
wp bc-sync alias --status
```

Displays comprehensive status information:

```
Collection Type: product
  Alias: product-mystore-com
  Target: product-mystore-com-a
  Active Collection: product-mystore-com-a
  Inactive Collection: product-mystore-com-b
  Status: ✓ Alias exists and is valid
```

#### Get Alias Names Only

```bash
wp bc-sync alias --get-aliases
```

Returns just the alias names for scripting purposes:

```
product-mystore-com
taxonomy-mystore-com
page-mystore-com
menu-mystore-com
site_info-mystore-com
navigation-mystore-com
```

#### Force Create Alias

```bash
wp bc-sync alias --force-alias=product
```

Forces creation of an alias for a specific collection type, useful for:

- Migrating from legacy collections
- Recovering from alias deletion
- Setting up aliases for existing collections

#### Cleanup Old Collections

```bash
wp bc-sync alias --cleanup=product
```

Removes old collections for a specific type, keeping only:

- The current active collection
- The current inactive collection
- Any collections created in the last 24 hours (safety buffer)

## API Key Configuration

The aliasing system requires specific API key configurations to work properly with both the blue-green collections and their aliases.

### Required API Keys

You need to generate **two types of API keys** in your Typesense dashboard:

#### 1. Private Key (Admin Operations)

Used for administrative operations including creating, updating, and deleting collections and aliases.

**Permissions Required:**

- **Actions**: `["*"]` (all actions)
- **Collections**: Must include both `-a` and `-b` suffixes for all collection types

**Example JSON for Private Key:**

```json
{
  "description": "mystore-com-private-key",
  "actions": ["*"],
  "collections": [
    "site_info-mystore-com-a",
    "site_info-mystore-com-b",
    "menu-mystore-com-a",
    "menu-mystore-com-b",
    "page-mystore-com-a",
    "page-mystore-com-b",
    "product-mystore-com-a",
    "product-mystore-com-b",
    "taxonomy-mystore-com-a",
    "taxonomy-mystore-com-b",
    "navigation-mystore-com-a",
    "navigation-mystore-com-b"
  ]
}
```

#### 2. Public Key (Search Operations)

Used for frontend search operations and only needs read access to the aliases.

**Permissions Required:**

- **Actions**: `["documents:search"]`
- **Collections**: Only the alias names (without `-a` or `-b` suffixes)

**Example JSON for Public Key:**

```json
{
  "description": "mystore-com-public-key",
  "actions": ["documents:search"],
  "collections": [
    "site_info-mystore-com",
    "menu-mystore-com",
    "page-mystore-com",
    "product-mystore-com",
    "taxonomy-mystore-com",
    "navigation-mystore-com"
  ]
}
```

### Key Configuration Best Practices

1. **Site URL Format**: Use the same normalized format as your collections (replace dots with hyphens)
2. **Collection Coverage**: Include all collection types your site uses
3. **Security**: Keep the private key secure and only use it for admin operations
4. **Frontend Usage**: Only use the public key for frontend search operations
5. **Environment Separation**: Use different keys for dev/staging/production environments

### WordPress Configuration

Configure the keys in your WordPress admin panel:

1. Go to **BlazeCommerce Settings**
2. Enter your **Private API Key** (used for syncing)
3. Configure your **Public API Key** (used for frontend search)
4. Set your **Typesense Host** and **Store ID**

## Common Operations

### Checking Current System Status

Before performing any operations, check the current status:

```bash
wp bc-sync alias --status
```

This shows you:

- Which collections are active/inactive
- Whether aliases exist and are properly configured
- Any potential issues with the setup

### Performing a Full Sync

To sync all data using the aliasing system:

```bash
# Sync all collection types
wp bc-sync product --all
wp bc-sync taxonomy --all
wp bc-sync page_and_post --all
wp bc-sync menu --all
wp bc-sync navigation --all
```

Each command will:

1. Create or update the inactive collection
2. Sync all data to the inactive collection
3. Switch the alias to point to the newly synced collection
4. Display the target collection being used

### Monitoring Sync Progress

During sync operations, you can monitor progress by:

1. **Checking WordPress Debug Logs** (if enabled):

   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   ```

2. **Monitoring Typesense Dashboard**:

   - Watch collection document counts
   - Monitor alias mappings
   - Check for any error messages

3. **Using Status Commands**:
   ```bash
   wp bc-sync alias --status
   ```

### Rolling Back to Previous Version

If you need to rollback to the previous collection version:

```bash
# Force the alias to point to the other collection
wp bc-sync alias --force-alias=product
```

This will point the alias to the most recent collection, which may be the previous version.

### Cleaning Up Old Collections

To free up storage space, clean up old collections:

```bash
# Clean up old product collections
wp bc-sync alias --cleanup=product

# Clean up all collection types
wp bc-sync alias --cleanup=taxonomy
wp bc-sync alias --cleanup=page
wp bc-sync alias --cleanup=menu
wp bc-sync alias --cleanup=site_info
wp bc-sync alias --cleanup=navigation
```

**Note**: Cleanup operations preserve:

- Current active collection
- Current inactive collection
- Collections created within the last 24 hours (safety buffer)

## Troubleshooting

### Common Issues and Solutions

#### 1. Aliases Not Working / Search Returning No Results

**Symptoms:**

- Frontend search returns no results
- Error messages about missing collections
- Alias commands show "No alias found"

**Diagnosis:**

```bash
wp bc-sync alias --status
```

**Solutions:**

**If no aliases exist:**

```bash
# Force create aliases for existing collections
wp bc-sync alias --force-alias=product
wp bc-sync alias --force-alias=taxonomy
wp bc-sync alias --force-alias=page
```

**If collections are missing:**

```bash
# Run a full sync to create new collections
wp bc-sync product --all
wp bc-sync taxonomy --all
```

#### 2. Interrupted Sync Operations

**Symptoms:**

- Sync command was interrupted or failed
- Inactive collection exists but is incomplete
- Alias still points to old collection

**Solution:**
Simply run the sync command again. The system will:

- Clean up the incomplete inactive collection
- Create a fresh collection
- Complete the sync process

```bash
wp bc-sync product --all
```

#### 3. API Key Permission Errors

**Symptoms:**

- "Collection not found" errors during sync
- "Insufficient permissions" errors
- Alias operations failing

**Diagnosis:**
Check your API key configuration:

1. **Verify Private Key Permissions:**

   - Must have `["*"]` actions
   - Must include both `-a` and `-b` collection names
   - Must include all collection types you're using

2. **Verify Public Key Permissions:**
   - Must have `["documents:search"]` action
   - Must include alias names (without `-a`/`-b` suffixes)

**Solution:**
Regenerate your API keys with the correct permissions (see [API Key Configuration](#api-key-configuration)).

#### 4. Legacy Collections Still in Use

**Symptoms:**

- Old collection naming format still active
- Aliases not being used despite being enabled
- Mixed collection naming in Typesense dashboard

**Solution:**

```bash
# Check current status
wp bc-sync alias --status

# Run new syncs to create alias-based collections
wp bc-sync product --all

# Force create aliases if needed
wp bc-sync alias --force-alias=product
```

#### 5. Both Blue and Green Collections Missing

**Symptoms:**

- Neither `-a` nor `-b` collection exists for a type
- Fresh installation or complete data loss

**Solution:**
Run a fresh sync to create the blue-green collection pair:

```bash
wp bc-sync product --all
```

The system will automatically:

- Create the first collection with `-a` suffix
- Set up the alias to point to it
- Prepare for future blue-green deployments

#### 6. Collection Size Discrepancies

**Symptoms:**

- Active and inactive collections have different document counts
- Search results seem incomplete

**Diagnosis:**

```bash
# Check collection status
wp bc-sync alias --status

# List all aliases and targets
wp bc-sync alias --list
```

**Solution:**
Run a fresh sync to ensure data consistency:

```bash
wp bc-sync product --all
```

### Debug Information and Logging

#### Enable WordPress Debug Logging

Add to your `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

#### Check Debug Logs

Look for aliasing-related log entries:

```bash
# View recent log entries
tail -f /path/to/wp-content/debug.log | grep -i "alias\|typesense\|collection"
```

#### Common Log Messages

- `TS Product collection (alias): product-mystore-com-a` - Collection created successfully
- `Using alias-pointed collection for product: product-mystore-com-a` - Fallback to alias-pointed collection
- `Failed to update alias: [error message]` - Alias update failed

#### Typesense Dashboard Monitoring

Monitor your Typesense dashboard for:

1. **Collection Pairs**: You should see both `-a` and `-b` collections for each type
2. **Alias Mappings**: Aliases should point to one of the collection pairs
3. **Document Counts**: Active collections should have expected document counts
4. **API Key Usage**: Monitor API key usage and any permission errors

### Performance Troubleshooting

#### High Memory Usage During Sync

**Symptoms:**

- PHP memory limit errors during sync
- Slow sync performance
- Server becoming unresponsive

**Solutions:**

1. **Increase PHP Memory Limit:**

   ```php
   ini_set('memory_limit', '512M');
   ```

2. **Reduce Batch Sizes:**

   ```php
   // In your theme's functions.php
   add_filter('wooless_product_query_args', function($args) {
       $args['limit'] = 2; // Reduce from default 5
       return $args;
   });
   ```

3. **Run Syncs During Off-Peak Hours:**
   ```bash
   # Schedule via cron for off-peak times
   0 2 * * * /usr/local/bin/wp bc-sync product --all --path=/path/to/wordpress
   ```

#### Slow Alias Switching

**Symptoms:**

- Long delays when switching aliases
- Timeouts during alias updates

**Solutions:**

1. **Check Typesense Server Performance:**

   - Monitor server resources
   - Check network connectivity
   - Verify Typesense server health

2. **Verify API Key Permissions:**
   - Ensure private key has full access
   - Check for any rate limiting

### Getting Help

If you continue to experience issues:

1. **Check the Debug Logs** for specific error messages
2. **Run Diagnostic Commands:**
   ```bash
   wp bc-sync alias --status
   wp bc-sync alias --list
   ```
3. **Verify API Key Configuration** in Typesense dashboard
4. **Test with a Simple Sync:**
   ```bash
   wp bc-sync product --all
   ```
5. **Contact Support** with:
   - Debug log excerpts
   - Output from diagnostic commands
   - Description of the issue and steps to reproduce

## Best Practices

### Development Workflow

#### 1. Environment Separation

Use different site URLs for different environments:

- **Development**: `dev-mystore-com`
- **Staging**: `staging-mystore-com`
- **Production**: `mystore-com`

This ensures complete isolation between environments.

#### 2. API Key Management

- **Never share API keys** between environments
- **Use environment-specific keys** with appropriate permissions
- **Rotate keys regularly** for security
- **Store keys securely** (use environment variables or secure vaults)

#### 3. Sync Scheduling

**For Production:**

```bash
# Schedule during low-traffic periods
0 2 * * * /usr/local/bin/wp bc-sync product --all --path=/path/to/wordpress
```

**For Development:**

```bash
# More frequent syncs for testing
0 */4 * * * /usr/local/bin/wp bc-sync product --all --path=/path/to/wordpress
```

#### 4. Monitoring and Alerting

Set up monitoring for:

- Sync completion status
- Collection document counts
- Alias health checks
- API key expiration

### Performance Optimization

#### 1. Batch Size Tuning

Adjust batch sizes based on your server capacity:

```php
// For high-performance servers
add_filter('wooless_product_query_args', function($args) {
    $args['limit'] = 10; // Increase batch size
    return $args;
});

// For resource-constrained servers
add_filter('wooless_product_query_args', function($args) {
    $args['limit'] = 2; // Decrease batch size
    return $args;
});
```

#### 2. Memory Management

```php
// Increase memory limit for sync operations
if (defined('WP_CLI') && WP_CLI) {
    ini_set('memory_limit', '1G');
}
```

#### 3. Collection Cleanup Strategy

Implement regular cleanup to manage storage:

```bash
# Weekly cleanup script
#!/bin/bash
wp bc-sync alias --cleanup=product
wp bc-sync alias --cleanup=taxonomy
wp bc-sync alias --cleanup=page
wp bc-sync alias --cleanup=menu
wp bc-sync alias --cleanup=site_info
wp bc-sync alias --cleanup=navigation
```

### Security Best Practices

#### 1. API Key Security

- **Private Keys**: Only use on secure servers, never in frontend code
- **Public Keys**: Safe for frontend use but limit to search operations only
- **Key Rotation**: Regularly rotate keys and update configurations
- **Access Logging**: Monitor API key usage in Typesense dashboard

#### 2. Collection Access Control

- **Scoped Keys**: Use collection-scoped keys rather than global keys
- **Principle of Least Privilege**: Grant minimum required permissions
- **Environment Isolation**: Separate keys for each environment

#### 3. Data Protection

- **Backup Strategy**: Maintain backups of critical collections
- **Rollback Plan**: Test rollback procedures regularly
- **Data Validation**: Verify data integrity after syncs

### Maintenance and Monitoring

#### 1. Regular Health Checks

```bash
# Daily health check script
#!/bin/bash
echo "=== BlazeCommerce Alias Health Check ==="
wp bc-sync alias --status
echo "=== Collection Status ==="
wp bc-sync alias --list
```

#### 2. Storage Management

Monitor collection storage usage:

- Each collection type maintains 2 versions (blue/green)
- Plan for 2x storage requirements
- Implement cleanup policies for old collections

#### 3. Performance Monitoring

Track key metrics:

- Sync completion times
- Collection document counts
- Search response times
- API key usage patterns

### Testing Procedures

#### 1. Pre-Deployment Testing

Before deploying to production:

```bash
# Test alias functionality
wp bc-sync alias --status

# Test sync operations
wp bc-sync product --all

# Verify search functionality
# (Test frontend search after sync)

# Check collection integrity
wp bc-sync alias --list
```

#### 2. Post-Deployment Verification

After deployment:

1. **Verify Search Functionality**: Test frontend search operations
2. **Check Collection Status**: Ensure aliases point to correct collections
3. **Monitor Performance**: Watch for any performance degradation
4. **Validate Data**: Spot-check search results for accuracy

#### 3. Rollback Testing

Regularly test rollback procedures:

```bash
# Test rollback capability
wp bc-sync alias --force-alias=product

# Verify search still works
# Test frontend functionality

# Switch back if needed
wp bc-sync product --all
```

## Migration Guide

### From Legacy System to Aliasing

#### Step 1: Preparation

1. **Backup Current Data**: Ensure you have backups of existing collections
2. **Verify API Keys**: Update API keys to support both `-a` and `-b` collections
3. **Test in Development**: Test the migration process in a development environment first

#### Step 2: Enable Aliasing

Add to your theme's `functions.php` or custom plugin:

```php
// Enable the aliasing system
add_filter('blazecommerce/use_collection_aliases', '__return_true');
```

#### Step 3: Run Initial Sync

```bash
# Run sync for each collection type
wp bc-sync product --all
wp bc-sync taxonomy --all
wp bc-sync page_and_post --all
wp bc-sync menu --all
wp bc-sync navigation --all
```

This will:

- Create new collections with `-a` suffix
- Set up aliases pointing to the new collections
- Maintain backward compatibility with legacy collections

#### Step 4: Verify Migration

```bash
# Check that aliases are working
wp bc-sync alias --status

# Verify search functionality
# Test frontend search operations

# List all aliases
wp bc-sync alias --list
```

#### Step 5: Cleanup (Optional)

After verifying everything works correctly, you can clean up old legacy collections manually through the Typesense dashboard.

### Rollback to Legacy System

If you need to rollback to the legacy system:

#### Step 1: Disable Aliasing

```php
// Disable the aliasing system
add_filter('blazecommerce/use_collection_aliases', '__return_false');
```

#### Step 2: Verify Legacy Collections

Ensure your legacy collections still exist and are functional. If not, run a sync to recreate them:

```bash
wp bc-sync product --all
```

#### Step 3: Update API Keys

Update your API keys to use the legacy collection naming format if needed.

### Gradual Migration Strategy

For large sites, consider a gradual migration:

#### Phase 1: Enable for Non-Critical Collections

```php
function selective_alias_usage() {
    // Only enable for menu and navigation initially
    $current_filter = current_filter();
    if (strpos($current_filter, 'menu') !== false || strpos($current_filter, 'navigation') !== false) {
        return true;
    }
    return false;
}
add_filter('blazecommerce/use_collection_aliases', 'selective_alias_usage');
```

#### Phase 2: Expand to All Collections

After testing and verification:

```php
// Enable for all collections
add_filter('blazecommerce/use_collection_aliases', '__return_true');
```

### Migration Checklist

- [ ] Backup existing collections
- [ ] Update API keys with correct permissions
- [ ] Test in development environment
- [ ] Enable aliasing system
- [ ] Run initial syncs for all collection types
- [ ] Verify alias status and functionality
- [ ] Test frontend search operations
- [ ] Monitor performance and error logs
- [ ] Document any custom configurations
- [ ] Train team on new CLI commands
- [ ] Update deployment scripts if needed
- [ ] Plan cleanup of legacy collections

---

## Conclusion

The Typesense collection aliasing system provides a robust, zero-downtime solution for data synchronization in the Blaze Commerce WordPress plugin. By following this guide, you can:

- Implement seamless blue-green deployments
- Eliminate search downtime during syncs
- Maintain high availability for your e-commerce search
- Monitor and troubleshoot the system effectively

For additional support or questions, refer to the troubleshooting section or contact the development team with specific log outputs and error messages.
