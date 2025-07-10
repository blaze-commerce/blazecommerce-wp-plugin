# Byron Bay Candles - Redeploy "Files Field" Fix

## Issue Resolved

**Error**: "✗ Redeploy failed: Invalid request: 'files' field should be an array"

## Root Cause Analysis

The BlazeCommerce middleware redeploy function was making a POST request without any payload data. The middleware API expects a JSON payload with a "files" field as an array, but our enhanced redeploy function was only sending headers without POST data.

## Fix Implementation

### 1. Added Required POST Payload

**File**: `app/Ajax.php` - `redeploy_via_blazecommerce_middleware()` function

**Before** (Missing POST data):
```php
curl_setopt_array( $curl, array(
    CURLOPT_URL => 'https://my-wooless-admin-portal.vercel.app/api/deployments',
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_HTTPHEADER => $this->get_headers(),
    // Missing CURLOPT_POSTFIELDS
) );
```

**After** (With required payload):
```php
// Prepare the payload with required "files" field as an array
$deployment_payload = array(
    'files' => array(), // Required by middleware API as an array
    'source' => 'blazecommerce-plugin',
    'wordpress_site' => home_url(),
    'store_id' => bw_get_general_settings( 'store_id' ),
    'timestamp' => time()
);

curl_setopt_array( $curl, array(
    CURLOPT_URL => 'https://my-wooless-admin-portal.vercel.app/api/deployments',
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => json_encode( $deployment_payload ),
    CURLOPT_HTTPHEADER => $this->get_headers(),
) );
```

### 2. Enhanced Headers for JSON Content

**File**: `app/Ajax.php` - `get_headers()` function

**Before**:
```php
return array(
    'x-wooless-secret-token: ' . base64_encode( $api_key . ':' . $store_id )
);
```

**After**:
```php
return array(
    'x-wooless-secret-token: ' . base64_encode( $api_key . ':' . $store_id ),
    'Content-Type: application/json'
);
```

### 3. Enhanced Error Handling

**File**: `app/Ajax.php` - Error handling in `redeploy_via_blazecommerce_middleware()`

**Added detailed error reporting**:
```php
// Handle HTTP errors
if ( $http_code !== 200 ) {
    // Include response body for better debugging
    $error_details = 'HTTP error: ' . $http_code;
    if ( ! empty( $response ) ) {
        $error_details .= ' - Response: ' . $response;
    }
    
    wp_send_json( array(
        'error' => $error_details,
        'message' => 'Failed to trigger redeploy due to HTTP error',
        'http_code' => $http_code,
        'response_body' => $response
    ) );
}
```

## Payload Format

The middleware API now receives the following JSON payload:

```json
{
    "files": [],
    "source": "blazecommerce-plugin",
    "wordpress_site": "https://byronbaycandles.com",
    "store_id": "74",
    "timestamp": 1625097600
}
```

## Testing Validation

### Test Both Deployment Methods

1. **BlazeCommerce Middleware** (default):
   - Now sends proper JSON payload with "files" array
   - Includes Content-Type: application/json header
   - Enhanced error reporting for debugging

2. **Direct Vercel API** (optional):
   - Unchanged - already working correctly
   - Uses different payload format for Vercel API

### Expected Results

- ✅ **No more "files field should be an array" errors**
- ✅ **Proper JSON payload sent to middleware**
- ✅ **Enhanced error messages for debugging**
- ✅ **Both deployment methods work correctly**
- ✅ **Backward compatibility maintained**

## Deployment Impact

### Before Fix:
- ❌ POST request with no payload data
- ❌ "Invalid request: 'files' field should be an array" error
- ❌ Silent failures with minimal error information

### After Fix:
- ✅ POST request with proper JSON payload
- ✅ Required "files" field as empty array
- ✅ Enhanced error reporting with response details
- ✅ Successful redeploy operations

## Files Modified

1. **`app/Ajax.php`**:
   - Enhanced `redeploy_via_blazecommerce_middleware()` with proper payload
   - Updated `get_headers()` to include Content-Type
   - Enhanced error handling with response body details

## Backward Compatibility

- ✅ **All existing functionality preserved**
- ✅ **No breaking changes to API**
- ✅ **Enhanced error handling improves debugging**
- ✅ **Both deployment methods supported**

## Security Considerations

- ✅ **API key authentication maintained**
- ✅ **Input sanitization preserved**
- ✅ **No sensitive data exposed in payload**
- ✅ **Proper JSON encoding used**

---

**This fix resolves the specific "files field should be an array" error while maintaining all the comprehensive redeploy functionality enhancements and error handling improvements.**
