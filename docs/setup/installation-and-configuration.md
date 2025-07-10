---
title: "Installation and Configuration Guide"
description: "Complete installation and initial configuration guide for the Blaze Commerce WordPress Plugin"
category: "setup"
version: "1.0.0"
last_updated: "2025-01-09"
author: "Blaze Commerce Team"
tags: ["installation", "configuration", "setup", "typesense", "graphql", "woocommerce"]
related_docs: ["../features/country-specific-images.md", "../api/typesense-aliases-readme.md", "../troubleshooting/common-issues.md"]
---

# Installation and Configuration Guide

This guide covers the complete installation and initial configuration of the Blaze Commerce WordPress Plugin.

## Requirements

### WordPress Requirements
- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher

### Required Plugins
- **WooCommerce** - Core e-commerce functionality
- **WP GraphQL** - GraphQL API support
- **WP GraphQL CORS** - Cross-origin resource sharing
- **WP GraphQL JWT Authentication** - JWT authentication
- **WP GraphQL WooCommerce** - WooCommerce GraphQL integration

### Optional but Recommended
- **Aelia Currency Switcher** - For country-specific features
- **JudgeMe** or **Yotpo** - For product reviews
- **WooCommerce Product Add-ons** - For product customization

## Installation

### Method 1: Manual Installation
1. Download the plugin files
2. Upload to `/wp-content/plugins/blaze-commerce/`
3. Activate the plugin through the 'Plugins' screen in WordPress
4. Navigate to **Blaze Commerce > Settings** to begin configuration

### Method 2: WordPress Admin Upload
1. Go to **Plugins > Add New** in WordPress admin
2. Click **Upload Plugin**
3. Choose the plugin ZIP file
4. Click **Install Now**
5. Activate the plugin

## Initial Configuration

### 1. Typesense Settings

Configure your Typesense connection in **Blaze Commerce > Settings > General**:

#### Required Fields
- **API Key**: Your Typesense private API key
- **Host**: Typesense server host (e.g., `https://your-cluster.typesense.net`)
- **Port**: Typesense server port (usually 443 for HTTPS)
- **Protocol**: HTTP or HTTPS (recommended: HTTPS)
- **Store ID**: Unique identifier for your store

#### API Key Configuration
You'll need two types of API keys:

**Private Key (Admin Operations)**:
- Used for data synchronization and admin operations
- Requires full access to all collections
- Should be kept secure and not exposed to frontend

**Public Key (Search Operations)**:
- Used for frontend search functionality
- Limited to search operations only
- Safe to expose in frontend applications

### 2. Collection Aliasing (Recommended)

Enable collection aliasing for zero-downtime syncing:

1. Go to **Blaze Commerce > Settings > General**
2. Ensure **"Use Collection Aliases"** is enabled (default)
3. This enables blue-green deployment pattern for data syncing

### 3. GraphQL Configuration

Configure GraphQL settings for headless commerce:

1. Install and activate required GraphQL plugins
2. Go to **GraphQL > Settings**
3. Configure CORS settings for your frontend domain
4. Set up JWT authentication if using headless architecture

### 4. WooCommerce Integration

Ensure WooCommerce is properly configured:

1. Complete WooCommerce setup wizard
2. Configure your store settings (currency, location, etc.)
3. Add some test products
4. Configure payment and shipping methods

## Data Synchronization

### Initial Sync

After configuration, perform an initial data sync:

```bash
# Sync all data types
wp bc-sync all

# Or sync individual data types
wp bc-sync product --all
wp bc-sync taxonomy --all
wp bc-sync menu --all
wp bc-sync page --all
```

### Verify Sync Status

Check synchronization status:

```bash
# Check collection status
wp bc-sync alias --status

# List all collections
wp bc-sync alias --list
```

## Feature Configuration

### Country-Specific Images

If using Aelia Currency Switcher:

1. Install and configure Aelia Currency Switcher
2. Go to **Blaze Commerce > Settings > General**
3. Enable **"Country-Specific Product Images"**
4. Configure country-specific images on individual products

### Export/Import Settings

Access backup and restore functionality:

1. Go to **Blaze Commerce > Settings > Export/Import**
2. Export current settings for backup
3. Import settings when migrating or restoring

### Product Filters

Configure search and filter functionality:

1. Go to **Blaze Commerce > Settings > Product Filters**
2. Configure available filter options
3. Set up filter layouts and styling

## Testing Your Installation

### 1. Admin Interface Test
- Verify all settings pages load correctly
- Check that Typesense connection is successful
- Confirm data sync operations work

### 2. Frontend Test
- Test GraphQL endpoints
- Verify search functionality
- Check product data synchronization

### 3. CLI Commands Test
```bash
# Test basic CLI functionality
wp bc-sync --help

# Test connection
wp bc-sync test-connection

# Test small sync operation
wp bc-sync product --limit=10
```

## Troubleshooting Common Issues

### Connection Issues
- Verify Typesense credentials and host settings
- Check firewall and network connectivity
- Ensure API keys have proper permissions

### Sync Issues
- Check WordPress debug logs
- Verify WooCommerce data integrity
- Test with smaller data sets first

### Performance Issues
- Monitor server resources during sync
- Consider batch size adjustments
- Use collection aliasing for zero-downtime syncing

## Next Steps

After successful installation:

1. **Configure your frontend application** to connect to the GraphQL API
2. **Set up automated syncing** using cron jobs or webhooks
3. **Configure additional features** based on your store requirements
4. **Test thoroughly** in a staging environment before going live

## Support Resources

- **Documentation**: See `/docs` directory for detailed feature guides
- **CLI Help**: Run `wp bc-sync --help` for command reference
- **Debug Logs**: Enable WordPress debug logging for troubleshooting
- **GitHub Issues**: Report bugs and request features on GitHub

---

For detailed feature documentation, see the `/docs/features/` directory.
For API documentation, see the `/docs/api/` directory.
For development workflows, see the `/docs/development/` directory.
