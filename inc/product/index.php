<?php

function getTypeSenseCollection()
{
    // Fetch the store ID from the saved options
    $wooless_site_id = get_option('store_id');
    // Build the collection name
    $collection = 'product-' . $wooless_site_id;
    return $collection;
}


function getTermData($taxonomyTerms)
{
    $termData = [];
    if (!empty($taxonomyTerms)) {
        foreach ($taxonomyTerms as $term) {
            $termData[] = [
                'name' => $term->name,
                'url' => get_term_link($term->term_id),
            ];
        }
    }

    return $termData;
}

function getProductTaxonomies($product)
{
    $taxonomies_data = [];
    $taxonomies = get_object_taxonomies('product');

    foreach ($taxonomies as $taxonomy) {
        // Exclude taxonomies based on their names
        if (preg_match('/^(ef_|elementor|pa_|nav_|ml-|ufaq|translation_priority|wpcode_)/', $taxonomy)) {
            continue;
        }

        $product_terms = get_the_terms($product->get_id(), $taxonomy);

        if (!empty($product_terms) && !is_wp_error($product_terms)) {
            foreach ($product_terms as $product_term) {
                $taxonomies_data[] = [
                    'name' => $product_term->name,
                    'url' => get_term_link($product_term->term_id),
                    'type' => $taxonomy,
                    'slug' => $product_term->slug,
                    'nameAndType' => $product_term->name . '|' . $taxonomy,

                ];
            }
        }
    }

    return $taxonomies_data;
}



