<?php

namespace BlazeWooless\Extensions;

/**
 * Google Analytics & Ecommerce Tracking Enhancement
 * 
 * Comprehensive Google Analytics tracking solution for BlazeCommerce sites.
 * Ensures ALL user types (guests, customers, administrators) are tracked.
 * Supports both traditional and headless WordPress/WooCommerce setups.
 * 
 * @package BlazeWooless\Extensions
 * @since 1.0.0
 * @author BlazeCommerce Team
 */
class GoogleAnalytics {

    /**
     * Available GA plugins detected on the site
     * 
     * @var array
     */
    private $available_plugins = array();

    /**
     * Tracking configuration
     * 
     * @var array
     */
    private $config = array();

    /**
     * Constructor - Initialize the Google Analytics extension
     *
     * @since 1.0.0
     */
    public function __construct() {
        // Only initialize if WooCommerce is available
        if (!$this->is_woocommerce_available()) {
            return;
        }

        // Detect available GA plugins
        $this->detect_available_plugins();

        // Load configuration
        $this->load_configuration();

        // Setup tracking enhancements
        $this->setup_tracking_filters();
        $this->setup_order_tracking_hooks();

        // Admin settings integration
        add_action('admin_init', array($this, 'register_settings'));

        // Sync analytics configuration to Typesense
        add_action('init', array($this, 'sync_analytics_config_to_typesense'), 20);

        // Re-sync when plugins are activated/deactivated
        add_action('activated_plugin', array($this, 'on_plugin_change'));
        add_action('deactivated_plugin', array($this, 'on_plugin_change'));
    }

    /**
     * Check if WooCommerce is available and active
     * 
     * @since 1.0.0
     * @return bool True if WooCommerce is available, false otherwise
     */
    private function is_woocommerce_available() {
        return class_exists('WooCommerce') && function_exists('WC');
    }

    /**
     * Detect available Google Analytics plugins
     * 
     * @since 1.0.0
     * @return void
     */
    private function detect_available_plugins() {
        $this->available_plugins = array();
        
        // WooCommerce Google Analytics Integration
        if (class_exists('WC_Google_Analytics_Integration')) {
            $this->available_plugins['wc_ga_integration'] = true;
        }
        
        // Google Tag Manager for WordPress
        if (function_exists('gtm4wp_get_the_gtm_tag')) {
            $this->available_plugins['gtm4wp'] = true;
        }
        
        // MonsterInsights
        if (class_exists('MonsterInsights')) {
            $this->available_plugins['monster_insights'] = true;
        }

        // ExactMetrics (MonsterInsights rebrand)
        if (class_exists('ExactMetrics')) {
            $this->available_plugins['exact_metrics'] = true;
        }
    }

    /**
     * Load tracking configuration
     * 
     * @since 1.0.0
     * @return void
     */
    private function load_configuration() {
        $defaults = array(
            'enable_admin_tracking' => true,
            'enable_headless_integration' => true,
            'enable_debug_logging' => defined('WP_DEBUG') && WP_DEBUG,
            'trackable_order_statuses' => array('completed', 'processing'),
            'excluded_user_roles' => array(),
        );

        $this->config = apply_filters('blazecommerce_analytics_config', $defaults);
    }

    /**
     * Setup Google Analytics tracking filters
     * 
     * @since 1.0.0
     * @return void
     */
    private function setup_tracking_filters() {
        // WooCommerce Google Analytics Integration fixes
        if (isset($this->available_plugins['wc_ga_integration'])) {
            $this->fix_wc_ga_integration();
        }
        
        // Google Tag Manager for WordPress fixes
        if (isset($this->available_plugins['gtm4wp'])) {
            $this->fix_gtm4wp_integration();
        }
        
        // MonsterInsights fixes
        if (isset($this->available_plugins['monster_insights'])) {
            $this->fix_monster_insights_integration();
        }

        // ExactMetrics fixes
        if (isset($this->available_plugins['exact_metrics'])) {
            $this->fix_exact_metrics_integration();
        }
    }

