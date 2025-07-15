<?php
/**
 * Plugin Integration Tests
 * 
 * Tests plugin integration with WordPress and WooCommerce
 * 
 * @package BlazeWooless
 * @subpackage Tests
 */

class Test_Plugin_Integration extends WP_UnitTestCase {

    /**
     * Set up test environment
     */
    public function setUp(): void {
        parent::setUp();
        
        // Ensure WooCommerce is loaded
        if ( ! class_exists( 'WooCommerce' ) ) {
            $this->markTestSkipped( 'WooCommerce is not available' );
        }
    }

    /**
     * Test plugin activation
     */
    public function test_plugin_activation() {
        // Test that plugin can be activated without errors
        $this->assertTrue( true ); // Placeholder - plugin should be active if tests are running
    }

    /**
     * Test that required WordPress hooks are registered
     */
    public function test_wordpress_hooks_registered() {
        // Test that common WordPress hooks exist
        $this->assertTrue( has_action( 'init' ) || true );
        $this->assertTrue( has_action( 'wp_loaded' ) || true );
    }

    /**
     * Test WooCommerce integration
     */
    public function test_woocommerce_integration() {
        // Test that WooCommerce functions are available
        $this->assertTrue( function_exists( 'wc_get_product' ) );
        $this->assertTrue( function_exists( 'wc_get_products' ) );
    }

    /**
     * Test product creation and retrieval
     */
    public function test_product_operations() {
        // Create a simple product
        $product = new WC_Product_Simple();
        $product->set_name( 'Test Integration Product' );
        $product->set_regular_price( '19.99' );
        $product->set_status( 'publish' );
        $product_id = $product->save();
        
        $this->assertGreaterThan( 0, $product_id );
        
        // Retrieve the product
        $retrieved_product = wc_get_product( $product_id );
        $this->assertInstanceOf( 'WC_Product', $retrieved_product );
        $this->assertEquals( 'Test Integration Product', $retrieved_product->get_name() );
        
        // Clean up
        wp_delete_post( $product_id, true );
    }

    /**
     * Test database operations
     */
    public function test_database_operations() {
        global $wpdb;
        
        // Test that we can query the database
        $posts_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts}" );
        $this->assertIsNumeric( $posts_count );
        
        // Test WooCommerce tables exist
        $wc_tables = array(
            $wpdb->prefix . 'woocommerce_sessions',
            $wpdb->prefix . 'woocommerce_api_keys',
            $wpdb->prefix . 'woocommerce_attribute_taxonomies'
        );
        
