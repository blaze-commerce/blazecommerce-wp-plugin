<?php
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
                ['name' => 'type', 'type' => 'string', 'facet' => true, 'infix' => true],
                ['name' => 'seoFullHead', 'type' => 'string', 'facet' => true, 'infix' => true],
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
            if (preg_match('/^(ef_|elementor|pa_|nav_|ml-|ufaq|translation_priority|wpcode_)/', $taxonomy)) {
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
                    $yoastMeta = YoastSEO()->meta->for_term($term->term_id);
                    $termHead = is_object($yoastMeta) ? $yoastMeta->get_head() : '';
                    $termHeadString = is_string($termHead) ? $termHead : (isset($termHead->html) ? $termHead->html : '');

                    $selFullHead = is_string($termHead) ? $termHead : $termHead->html;

                    // Prepare the data to be indexed
                    $document = [
                        'slug' => $term->slug,
                        'name' => $term->name,
                        'description' => $term->description,
                        'type' => $taxonomy,
                        'permalink' => get_term_link($term),
                        'seoFullHead' => $selFullHead,
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
        'type' => $taxonomy,
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