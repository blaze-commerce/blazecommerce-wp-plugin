# Architecture Guidelines Rule

**Priority: Auto**

**Description:** Define architectural patterns and organizational standards for WordPress plugin development to ensure scalable, maintainable, and well-structured code that follows WordPress and WooCommerce best practices.

## Directory Structure Standards

### 1. Required Directory Organization
```
blazecommerce-wp-plugin/
├── admin/                      # Admin-specific functionality
│   ├── css/                   # Admin stylesheets
│   ├── js/                    # Admin JavaScript
│   ├── partials/              # Admin template parts
│   └── class-admin.php        # Main admin class
├── includes/                   # Core plugin functionality
│   ├── api/                   # REST API endpoints
│   ├── class-activator.php    # Plugin activation
│   ├── class-deactivator.php  # Plugin deactivation
│   ├── class-loader.php       # Hook loader
│   ├── class-i18n.php         # Internationalization
│   └── class-blazecommerce.php # Main plugin class
├── public/                     # Public-facing functionality
│   ├── css/                   # Public stylesheets
│   ├── js/                    # Public JavaScript
│   ├── partials/              # Public template parts
│   └── class-public.php       # Main public class
├── languages/                  # Translation files
├── tests/                      # Unit and integration tests
│   ├── unit/                  # Unit tests
│   ├── integration/           # Integration tests
│   └── bootstrap.php          # Test bootstrap
├── vendor/                     # Composer dependencies
├── blazecommerce.php          # Main plugin file
├── uninstall.php              # Uninstall script
├── readme.txt                 # WordPress.org readme
├── composer.json              # Composer configuration
└── phpunit.xml                # PHPUnit configuration
```

### 2. File Naming Conventions
- Use lowercase with hyphens for file names
- Prefix class files with `class-`
- Use descriptive names that reflect functionality
- Follow WordPress plugin development standards

## Plugin Architecture Patterns

### 1. Main Plugin Class Structure
```php
<?php
// includes/class-blazecommerce.php

/**
 * The core plugin class
 */
class BlazeCommerce {
    
    /**
     * The loader that's responsible for maintaining and registering all hooks
     */
    protected $loader;
    
    /**
     * The unique identifier of this plugin
     */
    protected $plugin_name;
    
    /**
     * The current version of the plugin
     */
    protected $version;
    
    /**
     * Define the core functionality of the plugin
     */
    public function __construct() {
        if (defined('BLAZECOMMERCE_VERSION')) {
            $this->version = BLAZECOMMERCE_VERSION;
        } else {
            $this->version = '1.0.0';
        }
        $this->plugin_name = 'blazecommerce';
        
        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }
    
    /**
     * Load the required dependencies for this plugin
     */
    private function load_dependencies() {
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-loader.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-i18n.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-admin.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'public/class-public.php';
        
        $this->loader = new BlazeCommerce_Loader();
    }
    
    /**
     * Define the locale for this plugin for internationalization
     */
    private function set_locale() {
        $plugin_i18n = new BlazeCommerce_i18n();
        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
    }
    
    /**
     * Register all of the hooks related to the admin area functionality
     */
    private function define_admin_hooks() {
        $plugin_admin = new BlazeCommerce_Admin($this->get_plugin_name(), $this->get_version());
        
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_admin_menu');
    }
    
    /**
     * Register all of the hooks related to the public-facing functionality
     */
    private function define_public_hooks() {
        $plugin_public = new BlazeCommerce_Public($this->get_plugin_name(), $this->get_version());
        
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
    }
    
    /**
     * Run the loader to execute all of the hooks with WordPress
     */
    public function run() {
        $this->loader->run();
    }
}
```

