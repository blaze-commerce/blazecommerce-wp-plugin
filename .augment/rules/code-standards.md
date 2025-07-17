# Code Quality Standards Rule

**Priority: Auto**

**Description:** Enforce consistent code quality standards for WordPress plugin development to ensure maintainable, secure, and performant code that follows WordPress and WooCommerce best practices.

## PHP Code Standards

### 1. WordPress Coding Standards
- Follow WordPress PHP Coding Standards exactly
- Use WordPress VIP coding standards for enterprise-level code
- Implement proper indentation (tabs, not spaces)
- Use meaningful variable and function names with plugin prefix

```php
// ✅ Good - WordPress coding standards
class BlazeCommerce_Product_Manager {
    
    private $plugin_name;
    private $version;
    
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }
    
    /**
     * Get product data with proper validation
     *
     * @param int $product_id WooCommerce product ID
     * @return array|false Product data or false on failure
     */
    public function get_product_data($product_id) {
        if (!is_numeric($product_id) || $product_id <= 0) {
            return false;
        }
        
        $product = wc_get_product($product_id);
        
        if (!$product || !$product->exists()) {
            return false;
        }
        
        return array(
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'price' => $product->get_price(),
            'status' => $product->get_status()
        );
    }
}

// ❌ Bad - Poor naming and structure
class pm {
    function gpd($id) {
        return get_post_meta($id, '_product_data', true);
    }
}
```

### 2. Function and Class Standards
- Use descriptive function names with plugin prefix
- Implement proper error handling and validation
- Add comprehensive PHPDoc comments
- Follow single responsibility principle
- Use proper access modifiers (public, private, protected)

```php
/**
 * Process WooCommerce order data for BlazeCommerce integration
 *
 * @since 1.0.0
 * @param WC_Order $order WooCommerce order object
 * @param array $additional_data Additional data to process
 * @return bool|WP_Error True on success, WP_Error on failure
 */
private function blazecommerce_process_order_data($order, $additional_data = array()) {
    // Validate order object
    if (!$order instanceof WC_Order) {
        return new WP_Error('invalid_order', 'Invalid order object provided');
    }
    
    // Process order data
    $order_data = array(
        'order_id' => $order->get_id(),
        'customer_id' => $order->get_customer_id(),
        'total' => $order->get_total(),
        'status' => $order->get_status(),
        'date_created' => $order->get_date_created()->format('Y-m-d H:i:s')
    );
    
    // Merge additional data
    if (!empty($additional_data)) {
        $order_data = array_merge($order_data, $additional_data);
    }
    
    // Save to custom table
    $result = $this->save_order_data($order_data);
    
    if (!$result) {
        return new WP_Error('save_failed', 'Failed to save order data');
    }
    
    return true;
}
```

### 3. WordPress Hook Implementation
- Use appropriate hook priorities
- Implement proper callback functions
- Document hook usage and purpose
- Follow WordPress hook naming conventions

```php
// ✅ Good hook implementation
class BlazeCommerce_Order_Handler {
    
    public function __construct() {
        add_action('woocommerce_new_order', array($this, 'handle_new_order'), 10, 2);
        add_action('woocommerce_order_status_changed', array($this, 'handle_status_change'), 10, 4);
        add_filter('woocommerce_product_data_tabs', array($this, 'add_product_data_tab'));
    }
    
    /**
     * Handle new WooCommerce order creation
     *
     * @param int $order_id Order ID
     * @param WC_Order $order Order object
     */
    public function handle_new_order($order_id, $order) {
        // Validate parameters
        if (!$order instanceof WC_Order) {
            return;
        }
        
        // Process new order
        $this->process_new_order($order);
    }
    
    /**
     * Handle order status changes
     *
     * @param int $order_id Order ID
     * @param string $old_status Previous status
     * @param string $new_status New status
     * @param WC_Order $order Order object
     */
    public function handle_status_change($order_id, $old_status, $new_status, $order) {
        // Only process specific status changes
        $tracked_statuses = array('processing', 'completed', 'cancelled');
        
        if (!in_array($new_status, $tracked_statuses)) {
            return;
        }
        
        // Update custom order tracking
        $this->update_order_status($order_id, $new_status);
    }
}
```

## Database Standards

