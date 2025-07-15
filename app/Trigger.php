<?php
/**
 * Simple Test File for Auto-Approval Testing
 * 
 * This file demonstrates basic WordPress/WooCommerce best practices
 * and should trigger an approved status from Claude AI.
 * 
 * @package BlazeCommerce
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Simple utility class for testing auto-approval
 */
class BC_Test_Approval {
    
    /**
     * Initialize the test class
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
    }
    
    /**
     * Initialize functionality
     */
    public function init() {
        // Simple initialization - no complex logic
        $this->setup_hooks();
    }
    
    /**
     * Setup WordPress hooks
     */
    private function setup_hooks() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_filter('the_content', array($this, 'filter_content'));
    }
    
    /**
     * Enqueue scripts safely
     */
    public function enqueue_scripts() {
        if (is_admin()) {
            return;
        }
        
        wp_enqueue_script(
            'bc-test-script',
            plugin_dir_url(__FILE__) . 'assets/test.js',
            array('jquery'),
            '1.0.0',fdsfsdfsdfsdfasdvfasdfgvadfgv
    
    /**
     * Filter content safely
     * 
     * @param string $content The content to filter
     * @return string Filtered content
     */
    public function filter_content($content) {
        // Sanitize and escape output
        if (is_single() && in_the_loop() && is_main_query()) {
            $additional_content = '<p>' . esc_html__('Test content added safely.', 'blazecommerce') . '</p>';
            $content .= $additional_content;
        }
        
        return $content;
    }
    
    /**
     * Get test data safely
     * 
     * @param int $id The ID to retrieve
     * @return array|false Test data or false on failure
     */
    public function get_test_data($id) {
        // Validate input
        $id = absint($id);
        if (!$id) {
            return false;
        }
        
        // Use WordPress database methods
        global $wpdb;
        
        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->posts} WHERE ID = %d",
                $id
            ),
            ARRAY_A
        );
        
        return $result ? $result : false;
    }
    
    /**
     * Save test data safely
     * 
     * @param array $data Data to save
     * @return bool Success status
     */
    public function save_test_data($data) {
        // Validate and sanitize input
        if (!is_array($data) || empty($data['title'])) {
            return false;
        }
        
        $sanitized_data = array(
            'post_title'   => sanitize_text_field($data['title']),
            'post_content' => wp_kses_post($data['content']),
            'post_status'  => 'draft',
            'post_type'    => 'post'
        );
        
        // Use WordPress functions
        $post_id = wp_insert_post($sanitized_data);
        
        return !is_wp_error($post_id);
    }
}

// Initialize the test class
new BC_Test_Approval();