function getProductDataForTypeSense($product)
{
    // Format product data for indexing
    $product_id = $product->get_id();
    $shortDescription = $product->get_short_description();
    $description = $product->get_description();
    $attachment_ids = $product->get_gallery_image_ids();
    $product_gallery = array_map(function ($attachment_id) {
        $attachment = get_post($attachment_id);
        return [
            'id' => $attachment_id,
            'title' => $attachment->post_title,
            'altText' => get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
            'src' => wp_get_attachment_url($attachment_id)
        ];
    }, $attachment_ids);

    $shortDescription = $product->get_short_description();
    $description = $product->get_description();

    // Get the thumbnail
    $thumbnail_id = get_post_thumbnail_id($product_id);
    $attachment = get_post($thumbnail_id);

    $thumbnail = [
        'id' => $thumbnail_id,
        'title' => $attachment->post_title,
        'altText' => get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true),
        'src' => get_the_post_thumbnail_url($product_id),
    ];

    $stockQuantity = $product->get_stock_quantity();

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
    $taxonomies = getProductTaxonomies($product);
    $currency = get_option('woocommerce_currency');

    $default_price = [
        $currency => floatval($product->get_price())
    ];
    $default_regular_price = [
        $currency => floatval($product->get_regular_price())
    ];
    $default_sale_price = [
        $currency => floatval($product->get_sale_price())
    ];

    $product_data = [
        'id' => strval($product->get_id()),
        'productId' => strval($product->get_id()),
        'shortDescription' => !empty($shortDescription) ? $shortDescription : substr($description, 0, 150),
        'description' => $description,
        'name' => $product->get_name(),
        'permalink' => get_permalink($product->get_id()),
        'slug' => $product->get_slug(),
        'thumbnail' => $thumbnail,
        'sku' => $product->get_sku(),
        'price' => apply_filters( 'wooless_product_price', $default_price, $product_id ),
        'regularPrice' => apply_filters( 'wooless_product_regular_price', $default_regular_price, $product_id ),
        'salePrice' => apply_filters( 'wooless_product_sale_price', $default_sale_price, $product_id ),
        'onSale' => $product->is_on_sale(),
        'stockQuantity' => empty($stockQuantity) ? 0 : $stockQuantity,
        'stockStatus' => $product->get_stock_status(),
        'updatedAt' => strtotime($product->get_date_modified()),
        'createdAt' => strtotime($product->get_date_created()),
        'isFeatured' => $product->get_featured(),
        'totalSales' => $product->get_total_sales(),
        'galleryImages' => $product_gallery,
        'taxonomies' => $taxonomies,
        'productType' => $product_type,
        // Add product type
        'variations' => $variations_data,
        // Add variations data
        'crossSellData' => $cross_sell_data,
        'upsellData' => $upsell_data,
        'additionalTabs' => apply_filters('wooless_product_tabs', $formatted_additional_tabs, $product_id),
        // 'attributes' => $attributes,
        // 'additional_information_shipping' => $shipping,
    ];
    return apply_filters('blaze_wooless_product_data_for_typesense', $product_data, $product_id);
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
                    ['name' => 'name', 'type' => 'string', 'facet' => true, 'sort' => true],
                    ['name' => 'permalink', 'type' => 'string'],
                    ['name' => 'slug', 'type' => 'string', 'facet' => true],
                    ['name' => 'seoFullHead', 'type' => 'string'],
                    //['name' => 'thumbnail', 'type' => 'string'],
                    ['name' => 'sku', 'type' => 'string'],
                    [
                        'name' => 'price',
                        'type' => 'object',
                        "facet" => true
                    ],
                    [
                        'name' => 'regularPrice',
                        'type' => 'object',
                    ],
                    [
                        'name' => 'salePrice',
                        'type' => 'object',
                        'fields' => [
                            ['name' => 'amount', 'type' => 'float', 'sort' => true],
                            ['name' => 'currency', 'type' => 'string'],
                        ]
                    ],
                    ['name' => 'onSale', 'type' => 'bool', 'facet' => true],
                    ['name' => 'stockQuantity', 'type' => 'int64'],
                    ['name' => 'stockStatus', 'type' => 'string'],
                    ['name' => 'updatedAt', 'type' => 'int64'],
                    ['name' => 'createdAt', 'type' => 'int64'],
                    ['name' => 'isFeatured', 'type' => 'bool', 'facet' => true],
                    ['name' => 'totalSales', 'type' => 'int64'],
                    //['name' => 'galleryImages', 'type' => 'object[]'],
                    // ['name' => 'addons', 'type' => 'string'],
                    ['name' => 'productType', 'type' => 'string', 'facet' => true],
                    ['name' => 'taxonomies', 'type' => 'object[]', 'facet' => true],
                    // ['name' => 'attributes', 'type' => 'object[]', 'facet' => true],
                    // ['name' => 'additional_information_shipping', 'type' => 'object', 'fields' => [
                    //     ['name' => 'weight', 'type' => 'float'],
                    //     ['name' => 'dimensions', 'type' => 'object', 'fields' => [
                    //         ['name' => 'length', 'type' => 'float'],
                    //         ['name' => 'width', 'type' => 'float'],
                    //         ['name' => 'height', 'type' => 'float'],
                    //     ]],
                    // ]],
                ],
                'default_sorting_field' => 'updatedAt',
                'enable_nested_fields' => true
            ]
        );

        // Set initial values for pagination and batch size
        $finished = false;
        $page = 1;
        $batch_size = 100; // Adjust the batch size depending on your server's capacity
        $imported_products_count = 0;

        while (!$finished) {
            $products = wc_get_products(['status' => 'publish', 'limit' => $batch_size, 'page' => $page]);

            if (empty($products)) {
                $finished = true;
                continue;
            }

            $products_batch = [];

            // Prepare products for indexing in Typesense
            foreach ($products as $product) {
                // Get the product data
                $product_data = getProductDataForTypeSense($product);

                if (!$product_data) {
                    error_log("Skipping product ID: " . $product->get_id());
                    continue; // Skip this product if no product data is found
                }

                $products_batch[] = $product_data;

                // Free memory
                unset($product_data);
            }

            // Log the number of products in the batch
            error_log("Batch size: " . count($products_batch));

            // Increment the page number
            $page++;

            // Import products to Typesense
            try {
                $client->collections[$collection_product]->documents->import($products_batch);
                $imported_products_count += count($products_batch); // Increment the count of imported products
            } catch (Exception $e) {
                error_log("Error importing products to Typesense: " . $e->getMessage());
            }
        }

        // After the while loop, print the number of imported products
        echo "Imported products count: " . $imported_products_count . "\n";

        wp_die();
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
        echo $error_message; // Print the error message for debugging purposes
        echo "<script>
        console.log('Error block executed'); // Log a message to the browser console
        document.getElementById('error_message').innerHTML = '$error_message';
    </script>";
        echo "Error creating collection: " . $e->getMessage() . "\n";
    }

}