### 1. Database Query Optimization
- Always use prepared statements
- Implement proper indexing
- Use WordPress database APIs when possible
- Cache expensive queries

```php
// ✅ Optimized database operations
class BlazeCommerce_Database {
    
    /**
     * Get products with caching
     *
     * @param array $args Query arguments
     * @return array Product data
     */
    public function get_products($args = array()) {
        $cache_key = 'blazecommerce_products_' . md5(serialize($args));
        $products = wp_cache_get($cache_key, 'blazecommerce');
        
        if (false === $products) {
            global $wpdb;
            
            $defaults = array(
                'status' => 'active',
                'limit' => 10,
                'offset' => 0
            );
            
            $args = wp_parse_args($args, $defaults);
            
            $products = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT p.*, pm.meta_value as price 
                     FROM {$wpdb->prefix}blazecommerce_products p
                     LEFT JOIN {$wpdb->prefix}blazecommerce_product_meta pm ON p.id = pm.product_id
                     WHERE p.status = %s 
                     AND pm.meta_key = 'price'
                     ORDER BY p.created_date DESC
                     LIMIT %d OFFSET %d",
                    $args['status'],
                    $args['limit'],
                    $args['offset']
                )
            );
            
            wp_cache_set($cache_key, $products, 'blazecommerce', HOUR_IN_SECONDS);
        }
        
        return $products;
    }
}
```

### 2. Custom Table Management
```php
// ✅ Proper custom table creation and management
class BlazeCommerce_Database_Manager {
    
    /**
     * Create custom tables on plugin activation
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $table_name = $wpdb->prefix . 'blazecommerce_orders';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) NOT NULL,
            customer_id bigint(20) DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            total decimal(10,2) NOT NULL DEFAULT '0.00',
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            updated_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY customer_id (customer_id),
            KEY status (status),
            KEY created_date (created_date)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}
```

## WooCommerce Integration Standards

### 1. Product Data Handling
```php
// ✅ Proper WooCommerce product integration
class BlazeCommerce_Product_Integration {
    
    /**
     * Add custom product fields
     */
    public function add_product_fields() {
        woocommerce_wp_text_input(array(
            'id' => '_blazecommerce_sku',
            'label' => __('BlazeCommerce SKU', 'blazecommerce'),
            'description' => __('Custom SKU for BlazeCommerce integration', 'blazecommerce'),
            'desc_tip' => true,
            'type' => 'text'
        ));
        
        woocommerce_wp_select(array(
            'id' => '_blazecommerce_sync_status',
            'label' => __('Sync Status', 'blazecommerce'),
            'options' => array(
                'pending' => __('Pending', 'blazecommerce'),
                'synced' => __('Synced', 'blazecommerce'),
                'error' => __('Error', 'blazecommerce')
            )
        ));
    }
    
    /**
     * Save custom product fields
     *
     * @param int $post_id Product ID
     */
    public function save_product_fields($post_id) {
        // Verify nonce
        if (!isset($_POST['woocommerce_meta_nonce']) || 
            !wp_verify_nonce($_POST['woocommerce_meta_nonce'], 'woocommerce_save_data')) {
            return;
        }
        
        // Save custom fields
        $blazecommerce_sku = sanitize_text_field($_POST['_blazecommerce_sku']);
        update_post_meta($post_id, '_blazecommerce_sku', $blazecommerce_sku);
        
        $sync_status = sanitize_text_field($_POST['_blazecommerce_sync_status']);
        update_post_meta($post_id, '_blazecommerce_sync_status', $sync_status);
    }
}
```

## Gutenberg Block Development Standards

### 1. Dynamic Block Registration Standards

