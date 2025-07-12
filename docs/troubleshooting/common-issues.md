---
title: "Common Issues and Troubleshooting"
description: "Comprehensive troubleshooting guide covering common issues and solutions for the Blaze Commerce WordPress Plugin"
category: "troubleshooting"
version: "1.0.0"
last_updated: "2025-01-09"
author: "Blaze Commerce Team"
tags: ["troubleshooting", "issues", "debugging", "support", "solutions", "errors"]
related_docs: ["../setup/installation-and-configuration.md", "../features/country-specific-images.md", "../api/typesense-aliases-readme.md"]
---

# Common Issues and Troubleshooting

This guide covers the most common issues encountered when using the Blaze Commerce WordPress Plugin and their solutions.

## Installation Issues

### Plugin Activation Fails

**Symptoms**: Plugin cannot be activated, shows error message
**Causes**: Missing dependencies, PHP version incompatibility, file permissions

**Solutions**:
1. **Check PHP version**: Ensure PHP 7.4 or higher
2. **Verify dependencies**: Install required plugins (WooCommerce, WP GraphQL, etc.)
3. **Check file permissions**: Ensure proper WordPress file permissions
4. **Review error logs**: Check WordPress debug logs for specific errors

### Missing Required Plugins

**Symptoms**: Features not working, admin notices about missing plugins
**Causes**: Required plugins not installed or activated

**Solutions**:
1. Install and activate WooCommerce
2. Install WP GraphQL and related plugins
3. Check plugin compatibility versions
4. Verify plugin activation order

## Typesense Connection Issues

### Cannot Connect to Typesense

**Symptoms**: "Connection failed" errors, sync operations fail
**Causes**: Incorrect credentials, network issues, firewall blocking

**Solutions**:
1. **Verify credentials**: Check API key, host, port, and protocol
2. **Test network connectivity**: Ensure server can reach Typesense host
3. **Check firewall settings**: Allow outbound connections to Typesense
4. **Validate API key permissions**: Ensure key has required collection access

### API Key Permission Errors

**Symptoms**: "Forbidden" or "Unauthorized" errors during operations
**Causes**: API key lacks required permissions

**Solutions**:
1. **Check key scope**: Ensure API key includes all required collections
2. **Verify actions**: Confirm key allows required actions (search, documents:*, etc.)
3. **Regenerate keys**: Create new API keys with proper permissions
4. **Test with admin key**: Temporarily test with admin key to isolate issue

## Data Synchronization Issues

### Sync Operations Fail

**Symptoms**: WP-CLI sync commands fail, incomplete data in Typesense
**Causes**: Memory limits, timeout issues, data corruption, API limits

**Solutions**:
1. **Increase memory limits**: Adjust PHP memory_limit and max_execution_time
2. **Use batch processing**: Sync smaller batches with `--limit` parameter
3. **Check data integrity**: Verify WooCommerce data is valid
4. **Monitor logs**: Review WordPress and server error logs

### Collection Alias Issues

**Symptoms**: Search not working, alias pointing to wrong collection
**Causes**: Interrupted sync, manual collection changes, alias corruption

**Solutions**:
1. **Check alias status**: Run `wp bc-sync alias --status`
2. **Force alias creation**: Use `wp bc-sync alias --force-alias=product`
3. **Complete interrupted sync**: Re-run sync operation
4. **Clean up collections**: Remove orphaned collections manually

### Slow Sync Performance

**Symptoms**: Sync operations take very long time
**Causes**: Large datasets, server resource constraints, network latency

**Solutions**:
1. **Optimize batch sizes**: Adjust sync batch sizes for your environment
2. **Monitor resources**: Check CPU, memory, and network usage
3. **Use collection aliasing**: Enable zero-downtime syncing
4. **Schedule during off-peak**: Run large syncs during low-traffic periods

## Search and Frontend Issues

### Search Results Empty or Incorrect

**Symptoms**: No search results, outdated results, missing products
**Causes**: Sync issues, index problems, query configuration