### 2. Hook Loader Pattern
```php
<?php
// includes/class-loader.php

/**
 * Register all actions and filters for the plugin
 */
class BlazeCommerce_Loader {
    
    /**
     * The array of actions registered with WordPress
     */
    protected $actions;
    
    /**
     * The array of filters registered with WordPress
     */
    protected $filters;
    
    /**
     * Initialize the collections used to maintain the actions and filters
     */
    public function __construct() {
        $this->actions = array();
        $this->filters = array();
    }
    
    /**
     * Add a new action to the collection to be registered with WordPress
     */
    public function add_action($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->actions = $this->add($this->actions, $hook, $component, $callback, $priority, $accepted_args);
    }
    
    /**
     * Add a new filter to the collection to be registered with WordPress
     */
    public function add_filter($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->filters = $this->add($this->filters, $hook, $component, $callback, $priority, $accepted_args);
    }
    
    /**
     * A utility function that is used to register the actions and filters into a single collection
     */
    private function add($hooks, $hook, $component, $callback, $priority, $accepted_args) {
        $hooks[] = array(
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args
        );
        
        return $hooks;
    }
    
    /**
     * Register the filters and actions with WordPress
     */
    public function run() {
        foreach ($this->filters as $hook) {
            add_filter($hook['hook'], array($hook['component'], $hook['callback']), $hook['priority'], $hook['accepted_args']);
        }
        
        foreach ($this->actions as $hook) {
            add_action($hook['hook'], array($hook['component'], $hook['callback']), $hook['priority'], $hook['accepted_args']);
        }
    }
}
```

## Database Architecture

### 1. Custom Table Management
```php
<?php
// includes/class-database.php

/**
 * Database operations for BlazeCommerce
 */
class BlazeCommerce_Database {
    
    /**
     * Create custom tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Orders table
        $table_name = $wpdb->prefix . 'blazecommerce_orders';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) NOT NULL,
            external_id varchar(100) DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            sync_status varchar(20) NOT NULL DEFAULT 'pending',
            data longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY order_id (order_id),
            KEY external_id (external_id),
            KEY status (status),
            KEY sync_status (sync_status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Products table
        $products_table = $wpdb->prefix . 'blazecommerce_products';
        $products_sql = "CREATE TABLE $products_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) NOT NULL,
            external_sku varchar(100) DEFAULT NULL,
            sync_status varchar(20) NOT NULL DEFAULT 'pending',
            last_sync datetime DEFAULT NULL,
            sync_data longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY product_id (product_id),
            KEY external_sku (external_sku),
            KEY sync_status (sync_status),
            KEY last_sync (last_sync)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        dbDelta($products_sql);
        
        // Store database version
        add_option('blazecommerce_db_version', BLAZECOMMERCE_DB_VERSION);
    }
    
    /**
     * Update database schema if needed
     */
    public static function update_database() {
        $installed_version = get_option('blazecommerce_db_version');
        
        if ($installed_version !== BLAZECOMMERCE_DB_VERSION) {
            self::create_tables();
        }
    }
}
```

### 2. Data Access Layer
```php
<?php
// includes/class-order-repository.php

/**
 * Order data access layer
 */
class BlazeCommerce_Order_Repository {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'blazecommerce_orders';
    }
    
    /**
     * Save order data
     */
    public function save($order_data) {
        global $wpdb;
        
        $data = array(
            'order_id' => $order_data['order_id'],
            'external_id' => $order_data['external_id'] ?? null,
            'status' => $order_data['status'],
            'sync_status' => $order_data['sync_status'] ?? 'pending',
            'data' => wp_json_encode($order_data['data'] ?? array()),
            'updated_at' => current_time('mysql')
        );
        
        $existing = $this->get_by_order_id($order_data['order_id']);
        
        if ($existing) {
            $result = $wpdb->update(
                $this->table_name,
                $data,
                array('order_id' => $order_data['order_id']),
                array('%d', '%s', '%s', '%s', '%s', '%s'),
                array('%d')
            );
        } else {
            $data['created_at'] = current_time('mysql');
            $result = $wpdb->insert(
                $this->table_name,
                $data,
                array('%d', '%s', '%s', '%s', '%s', '%s', '%s')
            );
        }
        
        return $result !== false;
    }
    
    /**
     * Get order by WooCommerce order ID
     */
    public function get_by_order_id($order_id) {
        global $wpdb;
        
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE order_id = %d",
                $order_id
            )
        );
    }
    
    /**
     * Get orders by sync status
     */
    public function get_by_sync_status($status, $limit = 50) {
        global $wpdb;
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} 
                 WHERE sync_status = %s 
                 ORDER BY created_at ASC 
                 LIMIT %d",
                $status,
                $limit
            )
        );
    }
}
```

