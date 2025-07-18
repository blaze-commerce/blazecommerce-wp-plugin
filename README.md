# Blaze Commerce WordPress Plugin

The official plugin that integrates your WordPress/WooCommerce site with the Blaze Commerce service.

**Version:** 1.14.5
**Author:** Blaze Commerce
**Plugin URI:** https://www.blazecommerce.io
**Author URI:** https://www.blazecommerce.io

## Description

Blaze Commerce is a powerful WordPress plugin that provides seamless integration between your WooCommerce store and the Blaze Commerce headless commerce platform. The plugin features advanced Typesense search integration, collection aliasing for zero-downtime syncing, comprehensive settings management, and extensive customization options.

<!-- Testing automatic version bump system - final validation after status check fix -->

## Key Features

- **Typesense Integration**: Advanced search capabilities with real-time indexing
- **Collection Aliasing**: Zero-downtime syncing with blue-green deployment pattern
- **CLI Commands**: Comprehensive WP-CLI commands for data synchronization
- **Settings Management**: Centralized configuration system with filter-based architecture
- **Extension Support**: Built-in support for popular WooCommerce extensions
- **GraphQL Integration**: Enhanced GraphQL support for headless commerce
- **Performance Optimization**: Caching, batch processing, and memory optimization

<!-- Note: This plugin requires proper configuration of WooCommerce and GraphQL dependencies -->

## Requirements

- WordPress 5.0+
- WooCommerce
- WP GraphQL
- WP GraphQL CORS
- WP GraphQL JWT Authentication
- WP GraphQL WooCommerce

## Installation

For detailed installation and configuration instructions, see our [Installation Guide](docs/setup/installation-and-configuration.md).

**Quick Start:**
1. Upload the plugin files to `/wp-content/plugins/blaze-commerce/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Configure your Typesense settings in the admin panel
4. Run initial data sync using WP-CLI commands

## Documentation

Our documentation is organized into the following categories:

- **[Setup & Installation](docs/setup/)** - Installation guides and initial configuration
- **[Features](docs/features/)** - Detailed feature documentation and user guides
- **[API Documentation](docs/api/)** - Technical API references and integration guides
- **[Development](docs/development/)** - Developer workflows, automation, and coding guidelines
- **[Reference](docs/reference/)** - Changelog, legal documents, and reference materials
- **[Troubleshooting](docs/troubleshooting/)** - Problem-solving guides and common issues

For documentation standards and contribution guidelines, see [Documentation Standards](docs/DOCUMENTATION_STANDARDS.md).

📋 **[Documentation Organization Summary](docs/DOCUMENTATION_ORGANIZATION_SUMMARY.md)** - Overview of the recent documentation reorganization project and improvements.

## Configuration

For complete configuration instructions, see our [Installation and Configuration Guide](docs/setup/installation-and-configuration.md).

### Quick Configuration

Configure your Typesense connection in the WordPress admin:

- **API Key**: Your Typesense API key
- **Host**: Typesense server host
- **Store ID**: Unique identifier for your store

### Collection Aliasing

The plugin uses an advanced collection aliasing system for zero-downtime syncing. This can be controlled via filters:

```php
// Enable aliases (default)
add_filter('blazecommerce/use_collection_aliases', '__return_true');

