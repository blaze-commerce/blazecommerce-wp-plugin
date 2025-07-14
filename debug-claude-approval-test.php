<?php
/**
 * DEBUG: Claude Auto-Approval Workflow Test
 *
 * This file contains simple, secure code that should definitely pass
 * Claude AI review and trigger automatic approval. Used for debugging
 * the auto-approval workflow chain.
 *
 * @package BlazeCommerce
 * @version 1.0.0
 * @since 2025-07-14
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Simple utility class for debugging workflow
 * Contains only basic, secure functionality that should pass all checks
 */
class ClaudeApprovalDebugTest {
    
    /**
     * Version constant
     */
    const VERSION = '1.0.0';
    
    /**
     * Simple greeting function
     * 
     * @param string $name The name to greet
     * @return string Sanitized greeting message
     */
    public static function get_greeting($name = 'World') {
        // Sanitize input
        $clean_name = sanitize_text_field($name);
        
        // Return safe output
        return sprintf('Hello, %s! This is a debug test.', esc_html($clean_name));
    }
    
    /**
     * Get current timestamp in a safe format
     * 
     * @return string Current timestamp
     */
    public static function get_timestamp() {
        return current_time('Y-m-d H:i:s');
    }
    
    /**
     * Simple validation function
     * 
     * @param mixed $value Value to validate
     * @return bool True if valid
     */
    public static function is_valid_input($value) {
        // Basic validation - not empty and is string or number
        return !empty($value) && (is_string($value) || is_numeric($value));
    }
    
    /**
     * Debug information for workflow testing
     * 
     * @return array Debug information
     */
    public static function get_debug_info() {
        return array(
            'version' => self::VERSION,
            'timestamp' => self::get_timestamp(),
            'status' => 'ready_for_claude_review',
            'security_level' => 'high',
            'test_purpose' => 'debug_auto_approval_workflow'
        );
    }
}

/**
 * Simple function to demonstrate secure coding practices
 * This should definitely pass Claude AI security review
 */
function debug_claude_approval_test() {
    // Get debug info
    $debug_info = ClaudeApprovalDebugTest::get_debug_info();
    
    // Log for debugging (safe logging)
    if (function_exists('error_log')) {
        error_log('Claude Auto-Approval Debug Test: ' . wp_json_encode($debug_info));
    }
    
    return $debug_info;
}

/**
 * WordPress hook integration (if in WordPress context)
 */
if (function_exists('add_action')) {
    add_action('init', function() {
        // Only run in admin or during AJAX
        if (is_admin() || wp_doing_ajax()) {
            debug_claude_approval_test();
        }
    });
}
