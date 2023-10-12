<?php
/*
Plugin Name: Blaze Commerce
Plugin URI: https://www.blazecommerce.io
Description: The official plugin that integrates your site with the Blaze Commerce service.
Version: 1.4.5
Author: Blaze Commerce
Author URI: https://www.blazecommerce.io
*/

define( 'BLAZE_WOOLESS_PLUGIN_DIR', plugin_dir_path(__FILE__) );
define( 'BLAZE_COMMERCE_PLUGIN_VERSION', '1.4.5' );

require 'vendor/autoload.php';
require_once plugin_dir_path(__FILE__) . 'lib/regional-data-helper.php';
// require_once plugin_dir_path(__FILE__) . 'inc/settings/class-base-settings.php';
// require_once plugin_dir_path(__FILE__) . 'inc/settings/class-general-settings.php';
// require_once plugin_dir_path(__FILE__) . 'inc/settings/class-product-page-settings.php';
require_once plugin_dir_path(__FILE__) . 'lib/setting-helper.php';
require_once plugin_dir_path(__FILE__) . 'lib/blaze-wooless-functions.php';
// require_once plugin_dir_path(__FILE__) . 'inc/class-settings.php';
// require_once plugin_dir_path(__FILE__) . 'inc/product/index.php';
// require_once plugin_dir_path(__FILE__) . 'inc/menu/index.php';
// require_once plugin_dir_path(__FILE__) . 'inc/taxonomy/index.php';
// require_once plugin_dir_path(__FILE__) . 'inc/pages/index.php';
// require_once plugin_dir_path(__FILE__) . 'inc/site-info/index.php';
// require_once plugin_dir_path(__FILE__) . 'views/homepage-setting.php';
// require_once plugin_dir_path(__FILE__) . 'views/site-message-setting.php';


// Initialize plugin
function BlazeCommerce()
{
	return \BlazeWooless\BlazeWooless::get_instance();
}

BlazeCommerce()->init();


// function register_compatibilities()
// {
//     // Compatibility
//     require_once plugin_dir_path(__FILE__) . 'compatibility/woocommerce/product-addons.php';
//     require_once plugin_dir_path(__FILE__) . 'compatibility/woocommerce/woocommerce-price-based-on-country.php';
//     require_once plugin_dir_path(__FILE__) . 'compatibility/woocommerce/woocommerce-prices-by-country.php';
//     require_once plugin_dir_path(__FILE__) . 'compatibility/woocommerce/custom-product-tabs-for-woocommerce.php';
//     require_once plugin_dir_path(__FILE__) . 'compatibility/yoast-seo.php';
// }
// add_action('init', 'register_compatibilities');=

add_action('admin_enqueue_scripts', 'enqueue_typesense_product_indexer_scripts');
add_action('admin_menu', 'add_typesense_product_indexer_menu');

function enqueue_typesense_product_indexer_scripts()
{
    wp_enqueue_script('jquery');
}
function typesense_enqueue_google_fonts($hook)
{
    // Only load the font on your plugin's page
    if ('toplevel_page_wooless-settings' !== $hook) {
        return;
    }

    // Register and enqueue the 'Poppins' Google Font
    wp_register_style('google-font-poppins', 'https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,500;1,400&display=swap', array( 'chosen' ), null);
    wp_enqueue_style('google-font-poppins');

    wp_register_style(
        'chosen',
        '//cdnjs.cloudflare.com/ajax/libs/chosen/1.1.0/chosen.min.css',
        array(),
        null,
        'all',
    );
    wp_enqueue_style('chosen');

    wp_enqueue_style( 'blaze-wooless-admin-style', plugins_url( 'assets/css/blaze-wooless.css',  __FILE__ ), null, '1.0' );
    wp_enqueue_script( 'blaze-wooless-admin-script', plugins_url( 'assets/js/blaze-wooless.js', __FILE__ ), array( 'jquery', 'jquery-ui-droppable', 'jquery-ui-draggable', 'jquery-ui-sortable' ), '1.0', true );
    // wp_enqueue_script( 'blaze-wooless-admin-script-react', plugins_url( 'dist/main.js', __FILE__ ), array( 'jquery', 'jquery-ui-droppable', 'jquery-ui-draggable', 'jquery-ui-sortable' ), '1.0', true );

    wp_register_script(
        'chosen',
        '//cdnjs.cloudflare.com/ajax/libs/chosen/1.1.0/chosen.jquery.min.js',
        array('jquery'),
        null,
        true,
    );
    wp_enqueue_script('chosen');
}

add_action('admin_enqueue_scripts', 'typesense_enqueue_google_fonts');

// function typesense_enqueue_styles($hook)
// {
//     // Only load styles on your plugin's page
//     if ('toplevel_page_wooless-settings' !== $hook) {
//         return;
//     }

//     // Register and enqueue your stylesheet
//     wp_register_style('typesense_admin_styles', plugin_dir_url(__FILE__) . 'assets/css/style.css', array(), '1.0.0');
//     wp_enqueue_style('typesense_admin_styles');
// }

