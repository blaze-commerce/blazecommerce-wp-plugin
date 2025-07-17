# Testing Requirements Rule

**Priority: Auto**

**Description:** Establish comprehensive testing standards for WordPress plugin development to ensure code quality, functionality, and reliability with WordPress and WooCommerce integration.

## Testing Framework Requirements

### 1. PHPUnit for WordPress Plugin Testing
- **Minimum test coverage requirement**: 80%
- **Unit tests**: Test individual classes and methods
- **Integration tests**: Test WordPress and WooCommerce integration
- **Database tests**: Test custom table operations

```php
<?php
// tests/unit/test-order-manager.php

class Test_Order_Manager extends WP_UnitTestCase {
    
    private $order_manager;
    private $order_repository;
    
    public function setUp(): void {
        parent::setUp();
        
        $this->order_manager = new BlazeCommerce_Order_Manager();
        $this->order_repository = new BlazeCommerce_Order_Repository();
        
        // Create test tables
        BlazeCommerce_Database::create_tables();
    }
    
    public function tearDown(): void {
        // Clean up test data
        global $wpdb;
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}blazecommerce_orders");
        
        parent::tearDown();
    }
    
    /**
     * Test order creation handling
     */
    public function test_handle_new_order() {
        // Create a test WooCommerce order
        $order = wc_create_order();
        $order->set_status('pending');
        $order->save();
        
        // Handle the new order
        $this->order_manager->handle_new_order($order->get_id(), $order);
        
        // Verify order was saved to custom table
        $saved_order = $this->order_repository->get_by_order_id($order->get_id());
        
        $this->assertNotNull($saved_order);
        $this->assertEquals($order->get_id(), $saved_order->order_id);
        $this->assertEquals('pending', $saved_order->status);
        $this->assertEquals('pending', $saved_order->sync_status);
    }
    
    /**
     * Test order status change handling
     */
    public function test_handle_status_change() {
        // Create and save initial order
        $order = wc_create_order();
        $order->set_status('pending');
        $order->save();
        
        $this->order_manager->handle_new_order($order->get_id(), $order);
        
        // Change order status
        $order->set_status('processing');
        $order->save();
        
        $this->order_manager->handle_status_change(
            $order->get_id(),
            'pending',
            'processing',
            $order
        );
        
        // Verify status was updated
        $updated_order = $this->order_repository->get_by_order_id($order->get_id());
        
        $this->assertEquals('processing', $updated_order->status);
        $this->assertEquals('pending', $updated_order->sync_status); // Should be pending for sync
    }
    
    /**
     * Test order data extraction
     */
    public function test_extract_order_data() {
        // Create order with items
        $order = wc_create_order();
        $product = wc_get_product($this->factory->post->create(array(
            'post_type' => 'product'
        )));
        
        $order->add_product($product, 2);
        $order->set_billing_first_name('John');
        $order->set_billing_last_name('Doe');
        $order->set_billing_email('john@example.com');
        $order->calculate_totals();
        $order->save();
        
        // Use reflection to test private method
        $reflection = new ReflectionClass($this->order_manager);
        $method = $reflection->getMethod('extract_order_data');
        $method->setAccessible(true);
        
        $extracted_data = $method->invoke($this->order_manager, $order);
        
        $this->assertArrayHasKey('customer_id', $extracted_data);
        $this->assertArrayHasKey('billing', $extracted_data);
        $this->assertArrayHasKey('items', $extracted_data);
        $this->assertArrayHasKey('total', $extracted_data);
        
        $this->assertEquals('John', $extracted_data['billing']['first_name']);
        $this->assertEquals('john@example.com', $extracted_data['billing']['email']);
        $this->assertCount(1, $extracted_data['items']);
    }
}
```

