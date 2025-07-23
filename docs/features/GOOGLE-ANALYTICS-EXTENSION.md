# Google Analytics Extension

## Overview

The Google Analytics Extension is a comprehensive tracking solution integrated into the BlazeCommerce plugin. It ensures ALL user types (guests, customers, administrators) are properly tracked for ecommerce events, addressing common issues where admin users are excluded from analytics.

## Features

### ✅ **Universal Admin Tracking**
- Overrides admin exclusions in popular GA plugins
- Ensures administrators' purchases are tracked
- Maintains tracking for all user roles

### ✅ **Multi-Plugin Support**
- **WooCommerce Google Analytics Integration**
- **Google Tag Manager for WordPress (GTM4WP)**
- **MonsterInsights**
- **ExactMetrics**

### ✅ **Headless Architecture Support**
- Multiple communication methods for frontend integration
- PostMessage API for iframe checkouts
- SessionStorage for frontend pickup
- Custom events for advanced integrations

### ✅ **WordPress Standards Compliant**
- Proper namespacing and class structure
- Comprehensive error handling
- Configurable via WordPress filters
- Admin settings integration

## Architecture

### Extension Structure
```
blazecommerce-wp-plugin/
├── app/Extensions/
│   └── GoogleAnalytics.php    ← Main extension class
└── app/BlazeWooless.php       ← Extension registration
```

### Class Structure
```php
namespace BlazeWooless\Extensions;

class GoogleAnalytics {
    private $available_plugins = array();
    private $config = array();
    
    public function __construct()           // Auto-initialization
    private function detect_available_plugins()  // Plugin detection
    private function setup_tracking_filters()    // Override admin exclusions
    private function setup_order_tracking_hooks() // Headless integration
    public function send_purchase_data_to_frontend() // Order completion
}
```

## Configuration

### Default Settings
```php
$defaults = array(
    'enable_admin_tracking' => true,
    'enable_headless_integration' => true,
    'enable_debug_logging' => defined('WP_DEBUG') && WP_DEBUG,
    'trackable_order_statuses' => array('completed', 'processing'),
    'excluded_user_roles' => array(),
);
```

### Customization Hooks
```php
// Modify configuration
add_filter('blazecommerce_analytics_config', function($config) {
    $config['enable_admin_tracking'] = false; // Disable admin tracking
    $config['trackable_order_statuses'][] = 'on-hold'; // Add order status
    return $config;
});

// Customize order data
add_filter('blazecommerce_analytics_order_data', function($data, $order) {
    $data['custom_field'] = get_post_meta($order->get_id(), 'custom_field', true);
    return $data;
}, 10, 2);
```

## Plugin Integration Fixes

### WooCommerce Google Analytics Integration
```php
// Override admin exclusion
add_filter('woocommerce_ga_disable_tracking', '__return_false', 999);

// Target ecommerce tracking specifically
add_filter('woocommerce_google_analytics_disable_tracking', 
    array($this, 'enable_ecommerce_tracking'), 10, 2);
```

### Google Tag Manager for WordPress
```php
// Enable GTM for ecommerce contexts
add_filter('gtm4wp_disable_tracking', 
    array($this, 'enable_gtm_ecommerce_tracking'), 10, 1);
```

### MonsterInsights/ExactMetrics
```php
// Override user tracking exclusion
add_filter('monsterinsights_track_user', 
    array($this, 'enable_monster_insights_ecommerce_tracking'), 10, 2);
```

## Headless Integration

### Order Completion Tracking
When an order is completed, the extension sends data via multiple methods:

#### 1. PostMessage API
```javascript
window.parent.postMessage({
    type: 'BLAZECOMMERCE_ORDER_COMPLETE',
    orderData: orderData
}, '*');
```

#### 2. GTM DataLayer
```javascript
window.dataLayer.push({
    'event': 'purchase',
    'transaction_id': orderData.orderId,
    'value': orderData.total,
    'currency': orderData.currency,
    'items': orderData.items
});
```

#### 3. SessionStorage
```javascript
sessionStorage.setItem('blazecommerce_order_complete', JSON.stringify(orderData));
```

#### 4. Custom Events
```javascript
var customEvent = new CustomEvent('blazecommerce_order_complete', {
    detail: orderData
});
window.dispatchEvent(customEvent);
```

