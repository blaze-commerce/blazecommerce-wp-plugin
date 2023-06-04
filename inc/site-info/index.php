<?php
function site_info_index_to_typesense()
{
    // Fetch the store ID from the saved options
    $wooless_site_id = get_option('store_id');
    $collection_site_info = 'site_info-' . $wooless_site_id;
    //Indexing Site Info
    try {
        // Initialize the Typesense client
        $client = Blaze_Wooless_Typesense::get_instance()->client();

        // Delete the existing 'site_info' collection (if it exists)
        try {
            $client->collections[$collection_site_info]->delete();
        } catch (Exception $e) {
            // Don't error out if the collection was not found
        }
        // Create the 'site_info' collection with the required schema
        $client->collections->create([
            'name' => $collection_site_info,
            'fields' => [
                [
                    'name' => 'name',
                    'type' => 'string',
                ],
                [
                    'name' => '.*',
                    'type' => 'string*',
                ],
                [
                    'name' => 'updated_at',
                    'type' => 'int64',
                ],

            ],
            'default_sorting_field' => 'updated_at',
        ]);
        //display site title
        $SiteTitle = get_bloginfo('name');
        $SiteTagline = get_bloginfo('description');
        $updatedAt = time(); // The current Unix timestamp
        //display logo
        $logo_id = get_theme_mod('custom_logo');
        $logo_image = wp_get_attachment_image_src($logo_id, 'full');
        $logo_metadata = wp_get_attachment_metadata($logo_id);
        $logo_updated_at = isset($logo_metadata['file']) ? strtotime(date("Y-m-d H:i:s", filemtime(get_attached_file($logo_id)))) : null;
        $updatedAt = $logo_updated_at ? strtotime(date("Y-m-d H:i:s", $logo_updated_at)) : time();
        // Display the logo URL as a JSON object
        if ($logo_image) {
            $logo_url = $logo_image[0];
            //echo "Logo URL: " . $logo_url;
        }
        $store_notice = get_option('woocommerce_demo_store_notice');


        if ($store_notice) {
            //echo "Store Notice: " . $store_notice . "\n";

            // Get the last updated timestamp of the 'woocommerce_demo_store_notice' option
            global $wpdb;
            $store_notice_updated_at = $wpdb->get_var("SELECT UNIX_TIMESTAMP(option_value) FROM {$wpdb->options} WHERE option_name = '_transient_timeout_woocommerce_demo_store_notice'") ?: time();

        }
        //display the time format and its timestamp
        $time_format = get_option('time_format');
        $current_unix_timestamp = time();
        //display the search_engine and its timestamp
        $search_engine = get_option('blog_public');
        $search_engine_last_updated = get_option('blog_public_last_updated', time());
        //    $site_id = get_current_blog_id();
        $site_id = get_current_blog_id();
        $site_id_string = strval($site_id);
        //$wordpress_address_url = get_site_url();
        $wordpress_address_url = get_site_url();

        // Get the Site Icon URL
        $site_icon_id = get_option('site_icon');
        $favicon_url = $site_icon_id ? wp_get_attachment_image_url($site_icon_id, 'full') : '';

        // Get the favicon last updated timestamp
        if ($site_icon_id) {
            $favicon_updated_at = strtotime(get_the_modified_date('Y-m-d H:i:s', $site_icon_id));
        } else {
            $favicon_updated_at = 0;
        }


        //admin email
        function my_admin_email_updated_callback($old_value, $new_value, $option)
        {
            update_option('admin_email_last_updated', time());
        }
        add_action('update_option_admin_email', 'my_admin_email_updated_callback', 10, 3);

        add_action('update_option_admin_email', 'my_admin_email_updated_callback', 10, 3);
        $admin_email = get_option('admin_email');
        $admin_email_last_updated = get_option('admin_email_last_updated', time());
        //language
        function my_locale_updated_callback($old_value, $new_value, $option)
        {
            update_option('locale_last_updated', time());
        }
        add_action('update_option_WPLANG', 'my_locale_updated_callback', 10, 3);
        $language = get_locale();
        $language_last_updated = get_option('locale_last_updated', time());
        //timezone
        function my_timezone_updated_callback($old_value, $new_value, $option)
        {
            update_option('timezone_last_updated', time());
        }
        add_action('update_option_timezone_string', 'my_timezone_updated_callback', 10, 3);
        $timezone = date_default_timezone_get();
        $wp_timezone_last_updated = get_option('timezone_last_updated', time());
        //Date format
        function my_date_format_updated_callback($old_value, $new_value, $option)
        {
            update_option('date_format_last_updated', time());
        }
        add_action('update_option_date_format', 'my_date_format_updated_callback', 10, 3);
        $date_format = get_option('date_format');
        $date_format_last_updated = get_option('date_format_last_updated', time());

        // Get available payment gateways
        $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
        $payment_methods = [];

        if (!empty($available_gateways)) {
            foreach ($available_gateways as $gateway) {
                $payment_methods[] = $gateway->get_title() . ' (ID: ' . $gateway->id . ')';
            }
        }

        // Convert payment methods array to a JSON string
        $payment_methods_json = json_encode($payment_methods);


        global $wpdb;

        // Fetch the 'active_plugins' option from the WordPress options table
        $active_plugins_serialized = $wpdb->get_var("SELECT option_value FROM " . $wpdb->options . " WHERE option_name = 'active_plugins'");
        $active_plugins = unserialize($active_plugins_serialized);

        // List of known review plugin slugs
        $review_plugin_slugs = [
            'reviewscouk-for-woocommerce',
            'wp-review',
            'wp-product-review-lite',
            'all-in-one-schemaorg-rich-snippets',
            'site-reviews',
            'ultimate-reviews',
            'taqyeem',
            'author-hreview',
            'rich-reviews',
            'customer-reviews-for-woocommerce',
            'reviewer',
            'yelp-widget-pro',
            'testimonials-widget',
            'google-reviews-widget',
            'reviewer-plugin',
            'wp-customer-reviews',
            'starcat-reviews',
            'trustpilot-reviews',
            'tripadvisor-reviews',
            'facebook-reviews-pro',
            'wp-reviews',
            'multi-rating-pro'
        ];
        // Filter the active plugins by the known review plugin slugs
        $filtered_plugins = array_filter($active_plugins, function ($plugin) use ($review_plugin_slugs) {
            foreach ($review_plugin_slugs as $slug) {
                if (strpos($plugin, $slug) !== false) {
                    return true;
                }
            }
            return false;
        });

        // Extract the plugin directory names
        $filtered_plugin_directories = array_map(function ($plugin) {
            return dirname($plugin);
        }, $filtered_plugins);

        // Convert the filtered plugin directory names array to a string
        $filtered_plugin_directories_string = implode(', ', $filtered_plugin_directories);

        // Get the permalink structure from WordPress
        $permalink_structure = get_option('woocommerce_permalinks');
        $product_base = isset($permalink_structure['product_base']) ? $permalink_structure['product_base'] : '';

        // If the product base does not start with a slash, add one
        if ($product_base && $product_base[0] !== '/') {
            $product_base = '/' . $product_base;
        }

        $category_base = get_option('category_base') ?: 'category';
        $tag_base = get_option('tag_base') ?: 'tag';
        $base_permalink_structure = get_option('permalink_structure');

        // Assemble the permalink structure JSON object
        $permalink_structure = [
            'product' => $product_base . '/%postname%',
            'category' => '/' . $category_base . '/%categoryname%',
            'tag' => '/' . $tag_base . '/%tagname%',
            'base' => $base_permalink_structure . '/%postname%',
            'posts' => '/blog/%postname%',
            'pages' => $base_permalink_structure . '/%pagename%',
        ];

        // Convert the permalink structure to a JSON-encoded string
        $permalink_structure = json_encode($permalink_structure);

        // Get WooCommerce stock settings
        $manage_stock = get_option('woocommerce_manage_stock'); // 'yes' or 'no'
        $stock_format = get_option('woocommerce_stock_format'); // 'always', 'never', or 'low_amount'

        // If stock management is disabled, the stock display format will be empty
        if ($manage_stock === 'no') {
            $stock_display_format = '';
        } else {
            // Determine the stock display format based on the 'woocommerce_stock_format' option
            switch ($stock_format) {
                case 'always':
                    $stock_display_format = 'always';
                    break;
                case 'never':
                    $stock_display_format = 'never';
                    break;
                case 'low_amount':
                default:
                    $stock_display_format = 'low_amount';
                    break;
            }
        }

        // Now, $stock_display_format contains the stock display format setting from WooCommerce

        // Send the stock display format value to Typesense
        $document_id = 'stock_display_format_setting'; // Set an appropriate document ID
        $updated_at = time(); // Use the current time as the updated_at value



        // Prepare the product attributes
        $product_attributes = [
            ["name" => "size", "type" => "select"],
            ["name" => "color", "type" => "swatch"],
            ["name" => "style", "type" => "image"]
        ];

        // Convert the attributes to a JSON string
        $product_attributes_json = json_encode($product_attributes);


        $client->collections[$collection_site_info]->documents->create([
            'name' => 'product_attributes',
            'value' => $product_attributes_json,
            'updated_at' => time(),
        ]);

        $client->collections[$collection_site_info]->documents->create([
            'name' => 'stock_display_format',
            'value' => $stock_display_format,
            'updated_at' => $updated_at,
        ]);

        // Add the permalink structure to Typesense
        $client->collections[$collection_site_info]->documents->create([
            'name' => 'permalink_structure',
            'value' => $permalink_structure,
            'updated_at' => time(),
        ]);


        $client->collections[$collection_site_info]->documents->create([
            'name' => 'reviews_plugin',
            'value' => $filtered_plugin_directories_string,
            'updated_at' => $updatedAt,
        ]);


        $client->collections[$collection_site_info]->documents->create([
            'name' => 'Payment_methods',
            'value' => $payment_methods_json,
            'updated_at' => $updatedAt,
        ]);


        $client->collections[$collection_site_info]->documents->create([
            'name' => 'Site_title',
            'value' => $SiteTitle,
            'updated_at' => $updatedAt,
        ]);

        $client->collections[$collection_site_info]->documents->create([
            'name' => 'Site_tagline',
            'value' => $SiteTagline,
            'updated_at' => $updatedAt,
        ]);
        if (!empty($logo_url)) {
            $client->collections[$collection_site_info]->documents->create([
                'name' => 'site_logo',
                'value' => $logo_url,
                'updated_at' => $logo_updated_at,
            ]);
        }
        if (!empty($store_notice)) {
            $client->collections[$collection_site_info]->documents->create([
                'name' => 'Store_notice',
                'value' => $store_notice,
                'updated_at' => intval($store_notice_updated_at),
            ]);
        }
        $client->collections[$collection_site_info]->documents->create([
            'name' => 'Time_format',
            'value' => $time_format,
            'updated_at' => intval($current_unix_timestamp),
        ]);
        $client->collections[$collection_site_info]->documents->create([
            'name' => 'Search_engine',
            'value' => $search_engine,
            'updated_at' => intval($search_engine_last_updated),
        ]);
        $client->collections[$collection_site_info]->documents->create([
            'name' => 'Site_ID',
            'value' => $site_id_string,
            'updated_at' => intval($search_engine_last_updated),
        ]);
        $client->collections[$collection_site_info]->documents->create([
            'name' => 'WordPressAddressURL',
            'value' => $wordpress_address_url,
            'updated_at' => intval($search_engine_last_updated),
        ]);
        $client->collections[$collection_site_info]->documents->create([
            'name' => 'AdministrationEmailAddress',
            'value' => $admin_email,
            'updated_at' => intval($admin_email_last_updated),
        ]);
        $client->collections[$collection_site_info]->documents->create([
            'name' => 'Language',
            'value' => $language,
            'updated_at' => intval($language_last_updated),
        ]);
        $client->collections[$collection_site_info]->documents->create([
            'name' => 'Time_zone',
            'value' => $timezone,
            'updated_at' => intval($wp_timezone_last_updated),
        ]);
        $client->collections[$collection_site_info]->documents->create([
            'name' => 'Date_format',
            'value' => $date_format,
            'updated_at' => intval($date_format_last_updated),
        ]);
        $client->collections[$collection_site_info]->documents->create([
            'name' => 'fav_icon',
            'value' => $favicon_url,
            'updated_at' => intval($store_notice_updated_at),
        ]);

        $homepage_data = apply_filters('blaze_wooless_additional_homepage_info', array());
        foreach ($homepage_data as $key => $value) {
            if (empty($value)) {
                continue;
            }

            // If it's 'popular_categories', decode it from a JSON string
            if ($key == 'popular_categories') {
                $value = json_decode($value, true);
            }

            $client->collections[$collection_site_info]->documents->create([
                'name' => $key,
                'value' => (string) $value,
                'updated_at' => time(),
            ]);
        }


        $site_messages_data = apply_filters('blaze_wooless_additional_site_info_message', array());
        foreach ($site_messages_data as $key => $value) {
            if (empty($value)) {
                continue;
            }

            $client->collections[$collection_site_info]->documents->create([
                'name' => $key,
                'value' => $value,
                'updated_at' => time(),
            ]);
        }

        $initial_additional_data = array();

        $site_currency = get_woocommerce_currency();
        $default_region = array(
            'country' => RegionalDataHelper::$currency_country_map[ $site_currency ],
            'currency' => $site_currency,
            'default' => true,
        );
        $initial_additional_data['regional_data'] = array( $default_region );

        $additional_data = apply_filters('blaze_wooless_additional_site_info', $initial_additional_data);
        foreach ($additional_data as $key => $value) {
            if (empty($value)) {
                continue;
            }

            if ( is_array( $value ) ) {
                $value = json_encode( $value );
            }

            $client->collections[$collection_site_info]->documents->create([
                'name' => $key,
                'value' => $value,
                'updated_at' => time(),
            ]);
        }

        do_action( 'blaze_wooless_after_site_info_sync' );

        echo "Site info added successfully!";
    } catch (Exception $e) {
        echo $e->getMessage();
    }

}

// Function to be called when an option is updated
function site_info_update($option_name, $old_value, $new_value)
{
    // Array of target General Settings options
    $target_settings = array(
        'blogname',
        'blogdescription',
        'siteurl',
        'home',
        'admin_email',
        'users_can_register',
        'default_role',
        'timezone_string',
        'date_format',
        'time_format',
        'start_of_week',
        'WPLANG',
        'woocommerce_demo_store',
    );

    // Check if the updated option is in the array of target settings
    if (in_array($option_name, $target_settings)) {
        site_info_index_to_typesense();
    }
}