### 2. WooCommerce Integration Testing
```php
<?php
// tests/integration/test-woocommerce-integration.php

class Test_WooCommerce_Integration extends WC_Unit_Test_Case {
    
    public function setUp(): void {
        parent::setUp();
        
        // Ensure WooCommerce is loaded
        if (!class_exists('WooCommerce')) {
            $this->markTestSkipped('WooCommerce is not available');
        }
        
        // Create test tables
        BlazeCommerce_Database::create_tables();
    }
    
    /**
     * Test product sync functionality
     */
    public function test_product_sync() {
        // Create a WooCommerce product
        $product = new WC_Product_Simple();
        $product->set_name('Test Product');
        $product->set_regular_price(19.99);
        $product->set_sku('TEST-SKU-001');
        $product->save();
        
        // Test product sync
        $product_manager = new BlazeCommerce_Product_Manager();
        $result = $product_manager->sync_product($product->get_id());
        
        $this->assertTrue($result);
        
        // Verify product was synced
        $repository = new BlazeCommerce_Product_Repository();
        $synced_product = $repository->get_by_product_id($product->get_id());
        
        $this->assertNotNull($synced_product);
        $this->assertEquals($product->get_id(), $synced_product->product_id);
        $this->assertEquals('TEST-SKU-001', $synced_product->external_sku);
    }
    
    /**
     * Test order workflow integration
     */
    public function test_complete_order_workflow() {
        // Create customer
        $customer = new WC_Customer();
        $customer->set_email('test@example.com');
        $customer->set_first_name('Test');
        $customer->set_last_name('Customer');
        $customer->save();
        
        // Create product
        $product = new WC_Product_Simple();
        $product->set_name('Test Product');
        $product->set_regular_price(29.99);
        $product->set_manage_stock(true);
        $product->set_stock_quantity(10);
        $product->save();
        
        // Create order
        $order = wc_create_order();
        $order->set_customer_id($customer->get_id());
        $order->add_product($product, 2);
        $order->calculate_totals();
        $order->save();
        
        // Test order progression
        $order->update_status('pending');
        $order->update_status('processing');
        $order->update_status('completed');
        
        // Verify all status changes were tracked
        $repository = new BlazeCommerce_Order_Repository();
        $tracked_order = $repository->get_by_order_id($order->get_id());
        
        $this->assertNotNull($tracked_order);
        $this->assertEquals('completed', $tracked_order->status);
        
        // Verify order data integrity
        $order_data = json_decode($tracked_order->data, true);
        $this->assertEquals($customer->get_id(), $order_data['customer_id']);
        $this->assertEquals(59.98, $order_data['total']); // 2 * 29.99
        $this->assertCount(1, $order_data['items']);
    }
}
```

### 3. Database Testing
```php
<?php
// tests/unit/test-database-operations.php

class Test_Database_Operations extends WP_UnitTestCase {
    
    private $repository;
    
    public function setUp(): void {
        parent::setUp();
        
        BlazeCommerce_Database::create_tables();
        $this->repository = new BlazeCommerce_Order_Repository();
    }
    
    public function tearDown(): void {
        global $wpdb;
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}blazecommerce_orders");
        parent::tearDown();
    }
    
    /**
     * Test order repository save functionality
     */
    public function test_save_order() {
        $order_data = array(
            'order_id' => 123,
            'external_id' => 'EXT-123',
            'status' => 'processing',
            'sync_status' => 'synced',
            'data' => array('test' => 'data')
        );
        
        $result = $this->repository->save($order_data);
        
        $this->assertTrue($result);
        
        // Verify data was saved correctly
        $saved_order = $this->repository->get_by_order_id(123);
        
        $this->assertEquals(123, $saved_order->order_id);
        $this->assertEquals('EXT-123', $saved_order->external_id);
        $this->assertEquals('processing', $saved_order->status);
        $this->assertEquals('synced', $saved_order->sync_status);
        
        $saved_data = json_decode($saved_order->data, true);
        $this->assertEquals('data', $saved_data['test']);
    }
    
    /**
     * Test order repository update functionality
     */
    public function test_update_order() {
        // Save initial order
        $order_data = array(
            'order_id' => 456,
            'status' => 'pending',
            'sync_status' => 'pending',
            'data' => array('initial' => 'data')
        );
        
        $this->repository->save($order_data);
        
        // Update order
        $updated_data = array(
            'order_id' => 456,
            'status' => 'completed',
            'sync_status' => 'synced',
            'data' => array('updated' => 'data')
        );
        
        $result = $this->repository->save($updated_data);
        
        $this->assertTrue($result);
        
        // Verify update
        $updated_order = $this->repository->get_by_order_id(456);
        
        $this->assertEquals('completed', $updated_order->status);
        $this->assertEquals('synced', $updated_order->sync_status);
        
        $saved_data = json_decode($updated_order->data, true);
        $this->assertEquals('data', $saved_data['updated']);
        $this->assertArrayNotHasKey('initial', $saved_data);
    }
    
    /**
     * Test query performance
     */
    public function test_query_performance() {
        // Create multiple test orders
        for ($i = 1; $i <= 100; $i++) {
            $this->repository->save(array(
                'order_id' => $i,
                'status' => ($i % 2 === 0) ? 'completed' : 'pending',
                'sync_status' => 'pending',
                'data' => array('order_number' => $i)
            ));
        }
        
        // Measure query performance
        $start_time = microtime(true);
        $start_memory = memory_get_usage();
        
        $pending_orders = $this->repository->get_by_sync_status('pending', 50);
        
        $end_time = microtime(true);
        $end_memory = memory_get_usage();
        
        $execution_time = $end_time - $start_time;
        $memory_usage = $end_memory - $start_memory;
        
        // Assert performance thresholds
        $this->assertLessThan(0.5, $execution_time, 'Query should complete in under 0.5 seconds');
        $this->assertLessThan(5 * 1024 * 1024, $memory_usage, 'Memory usage should be under 5MB');
        $this->assertCount(50, $pending_orders);
    }
}
```

