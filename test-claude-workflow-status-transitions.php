<?php
/**
 * Test file for Claude AI workflow status transitions
 * 
 * This file is designed to test different Claude AI review statuses:
 * 1. BLOCKED - Code with WordPress coding standard violations
 * 2. APPROVED - Clean, compliant code
 * 3. BLOCKED - Critical security issues
 * 
 * @package BlazeWooless
 * @version 1.0.0
 */

// INTENTIONAL VIOLATIONS FOR TESTING BLOCKED STATUS

class TestClaudeWorkflowTransitions {
    
    // VIOLATION 1: No access modifier specified (should be public/private/protected)
    function process_user_data($data) {
        
        // VIOLATION 2: Direct $_POST access without sanitization
        $user_input = $_POST['user_data'];
        
        // VIOLATION 3: Direct database query without preparation
        global $wpdb;
        $results = $wpdb->get_results("SELECT * FROM {$wpdb->users} WHERE user_login = '$user_input'");
        
        // VIOLATION 4: No nonce verification
        if (isset($_POST['action'])) {
            $this->executeAction($_POST['action']);
        }
        
        // VIOLATION 5: eval() usage (extremely dangerous)
        if (isset($_GET['code'])) {
            eval($_GET['code']);
        }
        
        // VIOLATION 6: No output escaping
        echo $user_input;
        
        // VIOLATION 7: Inconsistent indentation and spacing
      $bad_spacing=true;
        if($bad_spacing){
        echo"This has bad spacing";
        }
        
        return $results;
    }
    
    // VIOLATION 8: Function name not following WordPress conventions
    function ExecuteAction($action) {
        
        // VIOLATION 9: Direct file inclusion without validation
        include $_GET['file'];
        
        // VIOLATION 10: No error handling
        $file_contents = file_get_contents('http://external-site.com/data.txt');
        
        // VIOLATION 11: Hardcoded credentials
        $db_password = 'admin123';
        $api_key = 'sk-1234567890abcdef';
        
        return true;
    }
    
    // VIOLATION 12: Missing docblock
    function processPayment($amount, $card_number) {
        
        // VIOLATION 13: Storing sensitive data in plain text
        update_option('credit_card_number', $card_number);
        
        // VIOLATION 14: No input validation
        $charge_amount = $amount * 100;
        
        // VIOLATION 15: Unsafe file operations
        file_put_contents('/tmp/payment_log.txt', $card_number, FILE_APPEND);
        
        return $charge_amount;
    }
}

// VIOLATION 16: Global variables without proper naming
$user_data = $_POST;
$admin_access = true;

// VIOLATION 17: Direct output without escaping
echo "Welcome " . $_GET['username'];

// VIOLATION 18: Unsafe deserialization
$serialized_data = $_COOKIE['user_prefs'];
$user_preferences = unserialize($serialized_data);

// VIOLATION 19: Missing closing PHP tag in some contexts and inconsistent formatting
?>

<?php
// VIOLATION 20: Multiple opening tags and poor structure

function unsafe_redirect() {
    // VIOLATION 21: Open redirect vulnerability
    $redirect_url = $_GET['redirect'];
    wp_redirect($redirect_url);
    exit;
}

// VIOLATION 22: SQL injection in custom query
function get_user_posts($user_id) {
    global $wpdb;
    
    // Direct variable interpolation in SQL
    $query = "SELECT * FROM {$wpdb->posts} WHERE post_author = $user_id AND post_status = 'publish'";
    return $wpdb->get_results($query);
}

// VIOLATION 23: XSS vulnerability
function display_user_comment($comment) {
    echo "<div class='comment'>" . $comment . "</div>";
}

// VIOLATION 24: Weak password hashing
function create_user_password($password) {
    return md5($password . 'salt');
}

// VIOLATION 25: File upload without validation
function handle_file_upload() {
    $upload_dir = wp_upload_dir();
    $target_file = $upload_dir['path'] . '/' . $_FILES['upload']['name'];
    move_uploaded_file($_FILES['upload']['tmp_name'], $target_file);
    return $target_file;
}