**Solutions**:
1. **Verify data sync**: Check if products are in Typesense collections
2. **Test search directly**: Use Typesense dashboard to test queries
3. **Check collection aliases**: Ensure aliases point to correct collections
4. **Review search configuration**: Verify search parameters and filters

### GraphQL API Issues

**Symptoms**: GraphQL queries fail, authentication errors
**Causes**: Plugin conflicts, CORS issues, JWT configuration

**Solutions**:
1. **Check plugin compatibility**: Ensure GraphQL plugins are compatible
2. **Configure CORS**: Set up proper CORS headers for your domain
3. **Verify JWT setup**: Check JWT authentication configuration
4. **Test queries**: Use GraphQL IDE to test queries directly

## Performance Issues

### High Memory Usage

**Symptoms**: Out of memory errors, slow performance
**Causes**: Large datasets, inefficient queries, memory leaks

**Solutions**:
1. **Increase PHP memory**: Adjust memory_limit in php.ini
2. **Optimize queries**: Review and optimize database queries
3. **Use pagination**: Implement proper pagination for large datasets
4. **Monitor usage**: Use profiling tools to identify bottlenecks

### Slow Admin Interface

**Symptoms**: WordPress admin loads slowly, timeouts
**Causes**: Large datasets, inefficient admin queries, plugin conflicts

**Solutions**:
1. **Disable unnecessary plugins**: Temporarily disable other plugins
2. **Optimize database**: Clean up and optimize WordPress database
3. **Check server resources**: Monitor CPU and memory usage
4. **Review admin queries**: Identify slow database queries

## Feature-Specific Issues

### Country-Specific Images Not Working

**Symptoms**: Images don't change based on country, meta box missing
**Causes**: Aelia Currency Switcher not configured, feature disabled

**Solutions**:
1. **Install Aelia Currency Switcher**: Required for country detection
2. **Enable feature**: Check "Enable Country-Specific Product Images" setting
3. **Configure currencies**: Set up multiple currencies in Aelia
4. **Test country detection**: Verify country detection is working

### Export/Import Settings Issues

**Symptoms**: Export fails, import doesn't restore settings
**Causes**: File permissions, JSON corruption, security restrictions

**Solutions**:
1. **Check file permissions**: Ensure WordPress can write files
2. **Validate JSON**: Verify exported file is valid JSON
3. **Review security settings**: Check if security plugins block file operations
4. **Test with small exports**: Try exporting/importing individual settings

## Debugging and Diagnostics

### Enable Debug Logging

Add to `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Useful WP-CLI Commands

```bash
# Check plugin status
wp plugin status blaze-commerce

# Test Typesense connection
wp bc-sync test-connection

# Check collection status
wp bc-sync alias --status

# Sync small batch for testing
wp bc-sync product --limit=10

# Clear all caches
wp cache flush
```

### Log File Locations

- **WordPress Debug Log**: `/wp-content/debug.log`
- **Server Error Log**: Varies by server configuration
- **PHP Error Log**: Check `php.ini` for error_log location

## Getting Additional Help

### Before Seeking Help

1. **Check this troubleshooting guide** for common solutions
2. **Review error logs** for specific error messages
3. **Test in staging environment** to isolate issues
4. **Document reproduction steps** for consistent issues

### Support Resources

- **GitHub Issues**: Report bugs with detailed reproduction steps
- **Documentation**: Review feature-specific documentation in `/docs/features/`
- **Community Support**: Check existing GitHub discussions
- **Professional Support**: Contact Blaze Commerce for enterprise support

### Information to Include When Reporting Issues

- WordPress and plugin versions
- PHP version and server environment
- Error messages from logs
- Steps to reproduce the issue
- Expected vs actual behavior
- Screenshots or examples when relevant

---

For feature-specific troubleshooting, see the individual feature documentation in `/docs/features/`.
For API-related issues, see `/docs/api/` documentation.
For development and automation issues, see `/docs/development/`.
