# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is the Blaze Commerce WordPress Plugin - a WooCommerce integration that enables headless commerce functionality with Typesense search and GraphQL API support. The plugin uses a blue-green deployment pattern with collection aliasing for zero-downtime synchronization.

## Core Architecture

The plugin follows PSR-4 autoloading with namespace `BlazeWooless\`:

- **app/Collections/** - Manages Typesense collections and aliasing system
- **app/Extensions/** - Integrations with 30+ WooCommerce extensions
- **app/Features/** - Core features (Auth, Cart, CLI, Sync, etc.)
- **app/Settings/** - Admin interface and configuration
- **blocks/** - Gutenberg block components

Key architectural patterns:
- Singleton pattern for major components
- WordPress hook-based integration
- Collection aliasing for zero-downtime deployment
- Filter-driven extensibility

## Development Commands

### Build & Setup
```bash
# Install dependencies
composer install
npm install

# Build Gutenberg blocks
npm run build:blocks
# or
cd blocks && npm install && npm run build
```

### Version Management
```bash
npm run version:patch    # Bump patch version (e.g., 1.5.2 → 1.5.3)
npm run version:minor    # Bump minor version (e.g., 1.5.2 → 1.6.0)
npm run version:major    # Bump major version (e.g., 1.5.2 → 2.0.0)
```

### Release Process
```bash
npm run changelog         # Generate changelog from commits
npm run prepare-release   # Full release prep (changelog + build + git add)
npm run release          # Create and push release tag
```

### WP-CLI Commands for Testing
```bash
# Product synchronization
wp bc-sync product --all
wp bc-sync product --variants
wp bc-sync product --nonvariants

# Other data syncs
wp bc-sync taxonomy --all
wp bc-sync page_and_post --all
wp bc-sync menu --all
wp bc-sync navigation --all
wp bc-sync site_info --all

# Collection alias management
wp bc-sync alias --list
wp bc-sync alias --status
wp bc-sync alias --cleanup=product
wp bc-sync alias --force-alias=product

# Cache operations
wp bc-sync cache
wp bc-sync cache --clear
```

## Important Files

- **blaze-wooless.php** - Main plugin entry point
- **app/Features/Cli/BlazeWooless.php** - WP-CLI command implementations
- **app/Collections/CollectionAlias.php** - Zero-downtime sync logic
- **app/Features/Sync/Controllers/** - Sync controllers for different data types

## Commit Convention

Use conventional commits for automatic versioning:
- `feat:` - New feature (triggers minor version bump)
- `fix:` - Bug fix (triggers patch version bump)
- `feat!:` or `BREAKING CHANGE:` - Breaking change (triggers major version bump)

## Testing Approach

The plugin uses manual testing scripts in the `test/` directory:
- `test/test-alias-implementation.php` - Tests collection aliasing
- `test/test-country-specific-images.php` - Tests country features
- `tests/export-import-test.php` - Tests settings import/export

Run these manually in a WordPress environment to verify functionality.

## Key Development Considerations

1. **Collection Aliasing**: Always test alias operations thoroughly as they affect live search functionality
2. **Extension Support**: When modifying sync logic, consider impact on all supported extensions
3. **Performance**: Large catalogs require efficient batching - see sync controllers for patterns
4. **Backwards Compatibility**: Maintain filter/action signatures for existing integrations
5. **GraphQL Dependencies**: Ensure WP GraphQL and related plugins are active during development

## Required WordPress Environment

- WordPress 5.0+
- WooCommerce (active)
- WP GraphQL
- WP GraphQL CORS
- WP GraphQL JWT Authentication
- WP GraphQL WooCommerce