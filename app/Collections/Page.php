<?php

namespace BlazeWooless\Collections;

class Page extends BaseCollection {
	private static $instance = null;
	public $collection_name = 'page';

	const BATCH_SIZE = 5;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function get_fields() {
		$fields = array(
			array( 'name' => 'name', 'type' => 'string' ),
			array( 'name' => 'slug', 'type' => 'string', 'facet' => true ),
			array( 'name' => 'seoFullHead', 'type' => 'string', 'optional' => true ),
			array( 'name' => 'permalink', 'type' => 'string', 'facet' => true ),
			array( 'name' => 'type', 'type' => 'string', 'facet' => true ),
			array( 'name' => 'thumbnail', 'type' => 'object', 'optional' => true ),
			array( 'name' => 'taxonomies', 'type' => 'object[]', 'facet' => true, 'optional' => true ),
			array( 'name' => 'taxonomies.name', 'type' => 'string[]', 'facet' => true, 'optional' => true ),
			array( 'name' => 'taxonomies.termId', 'type' => 'string[]', 'facet' => true, 'optional' => true ),
			array( 'name' => 'taxonomies.url', 'type' => 'string[]', 'optional' => true ),
			array( 'name' => 'taxonomies.type', 'type' => 'string[]', 'facet' => true, 'optional' => true ),
			array( 'name' => 'taxonomies.slug', 'type' => 'string[]', 'facet' => true, 'optional' => true ),
			array( 'name' => 'updatedAt', 'type' => 'int64' ),
			array( 'name' => 'createdAt', 'type' => 'int64' ),
			array( 'name' => 'publishedAt', 'type' => 'int64', 'optional' => true, 'facet' => true ),
			array( 'name' => 'content', 'type' => 'string', 'optional' => true, 'facet' => true ),
			array( 'name' => 'rawContent', 'type' => 'string', 'optional' => true ),
			array( 'name' => 'author', 'type' => 'object', 'optional' => true ),
			array( 'name' => 'template', 'type' => 'string', 'facet' => true ),
			array( 'name' => 'breadcrumbs', 'type' => 'object[]', 'optional' => true ),
		);

		return apply_filters( 'blazecommerce/collection/page/typesense_fields', $fields );
	}

	public function initialize() {
		$logger  = wc_get_logger();
		$context = array( 'source' => 'wooless-page-collection-initialize' );

		// Check if we should use the new alias system
		$use_aliases = apply_filters( 'blazecommerce/use_collection_aliases', true );

		if ( $use_aliases ) {
			try {
				$schema = array(
					'fields' => $this->get_fields(),
					'default_sorting_field' => 'updatedAt',
					'enable_nested_fields' => true
				);

				$new_collection_name = $this->initialize_with_alias( $schema );
				$logger->debug( 'TS Page collection (alias): ' . $new_collection_name, $context );

				// Note: initialize_with_alias() now automatically stores the active sync collection

			} catch (\Exception $e) {
				$logger->debug( 'TS Page collection alias initialize Exception: ' . $e->getMessage(), $context );
				throw $e;
			}
		} else {
			// Legacy behavior
			try {
				$this->drop_collection();
			} catch (\Exception $e) {
				// Don't error out if the collection was not found
			}

			try {
				$logger->debug( 'TS Page collection: ' . $this->collection_name(), $context );
				$this->create_collection( [ 
					'name' => $this->collection_name(),
					'fields' => $this->get_fields(),
					'default_sorting_field' => 'updatedAt',
					'enable_nested_fields' => true
				] );
			} catch (\Exception $e) {
				$logger->debug( 'TS Page collection initialize Exception: ' . $e->getMessage(), $context );
				echo "Error: " . $e->getMessage() . "\n";
			}
		}
	}



	public function get_author( $author_id ) {
		// Check if the author exists by ID
		if ( ! get_user_by( 'ID', $author_id ) ) {
			return null;
		}

		return array(
			'id' => $author_id,
			'displayName' => get_the_author_meta( 'display_name', $author_id ),
			'firstName' => get_the_author_meta( 'first_name', $author_id ),
			'lastName' => get_the_author_meta( 'last_name', $author_id )
		);
	}

	public function get_template( $page ) {
		$template = get_page_template_slug( $page->ID );
		if ( empty( $template ) ) {
			$template = 'page';
		}

		// empty template if home page and other woocommerce pages
		$front_page_id = get_option( 'page_on_front' );
		$home_page_id  = get_option( 'page_for_posts' );

		if ( $page->ID == $front_page_id || $page->ID == $home_page_id ) {
			$template = '';
		}


		return apply_filters( 'blazecommerce/page/template', $template, $page );
	}