## Gutenberg Block Testing Standards

### 1. Dynamic Block Testing

#### Block Registration Testing
```php
<?php
// tests/unit/test-gutenberg-blocks.php

class Test_Gutenberg_Blocks extends WP_UnitTestCase {

    private $block_registry;

    public function setUp(): void {
        parent::setUp();

        $this->block_registry = WP_Block_Type_Registry::get_instance();

        // Ensure WooCommerce is loaded
        if (!class_exists('WooCommerce')) {
            $this->markTestSkipped('WooCommerce is not available');
        }

        // Register our blocks
        do_action('init');
    }

    /**
     * Test that all required blocks are registered
     */
    public function test_blocks_registered() {
        $required_blocks = array(
            'blazecommerce/product-showcase',
            'blazecommerce/order-status',
            'blazecommerce/cart-summary'
        );

        foreach ($required_blocks as $block_name) {
            $this->assertTrue(
                $this->block_registry->is_registered($block_name),
                "Block {$block_name} should be registered"
            );
        }
    }

    /**
     * Test block metadata compliance
     */
    public function test_block_metadata_compliance() {
        $block = $this->block_registry->get_registered('blazecommerce/product-showcase');

        // Test required attributes exist
        $required_attributes = array(
            'uniqueId', 'className', 'anchor', 'backgroundColor',
            'textColor', 'style', 'productIds', 'columns'
        );

        foreach ($required_attributes as $attribute) {
            $this->assertArrayHasKey(
                $attribute,
                $block->attributes,
                "Block must have {$attribute} attribute"
            );
        }

        // Test render callback exists
        $this->assertNotNull($block->render_callback, 'Block must have render callback');
        $this->assertTrue(is_callable($block->render_callback), 'Render callback must be callable');
    }

    /**
     * Test block rendering with valid attributes
     */
    public function test_block_rendering() {
        // Create test products
        $product1 = $this->create_test_product('Test Product 1', 19.99);
        $product2 = $this->create_test_product('Test Product 2', 29.99);

        $attributes = array(
            'uniqueId' => 'test-showcase',
            'productIds' => array($product1->get_id(), $product2->get_id()),
            'columns' => 2,
            'showPrice' => true,
            'showAddToCart' => true
        );

        $block = $this->block_registry->get_registered('blazecommerce/product-showcase');
        $output = call_user_func($block->render_callback, $attributes, '', null);

        // Test output contains expected elements
        $this->assertStringContains('id="test-showcase"', $output);
        $this->assertStringContains('blazecommerce-product-showcase', $output);
        $this->assertStringContains('columns-2', $output);
        $this->assertStringContains('Test Product 1', $output);
        $this->assertStringContains('Test Product 2', $output);
        $this->assertStringContains('$19.99', $output);
        $this->assertStringContains('$29.99', $output);
    }

    /**
     * Test block rendering with empty product list
     */
    public function test_block_empty_state() {
        $attributes = array(
            'uniqueId' => 'empty-showcase',
            'productIds' => array(999999), // Non-existent product
            'columns' => 3
        );

        $block = $this->block_registry->get_registered('blazecommerce/product-showcase');
        $output = call_user_func($block->render_callback, $attributes, '', null);

        $this->assertStringContains('blazecommerce-no-products', $output);
        $this->assertStringContains('No products found', $output);
    }

    /**
     * Test block attribute validation
     */
    public function test_block_attribute_validation() {
        $block_class = new BlazeCommerce_Product_Block();

        // Test with invalid attributes
        $invalid_attributes = array(
            'uniqueId' => '<script>alert("xss")</script>',
            'columns' => 'invalid',
            'productIds' => 'not-an-array',
            'backgroundColor' => 'invalid-color',
            'className' => '<script>',
        );

        // Use reflection to test private validation method
        $reflection = new ReflectionClass($block_class);
        $method = $reflection->getMethod('validate_attributes');
        $method->setAccessible(true);

        $validated = $method->invoke($block_class, $invalid_attributes);

        // Test sanitization worked
        $this->assertStringNotContains('<script>', $validated['uniqueId']);
        $this->assertIsInt($validated['columns']);
        $this->assertIsArray($validated['productIds']);
        $this->assertEmpty($validated['backgroundColor']); // Invalid color should be empty
        $this->assertStringNotContains('<script>', $validated['className']);
    }

    /**
     * Helper method to create test product
     */
    private function create_test_product($name, $price) {
        $product = new WC_Product_Simple();
        $product->set_name($name);
        $product->set_regular_price($price);
        $product->set_status('publish');
        $product->set_catalog_visibility('visible');
        $product->save();

        return $product;
    }
}
```