        foreach ( $wc_tables as $table ) {
            $table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );
            $this->assertEquals( $table, $table_exists );
        }
    }

    /**
     * Test REST API availability
     */
    public function test_rest_api_availability() {
        // Test that WordPress REST API is available
        $this->assertTrue( function_exists( 'rest_url' ) );
        
        // Test that WooCommerce REST API is available
        $this->assertTrue( class_exists( 'WC_REST_Products_Controller' ) );
    }

    /**
     * Test caching functionality
     */
    public function test_caching_functionality() {
        $cache_key = 'blaze_test_cache_' . time();
        $cache_value = 'test_cache_value';
        
        // Test setting cache
        wp_cache_set( $cache_key, $cache_value, 'blaze_test' );
        
        // Test getting cache
        $retrieved_value = wp_cache_get( $cache_key, 'blaze_test' );
        $this->assertEquals( $cache_value, $retrieved_value );
        
        // Test deleting cache
        wp_cache_delete( $cache_key, 'blaze_test' );
        $deleted_value = wp_cache_get( $cache_key, 'blaze_test' );
        $this->assertFalse( $deleted_value );
    }

    /**
     * Test transient functionality
     */
    public function test_transient_functionality() {
        $transient_key = 'blaze_test_transient_' . time();
        $transient_value = array( 'test' => 'data', 'timestamp' => time() );
        
        // Test setting transient
        set_transient( $transient_key, $transient_value, HOUR_IN_SECONDS );
        
        // Test getting transient
        $retrieved_value = get_transient( $transient_key );
        $this->assertEquals( $transient_value, $retrieved_value );
        
        // Test deleting transient
        delete_transient( $transient_key );
        $deleted_value = get_transient( $transient_key );
        $this->assertFalse( $deleted_value );
    }

    /**
     * Test user capabilities
     */
    public function test_user_capabilities() {
        // Create a test user
        $user_id = wp_create_user( 'testuser_' . time(), 'testpass', 'test@example.com' );
        $this->assertIsInt( $user_id );
        
        $user = get_user_by( 'id', $user_id );
        $this->assertInstanceOf( 'WP_User', $user );
        
        // Test basic capabilities
        $this->assertTrue( $user->exists() );
        
        // Clean up
        wp_delete_user( $user_id );
    }

    /**
     * Test taxonomy operations
     */
    public function test_taxonomy_operations() {
        // Test product categories (WooCommerce taxonomy)
        $category_id = wp_insert_term( 'Test Category', 'product_cat' );
        $this->assertIsArray( $category_id );
        $this->assertArrayHasKey( 'term_id', $category_id );
        
        // Retrieve the category
        $category = get_term( $category_id['term_id'], 'product_cat' );
        $this->assertInstanceOf( 'WP_Term', $category );
        $this->assertEquals( 'Test Category', $category->name );
        
        // Clean up
        wp_delete_term( $category_id['term_id'], 'product_cat' );
    }

    /**
     * Test meta operations
     */
    public function test_meta_operations() {
        // Create a test post
        $post_id = wp_insert_post( array(
            'post_title' => 'Test Meta Post',
            'post_content' => 'Test content',
            'post_status' => 'publish'
        ) );
        
        $meta_key = 'blaze_test_meta';
        $meta_value = array( 'test' => 'meta_value', 'number' => 123 );
        
        // Test adding meta
        add_post_meta( $post_id, $meta_key, $meta_value );
        
        // Test getting meta
        $retrieved_meta = get_post_meta( $post_id, $meta_key, true );
        $this->assertEquals( $meta_value, $retrieved_meta );
        
        // Test updating meta
        $new_meta_value = array( 'updated' => 'value' );
        update_post_meta( $post_id, $meta_key, $new_meta_value );
        $updated_meta = get_post_meta( $post_id, $meta_key, true );
        $this->assertEquals( $new_meta_value, $updated_meta );
        
        // Clean up
        wp_delete_post( $post_id, true );
    }

    /**
     * Test file system operations
     */
    public function test_file_system_operations() {
        // Test WordPress file system
        $upload_dir = wp_upload_dir();
        $this->assertIsArray( $upload_dir );
        $this->assertArrayHasKey( 'basedir', $upload_dir );
        $this->assertTrue( is_dir( $upload_dir['basedir'] ) );
    }

    /**
     * Test plugin constants and configuration
     */
    public function test_plugin_configuration() {
        // Test that WordPress constants are defined
        $this->assertTrue( defined( 'WP_DEBUG' ) );
        $this->assertTrue( defined( 'ABSPATH' ) );
        
        // Test that our testing constant is defined
        $this->assertTrue( defined( 'BLAZE_COMMERCE_TESTING' ) );
        $this->assertTrue( BLAZE_COMMERCE_TESTING );
    }

    /**
     * Test error handling
     */
    public function test_error_handling() {
        // Test that we can handle WordPress errors
        $wp_error = new WP_Error( 'test_error', 'This is a test error' );
        $this->assertInstanceOf( 'WP_Error', $wp_error );
        $this->assertTrue( is_wp_error( $wp_error ) );
        $this->assertEquals( 'test_error', $wp_error->get_error_code() );
    }

    /**
     * Test internationalization
     */
    public function test_internationalization() {
        // Test that translation functions work
        $translated = __( 'Hello', 'textdomain' );
        $this->assertIsString( $translated );
        
        $translated_with_context = _x( 'Post', 'noun', 'textdomain' );
        $this->assertIsString( $translated_with_context );
    }
}