// add_action('admin_enqueue_scripts', 'typesense_enqueue_styles');


// function my_admin_enqueue_scripts($hook)
// {
//     if ($hook == 'wooless_page_typesense-product-indexer-site-message') {
//         wp_enqueue_style('my_admin-style', plugins_url('assets/css/style.css', __FILE__));
//         wp_enqueue_script('my_admin-script', plugins_url('assets/js/typesense-admin.js', __FILE__), array('jquery'), '1.0', true);
//     }
// }

// function homepage_enqueue_scripts($hook)
// {

//     if ($hook == 'wooless_page_typesense-product-indexer-homepage') {
//         wp_enqueue_style('my_admin-style', plugins_url('assets/css/style.css', __FILE__));
//         wp_enqueue_script('my_admin-script', plugins_url('assets/js/typesense-admin.js', __FILE__), array('jquery'), '1.0', true);
//     }
// }

// function add_sortable_js()
// {
//     wp_enqueue_script('sortable_js', 'https://cdn.jsdelivr.net/npm/sortablejs@1.14.0/Sortable.min.js', array('jquery'), '1.14.0', true);
// }
// add_action('wp_enqueue_scripts', 'add_sortable_js');

function typesense_product_indexer_page()
{
    echo '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;700&display=swap">';
    require_once plugin_dir_path(__FILE__) . 'views/settings.php';
}

function add_typesense_product_indexer_menu()
{
    $menu_slug = 'wooless-settings';

    add_menu_page(
        'Blaze Commerce',
        'Blaze Commerce',
        'manage_options',
        $menu_slug,
        'typesense_product_indexer_page',
        'dashicons-admin-generic'
    );

    // Create the submenus using the action
    do_action('bwl_setting_menu', $menu_slug);

    // Remove the default 'Wooless' submenu page
    remove_submenu_page($menu_slug, $menu_slug);

    // Add the "Setting" subpage last so it appears at the end
    add_submenu_page(
        $menu_slug,
        'Setting',
        'Setting',
        'manage_options',
        $menu_slug,
        'typesense_product_indexer_page'
    );
}


// function my_admin_theme_style()
// {
//     wp_enqueue_style('my-admin-theme', plugins_url('blaze-wooless/assets/css/style.css', __FILE__));
// }
// add_action('admin_enqueue_scripts', 'my_admin_theme_style');



// function save_typesense_api_key()
// {
//     if (isset($_POST['api_key'])) {
//         $private_key = $_POST['api_key'];
//         $decoded_api_key = base64_decode($private_key);
//         $trimmed_api_key = explode(':', $decoded_api_key);
//         $typesense_api_key = $trimmed_api_key[0];
//         $store_id = $trimmed_api_key[1];

//         update_option('private_key_master', $private_key);
//         update_option('typesense_api_key', $typesense_api_key);
//         update_option('store_id', $store_id);

//         //echo "Private key, API key, and store ID saved successfully.";
//         // Construct the message to display
//         $phpmessage = "Private key: " . $private_key . "<br>";
//         $phpmessage .= "Typesense API key: " . $typesense_api_key . "<br>";
//         $phpmessage .= "Store ID: " . $store_id;

//         // Echo the message to the div
//         //echo "<script>document.getElementById('phpdecoded').innerHTML = 'Private key, API key, and store ID saved successfully.';</script>";
//     } else {
//         echo "Error: Private key not provided.";
//     }

//     wp_die();
// }

// function get_typesense_collections()
// {
//     if (isset($_POST['api_key'])) {
//         $encoded_api_key = sanitize_text_field($_POST['api_key']);
//         $decoded_api_key = base64_decode($encoded_api_key);
//         $trimmed_api_key = explode(':', $decoded_api_key);
//         $typesense_private_key = $trimmed_api_key[0];
//         $wooless_site_id = $trimmed_api_key[1];

//         $client = getTypeSenseClient($typesense_private_key);


//         try {
//             $collection_name = 'product-' . $wooless_site_id;
//             $collections = $client->collections[$collection_name]->retrieve();
//             if (!empty($collections)) {
//                 echo json_encode(['status' => 'success', 'message' => 'Typesense is working!', 'collection' => $collections]);
//             } else {
//                 echo json_encode(['status' => 'error', 'message' => 'No collection found for store ID: ' . $wooless_site_id]);
//             }
//         } catch (Typesense\Exception\ObjectNotFound $e) {
//             echo json_encode(['status' => 'error', 'message' => 'Collection not found: ' . $e->getMessage()]);
//         } catch (Typesense\Exception\TypesenseClientError $e) {
//             echo json_encode(['status' => 'error', 'message' => 'Typesense client error: ' . $e->getMessage()]);
//         } catch (Exception $e) {
//             echo json_encode(['status' => 'error', 'message' => 'There was an error connecting to Typesense: ' . $e->getMessage()]);
//         }
//     } else {
//         echo json_encode(['status' => 'error', 'message' => 'API key not provided.']);
//     }

//     wp_die();
// }