### 2. WooCommerce Integration Block Testing

#### Order Status Block Testing
```php
<?php
// tests/integration/test-woocommerce-blocks.php

class Test_WooCommerce_Blocks extends WC_Unit_Test_Case {

    private $customer_user;

    public function setUp(): void {
        parent::setUp();

        // Create test customer
        $this->customer_user = $this->factory->user->create(array(
            'role' => 'customer'
        ));
    }

    /**
     * Test order status block with logged-in user
     */
    public function test_order_status_block_logged_in() {
        wp_set_current_user($this->customer_user);

        // Create test orders
        $order1 = $this->create_test_order('processing');
        $order2 = $this->create_test_order('completed');

        $attributes = array(
            'uniqueId' => 'test-orders',
            'showOrderNumber' => true,
            'showTrackingInfo' => true
        );

        $block = WP_Block_Type_Registry::get_instance()->get_registered('blazecommerce/order-status');
        $output = call_user_func($block->render_callback, $attributes, '', null);

        // Test output contains order information
        $this->assertStringContains('blazecommerce-order-status', $output);
        $this->assertStringContains('Recent Orders', $output);
        $this->assertStringContains('Order #' . $order1->get_order_number(), $output);
        $this->assertStringContains('Order #' . $order2->get_order_number(), $output);
        $this->assertStringContains('status-processing', $output);
        $this->assertStringContains('status-completed', $output);
    }

    /**
     * Test order status block with logged-out user
     */
    public function test_order_status_block_logged_out() {
        wp_set_current_user(0); // Logged out

        $attributes = array(
            'uniqueId' => 'test-orders-logged-out',
            'showOrderNumber' => true
        );

        $block = WP_Block_Type_Registry::get_instance()->get_registered('blazecommerce/order-status');
        $output = call_user_func($block->render_callback, $attributes, '', null);

        $this->assertStringContains('blazecommerce-login-required', $output);
        $this->assertStringContains('Please log in', $output);
    }

    /**
     * Test order status block with no orders
     */
    public function test_order_status_block_no_orders() {
        wp_set_current_user($this->customer_user);

        $attributes = array(
            'uniqueId' => 'test-no-orders',
            'showOrderNumber' => true
        );

        $block = WP_Block_Type_Registry::get_instance()->get_registered('blazecommerce/order-status');
        $output = call_user_func($block->render_callback, $attributes, '', null);

        $this->assertStringContains('blazecommerce-no-orders', $output);
        $this->assertStringContains('No recent orders', $output);
    }

    /**
     * Helper method to create test order
     */
    private function create_test_order($status = 'processing') {
        $order = wc_create_order();
        $order->set_customer_id($this->customer_user);
        $order->set_status($status);

        // Add a product to the order
        $product = new WC_Product_Simple();
        $product->set_name('Test Product');
        $product->set_regular_price(19.99);
        $product->save();

        $order->add_product($product, 1);
        $order->calculate_totals();
        $order->save();

        return $order;
    }
}
```