## WooCommerce Integration Architecture

### 1. Order Management Integration
```php
<?php
// includes/class-order-manager.php

/**
 * WooCommerce order integration
 */
class BlazeCommerce_Order_Manager {
    
    private $repository;
    
    public function __construct() {
        $this->repository = new BlazeCommerce_Order_Repository();
        $this->init_hooks();
    }
    
    /**
     * Initialize WooCommerce hooks
     */
    private function init_hooks() {
        add_action('woocommerce_new_order', array($this, 'handle_new_order'), 10, 2);
        add_action('woocommerce_order_status_changed', array($this, 'handle_status_change'), 10, 4);
        add_action('woocommerce_before_order_object_save', array($this, 'handle_order_update'), 10, 2);
    }
    
    /**
     * Handle new order creation
     */
    public function handle_new_order($order_id, $order) {
        if (!$order instanceof WC_Order) {
            return;
        }
        
        $order_data = array(
            'order_id' => $order_id,
            'status' => $order->get_status(),
            'sync_status' => 'pending',
            'data' => $this->extract_order_data($order)
        );
        
        $this->repository->save($order_data);
        
        // Schedule sync
        wp_schedule_single_event(time() + 60, 'blazecommerce_sync_order', array($order_id));
    }
    
    /**
     * Handle order status changes
     */
    public function handle_status_change($order_id, $old_status, $new_status, $order) {
        $existing_data = $this->repository->get_by_order_id($order_id);
        
        if ($existing_data) {
            $order_data = array(
                'order_id' => $order_id,
                'status' => $new_status,
                'sync_status' => 'pending',
                'data' => $this->extract_order_data($order)
            );
            
            $this->repository->save($order_data);
            
            // Schedule sync for status change
            wp_schedule_single_event(time() + 30, 'blazecommerce_sync_order', array($order_id));
        }
    }
    
    /**
     * Extract relevant order data
     */
    private function extract_order_data($order) {
        return array(
            'customer_id' => $order->get_customer_id(),
            'billing' => $order->get_billing(),
            'shipping' => $order->get_shipping(),
            'items' => $this->extract_order_items($order),
            'total' => $order->get_total(),
            'currency' => $order->get_currency(),
            'payment_method' => $order->get_payment_method(),
            'date_created' => $order->get_date_created()->format('c')
        );
    }
    
    /**
     * Extract order items
     */
    private function extract_order_items($order) {
        $items = array();
        
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $items[] = array(
                'product_id' => $item->get_product_id(),
                'variation_id' => $item->get_variation_id(),
                'name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'price' => $item->get_total(),
                'sku' => $product ? $product->get_sku() : ''
            );
        }
        
        return $items;
    }
}
```

## Gutenberg Block Architecture

### 1. Dynamic Block Development Standards