#### Block.json Metadata for Plugin Blocks
```json
{
    "apiVersion": 3,
    "name": "blazecommerce/product-showcase",
    "title": "Product Showcase",
    "category": "blazecommerce",
    "icon": "products",
    "description": "Display WooCommerce products with advanced filtering",
    "keywords": ["products", "woocommerce", "showcase", "grid"],
    "version": "1.0.0",
    "textdomain": "blazecommerce",
    "supports": {
        "className": true,
        "customClassName": true,
        "anchor": true,
        "spacing": {
            "padding": true,
            "margin": true,
            "blockGap": true
        },
        "color": {
            "background": true,
            "text": true,
            "link": true,
            "gradients": true
        },
        "typography": {
            "fontSize": true,
            "fontWeight": true,
            "lineHeight": true,
            "fontFamily": true
        },
        "border": {
            "width": true,
            "style": true,
            "color": true,
            "radius": true
        }
    },
    "attributes": {
        "uniqueId": {
            "type": "string",
            "default": ""
        },
        "productIds": {
            "type": "array",
            "default": []
        },
        "categoryIds": {
            "type": "array",
            "default": []
        },
        "columns": {
            "type": "number",
            "default": 3
        },
        "showPrice": {
            "type": "boolean",
            "default": true
        },
        "showAddToCart": {
            "type": "boolean",
            "default": true
        },
        "backgroundColor": {
            "type": "string",
            "default": ""
        },
        "textColor": {
            "type": "string",
            "default": ""
        },
        "linkColor": {
            "type": "string",
            "default": ""
        },
        "className": {
            "type": "string",
            "default": ""
        },
        "anchor": {
            "type": "string",
            "default": ""
        },
        "style": {
            "type": "object",
            "default": {}
        }
    },
    "providesContext": {
        "blazecommerce/productShowcaseId": "uniqueId"
    },
    "usesContext": ["postId", "postType"],
    "editorScript": "file:./index.js",
    "editorStyle": "file:./index.css",
    "style": "file:./style-index.css"
}
```

### 2. PHP Block Registration Standards

