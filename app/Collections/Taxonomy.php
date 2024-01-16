<?php

namespace BlazeWooless\Collections;

class Taxonomy extends BaseCollection
{
	private static $instance = null;
	public $collection_name = 'taxonomy';

	public static function get_instance()
	{ 
		if (self::$instance === null) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function initialize()
	{
		// Fetch the store ID from the saved options
		$wooless_site_id = get_option('store_id');
		$collection_taxonomy = 'taxonomy-' . $wooless_site_id;

		try {
			$this->drop_collection();
		} catch (\Exception $e) {
			// Don't error out if the collection was not found
		}

		$this->create_collection([
			'name' => $collection_taxonomy,
			'fields' => [
				['name' => 'id', 'type' => 'string', 'facet' => true],
				['name' => 'slug', 'type' => 'string', 'facet' => true],
				['name' => 'name', 'type' => 'string', 'facet' => true, 'infix' => true, 'sort' => true],
				['name' => 'description', 'type' => 'string'],
				['name' => 'type', 'type' => 'string', 'facet' => true, 'infix' => true],
				['name' => 'seoFullHead', 'type' => 'string', 'facet' => true, 'infix' => true],
				['name' => 'permalink', 'type' => 'string'],
				['name' => 'updatedAt', 'type' => 'int64'],
				['name' => 'bannerThumbnail', 'type' => 'string'],
				['name' => 'bannerText', 'type' => 'string'],
				['name' => 'parentTerm', 'type' => 'string'],
				['name' => 'breadcrumbs', 'type' => 'object[]', 'optional' => true],
				['name' => 'metaData', 'type' => 'object[]', 'optional' => true],
			],
			'default_sorting_field' => 'updatedAt',
			'enable_nested_fields' => true,
		]);
	}

	public function generate_typesense_data($term)
	{
		$taxonomy = $term->taxonomy;

		// Get the custom fields (bannerThumbnail and bannerText)
		$bannerThumbnail = get_term_meta($term->term_id, 'wpcf-image', true);
		$bannerText = get_term_meta($term->term_id, 'wpcf-term-banner-text', true);


		$yoastMeta = is_plugin_active('wordpress-seo/wp-seo.php') ? \YoastSEO()->meta->for_term($term->term_id) : [];
		$termHead = is_object($yoastMeta) ? $yoastMeta->get_head() : '';
		$seoFullHead = is_string($termHead) ? $termHead : (isset($termHead->html) ? $termHead->html : '');

		// Get Parent Term
		$parentTerm = get_term($term->parent, $taxonomy);

		/**
		 * set gb product ingredient image to banneThumbnail. 
		 */
		if ($taxonomy == 'product_ingredients' && function_exists('z_taxonomy_image_url')) {
			$bannerThumbnail = \z_taxonomy_image_url($term->term_id);
		}

		// Get the thumbnail
		$thumbnail_id = get_term_meta($term->term_id, 'thumbnail_id', true);
		$attachment = get_post($thumbnail_id);

		$thumbnail = [
			'id' => $thumbnail_id,
			'title' => $attachment->post_title,
			'altText' => get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true),
			'src' => wp_get_attachment_url($thumbnail_id),
		];

		// Prepare the data to be indexed
		$document = [
			'id' => (string) $term->term_id,
			'slug' => $term->slug,
			'name' => $term->name,
			'description' => $term->description,
			'type' => $taxonomy,
			'permalink' => wp_make_link_relative(get_term_link($term)),
			'seoFullHead' => $seoFullHead,
			'updatedAt' => time(),
			'bannerThumbnail' => (string) $bannerThumbnail,
			'bannerText' => $bannerText,
			'parentTerm' => $parentTerm->name ? $parentTerm->name : '',
			'thumbnail' => $thumbnail,
			'breadcrumbs' => $this->generate_breadcrumbs($term->term_id, $taxonomy),
			'metaData'	=> apply_filters('blaze_commerce_taxonomy_meta_data', array(), $term->term_id),
		];

		return $document;
	}

	public function index_to_typesense()
	{
		$logger = wc_get_logger();
		$context = array('source' => 'wooless-taxonomy-collection-initialize');

		$import_logger = wc_get_logger();
		$import_context = array('source' => 'wooless-taxonomy-import');


		//indexing taxonmy terms
		try {
			$this->initialize();

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
						// Prepare the data to be indexed
						$document = $this->generate_typesense_data($term);

						// Index the term data in Typesense
						try {
							$result = $this->create($document);
							$successful_imports = array_filter($result, function ($batch_result) {
								return isset($batch_result['success']) && $batch_result['success'] == true;
							});
							$import_logger->debug('TS Taxonomy Import result: ' . print_r($result, 1), $import_context);
						} catch (\Exception $e) {
							$logger->debug('TS Taxonomy Import Exception: ' . $e->getMessage(), $context);

							echo "Error adding term '{$term->name}' to Typesense: " . $e->getMessage() . "\n";
						}
					}
				}

				unset($terms);
			}

			unset($taxonomies);

			echo "taxonomy added successfully!\n";
		} catch (\Exception $e) {
			$logger->debug('TS Taxonomy collection intialize Exception: ' . $e->getMessage(), $context);

			echo "Error: " . $e->getMessage() . "\n";
		}
	}

	public function update_typesense_document_on_taxonomy_edit($term_id, $tt_id, $taxonomy)
	{
		// Check if the taxonomy starts with 'ef_'
		if (strpos($taxonomy, 'ef_') === 0) {
			return;
		}

		// Get the term
		$term = get_term($term_id, $taxonomy);

		if (!$term || is_wp_error($term)) {
			return;
		}

		// Prepare the data to be updated
		$document = $this->generate_typesense_data($term);
		// Update the term data in Typesense
		try {

			$this->upsert($document);
			do_action('blaze_wooless_after_term_update', $document);
		} catch (\Exception $e) {
			error_log("Error updating term '{$term->name}' in Typesense: " . $e->getMessage());
		}
	}

	public function generate_breadcrumbs($term_id, $taxonomy) {
		$args = array(
			'separator' => '[blz-commerce]',
		);

		// Get Term Parent, Child, and Grand Child 
		$parents_list = get_term_parents_list( $term_id, $taxonomy, $args );

		$parents_list_array = explode('[blz-commerce]', $parents_list);

		// Removes null values
		$parents_list_clean = array_filter($parents_list_array, function($value) { return !is_null($value) && $value !== ''; });

		$breadcrumbs = array();

		foreach($parents_list_clean as $key=>$value) {
			preg_match_all('#\bhttps?://[^,\s()<>]+(?:\([\w\d]+\)|([^,[:punct:]\s]|/))#', $value, $match);

			$breadcrumbs[] = array(
				'name' => wp_strip_all_tags($value),
				'permalink' => wp_make_link_relative($match[0][0]),
			);
		}

		return $breadcrumbs;
	}
}