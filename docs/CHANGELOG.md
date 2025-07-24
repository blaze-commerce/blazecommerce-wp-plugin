# Changelog

All notable changes to the BlazeCommerce WordPress Plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- WooCommerce checkout bug fixes implementation
- New `WooCommerceCheckout` feature class for checkout field modifications
- Enhanced `EditCartCheckout` feature with address reversal fix
- Comprehensive documentation for checkout fixes
- JavaScript-based address reversal correction system

### Fixed
- **Checkout Issue #1**: Incorrect checkbox label displaying "Shipping address same as billing address" instead of "Billing address same as shipping address"
- **Checkout Issue #2**: Address reversal bug where billing and shipping addresses were displayed in wrong sections during payment step
- Address display order in checkout payment step now shows correct information in correct sections

### Changed
- Enhanced `EditCartCheckout.php` with robust JavaScript fix for address reversal
- Updated `BlazeWooless.php` to register new checkout-related features
- Improved checkout user experience with correct labeling and address display

### Technical Details
- Added `app/Features/WooCommerceCheckout.php` for server-side checkout field modifications
- Enhanced `app/Features/EditCartCheckout.php` with client-side address reversal fix
- Implemented retry mechanism with up to 20 attempts for dynamic content handling
- Added comprehensive debug logging with "BlazeCommerce:" prefix for troubleshooting
- Used `woocommerce_checkout_fields` filter for label corrections
- Used `wp_footer` action for JavaScript injection on checkout pages only

### Documentation
- Added `docs/features/checkout-bug-fixes.md` - Complete technical documentation
- Added `docs/features/checkout-fixes-summary.md` - Quick reference implementation guide
- Included troubleshooting guides and verification steps
- Documented deployment procedures and testing scenarios

---

## Previous Releases

*Note: This changelog was created as part of the checkout bug fixes implementation. Previous release history may be added in future updates.*

---

## Release Notes Format

### Types of Changes
- **Added** for new features
- **Changed** for changes in existing functionality  
- **Deprecated** for soon-to-be removed features
- **Removed** for now removed features
- **Fixed** for any bug fixes
- **Security** for vulnerability fixes

### Versioning
This project follows [Semantic Versioning](https://semver.org/):
- **MAJOR** version for incompatible API changes
- **MINOR** version for backwards-compatible functionality additions
- **PATCH** version for backwards-compatible bug fixes
