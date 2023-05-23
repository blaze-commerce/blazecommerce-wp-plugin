<?php
/*
Plugin Name: Blaze Typesense Wooless
Plugin URI: https://www.blaze.online
Description: A plugin that integrates with Typesense server.
Version: 1.2
Author: Blaze Online
Author URI: https://www.blaze.online
*/
require 'inc/vendor/autoload.php';
require_once plugin_dir_path(__FILE__) . 'inc/product/index.php';
require_once plugin_dir_path(__FILE__) . 'inc/menu/index.php';
require_once plugin_dir_path(__FILE__) . 'inc/taxonomy/index.php';
require_once plugin_dir_path(__FILE__) . 'inc/pages/index.php';
require_once plugin_dir_path(__FILE__) . 'inc/site-info/index.php';
require_once plugin_dir_path(__FILE__) . 'views/homepage-setting.php';
require_once plugin_dir_path(__FILE__) . 'views/site-message-setting.php';


function register_compatibilities()
{
    // Compatibility
    require_once plugin_dir_path(__FILE__) . 'compatibility/woocommerce/product-addons.php';
    require_once plugin_dir_path(__FILE__) . 'compatibility/woocommerce/woocommerce-price-based-on-country.php';
    require_once plugin_dir_path(__FILE__) . 'compatibility/woocommerce/custom-product-tabs-for-woocommerce.php';
    require_once plugin_dir_path(__FILE__) . 'compatibility/yoast-seo.php';
}
add_action('init', 'register_compatibilities');


use Symfony\Component\HttpClient\HttplugClient;
use Typesense\Client;

function getTypeSenseClient($typesense_private_key)
{
    $client = new Client([
        'api_key' => $typesense_private_key,
        'nodes' => [
            [
                'host' => 'gq6r7nsikma359hep-1.a1.typesense.net',
                'port' => '443',
                'protocol' => 'https',
            ],
        ],
        'client' => new HttplugClient(),
    ]);

    return $client;
}

add_action('admin_enqueue_scripts', 'enqueue_typesense_product_indexer_scripts');
add_action('admin_menu', 'add_typesense_product_indexer_menu');
add_action('wp_ajax_index_data_to_typesense', 'index_data_to_typesense');
add_action('wp_ajax_get_typesense_collections', 'get_typesense_collections');
add_action('wp_ajax_save_typesense_api_key', 'save_typesense_api_key');
add_action('wp_update_nav_menu', 'update_typesense_document_on_menu_update', 10, 2);
add_action('edited_term', 'update_typesense_document_on_taxonomy_edit', 10, 3);
add_action('updated_option', 'site_info_update', 10, 3);
add_action('woocommerce_new_product', 'bwl_on_product_save', 10, 2);
add_action('woocommerce_update_product', 'bwl_on_product_save', 10, 2);
add_action('woocommerce_order_status_changed', 'bwl_on_order_status_changed', 10, 4);
add_action('woocommerce_checkout_update_order_meta', 'bwl_on_checkout_update_order_meta', 10, 2);



function enqueue_typesense_product_indexer_scripts()
{
    wp_enqueue_script('jquery');
}
function typesense_enqueue_google_fonts($hook)
{
    // Only load the font on your plugin's page
    if ('toplevel_page_typesense-product-indexer' !== $hook) {
        return;
    }

    // Register and enqueue the 'Poppins' Google Font
    wp_register_style('google-font-poppins', 'https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,500;1,400&display=swap', array(), null);
    wp_enqueue_style('google-font-poppins');
}

add_action('admin_enqueue_scripts', 'typesense_enqueue_google_fonts');

function typesense_enqueue_styles($hook)
{
    // Only load styles on your plugin's page
    if ('toplevel_page_typesense-product-indexer' !== $hook) {
        return;
    }

    // Register and enqueue your stylesheet
    wp_register_style('typesense_admin_styles', plugin_dir_url(__FILE__) . 'assets/css/style.css', array(), '1.0.0');
    wp_enqueue_style('typesense_admin_styles');
}

add_action('admin_enqueue_scripts', 'typesense_enqueue_styles');