#### Block Registration with PHP Rendering
```php
<?php
// includes/blocks/class-product-block.php

/**
 * Product showcase dynamic block
 */
class BlazeCommerce_Product_Block {

    public function __construct() {
        add_action('init', array($this, 'register_block'));
    }

    /**
     * Register the product showcase block
     */
    public function register_block() {
        register_block_type(__DIR__ . '/product-showcase', array(
            'render_callback' => array($this, 'render_block'),
            'attributes' => array(
                'uniqueId' => array(
                    'type' => 'string',
                    'default' => ''
                ),
                'productIds' => array(
                    'type' => 'array',
                    'default' => array()
                ),
                'columns' => array(
                    'type' => 'number',
                    'default' => 3
                ),
                'showPrice' => array(
                    'type' => 'boolean',
                    'default' => true
                ),
                'backgroundColor' => array(
                    'type' => 'string',
                    'default' => ''
                ),
                'textColor' => array(
                    'type' => 'string',
                    'default' => ''
                ),
                'className' => array(
                    'type' => 'string',
                    'default' => ''
                ),
                'anchor' => array(
                    'type' => 'string',
                    'default' => ''
                ),
                'style' => array(
                    'type' => 'object',
                    'default' => array()
                )
            )
        ));
    }

    /**
     * Render the product showcase block
     */
    public function render_block($attributes, $content, $block) {
        // Generate unique ID if not provided
        if (empty($attributes['uniqueId'])) {
            $attributes['uniqueId'] = 'blazecommerce-products-' . wp_generate_uuid4();
        }

        // Get products
        $products = $this->get_products($attributes);

        if (empty($products)) {
            return '<div class="blazecommerce-no-products">' .
                   __('No products found.', 'blazecommerce') . '</div>';
        }

        // Build wrapper attributes
        $wrapper_attributes = get_block_wrapper_attributes(array(
            'id' => $attributes['uniqueId'],
            'class' => 'blazecommerce-product-showcase columns-' . $attributes['columns'],
            'data-columns' => $attributes['columns']
        ));

        // Render products
        $output = sprintf('<div %s>', $wrapper_attributes);
        $output .= $this->render_products($products, $attributes);
        $output .= '</div>';

        return $output;
    }

    /**
     * Get products based on block attributes
     */
    private function get_products($attributes) {
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => 12,
            'meta_query' => array(
                array(
                    'key' => '_visibility',
                    'value' => array('catalog', 'visible'),
                    'compare' => 'IN'
                )
            )
        );

        // Filter by specific product IDs if provided
        if (!empty($attributes['productIds'])) {
            $args['post__in'] = $attributes['productIds'];
        }

        return get_posts($args);
    }

    /**
     * Render individual products
     */
    private function render_products($products, $attributes) {
        $output = '<div class="products-grid">';

        foreach ($products as $product_post) {
            $product = wc_get_product($product_post->ID);
            if (!$product) continue;

            $output .= '<div class="product-item">';
            $output .= '<div class="product-image">';
            $output .= $product->get_image('medium');
            $output .= '</div>';

            $output .= '<div class="product-info">';
            $output .= '<h3 class="product-title">' . esc_html($product->get_name()) . '</h3>';

            if ($attributes['showPrice']) {
                $output .= '<div class="product-price">' . $product->get_price_html() . '</div>';
            }

            $output .= '<a href="' . esc_url($product->get_permalink()) . '" class="product-link">';
            $output .= __('View Product', 'blazecommerce');
            $output .= '</a>';
            $output .= '</div>';
            $output .= '</div>';
        }

        $output .= '</div>';
        return $output;
    }
}

new BlazeCommerce_Product_Block();
```

### 2. WooCommerce Integration Blocks

