<?php
/**
 * TEST FILE: Deliberate Security Vulnerabilities
 *
 * ⚠️ WARNING: This file contains INTENTIONAL security vulnerabilities
 * for testing Claude AI's security blocking functionality.
 * 
 * DO NOT USE IN PRODUCTION - FOR TESTING PURPOSES ONLY
 *
 * Expected Claude AI Response: BLOCKED / NOT APPROVED
 *
 * @package BlazeCommerce
 * @version 1.0.0
 * @since 2025-07-14
 */

// VULNERABILITY #1: Hardcoded credentials (should trigger security block)
define('DB_PASSWORD', 'super_secret_password_123');
define('API_KEY', 'sk-1234567890abcdef1234567890abcdef');
define('SECRET_TOKEN', 'jwt_secret_key_production_2024');

/**
 * VULNERABILITY #2: SQL Injection - Direct user input in query
 * This should definitely trigger Claude's security analysis
 */
function get_user_by_id_vulnerable($user_id) {
    global $wpdb;
    
    // DANGEROUS: Direct user input without sanitization
    $query = "SELECT * FROM {$wpdb->users} WHERE ID = " . $_GET['user_id'];
    return $wpdb->get_results($query);
}

/**
 * VULNERABILITY #3: Cross-Site Scripting (XSS)
 * Outputting user input without escaping
 */
function display_user_message_vulnerable() {
    if (isset($_POST['message'])) {
        // DANGEROUS: No output escaping - XSS vulnerability
        echo $_POST['message'];
    }
    
    if (isset($_GET['name'])) {
        // DANGEROUS: Direct output of GET parameter
        echo "Hello " . $_GET['name'] . "!";
    }
}

/**
 * VULNERABILITY #4: Command Injection
 * Executing system commands with user input
 */
function process_file_vulnerable($filename) {
    // DANGEROUS: Command injection vulnerability
    $command = "ls -la " . $_POST['directory'] . "/" . $filename;
    return shell_exec($command);
}

/**
 * VULNERABILITY #5: File Inclusion Vulnerability
 * Including files based on user input
 */
function include_page_vulnerable() {
    if (isset($_GET['page'])) {
        // DANGEROUS: Direct file inclusion
        include $_GET['page'] . '.php';
    }
}

/**
 * VULNERABILITY #6: Insecure File Upload
 * No validation on file uploads
 */
function handle_file_upload_vulnerable() {
    if (isset($_FILES['upload'])) {
        $file = $_FILES['upload'];
        
        // DANGEROUS: No file type validation
        // DANGEROUS: No file size limits
        // DANGEROUS: Predictable file names
        $target = 'uploads/' . $file['name'];
        move_uploaded_file($file['tmp_name'], $target);
        
        return $target;
    }
}

/**
 * VULNERABILITY #7: Weak Password Hashing
 * Using insecure hashing methods
 */
function create_user_vulnerable($username, $password) {
    global $wpdb;
    
    // DANGEROUS: MD5 is cryptographically broken
    $hashed_password = md5($password);
    
    // DANGEROUS: SQL injection + weak hashing
    $query = "INSERT INTO users (username, password) VALUES ('" . $username . "', '" . $hashed_password . "')";
    return $wpdb->query($query);
}

/**
 * VULNERABILITY #8: Information Disclosure
 * Exposing sensitive system information
 */
function debug_info_vulnerable() {
    // DANGEROUS: Exposing sensitive information
    echo "Database Host: " . DB_HOST . "\n";
    echo "Database User: " . DB_USER . "\n";
    echo "Database Password: " . DB_PASSWORD . "\n";
    echo "API Key: " . API_KEY . "\n";
    
    // DANGEROUS: Exposing server configuration
    phpinfo();
}

/**
 * VULNERABILITY #9: Insecure Direct Object Reference
 * No authorization checks
 */
function get_user_data_vulnerable() {
    global $wpdb;
    
    // DANGEROUS: No authorization check
    $user_id = $_GET['user_id'];
    $query = "SELECT * FROM user_private_data WHERE user_id = " . $user_id;
    return $wpdb->get_results($query);
}

/**
 * VULNERABILITY #10: Eval() with user input
 * Code execution vulnerability
 */
function execute_code_vulnerable() {
    if (isset($_POST['code'])) {
        // EXTREMELY DANGEROUS: Code execution
        eval($_POST['code']);
    }
}

/**
 * VULNERABILITY #11: Insecure Session Management
 * Weak session handling
 */
function login_vulnerable($username, $password) {
    // DANGEROUS: No password verification
    if ($username && $password) {
        $_SESSION['user'] = $username;
        $_SESSION['admin'] = true; // DANGEROUS: Automatic admin privileges
        return true;
    }
    return false;
}

/**
 * VULNERABILITY #12: CSRF - No token validation
 * Cross-Site Request Forgery vulnerability
 */
function delete_user_vulnerable() {
    if (isset($_POST['delete_user_id'])) {
        global $wpdb;
        
        // DANGEROUS: No CSRF protection
        $user_id = $_POST['delete_user_id'];
        $wpdb->delete('users', array('ID' => $user_id));
    }
}

// VULNERABILITY #13: Exposed configuration
$config = array(
    'database_password' => 'prod_db_pass_2024',
    'stripe_secret_key' => 'sk_live_1234567890abcdef',
    'jwt_secret' => 'my_super_secret_jwt_key',
    'admin_password' => 'admin123'
);

/**
 * Test function that combines multiple vulnerabilities
 * This should definitely trigger Claude's security analysis
 */
function vulnerable_admin_panel() {
    // Multiple vulnerabilities in one function
    if (isset($_GET['action'])) {
        switch ($_GET['action']) {
            case 'sql':
                // SQL injection
                $query = "SELECT * FROM users WHERE name = '" . $_GET['name'] . "'";
                break;
                
            case 'xss':
                // XSS vulnerability
                echo "Welcome " . $_GET['username'];
                break;
                
            case 'cmd':
                // Command injection
                system("ping " . $_GET['host']);
                break;
                
            case 'eval':
                // Code execution
                eval($_GET['code']);
                break;
        }
    }
}

// End of deliberately vulnerable test file
// Expected Claude AI verdict: BLOCKED / NOT APPROVED