function my_admin_enqueue_scripts($hook)
{
    if ($hook == 'wooless_page_typesense-product-indexer-site-message') {
        wp_enqueue_style('my_admin-style', plugins_url('assets/css/style.css', __FILE__));
        wp_enqueue_script('my_admin-script', plugins_url('assets/js/typesense-admin.js', __FILE__), array('jquery'), '1.0', true);
    }
}
function homepage_enqueue_scripts($hook)
{

    if ($hook == 'wooless_page_typesense-product-indexer-homepage') {
        wp_enqueue_style('my_admin-style', plugins_url('assets/css/style.css', __FILE__));
        wp_enqueue_script('my_admin-script', plugins_url('assets/js/typesense-admin.js', __FILE__), array('jquery'), '1.0', true);
    }
}
function typesense_product_indexer_page()
{
    echo '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;700&display=swap">';
    $private_key_master = get_option('private_key_master', '');
    ?>
    <div class="indexer_page">
        <h1>Typesense Product Indexer</h1>
        <div id="wrapper-id" class="message-wrapper">
            <div class="message-image">
                <img src="<?php echo plugins_url('blaze-wooless/assets/images/Shape.png'); ?>" alt="" srcset="">
            </div>
            <div class="wooless_message">
                <div class="message_success">Success</div>
                <div id="message"></div>
            </div>
        </div>
        <div class="wrapper">
            <label class="api_label" for="api_key">API Private Key: </label>
            <div class="input-wrapper">
                <input class="input_p" type="password" id="api_key" name="api_key"
                    value="<?php echo esc_attr($private_key_master); ?>" />
                <div class="error-icon" id="error_id" style="display: none;">
                    <img src="<?php echo plugins_url('blaze-wooless/assets/images/error.png'); ?>" alt="" srcset="">
                    <div id="error_message"></div>
                </div>
            </div>
            <input type="checkbox" id="show_api_key" onclick="toggleApiKeyVisibility()">
            <label class="checkbox_Label">Show API Key</label>
        </div>
        <div class="item_wrapper_indexer_page">
            <button id="index_products" onclick="indexData()" disabled>Manual Sync
            </button>
            <button id="check_api_key" onclick="checkApiKey()">Save</button>
            <div id="jsdecoded" style="margin-top: 10px;"></div>
            <div id="phpdecoded" style="margin-top: 10px;"></div>
        </div>
    </div>



    <script>
        function toggleApiKeyVisibility() {
            var apiKeyInput = document.getElementById("api_key");
            var showApiKeyCheckbox = document.getElementById("show_api_key");

            if (showApiKeyCheckbox.checked) {
                apiKeyInput.type = "text";
            } else {
                apiKeyInput.type = "password";
            }
        }

        function decodeAndSaveApiKey(apiKey) {
            var decodedApiKey = atob(apiKey);
            var trimmedApiKey = decodedApiKey.split(':');
            var typesensePrivateKey = trimmedApiKey[0];
            var woolessSiteId = trimmedApiKey[1];

            // Display API key and store ID for testing purposes
            //document.getElementById("jsdecoded").innerHTML = 'Typesense Private Key: ' + typesensePrivateKey +
            //  '<br> Store ID: ' +
            //woolessSiteId;

            // Save the API key, store ID, and private key
            jQuery.post(ajaxurl, {
                'action': 'save_typesense_api_key',
                'api_key': apiKey, // Add the private key in the request
                'typesense_api_key': typesensePrivateKey,
                'store_id': woolessSiteId,
            }, function (save_response) {
                setTimeout(function () {
                    document.getElementById("message").textContent += ' - ' + save_response;
                }, 1000);
            });

        }

        function checkApiKey() {
            var apiKey = document.getElementById("api_key").value;
            var data = {
                'action': 'get_typesense_collections',
                'api_key': apiKey,
            };
            document.getElementById("wrapper-id").style.display = "none";
            document.getElementById("index_products").disabled = true;
            document.getElementById("check_api_key").disabled = true;
            document.getElementById("check_api_key").style.cursor = "no-drop";
            document.getElementById("index_products").style.cursor = "no-drop";
            jQuery.post(ajaxurl, data, function (response) {
                console.log(response);
                var parsedResponse = JSON.parse(response);
                if (parsedResponse.status === "success") {
                    //alert(parsedResponse.message);

                    // Log the collection data
                    console.log("Collection data:", parsedResponse.collection);
                    // Decode and save the API key
                    decodeAndSaveApiKey(apiKey);
                    indexData();
                    document.getElementById("index_products").disabled = false;
                    document.getElementById("wrapper-id").style.display = "none";
                    document.getElementById("error_id").style.display = "none";
                    document.getElementById("index_products").style.cursor = "pointer";
                } else {
                    //alert("Invalid API key. There was an error connecting to Typesense.");
                    var errorMessage = "Invalid API key.";
                    document.getElementById("error_message").textContent = errorMessage;
                    document.getElementById("index_products").disabled = true;
                    document.getElementById("error_id").style.display = "flex";
                    document.getElementById("index_products").disabled = false;
                    document.getElementById("check_api_key").disabled = false;
                    document.getElementById("check_api_key").style.cursor = "pointer";
                    document.getElementById("index_products").style.cursor = "pointer";

                }
            });
        }



        function indexData() {
            var apiKey = document.getElementById("api_key").value;
            var data = {
                'action': 'index_data_to_typesense',
                'api_key': apiKey,
                'collection_name': 'products',

            };
            document.getElementById("wrapper-id").style.display = "none";
            document.getElementById("message").textContent = "Indexing Data...";
            document.getElementById("check_api_key").textContent = "Indexing Data...";
            document.getElementById("index_products").disabled = true;
            document.getElementById("check_api_key").disabled = true;
            document.getElementById("check_api_key").style.cursor = "no-drop";
            document.getElementById("index_products").style.display = "none";
            jQuery.post(ajaxurl, data, function (response) {
                document.getElementById("message").textContent = response;
                data.collection_name = 'taxonomy';
                jQuery.post(ajaxurl, data, function (response) {
                    data.collection_name = 'menu';
                    jQuery.post(ajaxurl, data, function (response) {
                        data.collection_name = 'page';
                        jQuery.post(ajaxurl, data, function (response) {
                            data.collection_name = 'site_info';
                            jQuery.post(ajaxurl, data, function (response) {
                                document.getElementById("message").textContent =
                                    response;
                                document.getElementById("check_api_key").disabled =
                                    false;
                                document.getElementById("check_api_key").textContent =
                                    "Save";
                                document.getElementById("index_products").style
                                    .display =
                                    "flex";
                                document.getElementById("check_api_key").style.cursor =
                                    "pointer";
                                document.getElementById("wrapper-id").style.display =
                                    "flex";
                            });
                        });
                    });
                });
            });
        }
        // Enable or disable the 'Index Products' button based on the saved API key
        if (document.getElementById("api_key").value !== "") {
            document.getElementById("index_products").disabled = false;
        }
    </script>
    <?php
}