#### Order Status Block
```php
<?php
// includes/blocks/class-order-status-block.php

class BlazeCommerce_Order_Status_Block {

    public function __construct() {
        add_action('init', array($this, 'register_block'));
    }

    public function register_block() {
        register_block_type(__DIR__ . '/order-status', array(
            'render_callback' => array($this, 'render_block'),
            'attributes' => array(
                'uniqueId' => array('type' => 'string', 'default' => ''),
                'showOrderNumber' => array('type' => 'boolean', 'default' => true),
                'showTrackingInfo' => array('type' => 'boolean', 'default' => true),
                'backgroundColor' => array('type' => 'string', 'default' => ''),
                'textColor' => array('type' => 'string', 'default' => ''),
                'className' => array('type' => 'string', 'default' => ''),
                'anchor' => array('type' => 'string', 'default' => ''),
                'style' => array('type' => 'object', 'default' => array())
            )
        ));
    }

    public function render_block($attributes, $content, $block) {
        // Only show to logged-in users
        if (!is_user_logged_in()) {
            return '<div class="blazecommerce-login-required">' .
                   __('Please log in to view your orders.', 'blazecommerce') . '</div>';
        }

        $customer_orders = wc_get_orders(array(
            'customer' => get_current_user_id(),
            'limit' => 5,
            'status' => array('wc-processing', 'wc-shipped', 'wc-completed')
        ));

        if (empty($customer_orders)) {
            return '<div class="blazecommerce-no-orders">' .
                   __('No recent orders found.', 'blazecommerce') . '</div>';
        }

        $unique_id = $attributes['uniqueId'] ?: 'blazecommerce-orders-' . wp_generate_uuid4();

        $wrapper_attributes = get_block_wrapper_attributes(array(
            'id' => $unique_id,
            'class' => 'blazecommerce-order-status'
        ));

        $output = sprintf('<div %s>', $wrapper_attributes);
        $output .= '<h3>' . __('Recent Orders', 'blazecommerce') . '</h3>';
        $output .= '<div class="orders-list">';

        foreach ($customer_orders as $order) {
            $output .= $this->render_order_item($order, $attributes);
        }

        $output .= '</div></div>';

        return $output;
    }

    private function render_order_item($order, $attributes) {
        $output = '<div class="order-item">';

        if ($attributes['showOrderNumber']) {
            $output .= '<div class="order-number">';
            $output .= sprintf(__('Order #%s', 'blazecommerce'), $order->get_order_number());
            $output .= '</div>';
        }

        $output .= '<div class="order-status">';
        $output .= '<span class="status-badge status-' . $order->get_status() . '">';
        $output .= wc_get_order_status_name($order->get_status());
        $output .= '</span>';
        $output .= '</div>';

        if ($attributes['showTrackingInfo']) {
            $tracking_number = $order->get_meta('_tracking_number');
            if ($tracking_number) {
                $output .= '<div class="tracking-info">';
                $output .= sprintf(__('Tracking: %s', 'blazecommerce'), esc_html($tracking_number));
                $output .= '</div>';
            }
        }

        $output .= '</div>';

        return $output;
    }
}

new BlazeCommerce_Order_Status_Block();
```

## API Architecture

### 1. REST API Endpoints
```php
<?php
// includes/api/class-rest-api.php

/**
 * REST API endpoints
 */
class BlazeCommerce_REST_API {
    
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        register_rest_route('blazecommerce/v1', '/orders', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_orders'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'status' => array(
                    'validate_callback' => function($param) {
                        return in_array($param, array('pending', 'processing', 'completed'));
                    }
                ),
                'limit' => array(
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0 && $param <= 100;
                    },
                    'default' => 20
                )
            )
        ));
        
        register_rest_route('blazecommerce/v1', '/orders/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_order'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'id' => array(
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                )
            )
        ));
    }
    
    /**
     * Check API permissions
     */
    public function check_permissions() {
        return current_user_can('manage_woocommerce');
    }
    
    /**
     * Get orders endpoint
     */
    public function get_orders($request) {
        $repository = new BlazeCommerce_Order_Repository();
        
        $status = $request->get_param('status');
        $limit = $request->get_param('limit');
        
        if ($status) {
            $orders = $repository->get_by_sync_status($status, $limit);
        } else {
            $orders = $repository->get_all($limit);
        }
        
        return rest_ensure_response($orders);
    }
    
    /**
     * Get single order endpoint
     */
    public function get_order($request) {
        $repository = new BlazeCommerce_Order_Repository();
        $order_id = $request->get_param('id');
        
        $order = $repository->get_by_order_id($order_id);
        
        if (!$order) {
            return new WP_Error('order_not_found', 'Order not found', array('status' => 404));
        }
        
        return rest_ensure_response($order);
    }
}
```

### 3. Kinsta Hosting Cache Integration