### 3. Block Performance Testing

#### Caching and Performance Tests
```php
<?php
// tests/performance/test-block-performance.php

class Test_Block_Performance extends WP_UnitTestCase {

    public function setUp(): void {
        parent::setUp();

        // Create multiple test products for performance testing
        for ($i = 1; $i <= 50; $i++) {
            $product = new WC_Product_Simple();
            $product->set_name("Test Product {$i}");
            $product->set_regular_price(rand(10, 100));
            $product->set_status('publish');
            $product->save();
        }
    }

    /**
     * Test block rendering performance
     */
    public function test_block_rendering_performance() {
        $attributes = array(
            'uniqueId' => 'performance-test',
            'columns' => 4,
            'showPrice' => true,
            'showAddToCart' => true
        );

        $block = WP_Block_Type_Registry::get_instance()->get_registered('blazecommerce/product-showcase');

        // Measure rendering time
        $start_time = microtime(true);
        $start_memory = memory_get_usage();

        $output = call_user_func($block->render_callback, $attributes, '', null);

        $end_time = microtime(true);
        $end_memory = memory_get_usage();

        $execution_time = $end_time - $start_time;
        $memory_usage = $end_memory - $start_memory;

        // Assert performance thresholds
        $this->assertLessThan(2.0, $execution_time, 'Block rendering should complete in under 2 seconds');
        $this->assertLessThan(10 * 1024 * 1024, $memory_usage, 'Memory usage should be under 10MB');
        $this->assertNotEmpty($output, 'Block should produce output');
    }

    /**
     * Test block caching functionality
     */
    public function test_block_caching() {
        $attributes = array(
            'uniqueId' => 'cache-test',
            'columns' => 3
        );

        $block_class = new BlazeCommerce_Product_Block();

        // Clear cache first
        wp_cache_flush_group('blazecommerce_blocks');

        // First render (should cache)
        $start_time = microtime(true);
        $output1 = $block_class->render_block($attributes, '', null);
        $first_render_time = microtime(true) - $start_time;

        // Second render (should use cache)
        $start_time = microtime(true);
        $output2 = $block_class->render_block($attributes, '', null);
        $cached_render_time = microtime(true) - $start_time;

        // Test cache effectiveness
        $this->assertEquals($output1, $output2, 'Cached output should match original');
        $this->assertLessThan($first_render_time, $cached_render_time, 'Cached render should be faster');
        $this->assertLessThan(0.1, $cached_render_time, 'Cached render should be under 0.1 seconds');
    }

    /**
     * Test Kinsta cache compatibility
     */
    public function test_kinsta_cache_compatibility() {
        // Mock Kinsta cache function
        if (!function_exists('kinsta_cache_purge')) {
            function kinsta_cache_purge() {
                return true;
            }
        }

        // Test cache clearing
        $post_id = $this->factory->post->create();

        // Should not throw errors when clearing cache
        $this->expectNotToPerformAssertions();
        do_action('save_post', $post_id);
    }
}
```

## REST API Testing

