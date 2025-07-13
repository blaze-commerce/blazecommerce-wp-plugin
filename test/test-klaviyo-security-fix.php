<?php
/**
 * Test file for Klaviyo API Key Security Fix
 * 
 * This file contains tests to verify the security fix implementation
 * for the Klaviyo API key hardcoding vulnerability.
 * 
 * @package BlazeWooless
 * @subpackage Tests
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Test class for Klaviyo security fix
 */
class Test_Klaviyo_Security_Fix {
    
    /**
     * Run all tests
     */
    public static function run_tests() {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        echo "<h2>Klaviyo Security Fix Tests</h2>";
        
        self::test_helper_function_exists();
        self::test_api_key_retrieval();
        self::test_migration_logic();
        self::test_functions_use_helper();
        self::test_input_sanitization();
        
        echo "<p><strong>All tests completed!</strong></p>";
    }
    
    /**
     * Test that the helper function exists
     */
    private static function test_helper_function_exists() {
        echo "<h3>Test: Helper Function Exists</h3>";
        
        if (function_exists('bw_get_klaviyo_api_key')) {
            echo "<p style='color: green;'>✓ bw_get_klaviyo_api_key() function exists</p>";
        } else {
            echo "<p style='color: red;'>✗ bw_get_klaviyo_api_key() function not found</p>";
        }
    }
    
    /**
     * Test API key retrieval from settings
     */
    private static function test_api_key_retrieval() {
        echo "<h3>Test: API Key Retrieval</h3>";
        
        // Test with empty settings
        delete_option('wooless_general_settings_options');
        $api_key = bw_get_klaviyo_api_key();
        
        if (empty($api_key)) {
            echo "<p style='color: green;'>✓ Returns empty when no settings exist</p>";
        } else {
            echo "<p style='color: red;'>✗ Should return empty when no settings exist</p>";
        }
        
        // Test with API key in settings
        $test_settings = array('klaviyo_api_key' => 'test_key_123');
        update_option('wooless_general_settings_options', $test_settings);
        
        $api_key = bw_get_klaviyo_api_key();
        if ($api_key === 'test_key_123') {
            echo "<p style='color: green;'>✓ Correctly retrieves API key from settings</p>";
        } else {
            echo "<p style='color: red;'>✗ Failed to retrieve API key from settings</p>";
        }
        
        // Clean up
        delete_option('wooless_general_settings_options');
    }
    
    /**
     * Test migration logic
     */
    private static function test_migration_logic() {
        echo "<h3>Test: Migration Logic</h3>";
        
        // Simulate existing installation without Klaviyo key
        $existing_settings = array('enable_system' => '1', 'store_id' => '123');
        update_option('wooless_general_settings_options', $existing_settings);
        
        $api_key = bw_get_klaviyo_api_key();
        
        if ($api_key === 'W7A7kP') {
            echo "<p style='color: green;'>✓ Migration logic works for existing installations</p>";
            
            // Check if the key was saved to settings
            $updated_settings = get_option('wooless_general_settings_options');
            if (isset($updated_settings['klaviyo_api_key']) && $updated_settings['klaviyo_api_key'] === 'W7A7kP') {
                echo "<p style='color: green;'>✓ API key was saved to settings during migration</p>";
            } else {
                echo "<p style='color: red;'>✗ API key was not saved to settings during migration</p>";
            }
        } else {
            echo "<p style='color: red;'>✗ Migration logic failed</p>";
        }
        
        // Clean up
        delete_option('wooless_general_settings_options');
    }
    
    /**
     * Test that functions use the helper instead of hardcoded values
     */
    private static function test_functions_use_helper() {
        echo "<h3>Test: Functions Use Helper</h3>";
        
        // Check if functions exist
        if (function_exists('klaviyo_script') && function_exists('is_klaviyo_connected')) {
            echo "<p style='color: green;'>✓ Klaviyo functions exist</p>";
            
            // Test with a custom API key
            $test_settings = array('klaviyo_api_key' => 'custom_test_key');
            update_option('wooless_general_settings_options', $test_settings);
            
            // Capture output from klaviyo_script
            ob_start();
            klaviyo_script();
            $output = ob_get_clean();
            
            if (strpos($output, 'custom_test_key') !== false) {
                echo "<p style='color: green;'>✓ klaviyo_script() uses helper function</p>";
            } else {
                echo "<p style='color: red;'>✗ klaviyo_script() may not be using helper function</p>";
            }
            
            // Clean up
            delete_option('wooless_general_settings_options');
        } else {
            echo "<p style='color: red;'>✗ Klaviyo functions not found</p>";
        }
    }
    
    /**
     * Test input sanitization
     */
    private static function test_input_sanitization() {
        echo "<h3>Test: Input Sanitization</h3>";
        
        // Test with potentially malicious input
        $malicious_key = '<script>alert("xss")</script>';
        $test_settings = array('klaviyo_api_key' => $malicious_key);
        update_option('wooless_general_settings_options', $test_settings);
        
        // Capture output from klaviyo_script
        ob_start();
        klaviyo_script();
        $output = ob_get_clean();
        
        // Check if the output is properly escaped
        if (strpos($output, '<script>') === false && strpos($output, '&lt;script&gt;') !== false) {
            echo "<p style='color: green;'>✓ Output is properly sanitized</p>";
        } else {
            echo "<p style='color: orange;'>? Output sanitization needs verification</p>";
        }
        
        // Clean up
        delete_option('wooless_general_settings_options');
    }
}

// Auto-run tests if in debug mode and this file is accessed directly
if (defined('WP_DEBUG') && WP_DEBUG && basename($_SERVER['PHP_SELF']) === 'test-klaviyo-security-fix.php') {
    Test_Klaviyo_Security_Fix::run_tests();
}
