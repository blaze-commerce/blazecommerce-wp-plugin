# Security Guidelines Rule

**Priority: ALWAYS**

**Description:** Enforce strict security practices for WordPress plugin development to protect against vulnerabilities and ensure safe development practices.

## Core Security Principles

### 1. Credential Protection (CRITICAL)

#### Prohibited in ALL Files
- **API Keys**: Never include in plugin code, configuration files, or documentation
- **Database Credentials**: Connection strings, usernames, passwords
- **Authentication Tokens**: JWT tokens, OAuth secrets, API tokens
- **Payment Gateway Keys**: Stripe, PayPal, or other payment processor credentials
- **Third-party Service Keys**: Email services, analytics, external APIs
- **Encryption Keys**: Secret keys, salts, hashing keys

#### Safe Practices for WordPress Plugins
```php
// ❌ NEVER do this - exposes credentials
define('STRIPE_SECRET_KEY', 'sk_live_abc123...');

// ✅ Use WordPress options or constants defined elsewhere
$stripe_key = get_option('blazecommerce_stripe_secret_key');
// or use wp-config.php constants
$stripe_key = defined('BLAZECOMMERCE_STRIPE_KEY') ? BLAZECOMMERCE_STRIPE_KEY : '';

// ✅ Always validate credentials exist before use
if (empty($stripe_key)) {
    wp_die('Stripe API key not configured');
}
```

#### Environment Configuration
- Store sensitive data in wp-config.php constants
- Use WordPress options API for non-sensitive configuration
- Never commit credentials to version control
- Use placeholder text in documentation: `[REPLACE_WITH_ACTUAL_VALUE_FROM_USER_CREDENTIALS]`

### 2. Input Validation and Sanitization

#### WordPress Sanitization Functions
```php
// ✅ Always sanitize user input
function blazecommerce_process_form_data() {
    // Text fields
    $name = sanitize_text_field($_POST['customer_name']);
    
    // Email addresses
    $email = sanitize_email($_POST['customer_email']);
    
    // URLs
    $website = esc_url_raw($_POST['website']);
    
    // HTML content (if needed)
    $description = wp_kses_post($_POST['description']);
    
    // Numeric values
    $quantity = absint($_POST['quantity']);
    
    // Array data
    $selected_items = array_map('sanitize_text_field', $_POST['items']);
}
```

#### Custom Validation
```php
// ✅ Implement custom validation logic
function blazecommerce_validate_product_data($data) {
    $errors = array();
    
    // Required field validation
    if (empty($data['name'])) {
        $errors[] = 'Product name is required';
    }
    
    // Format validation
    if (!is_numeric($data['price']) || $data['price'] < 0) {
        $errors[] = 'Price must be a positive number';
    }
    
    // Length validation
    if (strlen($data['description']) > 1000) {
        $errors[] = 'Description must be less than 1000 characters';
    }
    
    return $errors;
}
```

### 3. Output Escaping

#### Context-Specific Escaping
```php
// ✅ Escape output based on context
function blazecommerce_display_product_info($product) {
    // HTML content
    echo '<h2>' . esc_html($product->name) . '</h2>';
    
    // HTML attributes
    echo '<input type="text" value="' . esc_attr($product->sku) . '">';
    
    // URLs
    echo '<a href="' . esc_url($product->permalink) . '">View Product</a>';
    
    // JavaScript
    echo '<script>var productId = ' . wp_json_encode($product->id) . ';</script>';
    
    // Allowed HTML (if needed)
    $allowed_html = array(
        'strong' => array(),
        'em' => array(),
        'br' => array()
    );
    echo wp_kses($product->description, $allowed_html);
}
```

### 4. Nonce Verification

#### Form Security
```php
// ✅ Always use nonces for forms and AJAX
function blazecommerce_display_admin_form() {
    ?>
    <form method="post" action="">
        <?php wp_nonce_field('blazecommerce_save_settings', 'blazecommerce_nonce'); ?>
        <input type="text" name="setting_value" />
        <input type="submit" value="Save" />
    </form>
    <?php
}

function blazecommerce_process_admin_form() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['blazecommerce_nonce'], 'blazecommerce_save_settings')) {
        wp_die('Security check failed');
    }
    
    // Verify user capabilities
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }
    
    // Process form data safely
    $setting_value = sanitize_text_field($_POST['setting_value']);
    update_option('blazecommerce_setting', $setting_value);
}
```

#### AJAX Security
```php
// ✅ Secure AJAX endpoints
add_action('wp_ajax_blazecommerce_update_product', 'blazecommerce_ajax_update_product');
add_action('wp_ajax_nopriv_blazecommerce_update_product', 'blazecommerce_ajax_update_product');

function blazecommerce_ajax_update_product() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'blazecommerce_ajax_nonce')) {
        wp_die('Security check failed');
    }
    
    // Verify capabilities
    if (!current_user_can('edit_products')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    // Process request
    $product_id = absint($_POST['product_id']);
    $result = blazecommerce_update_product($product_id, $_POST['data']);
    
    wp_send_json_success($result);
}
```