### 1. API Endpoint Testing
```php
<?php
// tests/integration/test-rest-api.php

class Test_REST_API extends WP_Test_REST_TestCase {
    
    private $admin_user;
    private $subscriber_user;
    
    public function setUp(): void {
        parent::setUp();
        
        $this->admin_user = $this->factory->user->create(array(
            'role' => 'administrator'
        ));
        
        $this->subscriber_user = $this->factory->user->create(array(
            'role' => 'subscriber'
        ));
        
        BlazeCommerce_Database::create_tables();
    }
    
    /**
     * Test orders endpoint with proper permissions
     */
    public function test_get_orders_endpoint_with_admin() {
        wp_set_current_user($this->admin_user);
        
        // Create test order data
        $repository = new BlazeCommerce_Order_Repository();
        $repository->save(array(
            'order_id' => 123,
            'status' => 'completed',
            'sync_status' => 'synced',
            'data' => array('test' => 'data')
        ));
        
        $request = new WP_REST_Request('GET', '/blazecommerce/v1/orders');
        $response = rest_get_server()->dispatch($request);
        
        $this->assertEquals(200, $response->get_status());
        
        $data = $response->get_data();
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertEquals(123, $data[0]->order_id);
    }
    
    /**
     * Test orders endpoint without proper permissions
     */
    public function test_get_orders_endpoint_without_permission() {
        wp_set_current_user($this->subscriber_user);
        
        $request = new WP_REST_Request('GET', '/blazecommerce/v1/orders');
        $response = rest_get_server()->dispatch($request);
        
        $this->assertEquals(403, $response->get_status());
    }
    
    /**
     * Test single order endpoint
     */
    public function test_get_single_order_endpoint() {
        wp_set_current_user($this->admin_user);
        
        // Create test order
        $repository = new BlazeCommerce_Order_Repository();
        $repository->save(array(
            'order_id' => 456,
            'status' => 'processing',
            'sync_status' => 'pending',
            'data' => array('customer' => 'John Doe')
        ));
        
        $request = new WP_REST_Request('GET', '/blazecommerce/v1/orders/456');
        $response = rest_get_server()->dispatch($request);
        
        $this->assertEquals(200, $response->get_status());
        
        $data = $response->get_data();
        $this->assertEquals(456, $data->order_id);
        $this->assertEquals('processing', $data->status);
    }
    
    /**
     * Test order not found
     */
    public function test_get_nonexistent_order() {
        wp_set_current_user($this->admin_user);
        
        $request = new WP_REST_Request('GET', '/blazecommerce/v1/orders/999');
        $response = rest_get_server()->dispatch($request);
        
        $this->assertEquals(404, $response->get_status());
        
        $data = $response->get_data();
        $this->assertEquals('order_not_found', $data['code']);
    }
}
```

## Test Configuration and Setup

### 1. Test Bootstrap Configuration
```php
<?php
// tests/bootstrap.php

// WordPress test environment
$_tests_dir = getenv('WP_TESTS_DIR');
if (!$_tests_dir) {
    $_tests_dir = '/tmp/wordpress-tests-lib';
}

// Give access to tests_add_filter() function
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested
 */
function _manually_load_plugin() {
    // Load WooCommerce first
    require dirname(dirname(__FILE__)) . '/vendor/woocommerce/woocommerce.php';
    
    // Load our plugin
    require dirname(dirname(__FILE__)) . '/blazecommerce.php';
}
tests_add_filter('muplugins_loaded', '_manually_load_plugin');

// Start up the WP testing environment
require $_tests_dir . '/includes/bootstrap.php';

// Load WooCommerce test framework
require dirname(dirname(__FILE__)) . '/vendor/woocommerce/tests/framework/class-wc-unit-test-case.php';
```

### 2. PHPUnit Configuration
```xml
<!-- phpunit.xml -->
<?xml version="1.0" encoding="UTF-8"?>
<phpunit
    bootstrap="tests/bootstrap.php"
    backupGlobals="false"
    colors="true"
    convertErrorsToExceptions="true"
    convertNoticesToExceptions="true"
    convertWarningsToExceptions="true"
    processIsolation="false"
    stopOnFailure="false">
    
    <testsuites>
        <testsuite name="Unit Tests">
            <directory>./tests/unit/</directory>
        </testsuite>
        <testsuite name="Integration Tests">
            <directory>./tests/integration/</directory>
        </testsuite>
    </testsuites>
    
    <filter>
        <whitelist processUncoveredFilesFromWhitelist="true">
            <directory suffix=".php">./includes/</directory>
            <directory suffix=".php">./admin/</directory>
            <directory suffix=".php">./public/</directory>
            <exclude>
                <directory>./tests/</directory>
                <directory>./vendor/</directory>
                <file>./blazecommerce.php</file>
                <file>./uninstall.php</file>
            </exclude>
        </whitelist>
    </filter>
    
    <logging>
        <log type="coverage-html" target="./tests/coverage/"/>
        <log type="coverage-clover" target="./tests/coverage.xml"/>
    </logging>
</phpunit>
```

## WordPress Plugin Context

These testing requirements apply specifically to:
- WordPress plugin functionality testing
- WooCommerce integration validation
- Database operations testing
- REST API endpoint testing
- WordPress hook and filter testing
- Multi-version compatibility testing
- Performance and security testing
- Admin interface testing