function add_typesense_product_indexer_menu()
{
    $menu_slug = 'typesense-product-indexer';

    add_menu_page(
        'Wooless',
        'Wooless',
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


function my_admin_theme_style()
{
    wp_enqueue_style('my-admin-theme', plugins_url('blaze-wooless/assets/css/style.css', __FILE__));
}
add_action('admin_enqueue_scripts', 'my_admin_theme_style');



function save_typesense_api_key()
{
    if (isset($_POST['api_key'])) {
        $private_key = $_POST['api_key'];
        $decoded_api_key = base64_decode($private_key);
        $trimmed_api_key = explode(':', $decoded_api_key);
        $typesense_api_key = $trimmed_api_key[0];
        $store_id = $trimmed_api_key[1];

        update_option('private_key_master', $private_key);
        update_option('typesense_api_key', $typesense_api_key);
        update_option('store_id', $store_id);

        //echo "Private key, API key, and store ID saved successfully.";
        // Construct the message to display
        $phpmessage = "Private key: " . $private_key . "<br>";
        $phpmessage .= "Typesense API key: " . $typesense_api_key . "<br>";
        $phpmessage .= "Store ID: " . $store_id;

        // Echo the message to the div
        //echo "<script>document.getElementById('phpdecoded').innerHTML = 'Private key, API key, and store ID saved successfully.';</script>";
    } else {
        echo "Error: Private key not provided.";
    }

    wp_die();
}

function get_typesense_collections()
{
    if (isset($_POST['api_key'])) {
        $encoded_api_key = sanitize_text_field($_POST['api_key']);
        $decoded_api_key = base64_decode($encoded_api_key);
        $trimmed_api_key = explode(':', $decoded_api_key);
        $typesense_private_key = $trimmed_api_key[0];
        $wooless_site_id = $trimmed_api_key[1];

        $client = getTypeSenseClient($typesense_private_key);


        try {
            $collection_name = 'product-' . $wooless_site_id;
            $collections = $client->collections[$collection_name]->retrieve();
            if (!empty($collections)) {
                echo json_encode(['status' => 'success', 'message' => 'Typesense is working!', 'collection' => $collections]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'No collection found for store ID: ' . $wooless_site_id]);
            }
        } catch (Typesense\Exception\ObjectNotFound $e) {
            echo json_encode(['status' => 'error', 'message' => 'Collection not found: ' . $e->getMessage()]);
        } catch (Typesense\Exception\TypesenseClientError $e) {
            echo json_encode(['status' => 'error', 'message' => 'Typesense client error: ' . $e->getMessage()]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'There was an error connecting to Typesense: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'API key not provided.']);
    }

    wp_die();
}


function index_data_to_typesense()
{
    $collection_name = !(empty($_POST['collection_name'])) ? $_POST['collection_name'] : '';
    if ($collection_name == 'products') {
        products_to_typesense();
    } else if ($collection_name == 'site_info') {
        site_info_index_to_typesense();
    } else if ($collection_name == 'taxonomy') {
        taxonmy_index_to_typesense();
    } else if ($collection_name == 'menu') {
        menu_index_to_typesense();
    } else if ($collection_name == 'page') {
        page_index_to_typesense();
    } else {
        echo "Collection name not found";
    }
    wp_die();
}