### Frontend Integration (Next.js)
```javascript
// Listen for order completion
window.addEventListener('message', function(event) {
    if (event.data && event.data.type === 'BLAZECOMMERCE_ORDER_COMPLETE') {
        const orderData = event.data.orderData;
        // Track with your analytics
        gtag('event', 'purchase', {
            transaction_id: orderData.orderId,
            value: orderData.total,
            currency: orderData.currency,
            items: orderData.items
        });
    }
});

// Check sessionStorage on page load
const orderData = sessionStorage.getItem('blazecommerce_order_complete');
if (orderData) {
    const data = JSON.parse(orderData);
    trackPurchase(data);
    sessionStorage.removeItem('blazecommerce_order_complete');
}
```

## Order Data Structure

### Prepared Order Data
```json
{
    "orderId": "12345",
    "total": 89.95,
    "currency": "USD",
    "items": [
        {
            "sku": "PRODUCT-001",
            "name": "Sample Product",
            "quantity": 2,
            "price": 44.95
        }
    ]
}
```

### GTM Enhanced Ecommerce Format
```json
{
    "event": "purchase",
    "transaction_id": "12345",
    "value": 89.95,
    "currency": "USD",
    "items": [
        {
            "item_id": "PRODUCT-001",
            "item_name": "Sample Product",
            "quantity": 2,
            "price": 44.95
        }
    ]
}
```

## Testing & Debugging

### Enable Debug Mode
```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Browser Console Messages
```
✅ BlazeCommerce Analytics: Purchase event sent to dataLayer: 12345
✅ BlazeCommerce Analytics: Order completion tracked via: dataLayer, sessionStorage
❌ BlazeCommerce Analytics: Could not send to parent window: Error message
```

### WordPress Debug Log
```
BlazeCommerce Analytics Error: Invalid order ID: 0
BlazeCommerce Analytics Error: Failed to encode order data to JSON
```

### Manual Testing
1. **Guest Purchase**: Complete order as guest, verify tracking
2. **Customer Purchase**: Login and complete order, verify tracking  
3. **Admin Purchase**: Login as admin, complete order, verify tracking works
4. **Browser Console**: Check for tracking confirmation messages
5. **GA Real-Time**: Monitor purchase events in Google Analytics

## Admin Settings Integration

The extension integrates with BlazeCommerce admin settings:

### Settings Section
- **Location**: BlazeCommerce Settings > Google Analytics Tracking
- **Options**: Enable admin tracking, headless integration, debug mode
- **Plugin Detection**: Shows detected analytics plugins

### Settings Fields
```php
register_setting('blazecommerce_settings', 'blazecommerce_analytics_enable_admin_tracking');
register_setting('blazecommerce_settings', 'blazecommerce_analytics_enable_headless');
register_setting('blazecommerce_settings', 'blazecommerce_analytics_debug_mode');
```

## Troubleshooting

### Common Issues

#### No Purchase Events
1. Check if WooCommerce is active
2. Verify GA/GTM plugin is installed and configured
3. Check order status (only 'completed'/'processing' tracked by default)
4. Enable debug logging and check WordPress logs

#### Admin Purchases Not Tracked
1. Verify `enable_admin_tracking` is true in config
2. Check browser console for error messages
3. Test with different user roles

#### Headless Integration Not Working
1. Verify `enable_headless_integration` is true
2. Check frontend JavaScript for event listeners
3. Test PostMessage communication
4. Verify sessionStorage is accessible

### Debug Commands
```php
// Check detected plugins
$analytics = \BlazeWooless\Extensions\GoogleAnalytics::get_instance();
var_dump($analytics->get_available_plugins());

// Test order data preparation
$order = wc_get_order(123);
$data = $analytics->prepare_order_data($order);
var_dump($data);
```

## Performance Considerations

### Minimal Impact
- **Conditional Loading**: Only loads when WooCommerce is active
- **Efficient Detection**: Plugin detection cached during initialization
- **Lazy Execution**: Tracking only fires on order completion
- **Error Handling**: Graceful degradation prevents site breakage

### Memory Usage
- **Singleton Pattern**: Single instance per request
- **Minimal Storage**: Only stores configuration and detected plugins
- **No Database Queries**: Uses WordPress options API efficiently

---

**Version**: 1.0.0  
**Integration**: BlazeCommerce Plugin v1.15.0+  
**Compatibility**: WordPress 5.0+, WooCommerce 3.0+  
**Author**: BlazeCommerce Team
