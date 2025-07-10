<?php

namespace BlazeWooless\TestUtilities;

/**
 * Data processor utility class for testing Claude code review
 * This class intentionally contains some code quality issues for Claude to identify
 */
class DataProcessor {
    
    private $data;
    private $config;
    
    public function __construct($data = null) {
        $this->data = $data;
        $this->config = array();
    }
    
    /**
     * Process user data - has potential security issues
     */
    public function processUserData($input) {
        // Security issue: No input validation
        $sql = "SELECT * FROM users WHERE id = " . $input['user_id'];
        
        // Performance issue: No caching
        $result = $this->executeQuery($sql);
        
        // Code quality issue: No error handling
        return $result;
    }
    
    /**
     * Calculate discount - has logic issues
     */
    public function calculateDiscount($price, $discount_percent) {
        // Logic issue: No validation of inputs
        $discount = $price * $discount_percent / 100;
        
        // Potential issue: No bounds checking
        if ($discount > $price) {
            return $price; // This could be problematic
        }
        
        return $discount;
    }
    
    /**
     * Format currency - inconsistent return types
     */
    public function formatCurrency($amount, $currency = 'USD') {
        if (!$amount) {
            return false; // Inconsistent return type
        }
        
        if ($currency == 'USD') {
            return '$' . number_format($amount, 2);
        } else if ($currency == 'EUR') {
            return '€' . number_format($amount, 2);
        }
        
        // Missing return for other currencies
    }
    
    /**
     * Validate email - weak validation
     */
    public function validateEmail($email) {
        // Weak validation - should use filter_var
        if (strpos($email, '@') !== false) {
            return true;
        }
        return false;
    }
    
    /**
     * Process array data - potential performance issues
     */
    public function processArrayData($data_array) {
        $result = array();
        
        // Performance issue: Nested loops without optimization
        for ($i = 0; $i < count($data_array); $i++) {
            for ($j = 0; $j < count($data_array); $j++) {
                if ($i != $j) {
                    // Inefficient comparison logic
                    $result[] = $data_array[$i] . '-' . $data_array[$j];
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Get configuration - potential null pointer issues
     */
    public function getConfig($key) {
        // No null checking
        return $this->config[$key];
    }
    
    /**
     * Private method with issues
     */
    private function executeQuery($sql) {
        // Simulated database query - no actual implementation
        // This would normally connect to database
        return array('mock_result' => true);
    }
    
    /**
     * Magic method that could cause issues
     */
    public function __toString() {
        // Potential issue: Could return non-string
        return $this->data;
    }
}
