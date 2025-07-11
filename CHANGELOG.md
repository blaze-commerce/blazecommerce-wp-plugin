# Changelog

All notable changes to the BlazeCommerce WordPress Plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

> **Note**: Release dates have been corrected based on actual Git commit history to ensure accuracy.

## [1.8.0] - 2025-07-10

### Features
- **CLI Commands**: Added comprehensive collections sync command to manage all Typesense collections from command line
- **Color Swatches**: Enhanced support for both hex colors and image URLs in product variation swatches
- **Page Meta Fields**: New extension for managing page metadata with AJAX search and Select2 integration
- **Country-Specific Images**: Added geolocation-based content feature with admin interface and testing suite
- **Collection Aliasing**: Implemented blue-green deployment pattern for seamless collection updates
- **Export/Import**: Added settings export and import functionality for easier site management

### Bug Fixes
- **Product Sync**: Fixed product variations not being included in --all flag sync, preventing NaN prices
- **Price Handling**: Implemented proper int64 price multiplication for all price methods and Typesense compatibility
- **Variation Swatches**: Added isset() checks to prevent array access errors in WoocommerceVariationSwatches
- **Collection Updates**: Fixed page collections not updating alias after full sync or UI sync
- **Product Updates**: Resolved bulk edit not revalidating product pages when variations are edited
- **Memory Issues**: Optimized memory usage and fixed fatal errors during sync operations
- **Tax Prices**: Fixed tax price fields conversion to int64 for proper Typesense synchronization

### Performance
- **Memory Optimization**: Improved memory usage during sync operations to prevent fatal errors
- **Sync Efficiency**: Enhanced collection aliasing system for faster and more reliable updates
- **Query Optimization**: Optimized alias name retrieval to reduce memory consumption

### CI/CD & Automation
- **Claude Code Review**: Added retry logic with exponential backoff for improved workflow reliability
- **GitHub Actions**: Enhanced workflow permissions and added PAT fallback for automation tokens
- **Version Management**: Implemented automated version management and release system
- **Testing**: Added comprehensive test suite for product sync variations and other critical features

### Documentation
- **Comprehensive Reorganization**: Restructured documentation with improved standards and validation
- **Image Hosting**: Enhanced .gitignore with comprehensive image hosting guidance
- **API Documentation**: Updated README and API key generation documentation
- **Development Guidelines**: Added Augment development guidelines and coding standards

### Refactoring
- **Plugin Branding**: Updated all references from "Blaze Wooless" to "Blaze Commerce"
- **Code Standards**: Improved code formatting and WordPress coding standards compliance
- **Filter Hooks**: Refactored filter hooks for Country-Specific Images feature
- **Price Formatting**: Created format_price_to_int64 helper function for consistent price handling
- **Site Info Sync**: Refactored to avoid deprecated blaze_wooless_after_site_info_sync hooks
- **Collection Naming**: Implemented hyphenated naming convention and blue-green deployment pattern

## [1.7.0] - 2025-07-10

### Features
- **Navigation Management**: Enhanced WordPress navigation sync to Typesense with menu ID support
- **Bundle Products**: Added comprehensive bundle product data synchronization and stock status management
- **Multi-Currency**: Enhanced price metadata filters for tax-inclusive and exclusive pricing by location
- **CLI Commands**: Added --nonvariants parameter to product sync CLI command for better control
- **Mega Menu**: Added menuLinkActiveBackgroundColor attribute for active menu styling
- **Permalink Management**: Made permalinks facetable and added URL query base session passing
- **Cookie Domain**: Added cookie domain saving to Typesense for better session management

### Bug Fixes
- **Bundle Sync**: Fixed bundle sync errors related to tax calculations
- **Cart Totals**: Corrected GraphQL cart item total and subtotal calculations for bundle products
- **Class Loading**: Resolved "class not found" errors during plugin initialization

### Performance
- **Custom Meta Data**: Optimized custom meta data retrieval and processing
- **Collection Settings**: Enhanced collection configuration management

## [1.6.0] - 2025-07-10

### Features
- **Tax Management**: Added comprehensive tax settings retrieval and table rates handling
- **Menu Enhancement**: Implemented real WordPress menu items formatting and class support
- **Collection Control**: Added settings to allow developers to exclude product, taxonomy, and page collections from sync
- **Page Filtering**: Added filter to exclude specific pages from Typesense synchronization
- **Time Tracking**: Added sync operation time tracking for performance monitoring

### Bug Fixes
- **Sync Errors**: Fixed sync errors when post data is empty or object batch is empty
- **PHP Warnings**: Resolved array offset access warnings on boolean values
- **Syntax Errors**: Fixed missing comma syntax errors in various components
- **Taxonomy Sync**: Fixed critical errors and warnings during taxonomy synchronization

### Performance
- **Memory Management**: Improved memory usage during sync operations
- **Batch Processing**: Enhanced batch processing for better performance and reliability

## [1.5.0] - 2025-06-27

### Features
- **Product Variants**: Added support for syncing product variants in CLI commands
- **Product Settings**: Integrated product settings into site info for better configuration management
- **Immediate Updates**: Added immediate Typesense variation updates during product import
- **Category Settings**: Added WooCommerce product per page settings integration
- **Pinterest Integration**: Added Pinterest plugin support for category pages
- **Product Thumbnails**: Enhanced product thumbnail and details display

### Bug Fixes
- **NaN Prices**: Fixed NaN product price issues through proper price validation
- **Menu Sync**: Resolved Max Mega Menu sync errors
- **Product Updates**: Fixed product update triggers on order status changes and meta updates
- **CORS Issues**: Resolved WordPress page CORS errors
- **Permalink Structure**: Fixed WooCommerce permalink structure saving