#### Proper Block Class Structure
```php
// ✅ Good - Complete block class with all required methods
class BlazeCommerce_Product_Block {

    private $block_name = 'blazecommerce/product-showcase';

    public function __construct() {
        add_action('init', array($this, 'register_block'));
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_editor_assets'));
    }

    /**
     * Register block with comprehensive configuration
     */
    public function register_block() {
        register_block_type(__DIR__ . '/product-showcase', array(
            'render_callback' => array($this, 'render_block'),
            'attributes' => $this->get_block_attributes()
        ));
    }

    /**
     * Define block attributes with proper validation
     */
    private function get_block_attributes() {
        return array(
            'uniqueId' => array(
                'type' => 'string',
                'default' => ''
            ),
            'productIds' => array(
                'type' => 'array',
                'default' => array(),
                'items' => array('type' => 'number')
            ),
            'columns' => array(
                'type' => 'number',
                'default' => 3,
                'minimum' => 1,
                'maximum' => 6
            ),
            'showPrice' => array(
                'type' => 'boolean',
                'default' => true
            ),
            // Include all standard attributes
            'backgroundColor' => array('type' => 'string', 'default' => ''),
            'textColor' => array('type' => 'string', 'default' => ''),
            'className' => array('type' => 'string', 'default' => ''),
            'anchor' => array('type' => 'string', 'default' => ''),
            'style' => array('type' => 'object', 'default' => array())
        );
    }

    /**
     * Render block with proper error handling and caching
     */
    public function render_block($attributes, $content, $block) {
        // Validate attributes
        $attributes = $this->validate_attributes($attributes);

        // Generate unique ID
        if (empty($attributes['uniqueId'])) {
            $attributes['uniqueId'] = 'blazecommerce-products-' . wp_generate_uuid4();
        }

        // Check cache first
        $cache_key = 'blazecommerce_block_' . md5(serialize($attributes));
        $cached_output = wp_cache_get($cache_key, 'blazecommerce_blocks');

        if (false !== $cached_output) {
            return $cached_output;
        }

        // Render block content
        $output = $this->render_block_content($attributes);

        // Cache output for 1 hour
        wp_cache_set($cache_key, $output, 'blazecommerce_blocks', HOUR_IN_SECONDS);

        return $output;
    }

    /**
     * Validate and sanitize block attributes
     */
    private function validate_attributes($attributes) {
        // Sanitize string attributes
        $attributes['uniqueId'] = sanitize_html_class($attributes['uniqueId']);
        $attributes['backgroundColor'] = sanitize_hex_color($attributes['backgroundColor']);
        $attributes['textColor'] = sanitize_hex_color($attributes['textColor']);
        $attributes['className'] = sanitize_html_class($attributes['className']);
        $attributes['anchor'] = sanitize_html_class($attributes['anchor']);

        // Validate numeric attributes
        $attributes['columns'] = max(1, min(6, intval($attributes['columns'])));

        // Validate array attributes
        if (!is_array($attributes['productIds'])) {
            $attributes['productIds'] = array();
        }
        $attributes['productIds'] = array_map('absint', $attributes['productIds']);

        return $attributes;
    }

    /**
     * Render the actual block content
     */
    private function render_block_content($attributes) {
        // Get products
        $products = $this->get_products($attributes);

        if (empty($products)) {
            return $this->render_empty_state();
        }

        // Build wrapper attributes
        $wrapper_attributes = get_block_wrapper_attributes(array(
            'id' => $attributes['uniqueId'],
            'class' => 'blazecommerce-product-showcase columns-' . $attributes['columns'],
            'data-columns' => $attributes['columns']
        ));

        // Render products
        ob_start();
        ?>
        <div <?php echo $wrapper_attributes; ?>>
            <div class="products-grid">
                <?php foreach ($products as $product_post) : ?>
                    <?php $this->render_product_item($product_post, $attributes); ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Get products based on block configuration
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

        // Filter by specific product IDs
        if (!empty($attributes['productIds'])) {
            $args['post__in'] = $attributes['productIds'];
            $args['orderby'] = 'post__in';
        }

        // Filter by categories
        if (!empty($attributes['categoryIds'])) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => $attributes['categoryIds']
                )
            );
        }

        return get_posts($args);
    }

    /**
     * Render individual product item
     */
    private function render_product_item($product_post, $attributes) {
        $product = wc_get_product($product_post->ID);
        if (!$product) return;

        ?>
        <div class="product-item" data-product-id="<?php echo esc_attr($product->get_id()); ?>">
            <div class="product-image">
                <?php echo $product->get_image('medium'); ?>
            </div>

            <div class="product-info">
                <h3 class="product-title">
                    <a href="<?php echo esc_url($product->get_permalink()); ?>">
                        <?php echo esc_html($product->get_name()); ?>
                    </a>
                </h3>

                <?php if ($attributes['showPrice']) : ?>
                    <div class="product-price">
                        <?php echo $product->get_price_html(); ?>
                    </div>
                <?php endif; ?>

                <?php if ($attributes['showAddToCart'] && $product->is_purchasable()) : ?>
                    <div class="product-actions">
                        <?php woocommerce_template_loop_add_to_cart(); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render empty state when no products found
     */
    private function render_empty_state() {
        return '<div class="blazecommerce-no-products">' .
               __('No products found. Please check your selection.', 'blazecommerce') .
               '</div>';
    }

    /**
     * Enqueue editor assets
     */
    public function enqueue_editor_assets() {
        wp_enqueue_script(
            'blazecommerce-product-block-editor',
            plugins_url('blocks/product-showcase/index.js', __FILE__),
            array('wp-blocks', 'wp-element', 'wp-editor'),
            BLAZECOMMERCE_VERSION
        );

        wp_enqueue_style(
            'blazecommerce-product-block-editor',
            plugins_url('blocks/product-showcase/index.css', __FILE__),
            array('wp-edit-blocks'),
            BLAZECOMMERCE_VERSION
        );
    }
}

// ❌ Bad - Minimal block registration without proper structure
function register_simple_block() {
    register_block_type('blazecommerce/simple', array(
        'render_callback' => function($attributes) {
            return '<div>Simple block</div>';
        }
    ));
}
```

### 3. WooCommerce Integration Standards

#### Product Data Integration
```php
// ✅ Proper WooCommerce data integration
class BlazeCommerce_WooCommerce_Block_Integration {

    /**
     * Get product data for block rendering
     */
    public function get_product_data($product_id) {
        $product = wc_get_product($product_id);

        if (!$product || !$product->exists()) {
            return false;
        }

        return array(
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'price' => $product->get_price(),
            'price_html' => $product->get_price_html(),
            'permalink' => $product->get_permalink(),
            'image_id' => $product->get_image_id(),
            'image_url' => wp_get_attachment_image_url($product->get_image_id(), 'medium'),
            'in_stock' => $product->is_in_stock(),
            'purchasable' => $product->is_purchasable(),
            'type' => $product->get_type(),
            'sku' => $product->get_sku(),
            'categories' => wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'names'))
        );
    }

    /**
     * Get cart data for cart-related blocks
     */
    public function get_cart_data() {
        if (!WC()->cart) {
            return array(
                'items' => array(),
                'total' => 0,
                'count' => 0
            );
        }

        $cart_items = array();
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            $cart_items[] = array(
                'key' => $cart_item_key,
                'product_id' => $cart_item['product_id'],
                'name' => $product->get_name(),
                'quantity' => $cart_item['quantity'],
                'price' => $product->get_price(),
                'total' => $cart_item['line_total']
            );
        }

        return array(
            'items' => $cart_items,
            'total' => WC()->cart->get_total(),
            'count' => WC()->cart->get_cart_contents_count(),
            'subtotal' => WC()->cart->get_subtotal(),
            'tax_total' => WC()->cart->get_total_tax()
        );
    }
}
```

