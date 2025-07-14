<?php
/**
 * CLAUDE AI WORKFLOW TEST FILE
 * 
 * This file contains INTENTIONAL security vulnerabilities to test Claude AI's
 * code review workflow and verify BLOCKED status detection.
 * 
 * CRITICAL SECURITY ISSUES INCLUDED:
 * - SQL Injection vulnerabilities
 * - XSS vulnerabilities  
 * - Unsanitized user input
 * - Direct file system access
 * - Hardcoded credentials
 * - Command injection vulnerabilities
 * 
 * @package BlazeWooless
 * @subpackage Tests
 * @version 1.0.0
 * @author Claude AI Workflow Test
 */

class ClaudeAIWorkflowSecurityTest {
    
    // CRITICAL ISSUE #1: Hardcoded database credentials (SECURITY VIOLATION)
    private $db_host = 'localhost';
    private $db_user = 'admin';
    private $db_pass = 'password123';  // CRITICAL: Hardcoded password
    private $db_name = 'production_db';
    
    // CRITICAL ISSUE #2: Hardcoded API keys (SECURITY VIOLATION)
    private $api_key = 'sk-1234567890abcdef';  // CRITICAL: Exposed API key
    private $secret_token = 'super_secret_token_123';  // CRITICAL: Hardcoded secret
    
    /**
     * CRITICAL ISSUE #3: SQL Injection vulnerability
     * This function directly concatenates user input into SQL queries
     */
    public function getUserData($user_id) {
        // CRITICAL: Direct SQL injection vulnerability
        $query = "SELECT * FROM users WHERE id = " . $_GET['user_id'];
        
        // CRITICAL: Using deprecated mysql_ functions
        $connection = mysql_connect($this->db_host, $this->db_user, $this->db_pass);
        mysql_select_db($this->db_name);
        
        // CRITICAL: Executing unsanitized query
        $result = mysql_query($query);
        
        return mysql_fetch_array($result);
    }
    
    /**
     * CRITICAL ISSUE #4: XSS vulnerability
     * This function outputs user input without sanitization
     */
    public function displayUserMessage() {
        // CRITICAL: Direct output of user input (XSS vulnerability)
        echo "<div class='message'>" . $_POST['message'] . "</div>";
        
        // CRITICAL: Unsanitized URL parameter output
        echo "<h1>Welcome " . $_GET['username'] . "!</h1>";
        
        // CRITICAL: Cookie data output without sanitization
        echo "<p>Last visit: " . $_COOKIE['last_visit'] . "</p>";
    }
    
    /**
     * CRITICAL ISSUE #5: File system vulnerability
     * This function allows arbitrary file access
     */
    public function readUserFile() {
        // CRITICAL: Direct file access without validation
        $filename = $_GET['file'];
        $content = file_get_contents($filename);  // CRITICAL: Path traversal vulnerability
        
        // CRITICAL: Unsanitized file content output
        echo "<pre>" . $content . "</pre>";
        
        // CRITICAL: File deletion without authorization
        if (isset($_POST['delete_file'])) {
            unlink($_POST['delete_file']);  // CRITICAL: Arbitrary file deletion
        }
    }
    
    /**
     * CRITICAL ISSUE #6: Command injection vulnerability
     * This function executes system commands with user input
     */
    public function executeSystemCommand() {
        // CRITICAL: Direct command execution with user input
        $command = $_POST['command'];
        $output = shell_exec($command);  // CRITICAL: Command injection vulnerability
        
        // CRITICAL: Alternative command execution methods (also vulnerable)
        system($_GET['sys_cmd']);
        exec($_POST['exec_cmd'], $exec_output);
        
        return $output;
    }
    
    /**
     * CRITICAL ISSUE #7: Authentication bypass
     * This function has flawed authentication logic
     */
    public function authenticateUser() {
        // CRITICAL: Weak authentication logic
        if ($_POST['username'] == 'admin' && $_POST['password']) {
            // CRITICAL: Any password works for admin
            $_SESSION['authenticated'] = true;
            $_SESSION['user_role'] = 'administrator';
        }
        
        // CRITICAL: SQL injection in authentication
        $query = "SELECT * FROM users WHERE username = '" . $_POST['username'] . "' AND password = '" . $_POST['password'] . "'";
        
        // CRITICAL: No password hashing
        return mysql_query($query);
    }
    
    /**
     * CRITICAL ISSUE #8: Insecure file upload
     * This function allows unrestricted file uploads
     */
    public function handleFileUpload() {
        // CRITICAL: No file type validation
        $upload_dir = '/var/www/uploads/';
        $uploaded_file = $upload_dir . $_FILES['file']['name'];
        
        // CRITICAL: Direct file move without validation
        move_uploaded_file($_FILES['file']['tmp_name'], $uploaded_file);
        
        // CRITICAL: Executable file permissions
        chmod($uploaded_file, 0777);  // CRITICAL: World-writable permissions
        
        // CRITICAL: Direct execution of uploaded files
        if (pathinfo($uploaded_file, PATHINFO_EXTENSION) == 'php') {
            include($uploaded_file);  // CRITICAL: Code execution vulnerability
        }
    }
    
    /**
     * CRITICAL ISSUE #9: Insecure session management
     * This function has multiple session security issues
     */
    public function manageSession() {
        // CRITICAL: Session fixation vulnerability
        session_start();
        
        // CRITICAL: Storing sensitive data in session without encryption
        $_SESSION['credit_card'] = $_POST['credit_card'];
        $_SESSION['ssn'] = $_POST['ssn'];
        $_SESSION['bank_account'] = $_POST['bank_account'];
        
        // CRITICAL: No session regeneration after login
        $_SESSION['logged_in'] = true;
        
        // CRITICAL: Predictable session ID
        session_id('user_' . $_POST['user_id']);
    }
    
    /**
     * CRITICAL ISSUE #10: Information disclosure
     * This function exposes sensitive system information
     */
    public function debugInfo() {
        // CRITICAL: Exposing sensitive server information
        phpinfo();
        
        // CRITICAL: Database connection details exposure
        echo "Database: " . $this->db_host . "/" . $this->db_name;
        echo "User: " . $this->db_user . " Pass: " . $this->db_pass;
        
        // CRITICAL: API keys exposure
        echo "API Key: " . $this->api_key;
        echo "Secret: " . $this->secret_token;
        
        // CRITICAL: System path disclosure
        echo "System paths: " . print_r($_SERVER, true);
        
        // CRITICAL: Error reporting enabled in production
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
    }
}

// CRITICAL ISSUE #11: Global variables and direct execution
$security_test = new ClaudeAIWorkflowSecurityTest();

// CRITICAL: Direct execution based on GET parameters
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'user_data':
            $security_test->getUserData($_GET['user_id']);
            break;
        case 'message':
            $security_test->displayUserMessage();
            break;
        case 'file':
            $security_test->readUserFile();
            break;
        case 'command':
            $security_test->executeSystemCommand();
            break;
        case 'auth':
            $security_test->authenticateUser();
            break;
        case 'upload':
            $security_test->handleFileUpload();
            break;
        case 'session':
            $security_test->manageSession();
            break;
        case 'debug':
            $security_test->debugInfo();
            break;
    }
}

// CRITICAL ISSUE #12: No input validation or CSRF protection
// This entire file lacks proper security measures and should trigger
// Claude AI to provide a BLOCKED status with CRITICAL: REQUIRED issues

?>