    /**
     * Fix WooCommerce Google Analytics Integration user exclusions
     *
     * @since 1.0.0
     * @return void
     */
    private function fix_wc_ga_integration() {
        if (!$this->config['enable_admin_tracking']) {
            return;
        }

        // Force enable tracking for all users (highest priority)
        add_filter('woocommerce_ga_disable_tracking', '__return_false', 999);

        // Specifically target ecommerce tracking
        add_filter('woocommerce_google_analytics_disable_tracking', array($this, 'enable_ecommerce_tracking'), 10, 2);

        // Override the admin check in the integration class
        add_filter('pre_option_woocommerce_google_analytics_settings', array($this, 'override_ga_settings'));

        // Override user role exclusions for logged-in users
        add_filter('woocommerce_google_analytics_user_tracking_disabled', array($this, 'enable_user_tracking'), 10, 2);

        // Override any general tracking disabling for logged-in users
        add_filter('woocommerce_google_analytics_tracking_disabled', array($this, 'enable_logged_in_user_tracking'), 10, 1);

        // Additional comprehensive fixes for user role exclusions
        add_action('init', array($this, 'fix_wc_ga_user_exclusions'), 20);
    }

    /**
     * Enable ecommerce tracking for all users
     * 
     * @since 1.0.0
     * @param bool $disable Whether tracking is disabled
     * @param string $type The tracking type
     * @return bool False for ecommerce tracking, original value otherwise
     */
    public function enable_ecommerce_tracking($disable, $type) {
        // Never disable ecommerce tracking, regardless of user role
        if (in_array($type, array('ecommerce', 'enhanced_ecommerce'))) {
            return false;
        }
        return $disable;
    }

    /**
     * Override Google Analytics settings to ensure tracking is enabled
     *
     * @since 1.0.0
     * @param mixed $value The option value
     * @return mixed Modified option value
     */
    public function override_ga_settings($value) {
        if (is_array($value) && isset($value['ga_disable_tracking'])) {
            // Ensure tracking is never disabled for ecommerce
            $value['ga_disable_tracking'] = array();
        }
        return $value;
    }

    /**
     * Enable user tracking for logged-in users
     *
     * @since 1.0.0
     * @param bool $disabled Whether tracking is disabled
     * @param int $user_id The user ID
     * @return bool False to enable tracking
     */
    public function enable_user_tracking($disabled, $user_id) {
        // Always enable tracking for logged-in users in ecommerce contexts
        if (is_woocommerce() || is_cart() || is_checkout() || is_account_page()) {
            return false;
        }
        return $disabled;
    }

    /**
     * Enable tracking for logged-in users
     *
     * @since 1.0.0
     * @param bool $disabled Whether tracking is disabled
     * @return bool False to enable tracking for logged-in users
     */
    public function enable_logged_in_user_tracking($disabled) {
        // Always enable tracking for logged-in users in ecommerce contexts
        if (is_user_logged_in() && (is_woocommerce() || is_cart() || is_checkout() || is_account_page())) {
            return false;
        }
        return $disabled;
    }

    /**
     * Comprehensive fix for WooCommerce GA user exclusions
     *
     * @since 1.0.0
     * @return void
     */
    public function fix_wc_ga_user_exclusions() {
        // Check if WooCommerce Google Analytics Integration is active
        if (!class_exists('WC_Google_Analytics_Integration')) {
            return;
        }

        // Get the GA integration instance
        $integrations = WC()->integrations->get_integrations();
        if (!isset($integrations['google_analytics'])) {
            return;
        }

        $ga_integration = $integrations['google_analytics'];

        // Override the disable_tracking method to always return false for logged-in users
        add_filter('woocommerce_google_analytics_disable_tracking', function($disable, $type) use ($ga_integration) {
            // For ecommerce tracking, never disable for logged-in users
            if (in_array($type, array('ecommerce', 'enhanced_ecommerce')) && is_user_logged_in()) {
                return false;
            }
            return $disable;
        }, 999, 2);

        // Override any user role checks
        add_filter('pre_option_woocommerce_google_analytics_settings', function($value) {
            if (is_array($value)) {
                // Clear any user role exclusions
                if (isset($value['ga_disable_tracking'])) {
                    $value['ga_disable_tracking'] = array();
                }
                // Ensure tracking is enabled for logged-in users
                if (isset($value['ga_track_user_id'])) {
                    $value['ga_track_user_id'] = 'yes';
                }
            }
            return $value;
        }, 999);
    }

