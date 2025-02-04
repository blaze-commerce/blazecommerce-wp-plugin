<?php

namespace BlazeWooless\Collections;

class Taxonomy extends BaseCollection {
	private static $instance = null;
	public $collection_name = 'taxonomy';

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function get_fields() {
		$fields = array(
			array( 'name' => 'id', 'type' => 'string', 'facet' => true ),
			array( 'name' => 'termId', 'type' => 'string', 'facet' => true ),
			array( 'name' => 'slug', 'type' => 'string', 'facet' => true ),
			array( 'name' => 'name', 'type' => 'string', 'facet' => true, 'infix' => true, 'sort' => true ),
			array( 'name' => 'description', 'type' => 'string' ),
			array( 'name' => 'type', 'type' => 'string', 'facet' => true, 'infix' => true ),
			array( 'name' => 'seoFullHead', 'type' => 'string', 'facet' => true, 'infix' => true ),
			array( 'name' => 'permalink', 'type' => 'string' ),
			array( 'name' => 'updatedAt', 'type' => 'int64' ),
			array( 'name' => 'bannerThumbnail', 'type' => 'string' ),
			array( 'name' => 'bannerText', 'type' => 'string' ),
			array( 'name' => 'parentTerm', 'type' => 'string' ),
			array( 'name' => 'parentSlug', 'type' => 'string', 'facet' => true ),
			array( 'name' => 'productCount', 'type' => 'int64' ),
			array( 'name' => 'order', 'type' => 'int64' ),
			array( 'name' => 'breadcrumbs', 'type' => 'object[]', 'optional' => true ),
			array( 'name' => 'metaData', 'type' => 'object[]', 'optional' => true ),
		);
		return apply_filters( 'blaze_commerce_taxonomy_fields', $fields );
	}

	public function initialize() {
		$collection_taxonomy = $this->collection_name();

		try {
			$this->drop_collection();
		} catch (\Exception $e) {
			// Don't error out if the collection was not found
		}

		$this->create_collection( [ 
			'name' => $collection_taxonomy,
			'fields' => $this->get_fields(),
			'default_sorting_field' => 'updatedAt',
			'enable_nested_fields' => true,
		] );
	}

	public function generate_typesense_data( $term ) {
		$taxonomy = $term->taxonomy;

		// Get the custom fields (bannerThumbnail and bannerText)
		$bannerThumbnail = get_term_meta( $term->term_id, 'wpcf-image', true );
		$bannerText      = get_term_meta( $term->term_id, 'wpcf-term-banner-text', true );
		$order           = get_term_meta( $term->term_id, 'order', true );



		// Get Parent Term
		$parentTerm = get_term( $term->parent, $taxonomy );

		/**
		 * set gb product ingredient image to banneThumbnail. 
		 */
		if ( $taxonomy == 'product_ingredients' && function_exists( 'z_taxonomy_image_url' ) ) {
			$bannerThumbnail = \z_taxonomy_image_url( $term->term_id );
		}

		// Get the thumbnail
		$thumbnail_id = get_term_meta( $term->term_id, 'thumbnail_id', true );
		$attachment   = get_post( $thumbnail_id );

		$thumbnail = [ 
			'id' => $thumbnail_id,
			'title' => $attachment->post_title,
			'altText' => get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ) ?: '',
			'src' => wp_get_attachment_url( $thumbnail_id ) ?: '',
		];

		// Prepare the data to be indexed
		$document = [ 
			'id' => (string) $term->term_id,
			'termId' => (string) $term->term_id,
			'slug' => $term->slug,
			'name' => $term->name,
			'description' => $term->description,
			'type' => $taxonomy,
			'permalink' => wp_make_link_relative( get_term_link( $term ) ),
			'updatedAt' => time(),
			'bannerThumbnail' => (string) $bannerThumbnail,
			'bannerText' => $bannerText,
			'parentTerm' => $parentTerm->name ? $parentTerm->name : '',
			'parentSlug' => $parentTerm->slug ? $parentTerm->slug : '0',
			'productCount' => (int) $term->count,
			'order' => (int) $order,
			'thumbnail' => $thumbnail,
			'breadcrumbs' => $this->generate_breadcrumbs( $term->term_id, $taxonomy ),
			'metaData' => apply_filters( 'blaze_commerce_taxonomy_meta_data', array(), $term->term_id ),
			'seoFullHead' => '',
		];

