<?php

namespace BlazeCommerce;

use BlazeCommerce\Collections\Product;
use BlazeCommerce\Collections\SiteInfo;
use BlazeCommerce\Collections\Taxonomy;
use BlazeCommerce\Collections\Page;
use BlazeCommerce\Collections\Menu;

class Ajax
{
    private static $instance = null;

    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function __construct()
    {
        add_action( 'wp_ajax_index_data_to_typesense', array( $this, 'index_data_to_typesense' ) );
        add_action( 'wp_ajax_get_typesense_collections', array( $this, 'get_typesense_collections' ) );
        add_action( 'wp_ajax_save_typesense_api_key', 'save_typesense_api_key' );
    }

    public function get_typesense_collections()
    {
        if (isset($_POST['api_key'])) {
            $encoded_api_key = sanitize_text_field($_POST['api_key']);
            $decoded_api_key = base64_decode($encoded_api_key);
            $trimmed_api_key = explode(':', $decoded_api_key);
            $typesense_private_key = $trimmed_api_key[0];
            $blaze_commerce_site_id = $trimmed_api_key[1];

            $client = TypesenseCLient::get_instance()->client();


            try {
                $collection_name = 'product-' . $blaze_commerce_site_id;
                $collections = $client->collections[$collection_name]->retrieve();
                if (!empty($collections)) {
                    echo json_encode(['status' => 'success', 'message' => 'Typesense is working!', 'collection' => $collections]);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'No collection found for store ID: ' . $blaze_commerce_site_id]);
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

    public function index_data_to_typesense()
    {
        $collection_name = !(empty($_POST['collection_name'])) ? $_POST['collection_name'] : '';
        if ($collection_name == 'products') {
            Product::get_instance()->index_to_typesense();
        } else if ($collection_name == 'site_info') {
            SiteInfo::get_instance()->index_to_typesense();
        } else if ($collection_name == 'taxonomy') {
            Taxonomy::get_instance()->index_to_typesense();
        } else if ($collection_name == 'menu') {
            Menu::get_instance()->index_to_typesense();
        } else if ($collection_name == 'page') {
            Page::get_instance()->index_to_typesense();
        } else {
            echo "Collection name not found";
        }
        wp_die();
    }
}

Ajax::get_instance();