    /**
     * Fix Google Tag Manager for WordPress admin exclusion
     * 
     * @since 1.0.0
     * @return void
     */
    private function fix_gtm4wp_integration() {
        if (!$this->config['enable_admin_tracking']) {
            return;
        }

        // Ensure GTM tracking works for all users on ecommerce pages
        add_filter('gtm4wp_disable_tracking', array($this, 'enable_gtm_ecommerce_tracking'), 10, 1);
        
        // Override admin exclusion for ecommerce events
        add_filter('gtm4wp_admin_disable_tracking', array($this, 'enable_gtm_ecommerce_tracking'), 10, 1);
    }

    /**
     * Enable GTM tracking for ecommerce contexts
     * 
     * @since 1.0.0
     * @param bool $disable Whether tracking is disabled
     * @return bool False for ecommerce contexts, original value otherwise
     */
    public function enable_gtm_ecommerce_tracking($disable) {
        if ($this->is_ecommerce_context()) {
            return false;
        }
        return $disable;
    }

    /**
     * Fix MonsterInsights admin exclusion
     * 
     * @since 1.0.0
     * @return void
     */
    private function fix_monster_insights_integration() {
        if (!$this->config['enable_admin_tracking']) {
            return;
        }

        // Override MonsterInsights admin exclusion for ecommerce
        add_filter('monsterinsights_track_user', array($this, 'enable_monster_insights_ecommerce_tracking'), 10, 2);
        
        // Ensure ecommerce tracking is enabled for all users
        add_filter('monsterinsights_ecommerce_is_disabled', array($this, 'enable_monster_insights_ecommerce'), 10, 1);
    }

    /**
     * Enable MonsterInsights tracking for ecommerce contexts
     * 
     * @since 1.0.0
     * @param bool $track Whether to track the user
     * @param int $user_id User ID
     * @return bool True for ecommerce contexts, original value otherwise
     */
    public function enable_monster_insights_ecommerce_tracking($track, $user_id) {
        if ($this->is_ecommerce_context()) {
            return true;
        }
        return $track;
    }

    /**
     * Enable MonsterInsights ecommerce tracking
     * 
     * @since 1.0.0
     * @param bool $disabled Whether ecommerce tracking is disabled
     * @return bool False for ecommerce contexts, original value otherwise
     */
    public function enable_monster_insights_ecommerce($disabled) {
        if ($this->is_ecommerce_context()) {
            return false;
        }
        return $disabled;
    }

    /**
     * Fix ExactMetrics admin exclusion (same as MonsterInsights)
     * 
     * @since 1.0.0
     * @return void
     */
    private function fix_exact_metrics_integration() {
        if (!$this->config['enable_admin_tracking']) {
            return;
        }

        // ExactMetrics uses same filters as MonsterInsights
        add_filter('exactmetrics_track_user', array($this, 'enable_monster_insights_ecommerce_tracking'), 10, 2);
        add_filter('exactmetrics_ecommerce_is_disabled', array($this, 'enable_monster_insights_ecommerce'), 10, 1);
    }

    /**
     * Check if current context is ecommerce-related
     * 
     * @since 1.0.0
     * @return bool True if in ecommerce context, false otherwise
     */
    private function is_ecommerce_context() {
        if (!$this->is_woocommerce_available()) {
            return false;
        }
        
        return is_woocommerce() || is_cart() || is_checkout() || is_account_page() || 
               is_shop() || is_product_category() || is_product_tag() || is_product();
    }

    /**
     * Setup order tracking hooks for headless integration
     * 
     * @since 1.0.0
     * @return void
     */
    private function setup_order_tracking_hooks() {
        if (!$this->config['enable_headless_integration']) {
            return;
        }

        // Hook into order completion
        add_action('woocommerce_thankyou', array($this, 'send_purchase_data_to_frontend'), 10, 1);
        
        // Add order tracking listener to checkout pages
        add_action('wp_footer', array($this, 'add_order_tracking_listener'));
        
        // Add tracking data to order received pages
        add_action('woocommerce_order_details_after_order_table', array($this, 'add_order_tracking_data'), 10, 1);
    }