		return apply_filters( 'blaze_commerce_taxonomy_data', $document, $term );
	}

	public function get_query_args( $page = 1, $batch_size = 50 ) {
		// Add the custom taxonomies to this array
		$taxonomies          = get_taxonomies( array(), 'names' );
		$taxonomies_for_sync = array();
		foreach ( $taxonomies as $taxonomy ) {
			// Skip taxonomies starting with 'ef_'
			if ( preg_match( '/^(ef_|link_category|product_shipping_class|post_format|wp_template_part_area|wp_pattern_category|gblocks_pattern_collections|fb_product_set|wp_theme|elementor|nav_|ml-|ufaq|translation_priority|wpcode_)/', $taxonomy ) ) {
				continue;
			}

			$taxonomies_for_sync[] = $taxonomy;
		}

		$offset = ( $page - 1 ) * $batch_size;
		return apply_filters( 'wooless_taxonomy_query_args', array(
			'taxonomy' => $taxonomies_for_sync,
			'hide_empty' => false,
			'number' => $batch_size,
			'offset' => $offset,
			'orderby' => 'term_id',
			'order' => 'ASC',
		) );
	}


	public function index_to_typesense() {

		$import_logger  = wc_get_logger();
		$import_context = array( 'source' => 'wooless-taxonomy-import' );
		$taxonomy_datas = array();

		try {
			$batch_size      = $_REQUEST['batch_size'] ?? 5;
			$page            = $_REQUEST['page'] ?? 1;
			$imported_count  = $_REQUEST['imported_count'] ?? 0;
			$total_imports   = $_REQUEST['total_imports'] ?? 0;
			$import_response = array();

			if ( $page == 1 ) {
				$this->initialize();
			}

			$query_args = $this->get_query_args( $page, $batch_size );

			$term_query = new \WP_Term_Query( $query_args );

			if ( ! empty( $term_query->terms ) && ! is_wp_error( $term_query->terms ) ) {
				foreach ( $term_query->terms as $term ) {
					$taxonomy_datas[] = $this->generate_typesense_data( $term );
				}

				$import_response = $this->collection()->documents->import( $taxonomy_datas );


				$successful_imports = array_filter( $import_response, function ($batch_result) {
					return isset( $batch_result['success'] ) && $batch_result['success'] == true;
				} );

				$imported_count = count( $successful_imports );
				$total_imports  = count( $taxonomy_datas );
			}



			$next_page          = $page + 1;
			$query_args['page'] = $next_page;
			$term_query         = new \WP_Term_Query( $query_args );
			$has_next_data      = ! empty( $term_query->terms ) && ! is_wp_error( $term_query->terms );



			wp_send_json( array(
				'imported_count' => $imported_count,
				'total_imports' => $total_imports,
				'next_page' => $has_next_data ? $next_page : null,
				'query_args' => $query_args,
				'import_response' => $import_response,
				'import_data_sent' => $taxonomy_datas,
			) );

		} catch (\Exception $e) {
			$import_logger->debug( 'TS Taxonomy collection import Exception: ' . $e->getMessage(), $import_context );
		}
	}

	public function update_typesense_document_on_taxonomy_edit( $term_id, $tt_id, $taxonomy ) {
		// Check if the taxonomy starts with 'ef_'
		if ( strpos( $taxonomy, 'ef_' ) === 0 ) {
			return;
		}

		// Get the term
		$term = get_term( $term_id, $taxonomy );

		if ( ! $term || is_wp_error( $term ) ) {
			return;
		}

		// Prepare the data to be updated
		$document = $this->generate_typesense_data( $term );
		// Update the term data in Typesense
		try {

			$this->upsert( $document );
			do_action( 'blaze_wooless_after_term_update', $document );
		} catch (\Exception $e) {
			error_log( "Error updating term '{$term->name}' in Typesense: " . $e->getMessage() );
		}
	}

	public function generate_breadcrumbs( $term_id, $taxonomy ) {
		$args = array(
			'separator' => '[blz-commerce]',
		);

		// Get Term Parent, Child, and Grand Child 
		$parents_list = get_term_parents_list( $term_id, $taxonomy, $args );

		$parents_list_array = explode( '[blz-commerce]', $parents_list );

		// Removes null values
		$parents_list_clean = array_filter( $parents_list_array, function ($value) {
			return ! is_null( $value ) && $value !== '';
		} );

		$breadcrumbs = array();

		foreach ( $parents_list_clean as $key => $value ) {
			preg_match_all( '#\bhttps?://[^,\s()<>]+(?:\([\w\d]+\)|([^,[:punct:]\s]|/))#', $value, $match );

			$breadcrumbs[] = array(
				'name' => wp_strip_all_tags( $value ),
				'permalink' => wp_make_link_relative( $match[0][0] ),
			);
		}

		return $breadcrumbs;
	}
}