## Admin Interface Standards

### 1. Settings Page Implementation
```php
// ✅ Proper WordPress admin page structure
class BlazeCommerce_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Add admin menu pages
     */
    public function add_admin_menu() {
        add_menu_page(
            __('BlazeCommerce', 'blazecommerce'),
            __('BlazeCommerce', 'blazecommerce'),
            'manage_options',
            'blazecommerce',
            array($this, 'admin_page'),
            'dashicons-store',
            30
        );
        
        add_submenu_page(
            'blazecommerce',
            __('Settings', 'blazecommerce'),
            __('Settings', 'blazecommerce'),
            'manage_options',
            'blazecommerce-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting(
            'blazecommerce_settings',
            'blazecommerce_options',
            array($this, 'validate_settings')
        );
        
        add_settings_section(
            'blazecommerce_general',
            __('General Settings', 'blazecommerce'),
            array($this, 'general_section_callback'),
            'blazecommerce_settings'
        );
        
        add_settings_field(
            'api_key',
            __('API Key', 'blazecommerce'),
            array($this, 'api_key_callback'),
            'blazecommerce_settings',
            'blazecommerce_general'
        );
    }
}
```

## Performance Standards

### 1. Caching Implementation
```php
// ✅ Implement proper caching
class BlazeCommerce_Cache {
    
    private $cache_group = 'blazecommerce';
    private $cache_expiration = 3600; // 1 hour
    
    /**
     * Get cached data or generate if not exists
     *
     * @param string $key Cache key
     * @param callable $callback Function to generate data
     * @return mixed Cached or generated data
     */
    public function get_or_set($key, $callback) {
        $data = wp_cache_get($key, $this->cache_group);
        
        if (false === $data) {
            $data = call_user_func($callback);
            wp_cache_set($key, $data, $this->cache_group, $this->cache_expiration);
        }
        
        return $data;
    }
    
    /**
     * Clear cache for specific key or entire group
     *
     * @param string|null $key Specific key to clear, null for entire group
     */
    public function clear($key = null) {
        if ($key) {
            wp_cache_delete($key, $this->cache_group);
        } else {
            wp_cache_flush_group($this->cache_group);
        }
    }
}
```

## Error Handling Standards

### 1. Comprehensive Error Handling
```php
// ✅ Proper error handling and logging
class BlazeCommerce_Error_Handler {
    
    /**
     * Log error with context
     *
     * @param string $message Error message
     * @param array $context Additional context
     * @param string $level Error level (error, warning, info)
     */
    public static function log($message, $context = array(), $level = 'error') {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $log_message = sprintf(
                '[BlazeCommerce] %s: %s',
                strtoupper($level),
                $message
            );
            
            if (!empty($context)) {
                $log_message .= ' Context: ' . wp_json_encode($context);
            }
            
            error_log($log_message);
        }
    }
    
    /**
     * Handle API errors gracefully
     *
     * @param WP_Error|mixed $response API response
     * @return array Standardized error response
     */
    public static function handle_api_error($response) {
        if (is_wp_error($response)) {
            self::log('API Error: ' . $response->get_error_message(), array(
                'error_code' => $response->get_error_code(),
                'error_data' => $response->get_error_data()
            ));
            
            return array(
                'success' => false,
                'message' => __('An error occurred. Please try again.', 'blazecommerce')
            );
        }
        
        return $response;
    }
}
```

## WordPress Plugin Context

These standards apply specifically to:
- WordPress plugin development
- WooCommerce integration
- Database operations and optimization
- Admin interface development
- Hook and filter implementation
- Performance optimization
- Error handling and logging
- Security implementation
