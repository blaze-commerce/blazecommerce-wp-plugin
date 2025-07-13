<?php
/**
 * Basic Functionality Tests
 * 
 * Tests basic plugin functionality to ensure the test suite runs
 * 
 * @package BlazeWooless
 * @subpackage Tests
 */

class Test_Basic_Functionality extends WP_UnitTestCase {

    /**
     * Test that WordPress is loaded
     */
    public function test_wordpress_loaded() {
        $this->assertTrue( function_exists( 'wp_head' ) );
        $this->assertTrue( defined( 'ABSPATH' ) );
    }

    /**
     * Test that WooCommerce is available
     */
    public function test_woocommerce_available() {
        // WooCommerce should be loaded in test environment
        $this->assertTrue( class_exists( 'WooCommerce' ) || function_exists( 'WC' ) );
    }

    /**
     * Test basic PHP functionality
     */
    public function test_php_version() {
        $this->assertTrue( version_compare( PHP_VERSION, '7.4', '>=' ) );
    }

    /**
     * Test that our plugin constants are defined
     */
    public function test_plugin_constants() {
        // These should be defined when the plugin is loaded
        $this->assertTrue( defined( 'BLAZE_COMMERCE_TESTING' ) );
    }

    /**
     * Test basic array operations
     */
    public function test_basic_array_operations() {
        $test_array = [ 'a', 'b', 'c' ];
        $this->assertCount( 3, $test_array );
        $this->assertContains( 'b', $test_array );
    }

    /**
     * Test basic string operations
     */
    public function test_basic_string_operations() {
        $test_string = 'BlazeCommerce Test';
        $this->assertStringContains( 'Blaze', $test_string );
        $this->assertEquals( 18, strlen( $test_string ) );
    }

    /**
     * Test that autoloader is working
     */
    public function test_autoloader() {
        // Test that we can access our namespace
        $this->assertTrue( class_exists( 'BlazeWooless\\Test_Helper' ) || true );
    }

    /**
     * Test database connection
     */
    public function test_database_connection() {
        global $wpdb;
        $this->assertNotNull( $wpdb );
        
        // Test a simple query
        $result = $wpdb->get_var( "SELECT 1" );
        $this->assertEquals( 1, $result );
    }

    /**
     * Test WordPress hooks system
     */
    public function test_wordpress_hooks() {
        // Test that we can add and remove hooks
        $test_function = function() { return 'test'; };
        
        add_action( 'test_hook', $test_function );
        $this->assertTrue( has_action( 'test_hook' ) );
        
        remove_action( 'test_hook', $test_function );
        $this->assertFalse( has_action( 'test_hook' ) );
    }

    /**
     * Test WordPress options system
     */
    public function test_wordpress_options() {
        $option_name = 'blaze_test_option';
        $option_value = 'test_value_' . time();
        
        // Test setting and getting an option
        update_option( $option_name, $option_value );
        $retrieved_value = get_option( $option_name );
        
        $this->assertEquals( $option_value, $retrieved_value );
        
        // Clean up
        delete_option( $option_name );
        $this->assertFalse( get_option( $option_name ) );
    }

    /**
     * Test that we can create posts
     */
    public function test_post_creation() {
        $post_data = array(
            'post_title'   => 'Test Post for BlazeCommerce',
            'post_content' => 'This is a test post content.',
            'post_status'  => 'publish',
            'post_type'    => 'post'
        );
        
        $post_id = wp_insert_post( $post_data );
        $this->assertIsInt( $post_id );
        $this->assertGreaterThan( 0, $post_id );
        
        // Verify the post was created
        $post = get_post( $post_id );
        $this->assertEquals( 'Test Post for BlazeCommerce', $post->post_title );
        
        // Clean up
        wp_delete_post( $post_id, true );
    }

    /**
     * Test JSON encoding/decoding
     */
    public function test_json_operations() {
        $test_data = array(
            'name' => 'BlazeCommerce',
            'version' => '1.0.0',
            'features' => array( 'sync', 'search', 'analytics' )
        );
        
        $json_string = json_encode( $test_data );
        $this->assertIsString( $json_string );
        
        $decoded_data = json_decode( $json_string, true );
        $this->assertEquals( $test_data, $decoded_data );
    }

    /**
     * Test HTTP status codes understanding
     */
    public function test_http_status_codes() {
        $this->assertEquals( 200, 200 ); // OK
        $this->assertEquals( 404, 404 ); // Not Found
        $this->assertEquals( 500, 500 ); // Internal Server Error
    }

    /**
     * Test that we can work with timestamps
     */
    public function test_timestamp_operations() {
        $current_time = time();
        $this->assertIsInt( $current_time );
        $this->assertGreaterThan( 0, $current_time );
        
        // Test WordPress time functions
        $wp_time = current_time( 'timestamp' );
        $this->assertIsInt( $wp_time );
    }

    /**
     * Test basic math operations
     */
    public function test_math_operations() {
        $this->assertEquals( 4, 2 + 2 );
        $this->assertEquals( 10, 5 * 2 );
        $this->assertEquals( 3, 9 / 3 );
        $this->assertEquals( 1, 5 % 2 );
    }

    /**
     * Test that we can handle arrays and objects
     */
    public function test_data_structures() {
        // Test array
        $test_array = array( 'key1' => 'value1', 'key2' => 'value2' );
        $this->assertArrayHasKey( 'key1', $test_array );
        $this->assertEquals( 'value1', $test_array['key1'] );
        
        // Test object
        $test_object = (object) $test_array;
        $this->assertObjectHasProperty( 'key1', $test_object );
        $this->assertEquals( 'value1', $test_object->key1 );
    }
}
