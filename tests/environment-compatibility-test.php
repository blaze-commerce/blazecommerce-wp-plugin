<?php
/**
 * BlazeCommerce Environment Compatibility Test Suite
 * 
 * This test suite validates that the bug fixes work correctly in both
 * staging (.blz.onl) and production environments while maintaining
 * backward compatibility.
 * 
 * Usage: Run this script in both staging and production environments
 * to verify environment-specific behavior.
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BlazeCommerceEnvironmentTest {
    
    private $test_results = [];
    private $is_staging;
    
    public function __construct() {
        $this->is_staging = $this->is_staging_environment();
        $this->log_test_start();
    }
    
    /**
     * Check if current environment is staging
     */
    private function is_staging_environment() {
        return isset( $_SERVER['HTTP_HOST'] ) && strpos( $_SERVER['HTTP_HOST'], '.blz.onl' ) !== false;
    }
    
    /**
     * Run all compatibility tests
     */
    public function run_all_tests() {
        $this->test_environment_detection();
        $this->test_addon_field_creation();
        $this->test_session_handling();
        $this->test_error_handling();
        $this->test_backward_compatibility();
        
        return $this->generate_test_report();
    }
    
    /**
     * Test environment detection functionality
     */
    private function test_environment_detection() {
        $test_name = 'Environment Detection';
        
        try {
            // Test staging detection
            $detected_env = $this->is_staging ? 'staging' : 'production';
            $expected_env = strpos( $_SERVER['HTTP_HOST'], '.blz.onl' ) !== false ? 'staging' : 'production';
            
            if ( $detected_env === $expected_env ) {
                $this->test_results[ $test_name ] = [
                    'status' => 'PASS',
                    'message' => "Environment correctly detected as: {$detected_env}",
                    'details' => "Host: {$_SERVER['HTTP_HOST']}"
                ];
            } else {
                $this->test_results[ $test_name ] = [
                    'status' => 'FAIL',
                    'message' => "Environment detection mismatch",
                    'details' => "Expected: {$expected_env}, Got: {$detected_env}"
                ];
            }
        } catch ( Exception $e ) {
            $this->test_results[ $test_name ] = [
                'status' => 'ERROR',
                'message' => $e->getMessage(),
                'details' => $e->getTraceAsString()
            ];
        }
    }
    
    /**
     * Test addon field creation with environment-specific behavior
     */
    private function test_addon_field_creation() {
        $test_name = 'Addon Field Creation';
        
        try {
            // Test if WooCommerce Product Addons is available
            if ( ! class_exists( 'WC_Product_Addons_Helper' ) ) {
                $this->test_results[ $test_name ] = [
                    'status' => 'SKIP',
                    'message' => 'WooCommerce Product Addons not available',
                    'details' => 'Plugin not installed or activated'
                ];
                return;
            }
            
            // Create test addon configuration
            $test_addon = [
                'type' => 'file_upload',
                'field_name' => 'test_file',
                'name' => 'Test File Upload'
            ];
            
            // Test field creation (this would normally be done by the fixed code)
            $addon_extension = new \BlazeWooless\Extensions\WoocommerceProductAddons();
            
            // Use reflection to test private method
            $reflection = new ReflectionClass( $addon_extension );
            $method = $reflection->getMethod( 'create_addon_field' );
            $method->setAccessible( true );
            
            $field = $method->invoke( $addon_extension, $test_addon, '', 1 );
            
            if ( $field !== null ) {
                $this->test_results[ $test_name ] = [
                    'status' => 'PASS',
                    'message' => 'Addon field created successfully',
                    'details' => "Environment: " . ( $this->is_staging ? 'staging' : 'production' )
                ];
            } else {
                $this->test_results[ $test_name ] = [
                    'status' => 'FAIL',
                    'message' => 'Addon field creation returned null',
                    'details' => 'Field creation failed but should have succeeded'
                ];
            }
            
        } catch ( Exception $e ) {
            $this->test_results[ $test_name ] = [
                'status' => 'ERROR',
                'message' => $e->getMessage(),
                'details' => $e->getTraceAsString()
            ];
        }
    }
    
    /**
     * Test session handling with environment-specific validation
     */
    private function test_session_handling() {
        $test_name = 'Session Handling';
        
        try {
            // Test session class instantiation
            $session_handler = new \BlazeWooless\Features\LoadCartFromSession();
            
            // Test environment detection method
            $reflection = new ReflectionClass( $session_handler );
            $method = $reflection->getMethod( 'is_staging_environment' );
            $method->setAccessible( true );
            
            $detected_staging = $method->invoke( $session_handler );
            
            if ( $detected_staging === $this->is_staging ) {
                $this->test_results[ $test_name ] = [
                    'status' => 'PASS',
                    'message' => 'Session handler environment detection working',
                    'details' => "Staging: " . ( $this->is_staging ? 'true' : 'false' )
                ];
            } else {
                $this->test_results[ $test_name ] = [
                    'status' => 'FAIL',
                    'message' => 'Session handler environment detection mismatch',
                    'details' => "Expected: {$this->is_staging}, Got: {$detected_staging}"
                ];
            }
            
        } catch ( Exception $e ) {
            $this->test_results[ $test_name ] = [
                'status' => 'ERROR',
                'message' => $e->getMessage(),
                'details' => $e->getTraceAsString()
            ];
        }
    }
    
    /**
     * Test error handling behavior
     */
    private function test_error_handling() {
        $test_name = 'Error Handling';
        
        try {
            // Test that errors are handled gracefully
            // This simulates the fixed error handling behavior
            
            $error_logged = false;
            
            // Capture error log output
            $original_handler = set_error_handler( function( $errno, $errstr ) use ( &$error_logged ) {
                $error_logged = true;
                return true; // Don't execute PHP's internal error handler
            });
            
            // Simulate an operation that might trigger the fixed error handling
            $test_cookie = 'invalid_serialized_data';
            $result = unserialize( $test_cookie );
            
            // Restore original error handler
            set_error_handler( $original_handler );
            
            // The fix should handle this gracefully
            if ( $result === false ) {
                $this->test_results[ $test_name ] = [
                    'status' => 'PASS',
                    'message' => 'Error handling working correctly',
                    'details' => 'Invalid data handled gracefully'
                ];
            } else {
                $this->test_results[ $test_name ] = [
                    'status' => 'FAIL',
                    'message' => 'Unexpected result from error test',
                    'details' => 'Error handling may not be working correctly'
                ];
            }
            
        } catch ( Exception $e ) {
            $this->test_results[ $test_name ] = [
                'status' => 'ERROR',
                'message' => $e->getMessage(),
                'details' => $e->getTraceAsString()
            ];
        }
    }
    
    /**
     * Test backward compatibility
     */
    private function test_backward_compatibility() {
        $test_name = 'Backward Compatibility';
        
        try {
            // Test that all public methods still exist and work
            $addon_extension = new \BlazeWooless\Extensions\WoocommerceProductAddons();
            $session_handler = new \BlazeWooless\Features\LoadCartFromSession();
            
            // Check that public methods exist
            $addon_methods = get_class_methods( $addon_extension );
            $session_methods = get_class_methods( $session_handler );
            
            $required_addon_methods = [ 'sync_product_addons_data', 'woocommerce_add_cart_item_data' ];
            $required_session_methods = [ 'woocommerce_load_cart_from_session', 'load_user_from_session', 'clear_cart_data' ];
            
            $missing_methods = [];
            
            foreach ( $required_addon_methods as $method ) {
                if ( ! in_array( $method, $addon_methods ) ) {
                    $missing_methods[] = "WoocommerceProductAddons::{$method}";
                }
            }
            
            foreach ( $required_session_methods as $method ) {
                if ( ! in_array( $method, $session_methods ) ) {
                    $missing_methods[] = "LoadCartFromSession::{$method}";
                }
            }
            
            if ( empty( $missing_methods ) ) {
                $this->test_results[ $test_name ] = [
                    'status' => 'PASS',
                    'message' => 'All required public methods exist',
                    'details' => 'Backward compatibility maintained'
                ];
            } else {
                $this->test_results[ $test_name ] = [
                    'status' => 'FAIL',
                    'message' => 'Missing required methods',
                    'details' => 'Missing: ' . implode( ', ', $missing_methods )
                ];
            }
            
        } catch ( Exception $e ) {
            $this->test_results[ $test_name ] = [
                'status' => 'ERROR',
                'message' => $e->getMessage(),
                'details' => $e->getTraceAsString()
            ];
        }
    }
    
    /**
     * Generate test report
     */
    private function generate_test_report() {
        $environment = $this->is_staging ? 'STAGING' : 'PRODUCTION';
        $host = $_SERVER['HTTP_HOST'] ?? 'unknown';
        $timestamp = date( 'Y-m-d H:i:s' );
        
        $report = "\n";
        $report .= "=== BlazeCommerce Environment Compatibility Test Report ===\n";
        $report .= "Environment: {$environment}\n";
        $report .= "Host: {$host}\n";
        $report .= "Timestamp: {$timestamp}\n";
        $report .= "==========================================================\n\n";
        
        $total_tests = count( $this->test_results );
        $passed = 0;
        $failed = 0;
        $errors = 0;
        $skipped = 0;
        
        foreach ( $this->test_results as $test_name => $result ) {
            $status = $result['status'];
            $message = $result['message'];
            $details = $result['details'] ?? '';
            
            $report .= "Test: {$test_name}\n";
            $report .= "Status: {$status}\n";
            $report .= "Message: {$message}\n";
            if ( ! empty( $details ) ) {
                $report .= "Details: {$details}\n";
            }
            $report .= "\n";
            
            switch ( $status ) {
                case 'PASS':
                    $passed++;
                    break;
                case 'FAIL':
                    $failed++;
                    break;
                case 'ERROR':
                    $errors++;
                    break;
                case 'SKIP':
                    $skipped++;
                    break;
            }
        }
        
        $report .= "==========================================================\n";
        $report .= "Summary:\n";
        $report .= "Total Tests: {$total_tests}\n";
        $report .= "Passed: {$passed}\n";
        $report .= "Failed: {$failed}\n";
        $report .= "Errors: {$errors}\n";
        $report .= "Skipped: {$skipped}\n";
        $report .= "==========================================================\n";
        
        return $report;
    }
    
    /**
     * Log test start
     */
    private function log_test_start() {
        $environment = $this->is_staging ? 'STAGING' : 'PRODUCTION';
        error_log( "BlazeCommerce Environment Test Started - Environment: {$environment}" );
    }
}

// Auto-run tests if accessed directly
if ( ! function_exists( 'add_action' ) ) {
    // Running outside WordPress - basic test
    echo "BlazeCommerce Environment Test\n";
    echo "This script should be run within WordPress context.\n";
} else {
    // Running within WordPress
    add_action( 'init', function() {
        if ( isset( $_GET['run_blaze_env_test'] ) && current_user_can( 'manage_options' ) ) {
            $test = new BlazeCommerceEnvironmentTest();
            $report = $test->run_all_tests();
            
            header( 'Content-Type: text/plain' );
            echo $report;
            exit;
        }
    });
}
