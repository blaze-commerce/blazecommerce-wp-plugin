<?php

namespace BlazeCommerce\Collections;

use BlazeCommerce\TypesenseClient;

class Menu extends BaseCollection
{
    private static $instance = null;
    public $collection_name = 'menu';

    public static function get_instance()
    {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    // Indexes the navigation menus to Typesense
    public function index_to_typesense()
    {
        try {
            $this->drop_collection();
        } catch (\Exception $e) {
            // Don't error out if the collection was not found
        }

        //Menu indexing
        try {
            // Create the 'menu' collection with the required schema
            $this->create_collection([
                'name' => $this->collection_name(),
                'fields' => [
                    ['name' => 'name', 'type' => 'string'],
                    ['name' => 'wp_menu_id', 'type' => 'int32'],
                    ['name' => 'items', 'type' => 'string'],
                    ['name' => 'updated_at', 'type' => 'int64'],
                ],
                'default_sorting_field' => 'wp_menu_id',
            ]);

            // Get all navigation menus
            $menus = get_terms('nav_menu');
            // Add WooCommerce my-account links as a menu
            $my_account_links = wc_get_account_menu_items();
            $my_account_menu = new \stdClass();
            $my_account_menu->name = 'WooCommerce My Account';
            $my_account_menu->term_id = 12444; // Assign a unique ID, can be any unique integer
            $my_account_menu_items = [];
            foreach ($my_account_links as $endpoint => $link_name) {
                $my_account_menu_items[] = (object) [
                    'title' => $link_name,
                    'url' => wc_get_endpoint_url($endpoint, '', wc_get_page_permalink('myaccount')),
                ];
            }
            $my_account_menu->menu_items = $my_account_menu_items;
            $document = [
                'name' => 'WooCommerce My Account',
                'wp_menu_id' => 12444,
                'items' => json_encode($my_account_menu_items),
                'updated_at' => time(), // Converts the timestamp to a 64-bit integer
            ];


            $this->create($document);

            // Loop through each menu and index its items to the 'menu' collection
            foreach ($menus as $menu) {
                // Get all the menu items from the current menu
                //$menu_items = wp_get_nav_menu_items($menu->term_id);
                $menu_items = isset($menu->menu_items) ? $menu->menu_items : wp_get_nav_menu_items($menu->term_id);

                // Initialize an empty array to hold the menu item data
                $menu_item_data = [];

                foreach ($menu_items as $item) {
                    if (!$item->menu_item_parent) {
                        // If there's no parent, add it to the top level of the nested array
                        $menu_item_data[$item->ID] = array(
                            'title' => $item->title,
                            'url' => $item->url,
                            'children' => array(),
                        );
                    } else {
                        // If there's a parent, add it as a child of its parent
                        $menu_item_data[$item->menu_item_parent]['children'][] = array(
                            'title' => $item->title,
                            'url' => $item->url,
                        );
                    }
                }

                $menu_item_data = array_values($menu_item_data);

                // Encode the menu item data as JSON
                $menu_item_json = json_encode($menu_item_data);

                // Create a document for the current menu and index it to the 'menu' collection
                $document = [
                    'name' => $menu->name,
                    'wp_menu_id' => (int) $menu->term_id,
                    'items' => $menu_item_json,
                    'updated_at' => intval(strtotime($menu->post_modified), 10), // Converts the timestamp to a 64-bit integer
                ];

                unset($menu_items);

                $this->create($document);
            }

            echo "Menu successfully added\n";
        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
    }

    public function update_typesense_document_on_menu_update($menu_id, $menu_data)
    {
        try {
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
            // try {
            //     // $this->collection()->documents[(string) $document['wp_menu_id']]->retrieve();
            //     $document_exists = true;
            // } catch (\Exception $e) {
            //     // Document not found, set $document_exists to false
            //     $document_exists = false;
            // }

            // Check if the document exists in the 'menu' collection
            TypesenseClient::get_instance()->menu()->upsert($document);
            // if ($document_exists) {
            //     $this->update((string) $document['wp_menu_id'], $document);
            //     set_transient('typesense_updated_success', true, 5);
            // } else {
            //     // If the document does not exist, create it
            //     $this->create($document);
            //     set_transient('typesense_created_success', true, 5);
            // }
        } catch (\Exception $e) {
            set_transient('typesense_error', true, 5);
        }

        // $location = add_query_arg('typesense_menu_updated', '1', wp_get_referer());
        // wp_redirect($location);
        // exit;
    }
}
