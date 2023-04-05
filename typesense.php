<?php
/*
Plugin Name: Blaze Typesense Wooless
Plugin URI: https://www.blaze.online
Description: A plugin that integrates with Typesense server.
Version: 1.1
Author: Blaze Online
Author URI: https://www.blaze.online
*/
require 'vendor/autoload.php';
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
    wp_register_style('typesense_admin_styles', plugin_dir_url(__FILE__) . 'assets/frontend/style.css', array(), '1.0.0');
    wp_enqueue_style('typesense_admin_styles');
}

add_action('admin_enqueue_scripts', 'typesense_enqueue_styles');

function add_typesense_product_indexer_menu()
{
    add_menu_page(
        'Typesense Product Indexer',
        'Typesense Product Indexer',
        'manage_options',
        'typesense-product-indexer',
        'typesense_product_indexer_page',
        'dashicons-admin-generic'
    );
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
            <img src="<?php echo plugins_url('blazeWooless/assets/frontend/images/Shape.png'); ?>" alt="" srcset="">
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
                <img src="<?php echo plugins_url('blazeWooless/assets/frontend/images/error.png'); ?>" alt="" srcset="">
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
                data.collection_name = 'site_info';
                jQuery.post(ajaxurl, data, function(response) {
                    document.getElementById("message").textContent = response;
                    document.getElementById("check_api_key").disabled = false;
                    document.getElementById("check_api_key").textContent =
                        "Save";
                    document.getElementById("index_products").style.display =
                        "flex";
                    document.getElementById("check_api_key").style.cursor =
                        "pointer";
                    document.getElementById("wrapper-id").style.display = "flex";
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



function getTermData($taxonomyTerms)
{
    $termNames = [];
    $termLinks = [];
    if (!empty($taxonomyTerms)) {
        foreach ($taxonomyTerms as $term) {
            $termNames[] = $term->name;
            $termLinks[] = get_term_link($term->term_id);
        }
    }

    return [$termNames, $termLinks];
}

function getProductDataForTypeSense($product)
{
    // Format product data for indexing
    $product_id = $product->get_id();
    $shortDescription = $product->get_short_description();
    $description = $product->get_description();
    $addons = json_encode(get_product_addons($product_id, false));
    $attachment_ids = $product->get_gallery_image_ids();
    $product_gallery = array_map(function ($attachment_id) {
        return [
            'altText' => get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
            'url' => wp_get_attachment_url($attachment_id)
        ];
    }, $attachment_ids);

    $meta = YoastSEO()->meta->for_post($product_id);
    $fullHead = wp_gql_seo_get_full_head($meta);

    $shortDescription = $product->get_short_description();
    $description = $product->get_description();

    $thumbnail = get_the_post_thumbnail_url($product_id);
    $stockQuantity = $product->get_stock_quantity();

    $ingredients = get_the_terms($product_id, 'product_ingredients');
    $ingredients = array_map(function ($term) {
        return [
            'name' => $term->name,
            'description' => $term->description,
            'imageSourceUrl' => z_taxonomy_image_url($term->term_id)
        ];
    }, $ingredients);
    $ingredients = json_encode($ingredients);

    $categories = get_the_terms($product_id, 'product_cat');
    $categoriesData = getTermData($categories);
    $categoryNames = $categoriesData[0];
    $categoryLinks = $categoriesData[1];

    $tags = get_the_terms($product_id, 'product_tag');
    $tagsData = getTermData($tags);
    $tagNames = $tagsData[0];
    $tagLinks = $tagsData[1];

    $favourites = get_the_terms($product_id, 'favourite');
    $favouritesData = getTermData($favourites);
    $favouriteNames = $favouritesData[0];
    $favouriteLinks = $favouritesData[1];

    $occassions = get_the_terms($product_id, 'occasion');
    $occassionsData = getTermData($occassions);
    $occassionNames = $occassionsData[0];
    $occassionLinks = $occassionsData[1];


    $shopBys = get_the_terms($product_id, 'shop-by');
    $shopBysData = getTermData($shopBys);
    $shopByNames = $shopBysData[0];
    $shopByLinks = $shopBysData[1];

    $types = get_the_terms($product_id, 'product-type');
    $typesData = getTermData($types);
    $typeNames = $typesData[0];
    $typeLinks = $typesData[1];
    $product_type = $product->get_type();


    // Get variations if the product is a variable product
    $variations_data = [];
    if ($product_type === 'variable') {
        $variable_product = wc_get_product($product->get_id());
        $variations = $variable_product->get_available_variations();
        foreach ($variations as $variation) {
            $variation_obj = wc_get_product($variation['variation_id']);
            $variations_data[] = [
                'variationId' => $variation['variation_id'],
                'attributes' => $variation['attributes'],
                'price' => floatval($variation_obj->get_price()),
                'regularPrice' => floatval($variation_obj->get_regular_price()),
                'salePrice' => floatval($variation_obj->get_sale_price()),
                'stockQuantity' => empty($variation_obj->get_stock_quantity()) ? 0 : $variation_obj->get_stock_quantity(),
                'stockStatus' => $variation_obj->get_stock_status(),
                'onSale' => $variation_obj->is_on_sale(),
                'sku' => $variation_obj->get_sku(),
            ];
        }
    }

    $cross_sell_ids = $product->get_cross_sell_ids();
    $cross_sell_data = array();
    if (!empty($cross_sell_ids)) {
        foreach ($cross_sell_ids as $cross_sell_id) {
            $cross_sell_product = wc_get_product($cross_sell_id);
            if ($cross_sell_product) {
                $cross_sell_data[] = array(
                    'id' => $cross_sell_product->get_id(),
                    'name' => $cross_sell_product->get_name(),
                );
            }
        }
    }

    $upsell_ids = $product->get_upsell_ids();
    $upsell_data = array();
    if (!empty($upsell_ids)) {
        foreach ($upsell_ids as $upsell_id) {
            $upsell_product = wc_get_product($upsell_id);
            if ($upsell_product) {
                $upsell_data[] = array(
                    'id' => $upsell_product->get_id(),
                    'name' => $upsell_product->get_name(),
                );
            }
        }
    }
    // Get the additional product tabs
    $product_id = $product->get_id();
    $additional_tabs = get_post_meta($product_id, '_additional_tabs', true);
    $formatted_additional_tabs = array();

    if (!empty($additional_tabs)) {
        foreach ($additional_tabs as $tab) {
            $formatted_additional_tabs[] = array(
                'title' => $tab['tab_title'],
                'content' => $tab['tab_content'],
            );
        }
    }


    $product_data = [
        'id' => strval($product->get_id()),
        'productId' => strval($product->get_id()),
        'shortDescription' => !empty($shortDescription) ? $shortDescription : substr($description, 0, 150),
        'description' => $description,
        'name' => $product->get_name(),
        'permalink' => get_permalink($product->get_id()),
        'slug' => $product->get_slug(),
        'seoFullHead' => $fullHead,
        'thumbnail' => empty($thumbnail) ? '' : $thumbnail,
        'sku' => $product->get_sku(),
        'price' => floatval($product->get_price()),
        'regularPrice' => floatval($product->get_regular_price()),
        'salePrice' => floatval($product->get_sale_price()),
        'onSale' => $product->is_on_sale(),
        'stockQuantity' => empty($stockQuantity) ? 0 : $stockQuantity,
        'stockStatus' => $product->get_stock_status(),
        'updatedAt' => strtotime($product->get_date_modified()),
        'createdAt' => strtotime($product->get_date_created()),
        'isFeatured' => $product->get_featured(),
        'totalSales' => $product->get_total_sales(),
        'galleryImages' => json_encode($product_gallery),
        'addons' => $addons,
        'ingredients' => $ingredients,
        'categoryNames' => $categoryNames,
        'categoryLinks' => $categoryLinks,
        'tagNames' => $tagNames,
        'tagLinks' => $tagLinks,
        'favouriteNames' => $favouriteNames,
        'favouriteLinks' => $favouriteLinks,
        'occassionNames' => $occassionNames,
        'occassionLinks' => $occassionLinks,
        'shopByNames' => $shopByNames,
        'shopByLinks' => $shopByLinks,
        'typeNames' => $typeNames,
        'typeLinks' => $typeLinks,
        'productType' => $product_type,
        // Add product type
        'variations' => $variations_data,
        // Add variations data
        'crossSellData' => $cross_sell_data,
        'upsellData' => $upsell_data,
        'additionalTabs' => $formatted_additional_tabs,
    ];

    return $product_data;
}

function menu_index_to_typesense()
{
    $typesense_private_key = get_option('typesense_api_key');
    $client = getTypeSenseClient($typesense_private_key);

    // Fetch the store ID from the saved options
    $wooless_site_id = get_option('store_id');
    $collection_menu = 'menu-' . $wooless_site_id;
    //Menu indexing
    try {
        // Initialize the Typesense client
        $client = getTypeSenseClient($typesense_private_key);
        // Delete the existing 'menu' collection (if it exists)
        try {
            $client->collections[$collection_menu]->delete();
        } catch (Exception $e) {
            // Don't error out if the collection was not found
        }
        // Create the 'menu' collection with the required schema
        $client->collections->create([
            'name' => $collection_menu,
            'fields' => [
                ['name' => 'name', 'type' => 'string'],
                ['name' => 'Wp_Menu_Id', 'type' => 'int32'],
                ['name' => 'items', 'type' => 'string'],
                ['name' => 'updated_at', 'type' => 'int64'],
            ],
            'default_sorting_field' => 'Wp_Menu_Id',
        ]);

        // Get all navigation menus
        $menus = get_terms('nav_menu');
        // Add WooCommerce my-account links as a menu
        $my_account_links = wc_get_account_menu_items();
        $my_account_menu = new stdClass();
        $my_account_menu->name = 'WooCommerce My Account';
        $my_account_menu->term_id = 1 + (int) $wooless_site_id; // Assign a unique ID, can be any unique integer
        $my_account_menu_items = [];
        foreach ($my_account_links as $endpoint => $link_name) {
            $my_account_menu_items[] = (object) [
                'title' => $link_name,
                'url' => wc_get_endpoint_url($endpoint, '', wc_get_page_permalink('myaccount')),
            ];
        }
        $my_account_menu->menu_items = $my_account_menu_items;
        $menus[] = $my_account_menu;

        // Loop through each menu and index its items to the 'menu' collection
        foreach ($menus as $menu) {
            // Get all the menu items from the current menu
            //$menu_items = wp_get_nav_menu_items($menu->term_id);
            $menu_items = isset($menu->menu_items) ? $menu->menu_items : wp_get_nav_menu_items($menu->term_id);

            // Initialize an empty array to hold the menu item data
            $menu_item_data = [];

            // Loop through each menu item and add its data to the array
            foreach ($menu_items as $menu_item) {
                $menu_item_data[] = [
                    'title' => $menu_item->title,
                    'url' => $menu_item->url,
                ];
            }

            // Encode the menu item data as JSON
            $menu_item_json = json_encode($menu_item_data);

            // Create a document for the current menu and index it to the 'menu' collection
            $document = [
                'name' => $menu->name,
                'Wp_Menu_Id' => (int) $menu->term_id,
                'items' => $menu_item_json,
                'updated_at' => intval(strtotime($menu_item->post_modified), 10), // Converts the timestamp to a 64-bit integer
            ];


            $client->collections[$collection_menu]->documents->create($document);
        }

        echo "Menu successfully added\n";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
function taxonmy_index_to_typesense()
{
    $typesense_private_key = get_option('typesense_api_key');
    $client = getTypeSenseClient($typesense_private_key);

    // Fetch the store ID from the saved options
    $wooless_site_id = get_option('store_id');
    $collection_taxonomy = 'taxonomy-' . $wooless_site_id;
    //indexing taxonmy terms
    try {
        // Initialize the Typesense client
        $client = getTypeSenseClient($typesense_private_key);

        // Delete the existing 'TaxonomyTerms-gb' collection (if it exists)
        try {
            $client->collections[$collection_taxonomy]->delete();
        } catch (Exception $e) {
            // Don't error out if the collection was not found
        }
        $client->collections->create([
            'name' => $collection_taxonomy,
            'fields' => [
                ['name' => 'slug', 'type' => 'string', 'facet' => true],
                ['name' => 'name', 'type' => 'string', 'facet' => true, 'infix' => true],
                ['name' => 'description', 'type' => 'string'],
                ['name' => 'taxonomy', 'type' => 'string', 'facet' => true, 'infix' => true],
                ['name' => 'permalink', 'type' => 'string'],
                ['name' => 'updatedAt', 'type' => 'int64'],
                ['name' => 'bannerThumbnail', 'type' => 'string'],
                ['name' => 'bannerText', 'type' => 'string'],
            ],
            'default_sorting_field' => 'updatedAt',
        ]);

        // Add the custom taxonomies to this array
        $taxonomies = get_taxonomies([], 'names');

        // Fetch terms for all taxonomies except those starting with 'ef_'
        foreach ($taxonomies as $taxonomy) {
            // Skip taxonomies starting with 'ef_'
            if (strpos($taxonomy, 'ef_') === 0) {
                continue;
            }

            $args = [
                'taxonomy' => $taxonomy,
                'hide_empty' => false,
            ];

            $terms = get_terms($args);

            if (!empty($terms) && !is_wp_error($terms)) {
                foreach ($terms as $term) {

                    $latest_modified_date = null;

                    $query_args = [
                        'post_type' => 'any',
                        'posts_per_page' => 1,
                        'orderby' => 'modified',
                        'order' => 'DESC',
                        'tax_query' => [
                            [
                                'taxonomy' => $taxonomy,
                                'field' => 'term_id',
                                'terms' => $term->term_id,
                            ],
                        ],
                    ];

                    $latest_post_query = new WP_Query($query_args);

                    if ($latest_post_query->have_posts()) {
                        while ($latest_post_query->have_posts()) {
                            $latest_post_query->the_post();
                            $latest_modified_date = get_the_modified_date('Y-m-d H:i:s', get_the_ID());
                        }
                        wp_reset_postdata();
                    }

                    // Get the custom fields (bannerThumbnail and bannerText)
                    $bannerThumbnail = get_term_meta($term->term_id, 'wpcf-image', true);
                    $bannerText = get_term_meta($term->term_id, 'wpcf-term-banner-text', true);

                    if ($latest_modified_date) {
                        $timestamp = strtotime($latest_modified_date);
                        //var_dump($latest_modified_date, $timestamp);
                    }

                    // Prepare the data to be indexed
                    $document = [
                        'slug' => $term->slug,
                        'name' => $term->name,
                        'description' => $term->description,
                        'taxonomy' => $taxonomy,
                        'permalink' => get_term_link($term),
                        'updatedAt' => $latest_modified_date ? (int) strtotime($latest_modified_date) : 0,
                        'bannerThumbnail' => $bannerThumbnail,
                        'bannerText' => $bannerText,

                    ];
                    // Index the term data in Typesense
                    try {
                        $client->collections[$collection_taxonomy]->documents->create($document);
                    } catch (Exception $e) {
                        echo "Error adding term '{$term->name}' to Typesense: " . $e->getMessage() . "\n";
                    }
                }
            }
        }

        echo "taxonomy added successfully!\n";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
function site_info_index_to_typesense()
{
    $typesense_private_key = get_option('typesense_api_key');
    $client = getTypeSenseClient($typesense_private_key);

    // Fetch the store ID from the saved options
    $wooless_site_id = get_option('store_id');
    $collection_site_info = 'site_info-' . $wooless_site_id;
    //Indexing Site Info
    try {
        // Initialize the Typesense client
        $client = getTypeSenseClient($typesense_private_key);

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
                    'name' => 'value',
                    'type' => 'string',
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
        
        if ( ! empty( $available_gateways ) ) {
            foreach ( $available_gateways as $gateway ) {
                $payment_methods[] = $gateway->get_title() . ' (ID: ' . $gateway->id . ')';
            }
        }
        
        // Convert payment methods array to a string
        $payment_methods_string = implode(', ', $payment_methods);

        $client->collections[$collection_site_info]->documents->create([
            'name' => 'PaymentMethods',
            'value' => $payment_methods_string,
            'updated_at' => $updatedAt,
        ]);


        $client->collections[$collection_site_info]->documents->create([
            'name' => 'SiteTitle',
            'value' => $SiteTitle,
            'updated_at' => $updatedAt,
        ]);

        $client->collections[$collection_site_info]->documents->create([
            'name' => 'SiteTagline',
            'value' => $SiteTagline,
            'updated_at' => $updatedAt,
        ]);
        $client->collections[$collection_site_info]->documents->create([
            'name' => 'site logo',
            'value' => $logo_url,
            'updated_at' => $logo_updated_at,
        ]);
        $client->collections[$collection_site_info]->documents->create([
            'name' => 'Store Notice',
            'value' => $store_notice,
            'updated_at' => intval($store_notice_updated_at),
        ]);
        $client->collections[$collection_site_info]->documents->create([
            'name' => 'Time format',
            'value' => $time_format,
            'updated_at' => intval($current_unix_timestamp),
        ]);
        $client->collections[$collection_site_info]->documents->create([
            'name' => 'Search Engine',
            'value' => $search_engine,
            'updated_at' => intval($search_engine_last_updated),
        ]);
        $client->collections[$collection_site_info]->documents->create([
            'name' => 'Site ID',
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
            'name' => 'Time Zone',
            'value' => $timezone,
            'updated_at' => intval($wp_timezone_last_updated),
        ]);
        $client->collections[$collection_site_info]->documents->create([
            'name' => 'Date format',
            'value' => $date_format,
            'updated_at' => intval($date_format_last_updated),
        ]);
        $client->collections[$collection_site_info]->documents->create([
            'name' => 'favicon',
            'value' => $favicon_url,
            'updated_at' => intval($store_notice_updated_at),
        ]);
        echo "Site info added successfully!";
    } catch (Exception $e) {
        echo $e->getMessage();
    }

}
function products_to_typesense()
{
    //Product indexing
    $typesense_private_key = get_option('typesense_api_key');
    $client = getTypeSenseClient($typesense_private_key);

    // Fetch the store ID from the saved options
    $wooless_site_id = get_option('store_id');
    $collection_product = 'product-' . $wooless_site_id;
    try {
        $client = getTypeSenseClient($typesense_private_key);
        try {
            $client->collections[$collection_product]->delete();
        } catch (Exception $e) {
            // Don't error out if the collection was not found
        }
        $client->collections->create(
            [
                'name' => $collection_product,
                'fields' => [
                    ['name' => 'id', 'type' => 'string', 'facet' => true],
                    [
                        'name' => 'productId',
                        'type' => 'string',
                        'facet' => true,
                    ],
                    ['name' => 'description', 'type' => 'string'],
                    ['name' => 'shortDescription', 'type' => 'string'],
                    ['name' => 'name', 'type' => 'string'],
                    ['name' => 'permalink', 'type' => 'string'],
                    ['name' => 'slug', 'type' => 'string', 'facet' => true],
                    ['name' => 'seoFullHead', 'type' => 'string'],
                    ['name' => 'thumbnail', 'type' => 'string'],
                    ['name' => 'sku', 'type' => 'string'],
                    ['name' => 'price', 'type' => 'float'],
                    ['name' => 'regularPrice', 'type' => 'float'],
                    ['name' => 'salePrice', 'type' => 'float'],
                    ['name' => 'onSale', 'type' => 'bool'],
                    ['name' => 'stockQuantity', 'type' => 'int64'],
                    ['name' => 'stockStatus', 'type' => 'string'],
                    ['name' => 'updatedAt', 'type' => 'int64'],
                    ['name' => 'createdAt', 'type' => 'int64'],
                    ['name' => 'isFeatured', 'type' => 'bool'],
                    ['name' => 'totalSales', 'type' => 'int64'],
                    ['name' => 'galleryImages', 'type' => 'string'],
                    ['name' => 'addons', 'type' => 'string'],
                    ['name' => 'ingredients', 'type' => 'string'],
                    ['name' => 'categoryNames', 'type' => 'string[]', 'facet' => true],
                    ['name' => 'categoryLinks', 'type' => 'string[]'],
                    ['name' => 'tagNames', 'type' => 'string[]', 'facet' => true],
                    ['name' => 'tagLinks', 'type' => 'string[]'],
                    ['name' => 'favouriteNames', 'type' => 'string[]', 'facet' => true],
                    ['name' => 'favouriteLinks', 'type' => 'string[]'],
                    ['name' => 'occassionNames', 'type' => 'string[]', 'facet' => true],
                    ['name' => 'occassionLinks', 'type' => 'string[]'],
                    ['name' => 'shopByNames', 'type' => 'string[]', 'facet' => true],
                    ['name' => 'shopByLinks', 'type' => 'string[]'],
                    ['name' => 'typeNames', 'type' => 'string[]', 'facet' => true],
                    ['name' => 'typeLinks', 'type' => 'string[]'],
                    ['name' => 'productType', 'type' => 'string', 'facet' => true],
                    ['name' => 'variations', 'type' => 'string[]', 'facet' => true],

                ],
                'default_sorting_field' => 'updatedAt',
            ]
        );

        // Fetch products from WooCommerce
        $products = wc_get_products(['status' => 'publish', 'limit' => -1]);

        // Index products in Typesense
        foreach ($products as $product) {
            // Index the product data
            $product_data = getProductDataForTypeSense($product);
            $client->collections[$collection_product]->documents->create($product_data);
        }

        echo "Products indexed successfully.";
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
        echo $error_message; // Print the error message for debugging purposes
        echo "<script>
            console.log('Error block executed'); // Log a message to the browser console
            document.getElementById('error_message').innerHTML = '$error_message';
        </script>";
    }


    wp_die();
} // Add the action hook

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
    } else {
        echo "Collection name not found";
    }
    wp_die();
}
// Function to update the product in Typesense when its metadata is updated in WooCommerce
function update_product_in_typesense($product_id)
{
    // Check if the product is published before updating
    if (get_post_status($product_id) == 'publish') {
        try {
            $client = getTypeSenseClient();
            $wc_product = wc_get_product($product_id);
            $document_data = getProductDataForTypeSense($wc_product);

            $client->collections['products']->documents[strval($product_id)]->update($document_data);
        } catch (Exception $e) {
            error_log("Error updating product in Typesense: " . $e->getMessage());
        }
    }

}


function update_typesense_document_on_menu_update($menu_id, $menu_data)
{
    $typesense_private_key = get_option('typesense_api_key');
    $client = getTypeSenseClient($typesense_private_key);

    // Fetch the store ID from the saved options
    $wooless_site_id = get_option('store_id');
    $collection_menu = 'menu-' . $wooless_site_id;
    try {
        // Initialize the Typesense client
        $client = getTypeSenseClient($typesense_private_key);

        // Get the updated navigation menu
        $menu = get_term($menu_id, 'nav_menu');

        // Get all the menu items from the updated menu
        $menu_items = wp_get_nav_menu_items($menu->term_id);

        // Initialize an empty array to hold the menu item data
        $menu_item_data = [];

        // Loop through each menu item and add its data to the array
        foreach ($menu_items as $menu_item) {
            $menu_item_data[] = [
                'title' => $menu_item->title,
                'url' => $menu_item->url,
            ];
        }

        // Encode the menu item data as JSON
        $menu_item_json = json_encode($menu_item_data);

        // Create a document for the updated menu
        $document = [
            'name' => $menu->name,
            'wp_menu_id' => (int) $menu->term_id,
            'items' => $menu_item_json,
            'updated_at' => intval(strtotime($menu_item->post_modified), 10), // Converts the timestamp to a 64-bit integer
        ];
        try {
            $client->collections[$collection_menu]->documents[(string) $document['wp_menu_id']]->retrieve();
            $document_exists = true;
        } catch (Exception $e) {
            // Document not found, set $document_exists to false
            $document_exists = false;
        }

        // Check if the document exists in the 'menu' collection
        if ($document_exists) {
            $client->collections[$collection_menu]->documents[(string) $document['wp_menu_id']]->update($document);
            set_transient('typesense_updated_success', true, 5);
        } else {
            // If the document does not exist, create it
            $client->collections[$collection_menu]->documents->create($document);
            set_transient('typesense_created_success', true, 5);
        }
    } catch (Exception $e) {
        set_transient('typesense_error', true, 5);
    }

    $location = add_query_arg('typesense_menu_updated', '1', wp_get_referer());
    wp_redirect($location);
    exit;
}
function update_typesense_document_on_taxonomy_edit($term_id, $tt_id, $taxonomy)
{
    $typesense_private_key = get_option('typesense_api_key');
    $client = getTypeSenseClient($typesense_private_key);

    // Fetch the store ID from the saved options
    $wooless_site_id = get_option('store_id');
    $collection_taxonomy = 'taxonomy-' . $wooless_site_id;
    // Check if the taxonomy starts with 'ef_'
    if (strpos($taxonomy, 'ef_') === 0) {
        return;
    }

    // Get the term
    $term = get_term($term_id, $taxonomy);

    if (!$term || is_wp_error($term)) {
        return;
    }

    // Initialize the Typesense client
    $client = getTypeSenseClient($typesense_private_key);

    // Get the custom fields (bannerThumbnail and bannerText)
    $bannerThumbnail = get_term_meta($term->term_id, 'wpcf-image', true);
    $bannerText = get_term_meta($term->term_id, 'wpcf-term-banner-text', true);

    // Prepare the data to be updated
    $document = [
        'slug' => $term->slug,
        'name' => $term->name,
        'description' => $term->description,
        'taxonomy' => $taxonomy,
        'permalink' => get_term_link($term),
        'updatedAt' => time(),
        'bannerThumbnail' => $bannerThumbnail,
        'bannerText' => $bannerText,
    ];

    // Update the term data in Typesense
    try {
        $client->collections[$collection_taxonomy]->documents[strval($term->term_id)]->update($document);
    } catch (Exception $e) {
        error_log("Error updating term '{$term->name}' in Typesense: " . $e->getMessage());
    }
}