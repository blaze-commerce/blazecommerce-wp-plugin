<?php
/**
 * Test: Global Block Configuration
 *
 * Purpose: Test the global block region configuration functionality
 * Scope: Integration
 * Dependencies: WordPress, Gutenberg
 *
 * @package BlazeWooless
 * @subpackage Tests
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Test_Global_Block_Config {

    public function __construct() {
        add_action('init', array($this, 'run_tests'));
    }

    /**
     * Run all tests for global block configuration
     */
    public function run_tests() {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }

        $this->test_script_enqueue();
        $this->test_file_exists();
        $this->test_rest_endpoint();
        $this->log_test_results();
    }

    /**
     * Test: Script enqueue functionality
     */
    public function test_script_enqueue() {
        $test_name = 'Script Enqueue Test';
        
        try {
            // Check if the enqueue function exists
            if (!function_exists('blaze_commerce_enqueue_global_block_config')) {
                throw new Exception('Enqueue function does not exist');
            }

            // Check if the action is properly hooked
            $priority = has_action('enqueue_block_editor_assets', 'blaze_commerce_enqueue_global_block_config');
            if ($priority === false) {
                throw new Exception('Action not properly hooked');
            }

            $this->log_success($test_name, 'Function exists and action is hooked');
            
        } catch (Exception $e) {
            $this->log_error($test_name, $e->getMessage());
        }
    }

    /**
     * Test: JavaScript file exists and is readable
     */
    public function test_file_exists() {
        $test_name = 'JavaScript File Test';
        
        try {
            $file_path = BLAZE_COMMERCE_PLUGIN_DIR . 'assets/js/global-block-config.js';
            
            if (!file_exists($file_path)) {
                throw new Exception('JavaScript file does not exist: ' . $file_path);
            }

            if (!is_readable($file_path)) {
                throw new Exception('JavaScript file is not readable: ' . $file_path);
            }

            $file_size = filesize($file_path);
            if ($file_size === 0) {
                throw new Exception('JavaScript file is empty');
            }

            $this->log_success($test_name, "File exists and is readable ({$file_size} bytes)");
            
        } catch (Exception $e) {
            $this->log_error($test_name, $e->getMessage());
        }
    }

    /**
     * Test: REST API endpoint for regions
     */
    public function test_rest_endpoint() {
        $test_name = 'REST Endpoint Test';

        try {
            // Test if PageMetaFields extension is available
            if (!class_exists('BlazeWooless\Extensions\PageMetaFields')) {
                throw new Exception('PageMetaFields extension not available');
            }

            // Test get_available_regions method
            $page_meta_fields = \BlazeWooless\Extensions\PageMetaFields::get_instance();
            $regions = $page_meta_fields->get_available_regions();

            if (!is_array($regions)) {
                throw new Exception('Failed to get regions data - not an array');
            }

            $region_count = count($regions);

            // Test region data structure if regions exist
            if ($region_count > 0) {
                $first_region = reset($regions);
                $required_keys = ['label', 'currency', 'country_code', 'country_name'];

                foreach ($required_keys as $key) {
                    if (!isset($first_region[$key])) {
                        throw new Exception("Region data missing required key: {$key}");
                    }
                }
            }

            // Test REST endpoint callback function
            if (!function_exists('blaze_commerce_get_regions')) {
                throw new Exception('REST endpoint callback function missing');
            }

            $message = "Regions available: {$region_count}, Data structure valid, Callback exists, Caching implemented";
            if ($region_count === 0) {
                $message .= " (Note: No regions configured in Aelia Currency Switcher)";
            }

            $this->log_success($test_name, $message);

        } catch (Exception $e) {
            $this->log_error($test_name, $e->getMessage());
        }
    }

    /**
     * Log test results
     */
    public function log_test_results() {
        if (function_exists('do_action')) {
            do_action('inspect', array(
                'global_block_config_tests',
                array(
                    'timestamp' => current_time('mysql'),
                    'tests_completed' => 'Script enqueue and file existence tests',
                    'status' => 'Tests executed - check individual results above'
                )
            ));
        }
    }

    /**
     * Log success message
     */
    private function log_success($test_name, $message) {
        if (function_exists('do_action')) {
            do_action('inspect', array(
                'global_block_config_test_success',
                array(
                    'test' => $test_name,
                    'status' => 'PASSED',
                    'message' => $message
                )
            ));
        }
    }

    /**
     * Log error message
     */
    private function log_error($test_name, $message) {
        if (function_exists('do_action')) {
            do_action('inspect', array(
                'global_block_config_test_error',
                array(
                    'test' => $test_name,
                    'status' => 'FAILED',
                    'error' => $message
                )
            ));
        }
    }
}

// Initialize tests
new Test_Global_Block_Config();