	public function get_data( $page ) {

		$excluded_pages = array();
		if ( function_exists( 'wc_get_page_id' ) ) {
			$woocommerce_pages = [ 
				wc_get_page_id( 'myaccount' ),
				wc_get_page_id( 'checkout' )
			];
			$excluded_pages    = array_merge( $excluded_pages, $woocommerce_pages );
		}

		$excluded_pages = apply_filters( 'blazecommerce/page/excluded_pages', $excluded_pages, $page );

		if ( ! empty( $excluded_pages ) && in_array( $page->ID, $excluded_pages ) ) {
			return null;
		}

		$exclude_page = apply_filters( 'blazecommerce/settings/sync/page/exclude_page', false, $page );
		if ( $exclude_page ) {
			return null;
		}

		$page_id         = $page->ID;
		$taxonomies_data = $this->get_taxonomies( $page_id, $page->post_type );

		$thumbnail_id = get_post_thumbnail_id( $page_id );
		$thumbnail    = $this->get_thumbnail( $thumbnail_id, $page_id );

		$published_at = strtotime( get_the_date( '', $page_id ) );

		$content                 = $page->post_content;
		$strip_shortcode_content = preg_replace( '#\[[^\]]+\]#', '', $content );
		$page_content            = wp_strip_all_tags( apply_filters( 'the_content', $strip_shortcode_content ) );

		$data = array(
			'id' => (string) $page_id,
			'slug' => $page->post_name,
			'name' => $page->post_title,
			'type' => $page->post_type,
			'permalink' => wp_make_link_relative( get_permalink( $page_id ) ),
			'taxonomies' => $taxonomies_data,
			'thumbnail' => $thumbnail,
			'updatedAt' => (int) strtotime( get_the_modified_date( 'c', $page_id ) ),
			'createdAt' => (int) strtotime( get_the_date( 'c', $page_id ) ),
			'publishedAt' => (int) $published_at,
			'content' => $page_content,
			'rawContent' => $content,
			'seoFullHead' => '',
			'author' => $this->get_author( $page->post_author ),
			'template' => $this->get_template( $page ),
			'breadcrumbs' => $this->get_breadcrumbs( $page )
		);

		return apply_filters( 'blazecommerce/collection/page/typesense_data', $data, $page );
	}

	public function get_breadcrumbs( $page ) {
		// Initialize an array for the breadcrumb trail
		$breadcrumbs = array(
			array(
				'title' => 'Home',
				'url' => site_url()
			)
		);

		if ( 'post' == $page->post_type ) {
			$blog_page = get_option( 'page_for_posts' );
			if ( ! empty( $blog_page ) ) {
				$breadcrumbs[] = array(
					'title' => get_the_title( $blog_page ),
					'url' => get_permalink( $blog_page )
				);
			}
		}

		$ancestors = get_post_ancestors( $page );

		// Reverse the order so the breadcrumbs go from parent to child
		$ancestors = array_reverse( $ancestors );

		foreach ( $ancestors as $ancestor ) {
			$breadcrumbs[] = array(
				'title' => get_the_title( $ancestor ),
				'url' => get_permalink( $ancestor )
			);
		}

		// Add the current page title without a URL
		$breadcrumbs[] = array(
			'title' => get_the_title( $page ),
			'url' => null
		);

		return $breadcrumbs;
	}

	public function get_syncable_post_types() {
		return apply_filters( 'blazecommerce/collection/syncable_post_types', array(
			'post',
			'page'
		) );
	}

	public function get_post_type_in_query() {
		return "'" . implode( "', '", $this->get_syncable_post_types() ) . "'";
	}

	public function get_post_ids( $page, $batch_size = 20 ) {
		global $wpdb;
		// Calculate the offset
		$offset = ( $page - 1 ) * $batch_size;

		$post_types = $this->get_post_type_in_query();
		// Query to select post IDs from the posts table with pagination
		$query = $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type IN ({$post_types}) AND post_status = 'publish' LIMIT %d OFFSET %d",
			$batch_size,
			$offset
		);