    /**
     * Register admin settings for the analytics extension
     * 
     * @since 1.0.0
     * @return void
     */
    public function register_settings() {
        // Register settings section in BlazeCommerce settings
        add_settings_section(
            'blazecommerce_analytics',
            __('Google Analytics Tracking', 'blaze-wooless'),
            array($this, 'render_settings_section'),
            'blazecommerce_settings'
        );

        // Register individual settings
        register_setting('blazecommerce_settings', 'blazecommerce_analytics_enable_admin_tracking');
        register_setting('blazecommerce_settings', 'blazecommerce_analytics_enable_headless');
        register_setting('blazecommerce_settings', 'blazecommerce_analytics_debug_mode');
    }

    /**
     * Render the analytics settings section
     * 
     * @since 1.0.0
     * @return void
     */
    public function render_settings_section() {
        echo '<p>' . __('Configure Google Analytics tracking enhancements for your BlazeCommerce site.', 'blaze-wooless') . '</p>';
        
        // Show detected plugins
        if (!empty($this->available_plugins)) {
            echo '<p><strong>' . __('Detected Analytics Plugins:', 'blaze-wooless') . '</strong></p>';
            echo '<ul>';
            foreach ($this->available_plugins as $plugin => $active) {
                $plugin_names = array(
                    'wc_ga_integration' => 'WooCommerce Google Analytics Integration',
                    'gtm4wp' => 'Google Tag Manager for WordPress',
                    'monster_insights' => 'MonsterInsights',
                    'exact_metrics' => 'ExactMetrics'
                );
                echo '<li>✅ ' . esc_html($plugin_names[$plugin] ?? $plugin) . '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p><em>' . __('No supported analytics plugins detected.', 'blaze-wooless') . '</em></p>';
        }
    }

    /**
     * Send purchase data to frontend for tracking
     *
     * @since 1.0.0
     * @param int $order_id The order ID
     * @return void
     */
    public function send_purchase_data_to_frontend($order_id) {
        if (!$order_id || !$this->is_woocommerce_available()) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            $this->log_error('Invalid order ID: ' . $order_id);
            return;
        }

        // Debug logging for user type
        $user_type = is_user_logged_in() ? 'logged-in' : 'guest';
        $user_id = is_user_logged_in() ? get_current_user_id() : 'guest';
        $this->log_debug("Processing order {$order_id} for {$user_type} user (ID: {$user_id})");

        // Only track orders with trackable statuses
        if (!in_array($order->get_status(), $this->config['trackable_order_statuses'])) {
            $this->log_debug("Order {$order_id} status '{$order->get_status()}' not in trackable statuses");
            return;
        }

        $order_data = $this->prepare_order_data($order);
        if (empty($order_data)) {
            $this->log_error('Empty order data for order: ' . $order_id);
            return;
        }

        $this->log_debug("Outputting tracking script for {$user_type} user order: {$order_id}");

        // Output tracking JavaScript
        $this->output_order_tracking_script($order_data);
    }