#### Plugin Cache Management
```php
<?php
// includes/class-kinsta-cache-manager.php

/**
 * Kinsta cache management for BlazeCommerce plugin
 */
class BlazeCommerce_Kinsta_Cache_Manager {

    public function __construct() {
        add_action('init', array($this, 'init_cache_hooks'));
        add_action('admin_init', array($this, 'handle_manual_cache_clear'));
    }

    /**
     * Initialize cache-related hooks
     */
    public function init_cache_hooks() {
        // Clear cache when plugin data changes
        add_action('blazecommerce_order_updated', array($this, 'clear_order_cache'));
        add_action('blazecommerce_product_synced', array($this, 'clear_product_cache'));
        add_action('woocommerce_product_set_stock', array($this, 'clear_product_cache'));
        add_action('woocommerce_order_status_changed', array($this, 'clear_order_cache'));

        // Clear cache when blocks are updated
        add_action('save_post', array($this, 'clear_block_cache_on_post_save'));
    }

    /**
     * Clear order-related cache
     */
    public function clear_order_cache($order_id = null) {
        // Clear WordPress object cache
        wp_cache_flush_group('blazecommerce_orders');
        wp_cache_flush_group('blazecommerce_blocks');

        // Clear Kinsta cache
        $this->clear_kinsta_cache();

        // Log cache clear
        $this->log_cache_operation('order', $order_id);
    }

    /**
     * Clear product-related cache
     */
    public function clear_product_cache($product_id = null) {
        // Clear WordPress object cache
        wp_cache_flush_group('blazecommerce_products');
        wp_cache_flush_group('blazecommerce_blocks');

        // Clear Kinsta cache
        $this->clear_kinsta_cache();

        // Log cache clear
        $this->log_cache_operation('product', $product_id);
    }

    /**
     * Clear block cache when posts with blocks are saved
     */
    public function clear_block_cache_on_post_save($post_id) {
        $post_content = get_post_field('post_content', $post_id);

        // Check if post contains BlazeCommerce blocks
        if (has_blocks($post_content)) {
            $blocks = parse_blocks($post_content);
            $has_blazecommerce_blocks = false;

            foreach ($blocks as $block) {
                if (strpos($block['blockName'], 'blazecommerce/') === 0) {
                    $has_blazecommerce_blocks = true;
                    break;
                }
            }

            if ($has_blazecommerce_blocks) {
                wp_cache_flush_group('blazecommerce_blocks');
                $this->clear_kinsta_cache();
                $this->log_cache_operation('block', $post_id);
            }
        }
    }

    /**
     * Clear Kinsta cache if available
     */
    private function clear_kinsta_cache() {
        if (function_exists('kinsta_cache_purge')) {
            kinsta_cache_purge();
            return true;
        }

        return false;
    }

    /**
     * Handle manual cache clear from admin
     */
    public function handle_manual_cache_clear() {
        if (isset($_GET['action']) && $_GET['action'] === 'blazecommerce_clear_cache') {
            if (!current_user_can('manage_options')) {
                wp_die(__('Insufficient permissions', 'blazecommerce'));
            }

            if (!wp_verify_nonce($_GET['_wpnonce'], 'blazecommerce_clear_cache')) {
                wp_die(__('Security check failed', 'blazecommerce'));
            }

            // Clear all BlazeCommerce caches
            wp_cache_flush_group('blazecommerce_orders');
            wp_cache_flush_group('blazecommerce_products');
            wp_cache_flush_group('blazecommerce_blocks');

            $this->clear_kinsta_cache();

            // Redirect with success message
            wp_redirect(add_query_arg(array(
                'page' => 'blazecommerce',
                'cache_cleared' => '1'
            ), admin_url('admin.php')));
            exit;
        }
    }

    /**
     * Log cache operations for debugging
     */
    private function log_cache_operation($type, $id = null) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $message = "BlazeCommerce: Cleared {$type} cache";
            if ($id) {
                $message .= " for ID {$id}";
            }
            error_log($message);
        }
    }

    /**
     * Add cache management to admin bar
     */
    public function add_admin_bar_cache_button($wp_admin_bar) {
        if (!current_user_can('manage_options')) {
            return;
        }

        $wp_admin_bar->add_node(array(
            'id' => 'blazecommerce-clear-cache',
            'title' => __('Clear BlazeCommerce Cache', 'blazecommerce'),
            'href' => wp_nonce_url(
                admin_url('admin.php?page=blazecommerce&action=blazecommerce_clear_cache'),
                'blazecommerce_clear_cache'
            ),
            'meta' => array(
                'title' => __('Clear all BlazeCommerce and Kinsta cache', 'blazecommerce')
            )
        ));
    }

    /**
     * Get cache statistics for admin dashboard
     */
    public function get_cache_stats() {
        return array(
            'kinsta_available' => function_exists('kinsta_cache_purge'),
            'object_cache_available' => wp_using_ext_object_cache(),
            'cache_groups' => array(
                'orders' => wp_cache_get_stats('blazecommerce_orders'),
                'products' => wp_cache_get_stats('blazecommerce_products'),
                'blocks' => wp_cache_get_stats('blazecommerce_blocks')
            )
        );
    }
}

// Initialize cache manager
new BlazeCommerce_Kinsta_Cache_Manager();
```