### 5. Database Security

#### Prepared Statements
```php
// ✅ Always use prepared statements
function blazecommerce_get_products_by_category($category_id) {
    global $wpdb;
    
    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}blazecommerce_products 
             WHERE category_id = %d AND status = %s",
            $category_id,
            'active'
        )
    );
    
    return $results;
}

// ✅ Complex queries with multiple parameters
function blazecommerce_search_products($search_term, $category, $min_price, $max_price) {
    global $wpdb;
    
    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}blazecommerce_products 
             WHERE name LIKE %s 
             AND category_id = %d 
             AND price BETWEEN %f AND %f
             ORDER BY name ASC",
            '%' . $wpdb->esc_like($search_term) . '%',
            $category,
            $min_price,
            $max_price
        )
    );
    
    return $results;
}
```

### 6. File Security

#### File Upload Security
```php
// ✅ Secure file upload handling
function blazecommerce_handle_file_upload($file) {
    // Verify file type
    $allowed_types = array('jpg', 'jpeg', 'png', 'gif');
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_extension, $allowed_types)) {
        return new WP_Error('invalid_file_type', 'Invalid file type');
    }
    
    // Verify file size (2MB limit)
    if ($file['size'] > 2 * 1024 * 1024) {
        return new WP_Error('file_too_large', 'File size exceeds limit');
    }
    
    // Use WordPress file handling
    $upload_overrides = array('test_form' => false);
    $uploaded_file = wp_handle_upload($file, $upload_overrides);
    
    if (isset($uploaded_file['error'])) {
        return new WP_Error('upload_error', $uploaded_file['error']);
    }
    
    return $uploaded_file;
}
```

#### File Access Protection
```php
// ✅ Prevent direct file access
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// ✅ Protect sensitive directories with .htaccess
function blazecommerce_create_protection_files() {
    $upload_dir = wp_upload_dir();
    $htaccess_file = $upload_dir['basedir'] . '/blazecommerce/.htaccess';
    
    if (!file_exists($htaccess_file)) {
        $htaccess_content = "Options -Indexes\n";
        $htaccess_content .= "deny from all\n";
        file_put_contents($htaccess_file, $htaccess_content);
    }
}
```

### 7. WooCommerce Security

#### Order Data Protection
```php
// ✅ Secure order data handling
function blazecommerce_get_order_data($order_id) {
    // Verify user can access this order
    $order = wc_get_order($order_id);
    
    if (!$order) {
        return false;
    }
    
    // Check if current user can view this order
    if (!current_user_can('manage_woocommerce') && 
        get_current_user_id() !== $order->get_customer_id()) {
        return false;
    }
    
    // Return sanitized order data
    return array(
        'id' => $order->get_id(),
        'status' => $order->get_status(),
        'total' => $order->get_total(),
        'date' => $order->get_date_created()->format('Y-m-d H:i:s')
    );
}
```

### 8. REST API Security

#### Custom Endpoint Security
```php
// ✅ Secure REST API endpoints
add_action('rest_api_init', 'blazecommerce_register_api_endpoints');

function blazecommerce_register_api_endpoints() {
    register_rest_route('blazecommerce/v1', '/products', array(
        'methods' => 'GET',
        'callback' => 'blazecommerce_api_get_products',
        'permission_callback' => 'blazecommerce_api_permissions_check',
        'args' => array(
            'category' => array(
                'validate_callback' => function($param) {
                    return is_numeric($param);
                }
            )
        )
    ));
}

function blazecommerce_api_permissions_check() {
    return current_user_can('read');
}

function blazecommerce_api_get_products($request) {
    $category = $request->get_param('category');
    $products = blazecommerce_get_products_by_category($category);
    
    return rest_ensure_response($products);
}
```

### 9. Security Headers and Configuration

#### Security Headers
```php
// ✅ Add security headers
function blazecommerce_add_security_headers() {
    if (!is_admin()) {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
    }
}
add_action('send_headers', 'blazecommerce_add_security_headers');
```

### 10. Security Review Checklist

#### Pre-Deployment Security Check
- [ ] No credentials in code or documentation
- [ ] All user inputs sanitized and validated
- [ ] All outputs properly escaped
- [ ] Nonces implemented for all forms and AJAX
- [ ] User capability checks in place
- [ ] Database queries use prepared statements
- [ ] File operations are secure
- [ ] REST API endpoints properly secured
- [ ] Error messages don't expose sensitive information
- [ ] Security headers implemented

## Enforcement

This rule has **ALWAYS** priority and cannot be bypassed. All security guidelines must be followed to ensure:

1. **Data Protection**: Safeguard user and business data
2. **Vulnerability Prevention**: Prevent security exploits
3. **Compliance**: Meet WordPress and WooCommerce security standards
4. **Trust**: Maintain user and client confidence

## WordPress Plugin Context

These security guidelines apply specifically to:
- WordPress plugin development security
- WooCommerce integration protection
- Database query security
- File handling security
- REST API endpoint protection
- User authentication and authorization
- Payment processing security
- Admin interface security