    /**
     * Prepare order data for frontend tracking
     *
     * @since 1.0.0
     * @param \WC_Order $order The WooCommerce order object
     * @return array|null Prepared order data or null on failure
     */
    private function prepare_order_data($order) {
        if (!$order) {
            return null;
        }

        try {
            $order_data = array(
                'orderId' => $order->get_order_number(),
                'total' => floatval($order->get_total()),
                'currency' => $order->get_currency(),
                'items' => array()
            );

            // Add order items with safety checks
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                if (!$product) {
                    continue;
                }

                $quantity = $item->get_quantity();
                $total = $item->get_total();

                // Calculate unit price safely
                $unit_price = $quantity > 0 ? floatval($total / $quantity) : 0;

                $order_data['items'][] = array(
                    'sku' => $product->get_sku() ?: $product->get_id(),
                    'name' => $item->get_name(),
                    'quantity' => intval($quantity),
                    'price' => $unit_price
                );
            }

            // Apply filters to allow customization
            return apply_filters('blazecommerce_analytics_order_data', $order_data, $order);

        } catch (Exception $e) {
            $this->log_error('Error preparing order data: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Output order tracking JavaScript
     *
     * @since 1.0.0
     * @param array $order_data Prepared order data
     * @return void
     */
    private function output_order_tracking_script($order_data) {
        if (empty($order_data)) {
            return;
        }

        // Sanitize data for JavaScript output
        $json_data = wp_json_encode($order_data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

        if (!$json_data) {
            $this->log_error('Failed to encode order data to JSON');
            return;
        }
        ?>
        <script type="text/javascript">
        (function() {
            'use strict';

            var orderData = <?php echo $json_data; ?>;
            var trackingMethods = [];
            var userType = '<?php echo is_user_logged_in() ? 'logged-in' : 'guest'; ?>';
            var userId = '<?php echo is_user_logged_in() ? get_current_user_id() : 'guest'; ?>';

            console.log('BlazeCommerce Analytics: Processing order for ' + userType + ' user (ID: ' + userId + '):', orderData);

            // Method 1: Send to parent window (for iframe checkouts)
            if (window.parent && window.parent !== window) {
                try {
                    window.parent.postMessage({
                        type: 'BLAZECOMMERCE_ORDER_COMPLETE',
                        orderData: orderData
                    }, '*');
                    trackingMethods.push('postMessage');
                } catch (e) {
                    console.warn('BlazeCommerce Analytics: Could not send to parent window:', e);
                }
            }

            // Method 2: Direct GTM dataLayer push
            if (typeof window.dataLayer !== 'undefined') {
                try {
                    window.dataLayer.push({
                        'event': 'purchase',
                        'transaction_id': orderData.orderId,
                        'value': orderData.total,
                        'currency': orderData.currency,
                        'items': orderData.items.map(function(item) {
                            return {
                                'item_id': item.sku,
                                'item_name': item.name,
                                'quantity': item.quantity,
                                'price': item.price
                            };
                        })
                    });
                    trackingMethods.push('dataLayer');
                    console.log('BlazeCommerce Analytics: Purchase event sent to dataLayer:', orderData.orderId);
                } catch (e) {
                    console.warn('BlazeCommerce Analytics: Could not send to dataLayer:', e);
                }
            }

            // Method 3: Store in sessionStorage for frontend pickup
            if (typeof Storage !== 'undefined') {
                try {
                    sessionStorage.setItem('blazecommerce_order_complete', JSON.stringify(orderData));
                    trackingMethods.push('sessionStorage');
                } catch (e) {
                    console.warn('BlazeCommerce Analytics: Could not store in sessionStorage:', e);
                }
            }

            // Method 4: Custom event for advanced integrations
            try {
                var customEvent = new CustomEvent('blazecommerce_order_complete', {
                    detail: orderData
                });
                window.dispatchEvent(customEvent);
                trackingMethods.push('customEvent');
            } catch (e) {
                console.warn('BlazeCommerce Analytics: Could not dispatch custom event:', e);
            }

            console.log('BlazeCommerce Analytics: Order completion tracked via:', trackingMethods.join(', '));
        })();
        </script>
        <?php
    }

    /**
     * Add order tracking listener to checkout pages
     *
     * @since 1.0.0
     * @return void
     */
    public function add_order_tracking_listener() {
        // Only add on WooCommerce order-received pages
        if (!$this->is_woocommerce_available() || !is_wc_endpoint_url('order-received')) {
            return;
        }
        ?>
        <script type="text/javascript">
        (function() {
            'use strict';

            // Listen for messages from checkout iframe or other sources
            window.addEventListener('message', function(event) {
                if (event.data && event.data.type === 'BLAZECOMMERCE_ORDER_COMPLETE') {
                    console.log('BlazeCommerce Analytics: Received order completion data:', event.data.orderData);

                    // Trigger GTM purchase event if dataLayer is available
                    if (typeof window.dataLayer !== 'undefined') {
                        var orderData = event.data.orderData;
                        window.dataLayer.push({
                            'event': 'purchase',
                            'transaction_id': orderData.orderId,
                            'value': orderData.total,
                            'currency': orderData.currency,
                            'items': orderData.items.map(function(item) {
                                return {
                                    'item_id': item.sku,
                                    'item_name': item.name,
                                    'quantity': item.quantity,
                                    'price': item.price
                                };
                            })
                        });
                        console.log('BlazeCommerce Analytics: Purchase event processed from message');
                    }
                }
            });

            // Also listen for custom events
            window.addEventListener('blazecommerce_order_complete', function(event) {
                console.log('BlazeCommerce Analytics: Custom order complete event received:', event.detail);
            });
        })();
        </script>
        <?php
    }

    /**
     * Add order tracking data to order details
     *
     * @since 1.0.0
     * @param \WC_Order $order The order object
     * @return void
     */
    public function add_order_tracking_data($order) {
        if (!$order || !$this->is_woocommerce_available()) {
            return;
        }

        $order_data = $this->prepare_order_data($order);
        if ($order_data) {
            $this->output_order_tracking_script($order_data);
        }
    }

    /**
     * Log tracking errors for debugging
     *
     * @since 1.0.0
     * @param string $message Error message to log
     * @return void
     */
    private function log_error($message) {
        if ($this->config['enable_debug_logging']) {
            error_log('BlazeCommerce Analytics Error: ' . $message);
        }
    }

    /**
     * Log debug information
     *
     * @since 1.0.0
     * @param string $message Debug message to log
     * @return void
     */
    private function log_debug($message) {
        if ($this->config['enable_debug_logging']) {
            error_log('BlazeCommerce Analytics Debug: ' . $message);
        }
    }

    /**
     * Sync analytics configuration to Typesense site_info collection
     *
     * This allows the frontend to know which analytics features are available
     * and conditionally enable tracking functionality
     *
     * @since 1.0.0
     * @return void
     */
    public function sync_analytics_config_to_typesense() {
        // Only sync if Typesense is available
        if (!class_exists('BlazeWooless\TypesenseClient')) {
            return;
        }

        $typesense_client = \BlazeWooless\TypesenseClient::get_instance();
        if (!$typesense_client->can_connect()) {
            return;
        }

        try {
            // Prepare analytics configuration data
            $analytics_config = $this->prepare_analytics_config_for_sync();

            // Sync each configuration item to Typesense
            foreach ($analytics_config as $key => $value) {
                $document = array(
                    'name' => $key,
                    'value' => $value,
                    'updated_at' => time(),
                );

                $typesense_client->site_info()->upsert($document);
            }

            // Log successful sync in debug mode
            if ($this->config['enable_debug_logging']) {
                error_log('BlazeCommerce Analytics: Configuration synced to Typesense');
            }

        } catch (Exception $e) {
            $this->log_error('Failed to sync analytics config to Typesense: ' . $e->getMessage());
        }
    }

    /**
     * Prepare analytics configuration data for Typesense sync
     *
     * @since 1.0.0
     * @return array Analytics configuration data
     */
    private function prepare_analytics_config_for_sync() {
        $config = array();

        // Available plugins status
        foreach ($this->available_plugins as $plugin => $available) {
            $config["analytics_plugin_{$plugin}"] = $available ? 'enabled' : 'disabled';
        }

        // Feature flags
        $config['analytics_admin_tracking'] = $this->config['enable_admin_tracking'] ? 'enabled' : 'disabled';
        $config['analytics_headless_integration'] = $this->config['enable_headless_integration'] ? 'enabled' : 'disabled';
        $config['analytics_debug_logging'] = $this->config['enable_debug_logging'] ? 'enabled' : 'disabled';

        // Trackable order statuses
        $config['analytics_trackable_statuses'] = json_encode($this->config['trackable_order_statuses']);

        // Extension status
        $config['analytics_extension_active'] = 'enabled';
        $config['analytics_extension_version'] = '1.0.0';

        // Apply filters to allow customization
        return apply_filters('blazecommerce_analytics_typesense_config', $config);
    }

    /**
     * Handle plugin activation/deactivation
     *
     * Re-detects available plugins and syncs updated configuration
     *
     * @since 1.0.0
     * @return void
     */
    public function on_plugin_change() {
        // Re-detect available plugins
        $this->detect_available_plugins();

        // Re-sync configuration
        $this->sync_analytics_config_to_typesense();
    }

    /**
     * Get the singleton instance
     *
     * @since 1.0.0
     * @return GoogleAnalytics
     */
    public static function get_instance() {
        static $instance = null;
        if (null === $instance) {
            $instance = new self();
        }
        return $instance;
    }
}
