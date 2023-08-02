<?php

namespace BlazeWooless\Collections;

class Product extends BaseCollection
{
    private static $instance = null;
    public $collection_name = 'product';

    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function index_to_typesense()
    {
        //Product indexing

        // Fetch the store ID from the saved options
        $wooless_site_id = get_option('store_id');
        $collection_product = 'product-' . $wooless_site_id;
        try {
            try {
                $this->drop_collection();
            } catch (\Exception $e) {
                // Don't error out if the collection was not found
            }

            $this->create_collection(
                array(
                    'name' => $collection_product,
                    'fields' => array(
                        array( 'name' => 'id', 'type' => 'string', 'facet' => true ),
                        array( 'name' => 'productId', 'type' => 'string', 'facet' => true ),
                        array( 'name' => 'description', 'type' => 'string' ),
                        array( 'name' => 'shortDescription', 'type' => 'string' ),
                        array( 'name' => 'name', 'type' => 'string', 'facet' => true, 'sort' => true ),
                        array( 'name' => 'permalink', 'type' => 'string' ),
                        array( 'name' => 'slug', 'type' => 'string', 'facet' => true ),
                        array( 'name' => 'seoFullHead', 'type' => 'string' ),
                        array( 'name' => 'sku', 'type' => 'string' ),
                        array( 'name' => 'price', 'type' => 'object', "facet" => true ),
                        array( 'name' => 'regularPrice', 'type' => 'object' ),
                        array( 'name' => 'salePrice', 'type' => 'object' ),
                        array( 'name' => 'onSale', 'type' => 'bool', 'facet' => true ),
                        array( 'name' => 'stockQuantity', 'type' => 'int64' ),
                        array( 'name' => 'stockStatus', 'type' => 'string' ),
                        array( 'name' => 'updatedAt', 'type' => 'int64' ),
                        array( 'name' => 'createdAt', 'type' => 'int64' ),
                        array( 'name' => 'isFeatured', 'type' => 'bool', 'facet' => true ),
                        array( 'name' => 'totalSales', 'type' => 'int64' ),
                        array( 'name' => 'productType', 'type' => 'string', 'facet' => true ),
                        array( 'name' => 'taxonomies', 'type' => 'object[]', 'facet' => true ),
                        array( 'name' => 'crossSellData', 'type' => 'object[]', 'facet' => true ),
                    ),
                    'default_sorting_field' => 'updatedAt',
                    'enable_nested_fields' => true
                )
            );

            // Set initial values for pagination and batch size
            $finished = false;
            $page = 1;
            $batch_size = 100; // Adjust the batch size depending on your server's capacity
            $imported_products_count = 0;

            while (!$finished) {
                $products = \wc_get_products(array( 'status' => 'publish', 'limit' => $batch_size, 'page' => $page ));

                if (empty($products)) {
                    $finished = true;
                    continue;
                }

                $products_batch = array();

                // Prepare products for indexing in Typesense
                foreach ($products as $product) {
                    // Get the product data
                    $product_data = $this->generate_typesense_data($product);

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
                    $result = $this->import($products_batch);
                    $imported_products_count += count($products_batch); // Increment the count of imported products
                } catch (\Exception $e) {
                    error_log("Error importing products to Typesense: " . $e->getMessage());
                }
            }

            // After the while loop, print the number of imported products
            echo "Imported products count: " . $imported_products_count . "\n";

            wp_die();
        } catch (\Exception $e) {
            $error_message = "Error: " . $e->getMessage();
            echo $error_message; // Print the error message for debugging purposes
            echo "<script>
            console.log('Error block executed'); // Log a message to the browser console
            document.getElementById('error_message').innerHTML = '$error_message';
        </script>";
            echo "Error creating collection: " . $e->getMessage() . "\n";
        }
    }

    public function generate_typesense_data($product)
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

        // Get variations if the product is a variable product
        $variations_data = $default_attributes = [];
        if ($product_type === 'variable') {
            $variations = $product->get_available_variations();
            foreach ($variations as $variation) {
                $variation_obj = wc_get_product($variation['variation_id']);

                $variant_thumbnail_id = get_post_thumbnail_id($variation['variation_id']);
                $variant_attachment = get_post($variant_thumbnail_id);

                $variations_data[] = [
                    'variationId' => $variation['variation_id'],
                    'attributes' => $variation['attributes'],
                    'price' => array(
                        $currency => floatval($variation_obj->get_price()),
                    ),
                    'regularPrice' => array(
                        $currency => floatval($variation_obj->get_regular_price()),
                    ),
                    'salePrice' => array(
                        $currency => floatval($variation_obj->get_sale_price()),
                    ),
                    'stockQuantity' => empty($variation_obj->get_stock_quantity()) ? 0 : $variation_obj->get_stock_quantity(),
                    'stockStatus' => $variation_obj->get_stock_status(),
                    'onSale' => $variation_obj->is_on_sale(),
                    'sku' => $variation_obj->get_sku(),
                    'image' => [
                        'id' => $variant_thumbnail_id,
                        'title' => $variant_attachment->post_title,
                        'altText' => get_post_meta($variant_thumbnail_id, '_wp_attachment_image_alt', true),
                        'src' => get_the_post_thumbnail_url($variation['variation_id']),
                    ],
                ];
            }

            $default_attributes = $product->get_default_attributes();
        }

