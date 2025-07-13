# Klaviyo API Key Security Fix

## Overview

This document outlines the security vulnerability fix for the hardcoded Klaviyo API key in the BlazeCommerce WordPress plugin and provides recommendations for secure credential management.

## Security Vulnerability Details

### **Issue Identified**
- **File**: `lib/blaze-wooless-functions.php`
- **Lines**: 114 and 126 (before fix)
- **Vulnerability**: Hardcoded Klaviyo API key "W7A7kP"
- **Risk Level**: HIGH
- **Impact**: API key exposed in version control, potential unauthorized access

### **Functions Affected**
1. `klaviyo_script()` - Loads Klaviyo tracking script
2. `is_klaviyo_connected()` - Tests Klaviyo API connectivity

## Security Fix Implementation

### **Changes Made**

#### 1. Added Secure Settings Field
- **File**: `app/Settings/GeneralSettings.php`
- **Addition**: New `klaviyo_api_key` field with password type
- **Location**: WordPress Admin → BlazeCommerce → General Settings

#### 2. Created Helper Function
- **File**: `lib/setting-helper.php`
- **Function**: `bw_get_klaviyo_api_key()`
- **Features**:
  - Retrieves API key from WordPress options
  - Automatic migration from hardcoded value
  - Backward compatibility for existing installations

#### 3. Updated Core Functions
- **File**: `lib/blaze-wooless-functions.php`
- **Changes**:
  - Replaced hardcoded API key with settings-based retrieval
  - Added proper input sanitization (`esc_attr`, `esc_url`, `urlencode`)
  - Enhanced security with SSL verification in cURL requests
  - Improved error handling

### **Migration Strategy**

The fix includes automatic migration for existing installations:

1. **Detection**: Checks if Klaviyo API key exists in settings
2. **Migration**: If not found, automatically migrates the legacy hardcoded value
3. **Seamless Transition**: No disruption to existing Klaviyo integrations
4. **One-time Process**: Migration occurs only once per installation

### **Security Enhancements**

1. **Input Sanitization**: All API key outputs are properly escaped
2. **SSL Verification**: Enhanced cURL security settings
3. **Password Field Type**: API key hidden in admin interface
4. **Validation**: Empty key validation prevents unnecessary API calls

## Configuration Instructions

### **For New Installations**

1. Navigate to WordPress Admin → BlazeCommerce → General Settings
2. Locate "Klaviyo API Key" field
3. Enter your Klaviyo API key
4. Save settings

### **For Existing Installations**

The migration is automatic, but you should:

1. Verify the API key in WordPress Admin → BlazeCommerce → General Settings
2. Update the key if needed
3. Test Klaviyo integration functionality

### **Environment Variables (Optional)**

For additional security, you can use environment variables:

```php
// In wp-config.php
define('KLAVIYO_API_KEY', getenv('KLAVIYO_API_KEY'));

// In your helper function
function bw_get_klaviyo_api_key() {
    // Check environment variable first
    if (defined('KLAVIYO_API_KEY') && !empty(KLAVIYO_API_KEY)) {
        return KLAVIYO_API_KEY;
    }
    
    // Fallback to settings
    return bw_get_general_settings('klaviyo_api_key');
}
```

## Security Best Practices

### **Credential Management**

1. **Never Hardcode**: Never commit API keys, tokens, or passwords to version control
2. **Use WordPress Options**: Store sensitive data using WordPress options API
3. **Password Fields**: Use password field types for API keys in admin interfaces
4. **Environment Variables**: Consider environment variables for production deployments
5. **Regular Rotation**: Rotate API keys periodically

### **Code Security**

1. **Input Sanitization**: Always sanitize and escape output
2. **SSL Verification**: Enable SSL verification for external API calls
3. **Error Handling**: Implement proper error handling for API failures
4. **Validation**: Validate API keys before making requests

### **Access Control**

1. **Admin Only**: Restrict API key configuration to administrators
2. **Capability Checks**: Use WordPress capability checks
3. **Audit Logs**: Consider logging API key changes
4. **Backup Security**: Ensure backups don't expose credentials

## Testing Checklist

- [ ] Klaviyo script loads correctly on frontend
- [ ] API connectivity test works in admin
- [ ] Settings save and retrieve properly
- [ ] Migration works for existing installations
- [ ] No hardcoded credentials remain in codebase
- [ ] SSL verification is enabled
- [ ] Input sanitization is working

## Rollback Instructions

If issues occur, you can temporarily rollback by:

1. Reverting the changes in `lib/blaze-wooless-functions.php`
2. Restoring the hardcoded API key (NOT RECOMMENDED for production)
3. Investigating and fixing the configuration issue
4. Re-applying the secure implementation

## Related Files

- `app/Settings/GeneralSettings.php` - Settings configuration
- `lib/setting-helper.php` - Helper functions
- `lib/blaze-wooless-functions.php` - Core Klaviyo functions
- `docs/security/credential-management-guidelines.md` - General security guidelines

## Support

For questions or issues related to this security fix, please:

1. Check the configuration in WordPress Admin
2. Verify API key validity with Klaviyo
3. Review error logs for specific issues
4. Contact the development team if problems persist