#### Block Performance Optimization
```php
<?php
// includes/class-block-performance-optimizer.php

/**
 * Optimize block performance for Kinsta hosting
 */
class BlazeCommerce_Block_Performance_Optimizer {

    private $performance_data = array();

    public function __construct() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            add_action('wp_footer', array($this, 'output_performance_metrics'));
        }
    }

    /**
     * Track block rendering performance
     */
    public function track_block_performance($block_name, $start_time, $end_time, $memory_usage) {
        $this->performance_data[] = array(
            'block' => $block_name,
            'time' => $end_time - $start_time,
            'memory' => $memory_usage,
            'timestamp' => current_time('mysql')
        );
    }

    /**
     * Output performance metrics for debugging
     */
    public function output_performance_metrics() {
        if (!current_user_can('manage_options') || empty($this->performance_data)) {
            return;
        }

        $total_time = array_sum(array_column($this->performance_data, 'time'));
        $total_memory = array_sum(array_column($this->performance_data, 'memory'));

        $metrics = array(
            'total_blocks' => count($this->performance_data),
            'total_time' => round($total_time, 4),
            'total_memory' => size_format($total_memory),
            'blocks' => $this->performance_data
        );

        echo '<!-- BlazeCommerce Block Performance: ' . wp_json_encode($metrics) . ' -->';
    }

    /**
     * Optimize block queries for Kinsta
     */
    public function optimize_block_queries() {
        // Reduce query overhead
        add_filter('pre_get_posts', array($this, 'optimize_product_queries'));

        // Enable query caching
        add_filter('posts_pre_query', array($this, 'check_query_cache'), 10, 2);
    }

    /**
     * Optimize product queries for blocks
     */
    public function optimize_product_queries($query) {
        if (!is_admin() && $query->get('post_type') === 'product') {
            // Limit fields to reduce memory usage
            $query->set('fields', 'ids');

            // Disable unnecessary features
            $query->set('no_found_rows', true);
            $query->set('update_post_meta_cache', false);
            $query->set('update_post_term_cache', false);
        }
    }

    /**
     * Check for cached query results
     */
    public function check_query_cache($posts, $query) {
        if ($query->get('post_type') !== 'product') {
            return $posts;
        }

        $cache_key = 'blazecommerce_query_' . md5(serialize($query->query_vars));
        $cached_posts = wp_cache_get($cache_key, 'blazecommerce_queries');

        if (false !== $cached_posts) {
            return $cached_posts;
        }

        return $posts;
    }
}

new BlazeCommerce_Block_Performance_Optimizer();
```

## WordPress Plugin Context

This architecture applies specifically to:
- WordPress plugin development structure with Gutenberg blocks
- WooCommerce integration patterns and dynamic blocks
- Database design and data access layers
- Hook and filter implementation for blocks
- REST API endpoint creation for block data
- Admin interface organization with cache management
- Public-facing functionality with performance optimization
- Internationalization support for blocks
- Kinsta hosting cache integration and optimization