        $cross_sell_ids = $product->get_cross_sell_ids();
        $cross_sell_data = [];
        if (!empty($cross_sell_ids)) {
            $cross_sell_data = $this->get_cross_sell_products( $cross_sell_ids );
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
        $taxonomies = $this->get_taxonomies( $product );

        $related_products = $this->get_related_products( $product_id, $taxonomies );

        $product_data = [
            'id' => strval($product->get_id()),
            'productId' => strval($product->get_id()),
            'shortDescription' => !empty($shortDescription) ? $shortDescription : substr($description, 0, 150),
            'description' => $description,
            'name' => $product->get_name(),
            'permalink' => wp_make_link_relative( get_permalink($product->get_id()) ),
            'slug' => $product->get_slug(),
            'thumbnail' => $thumbnail,
            'sku' => $product->get_sku(),
            'price' => apply_filters('wooless_product_price', $default_price, $product_id),
            'regularPrice' => apply_filters('wooless_product_regular_price', $default_regular_price, $product_id),
            'salePrice' => apply_filters('wooless_product_sale_price', $default_sale_price, $product_id),
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
            'crossSellData' => empty($cross_sell_data) ? $related_products : $cross_sell_data,
            'attributes' => $attributes,
            'defaultAttributes' => $default_attributes,
            'upsellData' => $upsell_data,
            'additionalTabs' => apply_filters('wooless_product_tabs', $formatted_additional_tabs, $product_id),
            // 'attributes' => $attributes,
            // 'additional_information_shipping' => $shipping,
        ];
        return apply_filters('blaze_wooless_product_data_for_typesense', $product_data, $product_id);
    }

    public function get_taxonomies($product)
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

                    // Get Parent Term
                    $parentTerm = get_term($product_term->parent, $taxonomy);

                    $taxonomies_data[] = [
                        'name' => $product_term->name,
                        'url' => get_term_link($product_term->term_id),
                        'type' => $taxonomy,
                        'slug' => $product_term->slug,
                        'nameAndType' => $product_term->name . '|' . $taxonomy,                       'childAndParentTerm' => $parentTerm->name ? $product_term->name . '|' . $parentTerm->name : '',
                        'parentTerm' => $parentTerm->name ? $parentTerm->name : '',

                    ];
                }
            }
        }

        return $taxonomies_data;
    }

    public function get_related_products($product_id, $taxonomies) {
        $category = array();
        foreach($taxonomies as $taxonomy) {
            if($taxonomy['type'] == 'product_cat') {
                $category[] = $taxonomy['name'];
            }
        }

        // Get products that aren't the current product.
        $args = array(
            'exclude' => array( $product_id ),
            'limit' => 5,
            'page'  => 1,
            'status' => 'publish',
            'return' => 'ids',
            'category' => $category,
            'stock_status' => 'instock',
        );
        $products = wc_get_products( $args );

        return $this->get_cross_sell_products( $products );
    }

    public function get_cross_sell_products($product_ids) {
        $product_data = array();

        foreach($product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if ($product) {
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

                $product_data[] = array(
                    'id' => $product->get_id(),
                    'name' => $product->get_name(),
                    'permalink' => wp_make_link_relative( get_permalink($product->get_id()) ),
                    'slug' => $product->get_slug(),
                    'thumbnail' => $thumbnail,
                    'sku' => $product->get_sku(),
                    'price' => apply_filters('wooless_product_price', $default_price, $product_id),
                    'regularPrice' => apply_filters('wooless_product_regular_price', $default_regular_price, $product_id),
                    'salePrice' => apply_filters('wooless_product_sale_price', $default_sale_price, $product_id),
                    'onSale' => $product->is_on_sale(),
                    'stockQuantity' => empty($stockQuantity) ? 0 : $stockQuantity,
                    'stockStatus' => $product->get_stock_status(),
                    'updatedAt' => strtotime($product->get_date_modified()),
                    'createdAt' => strtotime($product->get_date_created()),
                    'isFeatured' => $product->get_featured(),
                    'totalSales' => $product->get_total_sales(),
                    'galleryImages' => $product_gallery,
                    'productType' => $product->get_type(),
                );
            }
        }

        return $product_data;
    }
}
