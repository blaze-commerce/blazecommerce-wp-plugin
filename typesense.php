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

function register_compatibilities() {
    // Compatibility
    require_once plugin_dir_path(__FILE__) . 'compatibility/woocommerce/product-addons.php';
    require_once plugin_dir_path(__FILE__) . 'compatibility/woocommerce/woocommerce-price-based-on-country.php';
    require_once plugin_dir_path(__FILE__) . 'compatibility/woocommerce/custom-product-tabs-for-woocommerce.php';
    require_once plugin_dir_path(__FILE__) . 'compatibility/yoast-seo.php';
}
add_action( 'init', 'register_compatibilities' );


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

    add_submenu_page(
        $menu_slug,
        'Setting',
        'Setting',
        'manage_options',
        $menu_slug,
        'typesense_product_indexer_page'
    );

    add_submenu_page(
        $menu_slug,
        'Homepage',
        'Homepage',
        'manage_options',
        $menu_slug . '-homepage',
        'typesense_homepage_page'
    );

    add_submenu_page(
        $menu_slug,
        'Site Message',
        'Site Message',
        'manage_options',
        $menu_slug . '-site-message',
        'typesense_site_message_page'
    );
}
function typesense_product_indexer_page()
{
	$wooless_site_id = get_option('store_id');
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
                <img src="<?php echo plugins_url('blaze-wooless/assets/images/error.png'); ?>" alt=""
                    srcset="">
                <div id="error_message"></div>
            </div>
        </div>
        <input type="checkbox" id="show_api_key" onclick="toggleApiKeyVisibility()">
        <label class="checkbox_Label">Show API Key</label>
    </div>
    <div class="item_wrapper_indexer_page">
        <button id="index_products" onclick="indexData()" store-id="<?php echo $wooless_site_id; ?>" disabled>Manual Sync
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
    }, function(save_response) {
        setTimeout(function() {
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
    jQuery.post(ajaxurl, data, function(response) {
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
    jQuery.post(ajaxurl, data, function(response) {
        document.getElementById("message").textContent = response;
        data.collection_name = 'taxonomy';
        jQuery.post(ajaxurl, data, function(response) {
            data.collection_name = 'menu';
            jQuery.post(ajaxurl, data, function(response) {
                data.collection_name = 'page';
                jQuery.post(ajaxurl, data, function(response) {
                    data.collection_name = 'site_info';
                    jQuery.post(ajaxurl, data, function(response) {
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
function typesense_homepage_page()
{
    // Your code for the homepage submenu page
    ?>
<div class="wrap">
    <h1>
        <?php _e('Homepage Settings', 'typesense'); ?>
    </h1>
    <form method="post" action="options.php">
        <?php
            settings_fields('typesense_homepage_settings');
            do_settings_sections('typesense_homepage_settings');
            submit_button('Save Settings');
            ?>
    </form>
</div>
<?php
}
function typesense_register_homepage_settings()
{
    register_setting('typesense_homepage_settings', 'typesense_homepage_settings');

    // Add the homepage banner settings section
    add_settings_section(
        'typesense_homepage_banner_settings',
        __('Homepage Banner Settings', 'typesense'),
        'typesense_homepage_banner_settings_callback',
        'typesense_homepage_settings'
    );
    // Add the popular categories settings section
    add_settings_section(
        'typesense_homepage_popular_categories_settings',
        __('Popular Categories Settings', 'typesense'),
        'typesense_homepage_popular_categories_settings_callback',
        'typesense_homepage_settings'
    );

    // Add other settings sections here
}

add_action('admin_init', 'typesense_register_homepage_settings');

function typesense_homepage_banner_settings_callback()
{
    // Add the settings fields for the homepage banner
    add_settings_field(
        'typesense_homepage_banner_image',
        __('Image', 'typesense'),
        'typesense_homepage_banner_image_callback',
        'typesense_homepage_settings',
        'typesense_homepage_banner_settings'
    );

    // Primary Message field
    add_settings_field(
        'typesense_homepage_primary_message',
        __('Primary Message', 'typesense'),
        'typesense_homepage_primary_message_callback',
        'typesense_homepage_settings',
        'typesense_homepage_banner_settings'
    );

    // Secondary Message field
    add_settings_field(
        'typesense_homepage_secondary_message',
        __('Secondary Message', 'typesense'),
        'typesense_homepage_secondary_message_callback',
        'typesense_homepage_settings',
        'typesense_homepage_banner_settings'
    );

    // Button Text field
    add_settings_field(
        'typesense_homepage_button_text',
        __('Button Text', 'typesense'),
        'typesense_homepage_button_text_callback',
        'typesense_homepage_settings',
        'typesense_homepage_banner_settings'
    );

    // Button Link field
    add_settings_field(
        'typesense_homepage_button_link',
        __('Button Link', 'typesense'),
        'typesense_homepage_button_link_callback',
        'typesense_homepage_settings',
        'typesense_homepage_banner_settings'
    );

}

function typesense_homepage_banner_image_callback()
{
    $options = get_option('typesense_homepage_settings');
    $image_url = isset($options['typesense_homepage_banner_image']) ? $options['typesense_homepage_banner_image'] : '';
    ?>
<input type="text" name="typesense_homepage_settings[typesense_homepage_banner_image]"
    id="typesense_homepage_banner_image" value="<?php echo esc_attr($image_url); ?>">
<input type="button" id="typesense_homepage_banner_image_button" class="button"
    value="<?php _e('Upload Image', 'typesense'); ?>">
<script>
jQuery(document).ready(function($) {
    var custom_uploader;

    $('#typesense_homepage_banner_image_button').click(function(e) {
        e.preventDefault();

        if (custom_uploader) {
            custom_uploader.open();
            return;
        }

        custom_uploader = wp.media.frames.file_frame = wp.media({
            title: '<?php _e('Choose Image', 'typesense'); ?>',
            button: {
                text: '<?php _e('Choose Image', 'typesense'); ?>'
            },
            multiple: false
        });

        custom_uploader.on('select', function() {
            var attachment = custom_uploader.state().get('selection').first().toJSON();
            $('#typesense_homepage_banner_image').val(attachment.url);
        });

        custom_uploader.open();
    });
});
</script>
<?php
}

function typesense_homepage_primary_message_callback()
{
    $options = get_option('typesense_homepage_settings');
    $primary_message = isset($options['typesense_homepage_primary_message']) ? $options['typesense_homepage_primary_message'] : '';
    ?>
<input type="text" name="typesense_homepage_settings[typesense_homepage_primary_message]"
    id="typesense_homepage_primary_message" value="<?php echo esc_attr($primary_message); ?>">
<?php
}

function typesense_homepage_secondary_message_callback()
{
    $options = get_option('typesense_homepage_settings');
    $secondary_message = isset($options['typesense_homepage_secondary_message']) ? $options['typesense_homepage_secondary_message'] : '';
    ?>
<input type="text" name="typesense_homepage_settings[typesense_homepage_secondary_message]"
    id="typesense_homepage_secondary_message" value="<?php echo esc_attr($secondary_message); ?>">
<?php
}

function typesense_homepage_button_text_callback()
{
    $options = get_option('typesense_homepage_settings');
    $button_text = isset($options['typesense_homepage_button_text']) ? $options['typesense_homepage_button_text'] : '';
    ?>
<input type="text" name="typesense_homepage_settings[typesense_homepage_button_text]"
    id="typesense_homepage_button_text" value="<?php echo esc_attr($button_text); ?>">
<?php
}

function typesense_homepage_button_link_callback()
{
    $options = get_option('typesense_homepage_settings');
    $button_link = isset($options['typesense_homepage_button_link']) ? $options['typesense_homepage_button_link'] : '';
    ?>
<input type="text" name="typesense_homepage_settings[typesense_homepage_button_link]"
    id="typesense_homepage_button_link" value="<?php echo esc_attr($button_link); ?>">
<?php
}
function typesense_homepage_popular_categories_settings_callback()
{
    // Add the settings fields for the popular categories
    add_settings_field(
        'typesense_homepage_popular_categories',
        __('Categories', 'typesense'),
        'typesense_homepage_popular_categories_callback',
        'typesense_homepage_settings',
        'typesense_homepage_popular_categories_settings'
    );
}

function typesense_homepage_popular_categories_callback()
{
    $options = get_option('typesense_homepage_settings');
    $categories = isset($options['typesense_homepage_popular_categories']) ? $options['typesense_homepage_popular_categories'] : '';
    ?>
<div id="popular-categories-container">
    <input type="hidden" name="typesense_homepage_settings[typesense_homepage_popular_categories]"
        id="typesense_homepage_popular_categories" value="<?php echo esc_attr(json_encode($categories)); ?>">
</div>
<input type="button" id="add-popular-category" class="button" value="<?php _e('Add Category', 'typesense'); ?>">
<script>
jQuery(document).ready(function($) {
    var categories = <?php echo json_encode($categories); ?>;
    var container = $('#popular-categories-container');

    function addCategory(category) {
        var categoryElem = $('<div class="popular-category"></div>');
        var imageInput = $('<input type="text" class="popular-category-image" placeholder="Image URL">');
        var titleInput = $('<input type="text" class="popular-category-title" placeholder="Title">');
        var linkInput = $('<input type="text" class="popular-category-link" placeholder="Link">');
        var deleteButton = $('<button class="button popular-category-delete">Delete</button>');

        imageInput.val(category.image);
        titleInput.val(category.title);
        linkInput.val(category.link);

        categoryElem.append(imageInput);
        categoryElem.append(titleInput);
        categoryElem.append(linkInput);
        categoryElem.append(deleteButton);
        container.append(categoryElem);
    }

    function updateCategoriesInput() {
        var categories = [];
        $('.popular-category').each(function() {
            var category = {
                image: $(this).find('.popular-category-image').val(),
                title: $(this).find('.popular-category-title').val(),
                link: $(this).find('.popular-category-link').val()
            };
            categories.push(category);
        });
        $('#typesense_homepage_popular_categories').val(JSON.stringify(categories));
    }

    if (categories) {
        categories.forEach(function(category) {
            addCategory(category);

        });

        // Make the categories sortable
        container.sortable({
            update: function(event, ui) {
                updateCategoriesInput();
            }
        });
    }

    // Add a new category when the "Add Category" button is clicked
    $('#add-popular-category').on('click', function() {
        var categoryCount = $('.popular-category').length;
        if (categoryCount < 10) {
            var emptyCategory = {
                image: '',
                title: '',
                link: ''
            };
            addCategory(emptyCategory);
        } else {
            alert('You can only add up to 10 categories.');
        }
    });

    // Delete a category when the "Delete" button is clicked
    container.on('click', '.popular-category-delete', function() {
        $(this).closest('.popular-category').remove();
        updateCategoriesInput();
    });

    // Update the categories input whenever a category field is changed
    container.on('change', '.popular-category-image, .popular-category-title, .popular-category-link',
        function() {
            updateCategoriesInput();
        });
});
</script>
<?php
}

function typesense_enqueue_scripts()
{
    wp_enqueue_script('jquery-ui-sortable');
}

add_action('admin_enqueue_scripts', 'typesense_enqueue_scripts');
$options = get_option('typesense_homepage_settings');
$popular_categories = json_decode($options['typesense_homepage_popular_categories'], true);


function typesense_site_message_page()
{
    // Your code for the site message submenu page
    echo '<h1>Site Message</h1>';
}

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
