<?php
namespace BlazeWooless\Collections;

use BlazeWooless\Settings\RegionalSettings;

class SiteInfo extends BaseCollection
{
    private static $instance = null;
    public $collection_name = 'site_info';

    public static function get_instance()
    {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    // public function __construct()
    // {
    //     add_action( 'updated_option', array( 'site_info_update' ), 10, 3 );
    // }

    public function index_to_typesense()
    {
        // Fetch the store ID from the saved options
        $wooless_site_id = get_option('store_id');
        $collection_site_info = 'site_info-' . $wooless_site_id;
        //Indexing Site Info
        try {
            // Delete the existing 'site_info' collection (if it exists)
            try {
                $this->drop_collection();
            } catch (\Exception $e) {
                // Don't error out if the collection was not found
            }
            // Create the 'site_info' collection with the required schema
            $this->create_collection(
                array(
                    'name' => $collection_site_info,
                    'fields' => array(
                        array( 'name' => 'name', 'type' => 'string' ),
                        array( 'name' => '.*', 'type' => 'string*' ),
                        array( 'name' => 'updated_at', 'type' => 'int64' ),
                    ),
                    'default_sorting_field' => 'updated_at',
                ),
            );

            $update_at = time();

            $datas = array(
                array(
                    'name' => 'site_title',
                    'value' => get_bloginfo('name'),
                ),
                array(
                    'name' => 'Site_tagline',
                    'value' => get_bloginfo('description'),
                ),
                array(
                    'name' => 'stock_display_format',
                    'value' => $this->get_stock_display_format(),
                ),
                array(
                    'name' => 'product_attributes',
                    'value' => $this->get_product_attributes(),
                ),
                array(
                    'name' => 'permalink_structure',
                    'value' => $this->get_permalink_structure(),
                ),
                array(
                    'name' => 'reviews_plugin',
                    'value' => $this->get_active_reviews_plugin(),
                ),
                array(
                    'name' => 'payment_methods',
                    'value' => $this->get_available_payment_methods(),
                ),
                array(
                    'name' => 'time_format',
                    'value' => get_option('time_format'),
                ),
                array(
                    'name' => 'search_engine',
                    'value' => get_option('blog_public'),
                ),
                array(
                    'name' => 'site_id',
                    'value' => strval( get_current_blog_id() ),
                ),
                array(
                    'name' => 'wordpress_address_url',
                    'value' => get_site_url(),
                ),
                array(
                    'name' => 'admin_email_address',
                    'value' => get_option('admin_email'),
                    'updated_at' => intval( get_option('admin_email_last_updated', $update_at) ),
                ),
                array(
                    'name' => 'language',
                    'value' => get_locale(),
                    'updated_at' => intval( get_option('locale_last_updated', $update_at) ),
                ),
                array(
                    'name' => 'time_zone',
                    'value' => date_default_timezone_get(),
                    'updated_at' => intval( get_option('timezone_last_updated', $update_at) ),
                ),
                array(
                    'name' => 'date_format',
                    'value' => get_option('date_format'),
                    'updated_at' => intval( get_option('date_format_last_updated', $update_at) ),
                ),
                array(
                    'name' => 'woocommerce_calc_taxes',
                    'value' => get_option( 'woocommerce_calc_taxes', 'no' ),
                ),
                array(
                    'name' => 'woocommerce_prices_include_tax',
                    'value' => get_option( 'woocommerce_prices_include_tax', 'no' ),
                ),
            );

            $datas[] = $this->site_logo_settings();
            $datas[] = $this->store_notice_settings();
            $datas[] = $this->favicon_settings();

            foreach ( $datas as $data ) {
                if ( ! isset( $data['updated_at'] ) ) {
                    $data['updated_at'] = $update_at;
                }

                if ( is_array( $data['value'] ) ) {
                    $data['value'] = json_encode( $data['value'] );
                }

                $response = $this->create( $data );
            }

            unset($datas);

            // Get the favicon last updated timestamp
            // if ($site_icon_id) {
            //     $favicon_updated_at = strtotime(get_the_modified_date('Y-m-d H:i:s', $site_icon_id));
            // } else {
            //     $favicon_updated_at = 0;
            // }
    
    
            //admin email
            // function my_admin_email_updated_callback($old_value, $new_value, $option)
            // {
            //     update_option('admin_email_last_updated', time());
            // }
            // add_action('update_option_admin_email', 'my_admin_email_updated_callback', 10, 3);
    
            // add_action('update_option_admin_email', 'my_admin_email_updated_callback', 10, 3);

            //language
            // function my_locale_updated_callback($old_value, $new_value, $option)
            // {
            //     update_option('locale_last_updated', time());
            // }
            // add_action('update_option_WPLANG', 'my_locale_updated_callback', 10, 3);
            //timezone
            // function my_timezone_updated_callback($old_value, $new_value, $option)
            // {
            //     update_option('timezone_last_updated', time());
            // }
            // add_action('update_option_timezone_string', 'my_timezone_updated_callback', 10, 3);

            //Date format
            // function my_date_format_updated_callback($old_value, $new_value, $option)
            // {
            //     update_option('date_format_last_updated', time());
            // }
            // add_action('update_option_date_format', 'my_date_format_updated_callback', 10, 3);
    
            $homepage_data = apply_filters('blaze_wooless_additional_homepage_info', array());
            foreach ($homepage_data as $key => $value) {
                if (empty($value)) {
                    continue;
                }
    
                // If it's 'popular_categories', decode it from a JSON string
                if ($key == 'popular_categories') {
                    $value = json_decode($value, true);
                }
    
                $this->create([
                    'name' => $key,
                    'value' => (string) $value,
                    'updated_at' => time(),
                ]);
            }

            unset($homepage_data);
    
            $site_messages_data = apply_filters('blaze_wooless_additional_site_info_message', array());
            foreach ($site_messages_data as $key => $value) {
                if (empty($value)) {
                    continue;
                }
    
                $this->create([
                    'name' => $key,
                    'value' => $value,
                    'updated_at' => time(),
                ]);
            }

            unset($site_messages_data);
    
            $initial_additional_data = array();
            
            $site_currency = get_woocommerce_currency();
            $base_currency = \RegionalDataHelper::$currency_country_map[ $site_currency ];
            $currencies = array(
                'countries' => [$base_currency],
                'baseCountry' => $base_currency,
                'currency' => $site_currency,
                'symbol' => html_entity_decode(get_woocommerce_currency_symbol( $site_currency )),
                'symbolPosition'  => get_option( 'woocommerce_currency_pos' ),
                'thousandSeparator' => get_option( 'woocommerce_price_thousand_sep' ),
                'decimalSeparator'  => get_option( 'woocommerce_price_decimal_sep' ),
                'precision' => wc_get_price_decimals(),
                'default' => true,
            );
            $initial_additional_data['currencies'] = array( $currencies );
            $initial_additional_data['regions'] = RegionalSettings::get_selected_regions();
    
            $additional_data = apply_filters('blaze_wooless_additional_site_info', $initial_additional_data);
            foreach ($additional_data as $key => $value) {
                if (empty($value)) {
                    continue;
                }
    
                if ( is_array( $value ) ) {
                    $value = json_encode( $value );
                }
    
                $this->create([
                    'name' => $key,
                    'value' => $value,
                    'updated_at' => time(),
                ]);
            }

            unset($additional_data);
    
            do_action( 'blaze_wooless_after_site_info_sync' );
    
            echo "Site info added successfully!";
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
    
    }

    public function get_stock_display_format()
    {
        // Get WooCommerce stock settings
        $manage_stock = get_option('woocommerce_manage_stock'); // 'yes' or 'no'
        $stock_format = get_option('woocommerce_stock_format'); // 'always', 'never', or 'low_amount'

        // If stock management is disabled, the stock display format will be empty
        if ($manage_stock === 'no') {
            return '';
        }

        return $stock_format;
    }

    public function get_product_attributes()
    {
        // TODO: Make that it will get the variants present on woocommerce
        // Prepare the product attributes
        $product_attributes = [
            ["name" => "size", "type" => "select"],
            ["name" => "color", "type" => "swatch"],
            ["name" => "style", "type" => "image"]
        ];

        // Convert the attributes to a JSON string
        return $product_attributes;
    }

    public function get_permalink_structure()
    {
        $permalink_structure = get_option('woocommerce_permalinks');
        $product_base = $permalink_structure['product_base'] ?: '';

        // If the product base does not start with a slash, add one
        if ($product_base && $product_base[0] !== '/') {
            $product_base = '/' . $product_base;
        }

        $category_base = get_option('category_base', 'category');
        $tag_base = get_option('tag_base', 'tag');
        $base_permalink_structure = get_option('permalink_structure');

        return array(
            'product' => $product_base . '/%postname%',
            'category' => '/' . $category_base . '/%categoryname%',
            'tag' => '/' . $tag_base . '/%tagname%',
            'base' => $base_permalink_structure . '/%postname%',
            'posts' => '/blog/%postname%',
            'pages' => $base_permalink_structure . '/%pagename%',
        );
    }

    public function get_active_reviews_plugin()
    {
        global $wpdb;
    
        // Fetch the 'active_plugins' option from the WordPress options table
        $active_plugins_serialized = $wpdb->get_var("SELECT option_value FROM " . $wpdb->options . " WHERE option_name = 'active_plugins'");
        $active_plugins = unserialize($active_plugins_serialized);

        // List of known review plugin slugs
        $review_plugin_slugs = array(
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
            'multi-rating-pro',
        );
        
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
        return implode(', ', $filtered_plugin_directories);
    }

    public function get_available_payment_methods()
    {
        $available_gateways = \WC()->payment_gateways->get_available_payment_gateways();
        return array_map( function($gateway) {
            return $gateway->id;
        }, $available_gateways);
    }

    public function site_logo_settings()
    {
        $logo_id = get_theme_mod('custom_logo');
        $logo_image = wp_get_attachment_image_src($logo_id, 'full');
        $logo_metadata = wp_get_attachment_metadata($logo_id);
        $logo_updated_at = isset($logo_metadata['file']) ? strtotime(date("Y-m-d H:i:s", filemtime(get_attached_file($logo_id)))) : null;

        return array(
            'name' => 'site_logo',
            'value' => $logo_image ? $logo_image[0] : '',
            'updated_at' => $logo_updated_at,
        );
    }

    public function store_notice_settings()
    {
        global $wpdb;

        $store_notice = get_option('woocommerce_demo_store_notice');
        $store_notice_updated_at = $wpdb->get_var("SELECT UNIX_TIMESTAMP(option_value) FROM {$wpdb->options} WHERE option_name = '_transient_timeout_woocommerce_demo_store_notice'") ?: time();
        
        return array(
            'name' => 'store_notice',
            'value' => $store_notice,
            'updated_at' => intval($store_notice_updated_at),
        );
    }

    public function favicon_settings()
    {
        $site_icon_id = get_option('site_icon');
        $favicon_url = $site_icon_id ? wp_get_attachment_image_url($site_icon_id, 'full') : '';

        if ($site_icon_id) {
            $favicon_updated_at = strtotime( get_the_modified_date('Y-m-d H:i:s', $site_icon_id) );
        } else {
            $favicon_updated_at = 0;
        }

        return array(
            'name' => 'favicon',
            'value' => $favicon_url,
            'updated_at' => intval( $favicon_updated_at ),
        );
    }

    // Function to be called when an option is updated
    // public function site_info_update($option_name, $old_value, $new_value)
    // {
    //     // Array of target General Settings options
    //     $target_settings = array(
    //         'blogname',
    //         'blogdescription',
    //         'siteurl',
    //         'home',
    //         'admin_email',
    //         'users_can_register',
    //         'default_role',
    //         'timezone_string',
    //         'date_format',
    //         'time_format',
    //         'start_of_week',
    //         'WPLANG',
    //         'woocommerce_demo_store',
    //     );

    //     // Check if the updated option is in the array of target settings
    //     if (in_array($option_name, $target_settings)) {
    //         $this->site_info_index_to_typesense();
    //     }
    // }
}
