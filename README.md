## Action Hooks

## Filter Hooks

### blaze_commerce_cookie_domain
Used to modify the cookie domain when setting cookies.

Example:
```php
<?php
add_filter('blaze_commerce_cookie_domain', function($domain) {
    return '.my-site.com';
});
```

### blaze_commerce_cookie_expiry
Used to modify the cookie expiry when setting cookies.

Example:
```php
<?php
add_filter('blaze_commerce_cookie_expiry', function($cookie_expiry) {
    return $cookie_expiry + 3600; // adds 1 hour
});
```