### Performance
- **Addon Sorting**: Optimized addon sorting based on priority
- **Page Revalidation**: Enhanced Next.js page URL revalidation system

## [1.4.0] - 2025-06-12

### Features
- **Custom Post Types**: Added filter for custom post types to dynamically sync to page collections
- **ACF Integration**: Added Advanced Custom Fields (ACF) support with field synchronization to Typesense
- **WP Offload Media**: Added WP Offload Media extension for better image handling
- **Best Seller Override**: Added option to override best seller function with custom logic
- **Blog Content**: Enhanced blog content support based on Figma templates
- **URL Key Replacement**: Added domain replacement functionality for URL keys
- **Subscription Support**: Added WooCommerce Subscriptions extension with extra options
- **Cart Management**: Added cart page slug to site info and removed from page exclusion
- **Geo Restrictions**: Added checkbox to enable geographical restrictions
- **No Redirect Flag**: Added local page request detection to prevent unnecessary redirects

### Bug Fixes
- **Image Display**: Fixed images not displaying properly in various contexts
- **ACF Sync**: Resolved sync issues caused by ACF errors
- **Data Types**: Fixed ACF datatype issues by setting to string
- **Post Type Detection**: Corrected incorrect data for getting post_type
- **Extension Registration**: Fixed renamed registered extension issues

### Performance
- **Frontend Styles**: Added ability to load frontend styles in backend for better consistency
- **Taxonomy Filters**: Enhanced taxonomies data processing with individual term filters
- **Page Collection**: Updated page collection filter for better performance
- **WordPress Standards**: Improved code compliance with WordPress coding standards

## [1.3.0] - 2025-05-31

### Features
- **Term ID Management**: Added new field for term ID to enable better filtering and querying
- **Bundle Products**: Enhanced bundle product support with proper stock status and pricing
- **Product Updates**: Improved product update triggers on order status changes and meta updates
- **Menu Enhancements**: Added menu ID and custom icons to Typesense synchronization
- **Discount Support**: Added Flycart discount/promo plugin support with cart item handling

### Bug Fixes
- **Merge Conflicts**: Resolved various merge conflicts and integration issues
- **Bundle Sync**: Fixed bundle product synchronization errors
- **Class Loading**: Resolved class not found errors during initialization

### Performance
- **CLI Sync**: Enhanced CLI sync operations with better error handling
- **Memory Management**: Improved memory usage during large sync operations

## [1.2.0] - 2025-05-28

### Features
- **Gift Cards**: Added comprehensive gift card product type support with metadata handling
- **Smart Coupons**: Integrated Smart Coupons plugin support for enhanced discount functionality
- **Wholesale Features**: Added wholesale pricing and functionality support
- **Page Templates**: Added page template settings and management
- **Theme Configuration**: Added current theme configuration sync to Typesense
- **Author Information**: Added basic author info to post/page collections
- **Breadcrumbs**: Added breadcrumb support for posts and pages with blog page integration

### Bug Fixes
- **Product Sync**: Fixed issues where not all published products were being synced
- **Gift Card Pricing**: Resolved gift card price metadata and minimum price issues
- **Sync Spamming**: Prevented sync operations from overwhelming API with requests
- **Menu Items**: Fixed missing desktop submenu items and mobile menu issues
- **Currency Schema**: Fixed currency schema configuration problems

### Performance
- **Sync Optimization**: Improved sync request handling to prevent server overload
- **Product Filtering**: Enhanced product filtering to manually handle published status
- **Menu Processing**: Optimized menu processing with 3rd level submenu support

## [1.1.0] - 2025-04-15

### Features
- **Multi-Currency**: Enhanced multi-currency support with Aelia integration
- **Product Bundles**: Added comprehensive product bundle support with pricing
- **Wishlist Integration**: Added YITH Wishlist integration with cookie management
- **Product Addons**: Enhanced WooCommerce Product Addons support
- **Grouped Categories**: Added grouped sub-category filtering functionality
- **Container Styling**: Added container width and padding configuration options

### Bug Fixes
- **Backend Access**: Fixed 400 errors when accessing WordPress backend
- **Price Calculations**: Resolved product variation price calculation issues
- **Sync Load Time**: Improved sync performance for WooCommerce addons
- **Data Types**: Fixed data type issues preventing products from syncing to Typesense

### Performance
- **Addon Loading**: Optimized addon loading to only fetch on product initialization
- **Transient Usage**: Added transient caching for general addons to improve performance
- **Date Formatting**: Added current date and year shortcode support

## [1.0.0] - 2023-07-27

### Features
- **Initial Release**: First stable release of BlazeCommerce WordPress Plugin
- **Typesense Integration**: Complete Typesense search and synchronization functionality
- **WooCommerce Support**: Full WooCommerce product, category, and order integration
- **Headless Architecture**: Support for headless WordPress with Next.js frontend
- **Product Sync**: Comprehensive product synchronization with variations and metadata
- **Menu Management**: WordPress menu sync with mega menu support
- **Page Management**: Page and post synchronization with custom fields
- **Site Configuration**: Site info and settings synchronization
- **CLI Commands**: Command-line interface for bulk operations and maintenance

### Documentation
- **Setup Guide**: Complete installation and configuration documentation
- **API Reference**: Comprehensive API documentation for developers
- **Integration Guide**: Step-by-step integration instructions for various plugins
- **Troubleshooting**: Common issues and solutions documentation

---

## Links

- [GitHub Repository](https://github.com/blaze-commerce/blazecommerce-wp-plugin)
- [Documentation](https://github.com/blaze-commerce/blazecommerce-wp-plugin/tree/main/docs)
- [Issues](https://github.com/blaze-commerce/blazecommerce-wp-plugin/issues)
- [Releases](https://github.com/blaze-commerce/blazecommerce-wp-plugin/releases)
