# Credential Management Guidelines

## Overview

This document provides comprehensive guidelines for secure credential management in the BlazeCommerce WordPress plugin and related projects.

## Security Principles

### **1. Never Hardcode Credentials**

❌ **NEVER DO THIS:**
```php
$api_key = "abc123secretkey";
$password = "mypassword123";
$token = "hardcoded-token-value";
```

✅ **DO THIS INSTEAD:**
```php
$api_key = get_option('my_plugin_api_key');
$password = bw_get_general_settings('secure_password');
$token = defined('API_TOKEN') ? API_TOKEN : get_option('api_token');
```

### **2. Use WordPress Options API**

WordPress provides secure storage for sensitive data:

```php
// Store securely
update_option('my_api_key', $sanitized_api_key);

// Retrieve securely
$api_key = get_option('my_api_key');
```

### **3. Implement Proper Field Types**

Use password field types in admin interfaces:

```php
array(
    'id' => 'api_key',
    'label' => 'API Key',
    'type' => 'password',  // Hides the value in UI
    'args' => array(
        'description' => 'Enter your API key here.'
    ),
),
```

## Implementation Patterns

### **Settings-Based Storage**

Follow the existing pattern in the codebase:

```php
// In Settings class
array(
    'id' => 'service_api_key',
    'label' => 'Service API Key',
    'type' => 'password',
    'args' => array(
        'description' => 'API key for service integration.'
    ),
),

// Helper function
function bw_get_service_api_key() {
    return bw_get_general_settings('service_api_key');
}

// Usage in code
$api_key = bw_get_service_api_key();
if (!empty($api_key)) {
    // Use the API key
}
```

### **Environment Variables**

For production deployments, consider environment variables:

```php
// In wp-config.php
define('EXTERNAL_API_KEY', getenv('EXTERNAL_API_KEY'));

// In your code
function get_external_api_key() {
    // Environment variable takes precedence
    if (defined('EXTERNAL_API_KEY') && !empty(EXTERNAL_API_KEY)) {
        return EXTERNAL_API_KEY;
    }
    
    // Fallback to WordPress options
    return get_option('external_api_key');
}
```

### **Migration Strategy**

When fixing hardcoded credentials:

```php
function migrate_legacy_credentials() {
    $current_setting = get_option('new_secure_setting');
    
    if (empty($current_setting)) {
        $legacy_value = 'old-hardcoded-value';
        
        // Only migrate for existing installations
        if (get_option('plugin_installed') !== false) {
            update_option('new_secure_setting', $legacy_value);
            
            // Log the migration
            error_log('Migrated legacy credential to secure storage');
        }
    }
}
```

## Security Enhancements

### **Input Sanitization**

Always sanitize and escape credential outputs:

```php
// When outputting in HTML
$api_key = esc_attr($api_key);

// When using in URLs
$url = 'https://api.service.com/endpoint?key=' . urlencode($api_key);

// When outputting URLs
echo esc_url($url);
```

### **SSL/TLS Verification**

Enable SSL verification for external API calls:

```php
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);  // Enable SSL verification
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);     // Verify hostname
curl_setopt($ch, CURLOPT_TIMEOUT, 30);           // Set timeout
```

### **Error Handling**

Implement secure error handling:

```php
function test_api_connection($api_key) {
    if (empty($api_key)) {
        return array(
            'status' => 'error',
            'message' => 'API key is required'
        );
    }
    
    // Test connection without exposing the key in logs
    $response = make_api_request($api_key);
    
    if (is_wp_error($response)) {
        error_log('API connection failed: ' . $response->get_error_message());
        return array(
            'status' => 'error',
            'message' => 'Connection failed'
        );
    }
    
    return array(
        'status' => 'success',
        'message' => 'Connected successfully'
    );
}
```

## Access Control

### **Capability Checks**

Restrict access to credential settings:

```php
function render_api_settings() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    
    // Render settings form
}
```

### **Nonce Verification**

Use nonces for form security:

```php
// In form
wp_nonce_field('save_api_settings', 'api_settings_nonce');

// When processing
if (!wp_verify_nonce($_POST['api_settings_nonce'], 'save_api_settings')) {
    wp_die(__('Security check failed.'));
}
```

## Common Vulnerabilities to Avoid

### **1. Logging Credentials**

❌ **NEVER DO THIS:**
```php
error_log('API Key: ' . $api_key);
var_dump($credentials);
```

✅ **DO THIS INSTEAD:**
```php
error_log('API connection attempt');
error_log('Credentials validation: ' . (empty($api_key) ? 'missing' : 'present'));
```

### **2. Exposing in JavaScript**

❌ **NEVER DO THIS:**
```php
echo '<script>var apiKey = "' . $api_key . '";</script>';
```

✅ **DO THIS INSTEAD:**
```php
// Use AJAX endpoints with proper authentication
wp_localize_script('my-script', 'ajax_object', array(
    'ajax_url' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('my_ajax_nonce')
));
```

### **3. Including in Backups**

Ensure sensitive data is excluded from public backups:

```php
// Add to .gitignore
.env
wp-config.php
/uploads/sensitive-data/

// Consider encrypting database backups
// Use separate storage for credential backups
```

## Audit and Monitoring

### **Regular Security Audits**

1. **Code Reviews**: Check for hardcoded credentials in all commits
2. **Automated Scanning**: Use tools to detect credential patterns
3. **Access Logs**: Monitor who accesses credential settings
4. **Key Rotation**: Regularly rotate API keys and passwords

### **Monitoring Tools**

```bash
# Search for potential hardcoded credentials
grep -r "api_key.*=" --include="*.php" .
grep -r "password.*=" --include="*.php" .
grep -r "token.*=" --include="*.php" .
```

## Emergency Response

### **If Credentials Are Compromised**

1. **Immediate Actions**:
   - Revoke/rotate the compromised credentials
   - Update all systems using those credentials
   - Review access logs for unauthorized usage

2. **Investigation**:
   - Determine how the credentials were exposed
   - Identify affected systems and data
   - Document the incident

3. **Prevention**:
   - Implement additional security measures
   - Update security policies
   - Train team members on secure practices

## Compliance Considerations

### **Data Protection**

- Follow GDPR, CCPA, and other applicable regulations
- Implement data encryption for sensitive credentials
- Maintain audit trails for credential access
- Provide data deletion capabilities

### **Industry Standards**

- Follow OWASP security guidelines
- Implement PCI DSS requirements for payment data
- Use industry-standard encryption methods
- Regular security assessments

## Tools and Resources

### **WordPress Security Plugins**

- Wordfence Security
- Sucuri Security
- iThemes Security

### **Development Tools**

- Git hooks for credential scanning
- IDE plugins for security analysis
- Automated security testing tools

### **External Resources**

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [WordPress Security Handbook](https://developer.wordpress.org/plugins/security/)
- [PHP Security Best Practices](https://www.php.net/manual/en/security.php)

## Conclusion

Secure credential management is crucial for protecting user data and maintaining system integrity. Always follow these guidelines and regularly review your implementation for potential security improvements.
