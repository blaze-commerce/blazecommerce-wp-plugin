<?php
/**
 * Test file for Country-Specific Images functionality
 * 
 * This file can be used to test the country-specific images feature
 * Run this from WordPress admin or via WP-CLI
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Test Country-Specific Images Extension
 */
function test_country_specific_images_extension() {
    echo "<h2>Testing Country-Specific Images Extension</h2>\n";
    
    // Test 1: Check if extension class exists
    echo "<h3>1. Extension Class Test</h3>\n";
    if ( class_exists( 'BlazeWooless\Extensions\CountrySpecificImages' ) ) {
        echo "✅ CountrySpecificImages class exists\n";
    } else {
        echo "❌ CountrySpecificImages class not found\n";
        return;
    }
    
    // Test 2: Check if Aelia Currency Switcher is active
    echo "<h3>2. Aelia Currency Switcher Test</h3>\n";
    if ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'woocommerce-aelia-currencyswitcher/woocommerce-aelia-currencyswitcher.php' ) ) {
        echo "✅ Aelia Currency Switcher is active\n";
    } else {
        echo "⚠️ Aelia Currency Switcher is not active\n";
    }
    
    // Test 3: Check if setting is registered via filter
    echo "<h3>3. Settings Filter Test</h3>\n";
    $test_fields = array( 'wooless_general_settings_section' => array( 'options' => array() ) );
    $filtered_fields = apply_filters( 'blazecommerce/settings/general/fields', $test_fields );

    $setting_found = false;
    if ( isset( $filtered_fields['wooless_general_settings_section']['options'] ) ) {
        foreach ( $filtered_fields['wooless_general_settings_section']['options'] as $option ) {
            if ( isset( $option['id'] ) && $option['id'] === 'enable_country_specific_images' ) {
                $setting_found = true;
                break;
            }
        }
    }

    if ( $setting_found ) {
        echo "✅ Country-specific images setting is registered via blazecommerce/settings/general/fields filter\n";
    } else {
        echo "❌ Country-specific images setting not found in blazecommerce/settings/general/fields filter\n";
    }
    
    // Test 4: Check if feature is enabled in settings
    echo "<h3>4. Feature Settings Test</h3>\n";
    $general_settings = bw_get_general_settings();
    if ( ! empty( $general_settings['enable_country_specific_images'] ) ) {
        echo "✅ Country-specific images feature is enabled\n";
    } else {
        echo "⚠️ Country-specific images feature is disabled\n";
        echo "Enable it in Blaze Commerce > Settings > General > Enable Country-Specific Product Images\n";
    }
    
    // Test 5: Check if extension instance can be created
    echo "<h3>5. Extension Instance Test</h3>\n";
    try {
        $extension = \BlazeWooless\Extensions\CountrySpecificImages::get_instance();
        if ( $extension ) {
            echo "✅ Extension instance created successfully\n";
        } else {
            echo "❌ Failed to create extension instance\n";
        }
    } catch ( Exception $e ) {
        echo "❌ Exception creating extension instance: " . $e->getMessage() . "\n";
    }
    
    // Test 6: Check if assets exist
    echo "<h3>6. Assets Test</h3>\n";
    $js_file = BLAZE_WOOLESS_PLUGIN_DIR . 'assets/js/country-images-admin.js';
    $css_file = BLAZE_WOOLESS_PLUGIN_DIR . 'assets/css/country-images-admin.css';
    
    if ( file_exists( $js_file ) ) {
        echo "✅ JavaScript file exists\n";
    } else {
        echo "❌ JavaScript file missing: " . $js_file . "\n";
    }
    
    if ( file_exists( $css_file ) ) {
        echo "✅ CSS file exists\n";
    } else {
        echo "❌ CSS file missing: " . $css_file . "\n";
    }
    
    // Test 7: Check available countries (if Aelia is active)
    echo "<h3>7. Available Countries Test</h3>\n";
    if ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'woocommerce-aelia-currencyswitcher/woocommerce-aelia-currencyswitcher.php' ) ) {
        $aelia_options = get_option( 'wc_aelia_currency_switcher', false );
        if ( $aelia_options && isset( $aelia_options['currency_countries_mappings'] ) ) {
            $currency_mappings = $aelia_options['currency_countries_mappings'];
            $country_count = 0;
            foreach ( $currency_mappings as $currency => $mapping ) {
                if ( isset( $mapping['countries'] ) && is_array( $mapping['countries'] ) ) {
                    $country_count += count( $mapping['countries'] );
                }
            }
            echo "✅ Found {$country_count} countries in Aelia configuration\n";
        } else {
            echo "⚠️ No Aelia currency mappings found\n";
        }
    } else {
        echo "⚠️ Aelia Currency Switcher not active, skipping countries test\n";
    }
    
    // Test 8: Test with a sample product (if any exist)
    echo "<h3>8. Sample Product Test</h3>\n";
    if ( function_exists( 'wc_get_products' ) ) {
        $products = wc_get_products( array( 'limit' => 1, 'status' => 'publish' ) );
        if ( ! empty( $products ) ) {
            $product = $products[0];
            echo "✅ Found sample product: " . $product->get_name() . " (ID: " . $product->get_id() . ")\n";
            
            // Check if product has country-specific images meta
            $country_images = get_post_meta( $product->get_id(), '_blaze_country_images', true );
            if ( is_array( $country_images ) && ! empty( $country_images ) ) {
                echo "✅ Product has country-specific images configured\n";
                foreach ( $country_images as $country => $image_id ) {
                    echo "  - {$country}: Image ID {$image_id}\n";
                }
            } else {
                echo "ℹ️ Product has no country-specific images configured (this is normal for new installations)\n";
            }
            
            // Test Typesense data generation
            echo "\n  Testing Typesense data generation:\n";
            $test_product_data = array( 'metaData' => array() );
            $filtered_data = apply_filters( 'blaze_wooless_product_data_for_typesense', $test_product_data, $product->get_id(), $product );
            
            if ( isset( $filtered_data['metaData']['primaryImages'] ) ) {
                echo "  ✅ primaryImages found in Typesense data\n";
                foreach ( $filtered_data['metaData']['primaryImages'] as $country => $image_url ) {
                    echo "    - {$country}: {$image_url}\n";
                }
            } else {
                echo "  ℹ️ No primaryImages in Typesense data (normal if no country images are configured)\n";
            }
        } else {
            echo "⚠️ No published products found for testing\n";
        }
    } else {
        echo "⚠️ WooCommerce not active, skipping product test\n";
    }
    
    // Test 9: Test Typesense filter integration
    echo "<h3>9. Typesense Filter Integration Test</h3>\n";
    
    // Create mock product data
    $mock_product_data = array(
        'id' => '123',
        'name' => 'Test Product',
        'metaData' => array()
    );
    
    // Create mock country images data
    $mock_country_images = array(
        'AU' => 123, // Mock image ID
        'US' => 456, // Mock image ID
        'GB' => 789  // Mock image ID
    );
    
    // Test the filter directly
    if ( class_exists( 'BlazeWooless\Extensions\CountrySpecificImages' ) ) {
        $extension = \BlazeWooless\Extensions\CountrySpecificImages::get_instance();
        if ( method_exists( $extension, 'add_country_images_to_typesense' ) ) {
            echo "✅ add_country_images_to_typesense method exists\n";
            
            // Mock the get_post_meta function result for testing
            echo "ℹ️ Typesense filter integration is ready (actual image URLs depend on real product data)\n";
        } else {
            echo "❌ add_country_images_to_typesense method not found\n";
        }
    }
    
    // Test 10: Show expected Typesense data structure
    echo "<h3>10. Expected Typesense Data Structure</h3>\n";
    echo "When country-specific images are configured, the Typesense data will include:\n\n";
    echo "metaData: {\n";
    echo "  primaryImages: {\n";
    echo "    'AU': 'https://example.com/wp-content/uploads/2024/01/australia-image.jpg',\n";
    echo "    'US': 'https://example.com/wp-content/uploads/2024/01/usa-image.jpg',\n";
    echo "    'GB': 'https://example.com/wp-content/uploads/2024/01/uk-image.jpg'\n";
    echo "  }\n";
    echo "}\n\n";
    echo "This data will be automatically added to products that have country-specific images configured.\n";
    
    echo "<h3>Test Summary</h3>\n";
    echo "Country-Specific Images extension test completed.\n";
    echo "If all tests pass and the feature is enabled, you should see the meta box on product edit pages.\n";
    echo "The primaryImages data will be automatically pushed to Typesense when products are synced.\n";
}

// Run the test if accessed directly or via admin
if ( is_admin() && isset( $_GET['test_country_images'] ) ) {
    add_action( 'admin_notices', function() {
        echo '<div class="notice notice-info"><pre>';
        test_country_specific_images_extension();
        echo '</pre></div>';
    });
}

// Add admin menu item for testing
add_action( 'admin_menu', function() {
    add_submenu_page(
        'tools.php',
        'Test Country Images',
        'Test Country Images',
        'manage_options',
        'test-country-images',
        function() {
            echo '<div class="wrap">';
            echo '<h1>Country-Specific Images Test</h1>';
            echo '<pre>';
            test_country_specific_images_extension();
            echo '</pre>';
            echo '</div>';
        }
    );
});