// Disable aliases (fallback to legacy behavior)
add_filter('blazecommerce/use_collection_aliases', '__return_false');
```

For detailed information about the aliasing system, see: [Typesense Aliases Documentation](docs/TYPESENSE_ALIASES_README.md)

## CLI Commands

The plugin provides comprehensive WP-CLI commands for data synchronization:

### All Collections Sync

```bash
wp bc-sync collections --all      # Sync all collections in order: site_info, products, taxonomy, menu, page_and_post, navigation
```

This command syncs all Typesense collections in the specified order with comprehensive timing and summary statistics.

### Individual Collection Sync

#### Product Sync

```bash
wp bc-sync product --all          # Sync all products including variations
wp bc-sync product --variants     # Sync product variants only
wp bc-sync product --nonvariants  # Sync non-variant products only
```

#### Taxonomy Sync

```bash
wp bc-sync taxonomy --all         # Sync all taxonomies
```

#### Page and Post Sync

```bash
wp bc-sync page_and_post --all    # Sync all pages and posts
```

#### Menu Sync

```bash
wp bc-sync menu --all             # Sync all menus
```

#### Navigation Sync

```bash
wp bc-sync navigation --all       # Sync wp_navigation posts
```

#### Site Info Sync

```bash
wp bc-sync site_info --all        # Sync site information
```

### Management Commands

#### Alias Management

```bash
wp bc-sync alias --list                    # List all aliases
wp bc-sync alias --status                  # Show collection status
wp bc-sync alias --cleanup=product         # Clean up old collections
wp bc-sync alias --force-alias=product     # Force create alias
```

#### Cache Management

```bash
wp bc-sync cache                   # Show cache statistics
wp bc-sync cache --clear           # Clear all caches
```

## Action Hooks

### Core Actions

#### `init`

- **Description**: Plugin initialization
- **Usage**: Core plugin setup and feature registration

#### `admin_init`

- **Description**: Admin initialization
- **Usage**: Settings registration and admin-specific setup

#### `template_redirect`

- **Description**: Template redirection handling
- **Usage**: Search redirects and non-admin user redirects

#### `cli_init`

- **Description**: WP-CLI command registration
- **Usage**: Registers the `bc-sync` command

#### `edited_term`

- **Description**: Taxonomy term editing
- **Usage**: Updates Typesense documents when taxonomy terms are edited

### Settings Actions

#### `blazecommerce/settings/register_tab_link`

- **Description**: Register settings tab navigation links
- **Parameters**: `$active_tab` (string) - Currently active tab

#### `blazecommerce/settings/render_settings_tab_content`

- **Description**: Render settings tab content
- **Parameters**: `$active_tab` (string) - Currently active tab

#### `blazecommerce/settings/render_settings_tab_content_footer`

- **Description**: Render settings tab footer content
- **Parameters**: `$active_tab` (string) - Currently active tab

### Product and Data Actions

#### `blaze_wooless_generate_product_reviews_data`

- **Description**: Generate product reviews data
- **Parameters**: `$product_id` (int) - Product ID

#### `blaze_wooless_save_product_page_settings`

- **Description**: Save product page settings
- **Parameters**: `$options` (array) - Settings options

#### `rest_api_init`

- **Description**: REST API initialization
- **Usage**: Register custom REST endpoints

## Filter Hooks

### Core Filters

#### `blazecommerce/settings`

- **Description**: Modify settings documents for Typesense sync
- **Parameters**: `$documents` (array) - Array of settings documents
- **Return**: `array` - Modified documents array

#### `blazecommerce/use_collection_aliases`

- **Description**: Control whether to use collection aliasing system
- **Parameters**: `$use_aliases` (bool) - Whether to use aliases
- **Return**: `bool` - True to use aliases, false for legacy behavior

#### `blazecommerce/settings/sync/products`

- **Description**: Control product synchronization
- **Parameters**: `$should_sync` (bool) - Whether products should sync
- **Return**: `bool` - True to allow sync, false to prevent

### Cookie Management

#### `blaze_commerce_cookie_domain`

- **Description**: Modify the cookie domain when setting cookies
- **Parameters**: `$domain` (string) - Cookie domain
- **Return**: `string` - Modified domain

**Example:**

```php
add_filter('blaze_commerce_cookie_domain', function($domain) {
    return '.my-site.com';
});
```

#### `blaze_commerce_cookie_expiry`

- **Description**: Modify the cookie expiry when setting cookies
- **Parameters**: `$cookie_expiry` (int) - Cookie expiry timestamp
- **Return**: `int` - Modified expiry timestamp

**Example:**

```php
add_filter('blaze_commerce_cookie_expiry', function($cookie_expiry) {
    return $cookie_expiry + 3600; // adds 1 hour
});
```

### Product Data Filters

#### `blaze_wooless_product_data_for_typesense`

- **Description**: Modify product data before sending to Typesense
- **Parameters**:
  - `$product_data` (array) - Product data
  - `$product` (WC_Product) - WooCommerce product object
- **Return**: `array` - Modified product data

#### `blaze_wooless_cross_sell_data_for_typesense`

- **Description**: Modify cross-sell product data for Typesense
- **Parameters**:
  - `$product_data` (array) - Product data
  - `$product` (WC_Product) - WooCommerce product object
- **Return**: `array` - Modified product data

#### `blaze_wooless_product_for_typesense_fields`

- **Description**: Modify Typesense fields for products
- **Parameters**: `$fields` (array) - Typesense field definitions
- **Return**: `array` - Modified fields

#### `blaze_commerce_variation_data`

- **Description**: Modify product variation data
- **Parameters**:
  - `$variation_data` (array) - Variation data
  - `$variation` (WC_Product_Variation) - Variation object
- **Return**: `array` - Modified variation data

### Site Information Filters

#### `blaze_wooless_additional_site_info`

- **Description**: Add additional site information to sync
- **Parameters**: `$site_info` (array) - Site information array
- **Return**: `array` - Modified site information

### URL and Link Filters

#### `rest_url`

- **Description**: Modify REST API URLs
- **Parameters**: `$url` (string) - REST URL
- **Return**: `string` - Modified URL

#### `post_link`

- **Description**: Modify post permalinks
- **Parameters**: `$permalink` (string) - Post permalink
- **Return**: `string` - Modified permalink

#### `post_type_link`

- **Description**: Modify post type permalinks
- **Parameters**: `$link` (string) - Post type link
- **Return**: `string` - Modified link

#### `page_link`

- **Description**: Modify page permalinks
- **Parameters**: `$link` (string) - Page link
- **Return**: `string` - Modified link

#### `term_link`

- **Description**: Modify taxonomy term links
- **Parameters**: `$termlink` (string) - Term link
- **Return**: `string` - Modified link

#### `option_home`

- **Description**: Modify home URL option
- **Parameters**:
  - `$value` (string) - Home URL value
  - `$option` (string) - Option name
- **Return**: `string` - Modified home URL

### Taxonomy and Collection Filters

#### `blaze_wooless_generate_breadcrumbs`

- **Description**: Generate breadcrumbs for taxonomy terms
- **Parameters**:
  - `$breadcrumbs` (array) - Breadcrumb data
  - `$term_id` (int) - Term ID
- **Return**: `array` - Generated breadcrumbs

#### `blaze_commerce_taxonomy_fields`

- **Description**: Modify Typesense fields for taxonomy collection
- **Parameters**: `$fields` (array) - Field definitions
- **Return**: `array` - Modified fields

### Settings Page Filters

#### `blaze_wooless_product_page_settings`

- **Description**: Modify product page settings configuration
- **Parameters**: `$settings` (array) - Settings configuration
- **Return**: `array` - Modified settings

#### `blazecommerce/settings/product_page`

- **Description**: Modify product page settings documents
- **Parameters**:
  - `$documents` (array) - Settings documents
  - `$options` (array) - Settings options
- **Return**: `array` - Modified documents

#### `blaze_wooless_review_setting_options`

- **Description**: Register review settings options
- **Parameters**: `$options` (array) - Review settings
- **Return**: `array` - Modified options

### GraphQL Filters

#### `graphql_CartItem_fields`

- **Description**: Modify GraphQL cart item fields
- **Parameters**: `$fields` (array) - GraphQL field definitions
- **Return**: `array` - Modified fields

## Extension Support

The plugin includes built-in support for popular WooCommerce extensions:

- ACF Product Tabs
- Business Reviews Bundle
- Custom Product Tabs Manager
- Judge.me Reviews
- Yotpo Reviews
- YITH WishList
- Aelia Currency Switcher
- WooCommerce Afterpay
- WooCommerce Gift Cards
- Yoast SEO
- RankMath
- And many more...

## Settings Management

The plugin uses a centralized settings system with the following configuration pages:

- **General Settings**: Core plugin configuration
- **Regional Settings**: Location and currency settings
- **Product Filter Settings**: Search and filter configuration
- **Product Page Settings**: Product display options
- **Category Page Settings**: Category display configuration
- **Home Page Settings**: Homepage layout and content
- **Synonym Settings**: Search synonym management

## Performance Features

- **Collection Aliasing**: Zero-downtime syncing with blue-green deployment
- **Batch Processing**: Efficient handling of large datasets
- **Caching System**: Multiple levels of caching for optimal performance
- **Memory Optimization**: Careful memory management for large operations
- **Transient Storage**: Temporary data storage for improved performance

## Development

### File Structure

```
blaze-commerce/
├── app/                    # Core application files
│   ├── Collections/        # Typesense collection classes
│   ├── Extensions/         # Third-party extension integrations
│   ├── Features/          # Plugin features and functionality
│   └── Settings/          # Settings management classes
├── assets/                # CSS, JS, and image assets
├── blocks/                # Gutenberg blocks
├── docs/                  # Documentation
├── lib/                   # Helper libraries
├── test/                  # Test files
└── views/                 # Template files
```

### Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Submit a pull request

## Support

For support and documentation, visit:

- **Website**: https://www.blazecommerce.io
- **Documentation**: [Typesense Aliases Documentation](docs/TYPESENSE_ALIASES_README.md)

## License

This plugin is proprietary software developed by Blaze Commerce.