function bwl_on_order_status_changed($order_id, $old_status, $new_status, $order)
{
    if ($new_status === 'completed' || $new_status === 'processing' || $new_status === 'cancelled' || $new_status === 'refunded') {
        // Get the items in the order
        $items = $order->get_items();

        // Loop through each item and update the corresponding product in Typesense
        foreach ($items as $item) {
            $product_id = $item->get_product_id();
            $wc_product = wc_get_product($product_id);

            if ($wc_product->get_status() == 'publish') {
                try {
                    $typesense_private_key = get_option('typesense_api_key');
                    $client = getTypeSenseClient($typesense_private_key);

                    $document_data = getProductDataForTypeSense($wc_product);

                    // Use the bwlGetProductCollectionName function for the collection_name value
                    $collection_name = getTypeSenseCollection();

                    $client->collections[$collection_name]->documents[strval($product_id)]->update($document_data);
                    do_action('ts_product_update', $product_id, $wc_product);
                } catch (Exception $e) {
                    error_log("Error updating product in Typesense during checkout: " . $e->getMessage());
                }
            }
        }
    }
}

// Function to update the product in Typesense when its metadata is updated in WooCommerce
function bwl_on_product_save($product_id, $wc_product)
{
    // Creating global variable so that this function only runs once if product id is exactly equal to product id
    global $bwl_previous_product_id;
    if ($bwl_previous_product_id === $product_id) {
        // Check if the product is published before updating typesense data
        if ($wc_product->get_status() == 'publish') {
            try {
                $typesense_private_key = get_option('typesense_api_key'); // Get the API key
                $client = getTypeSenseClient($typesense_private_key); // Pass the API key as an argument
                $document_data = getProductDataForTypeSense($wc_product);
                // Use the bwlGetProductCollectionName function for the collection_name value
                $collection_name = getTypeSenseCollection();
                $client->collections[$collection_name]->documents[strval($product_id)]->update($document_data);

                do_action('ts_product_update', $product_id, $wc_product);
            } catch (Exception $e) {
                error_log("Error updating product in Typesense: " . $e->getMessage());
            }
        }
    }
    // Setting the variable in memory so that we can use this later for checking
    $bwl_previous_product_id = $product_id;
}
function bwl_on_checkout_update_order_meta($order_id, $data)
{
    // Get the order object
    $order = wc_get_order($order_id);

    // Get the items in the order
    $items = $order->get_items();

    // Loop through each item and update the corresponding product in Typesense
    foreach ($items as $item) {
        $product_id = $item->get_product_id();
        $wc_product = wc_get_product($product_id);

        if ($wc_product->get_status() == 'publish') {
            try {
                $typesense_private_key = get_option('typesense_api_key');
                $client = getTypeSenseClient($typesense_private_key);

                $document_data = getProductDataForTypeSense($wc_product);

                $collection_name = getTypeSenseCollection();

                $client->collections[$collection_name]->documents[strval($product_id)]->update($document_data);
                do_action('ts_product_update', $product_id, $wc_product);
            } catch (Exception $e) {
                error_log("Error updating product in Typesense during checkout: " . $e->getMessage());
            }
        }
    }
}