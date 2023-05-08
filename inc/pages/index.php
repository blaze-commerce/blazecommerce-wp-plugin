<?php

function page_index_to_typesense()
{
    $typesense_private_key = get_option('typesense_api_key');
    $client = getTypeSenseClient($typesense_private_key);

    // Fetch the store ID from the saved options
    $wooless_site_id = get_option('store_id');
    $collection_pages = 'page-' . $wooless_site_id;

    try {
        // Initialize the Typesense client
        $client = getTypeSenseClient($typesense_private_key);

        // Delete the existing collection (if it exists)
        try {
            $client->collections[$collection_pages]->delete();
        } catch (Exception $e) {
            // Don't error out if the collection was not found
        }

        $client->collections->create([
            'name' => $collection_pages,
            'fields' => [
                ['name' => 'name', 'type' => 'string'],
                ['name' => 'slug', 'type' => 'string', 'facet' => true],
                //['name' => 'seoFullHead', 'type' => 'string'],
                ['name' => 'permalink', 'type' => 'string'],
                ['name' => 'type', 'type' => 'string', 'facet' => true],
                ['name' => 'thumbnail', 'type' => 'string'],
                ['name' => 'taxonomies', 'type' => 'string'],
                ['name' => 'updatedAt', 'type' => 'int64'],
                ['name' => 'createdAt', 'type' => 'int64'],
            ],
            'default_sorting_field' => 'updatedAt',
            'enable_nested_fields' => true
        ]);


        function getPostTaxonomies($post_id, $post_type)
        {
            echo "Registered taxonomies for {$post_type}: " . json_encode(get_object_taxonomies($post_type)) . "\n\n";

            $taxonomies_data = [];
            $taxonomies = get_object_taxonomies($post_type);

            foreach ($taxonomies as $taxonomy) {
                // Exclude taxonomies based on their names
                if (preg_match('/^(ef_|elementor|pa_|nav_|ml-|ufaq|product_visibility|translation_priority|wpcode_|following_users|post_format|post_status)/', $taxonomy)) {
                    continue;
                }

                $post_terms = get_the_terms($post_id, $taxonomy);

                if (!empty($post_terms) && !is_wp_error($post_terms)) {
                    foreach ($post_terms as $post_term) {
                        $taxonomies_data[$taxonomy][] = [
                            'name' => $post_term->name,
                            'url' => get_term_link($post_term->term_id),
                            'slug' => $post_term->slug,
                        ];
                    }
                }
            }

            return $taxonomies_data;
        }

        $args = [
            'post_type' => ['post', 'page'],
            'post_status' => 'publish',
            'posts_per_page' => -1,
        ];

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();

                $taxonomies_data = getPostTaxonomies(get_the_ID(), get_post_type());

                // Debug code
                echo "Post ID: " . get_the_ID() . "\n";
                echo "Post type: " . get_post_type() . "\n";
                echo "Taxonomies data: " . json_encode($taxonomies_data) . "\n\n";


                $yoastMeta = YoastSEO()->meta->for_term($term->term_id);
                $termHead = is_object($yoastMeta) ? $yoastMeta->get_head() : '';
                $termHeadString = is_string($termHead) ? $termHead : (isset($termHead->html) ? $termHead->html : '');

                $selFullHead = is_string($termHead) ? $termHead : $termHead->html;

                $thumbnail_id = get_post_thumbnail_id();
                $attachment = get_post($thumbnail_id);

                $thumbnail = [
                    'id' => $thumbnail_id,
                    'title' => is_object($attachment) ? $attachment->post_title : '',
                    'altText' => get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true),
                    'src' => get_the_post_thumbnail_url(),
                ];
                $document = [
                    'slug' => get_post_field('post_name'),
                    'name' => get_the_title(),
                    'seoFullHead' => $selFullHead,
                    'type' => get_post_type(),
                    'permalink' => get_permalink(),
                    'thumbnail' => json_encode($thumbnail),
                    'taxonomies' => json_encode($taxonomies_data),
                    'updatedAt' => (int) strtotime(get_the_modified_date('c')),
                    'createdAt' => (int) strtotime(get_the_date('c')),
                ];

                // Index the page/post data in Typesense
                try {
                    $client->collections[$collection_pages]->documents->create($document);
                } catch (Exception $e) {
                    echo "Error adding page/post to Typesense: " . $e->getMessage() . "\n";
                }
            }
        }

        echo "Pages and posts are added successfully!\n";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}