		// Get the results as an array of IDs
		return $wpdb->get_col( $query );
	}

	public function get_total_pages( $batch_size = 20 ) {
		global $wpdb;
		$post_types  = $this->get_post_type_in_query();
		$query       = "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ({$post_types}) AND post_status = 'publish'";
		$total_posts = $wpdb->get_var( $query );
		$total_pages = ceil( $total_posts / $batch_size );
		return $total_pages;
	}

	public function prepare_batch_data( $post_ids ) {
		$post_datas = array();
		if ( empty( $post_ids ) ) {
			return $post_datas;
		}

		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );
			if ( $post ) {
				$document = $this->get_data( $post );
				if ( ! empty( $document ) ) {
					$post_datas[] = $document;
				}
				unset( $document );
			}

		}
		// Restore original post data.
		wp_reset_postdata();
		wp_reset_query();

		return $post_datas;
	}



	public function import_prepared_batch( $posts_batch ) {
		$import_response = $this->import( $posts_batch );

		$successful_imports = array_filter( $import_response, function ($batch_result) {
			return isset( $batch_result['success'] ) && $batch_result['success'] == true;
		} );

		return $successful_imports;
	}

	public function index_to_typesense() {
		$batch_size      = $_REQUEST['batch_size'] ?? 20;
		$page            = $_REQUEST['page'] ?? 1;
		$imported_count  = $_REQUEST['imported_count'] ?? 0;
		$total_imports   = $_REQUEST['total_imports'] ?? 0;
		$import_response = array();

		$post_datas = array();
		if ( 1 == $page ) {
			$this->initialize();
		}

		// the settings to not sync all pageAndPost. Set to false so that no pageAndPost syncs happen
		$should_sync = apply_filters( 'blazecommerce/settings/sync/pageAndPost', true );
		if ( ! $should_sync ) {
			// This prevents syncing all pageAndPost
			wp_send_json( array(
				'imported_count' => 0,
				'total_imports' => 0,
				'next_page' => null,
				'page' => 1,
				'import_response' => [],
				'import_data_sent' => [],
			) );
		}

		try {
			$post_ids = $this->get_post_ids( $page, $batch_size );
			if ( ! empty( $post_ids ) ) {

				$post_datas = $this->prepare_batch_data( $post_ids );
				if ( ! empty( $post_datas ) ) {
					$successful_imports = $this->import_prepared_batch( $post_datas );
					$imported_count += count( $successful_imports );
				}

				$total_imports += count( $post_datas );
			}

			$total_pages   = $this->get_total_pages( $batch_size );
			$next_page     = $page + 1;
			$has_next_data = $page < $total_pages;

			// Complete the sync if using aliases and this is the final page
			if ( ! $has_next_data ) {
				$use_aliases = apply_filters( 'blazecommerce/use_collection_aliases', true );
				if ( $use_aliases && isset( $this->active_sync_collection ) ) {
					$logger      = wc_get_logger();
					$context     = array( 'source' => 'wooless-page-collection-complete' );
					$sync_result = $this->complete_collection_sync();
					$logger->debug( 'TS Page sync result: ' . json_encode( $sync_result ), $context );
				}
			}

			wp_send_json( array(
				'imported_count' => $imported_count,
				'total_imports' => $total_imports,
				'next_page' => $has_next_data ? $next_page : null,
				'page' => $page,
				'import_response' => $import_response,
				'import_data_sent' => $post_datas,
			) );

		} catch (\Exception $e) {
			echo "Error: " . $e->getMessage() . "\n";
		}
	}

	public function get_thumbnail( $thumbnail_id, $page_id ) {
		// Initialize empty Thumbnail
		$thumbnail = [];
		if ( ! empty( $thumbnail_id ) ) {
			$attachment = get_post( $thumbnail_id );

			$thumbnail = [ 
				'id' => $thumbnail_id,
				'title' => is_object( $attachment ) ? $attachment->post_title : '',
				'altText' => get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ),
				'src' => get_the_post_thumbnail_url( $page_id ),
			];
		}

		// If there is no featured image, get the first image attachment from the post content
		$content  = get_the_content();
		$image_id = '';
		$output   = preg_match_all( '/image="(.*?)"/m', $content, $matches );
		if ( ! empty( $matches[1][0] ) ) {
			$image_id = $matches[1][0];
		}

		// Use the first image found in the post content
		if ( ! empty( $image_id ) && $image_src = wp_get_attachment_image_src( $image_id, 'full' ) ) {
			$attachment = get_post( $image_id );
			$thumbnail  = [ 
				'id' => $image_id,
				'title' => is_object( $attachment ) ? $attachment->post_title : '',
				'altText' => get_post_meta( $image_id, '_wp_attachment_image_alt', true ),
				'src' => $image_src[0],
			];

			if ( empty( $thumbnail['altText'] ) ) {
				$thumbnail['altText'] = $attachment->post_title;
			}
		}

		return $thumbnail;
	}

	public function get_taxonomy_item( $term ) {
		return apply_filters( 'blazecommerce/collection/page/taxonomy_item', array(
			'name' => $term->name,
			'termId' => (string) $term->term_id,
			'url' => get_term_link( $term->term_id ),
			'type' => $term->taxonomy,
			'slug' => $term->slug,
		) );
	}

	public function get_taxonomies( $post_id, $post_type ) {

		$taxonomies_data = [];
		$taxonomies      = get_object_taxonomies( $post_type );

		foreach ( $taxonomies as $taxonomy ) {
			// Exclude taxonomies based on their names
			if ( preg_match( '/^(ef_|elementor|pa_|nav_|ml-|ufaq|product_visibility|translation_priority|wpcode_|following_users|post_format|post_status)/', $taxonomy ) ) {
				continue;
			}

			$post_terms = get_the_terms( $post_id, $taxonomy );

			if ( ! empty( $post_terms ) && ! is_wp_error( $post_terms ) ) {
				foreach ( $post_terms as $post_term ) {
					$taxonomies_data[] = $this->get_taxonomy_item( $post_term );
				}
			}
			unset( $post_terms );
		}
		unset( $taxonomies );

		return $taxonomies_data;
	}
}
