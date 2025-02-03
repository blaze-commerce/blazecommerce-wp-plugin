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

	public function initialize() {
		try {
			$this->drop_collection();
		} catch (\Exception $e) {
			// Don't error out if the collection was not found
		}

		try {
			$this->create_collection( [ 
				'name' => $this->collection_name(),
				'fields' => [ 
					[ 'name' => 'name', 'type' => 'string' ],
					[ 'name' => 'slug', 'type' => 'string', 'facet' => true ],
					[ 'name' => 'seoFullHead', 'type' => 'string', 'optional' => true ],
					[ 'name' => 'permalink', 'type' => 'string' ],
					[ 'name' => 'type', 'type' => 'string', 'facet' => true ],
					[ 'name' => 'thumbnail', 'type' => 'object', 'optional' => true ],
					[ 'name' => 'taxonomies', 'type' => 'object', 'facet' => true, 'optional' => true ],
					[ 'name' => 'updatedAt', 'type' => 'int64' ],
					[ 'name' => 'createdAt', 'type' => 'int64' ],
					[ 'name' => 'publishedAt', 'type' => 'int64', 'optional' => true, 'facet' => true ],
					[ 'name' => 'content', 'type' => 'string', 'optional' => true, 'facet' => true ],
					[ 'name' => 'rawContent', 'type' => 'string', 'optional' => true ],
					[ 'name' => 'author', 'type' => 'object', 'optional' => true ],
					[ 'name' => 'template', 'type' => 'string', 'facet' => true ],
					[ 'name' => 'breadcrumbs', 'type' => 'object[]', 'optional' => true ],
				],
				'default_sorting_field' => 'updatedAt',
				'enable_nested_fields' => true
			] );
		} catch (\Exception $e) {
			echo "Error: " . $e->getMessage() . "\n";
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
				wc_get_page_id( 'cart' ),
				wc_get_page_id( 'checkout' )
			];
			$excluded_pages    = array_merge( $excluded_pages, $woocommerce_pages );
		}

		$excluded_pages = apply_filters( 'blazecommerce/page/excluded_pages', $excluded_pages, $page );

		if ( ! empty( $excluded_pages ) && in_array( $page->ID, $excluded_pages ) ) {
			return null;
		}

		$page_id         = $page->ID;
		$taxonomies_data = $this->get_taxonomies( $page_id, get_post_type() );

		$thumbnail_id = get_post_thumbnail_id( $page_id );
		$thumbnail    = $this->get_thumbnail( $thumbnail_id, $page_id );

		$published_at = strtotime( get_the_date( '', $page_id ) );

		$content                 = $page->post_content;
		$strip_shortcode_content = preg_replace( '#\[[^\]]+\]#', '', $content );
		$page_content            = wp_strip_all_tags( apply_filters( 'the_content', $strip_shortcode_content ) );

		return apply_filters( 'blaze_wooless_page_data_for_typesense', [ 
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
		], $page );
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

	public function get_post_ids( $page, $batch_size = 20 ) {
		global $wpdb;
		// Calculate the offset
		$offset = ( $page - 1 ) * $batch_size;

		// Query to select post IDs from the posts table with pagination
		$query = $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type IN ('post', 'page') AND post_status = 'publish' LIMIT %d OFFSET %d",
			$batch_size,
			$offset
		);

		// Get the results as an array of IDs
		return $wpdb->get_col( $query );
	}

	public function get_total_pages( $batch_size = 20 ) {
		global $wpdb;
		$query       = "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ('post', 'page') AND post_status = 'publish'";
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
		$import_response = $this->collection()->documents->import( $posts_batch );

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

		try {
			$post_ids = $this->get_post_ids( $page, $batch_size );
			if ( ! empty( $post_ids ) ) {

				$post_datas         = $this->prepare_batch_data( $post_ids );
				$successful_imports = $this->import_prepared_batch( $post_datas );

				$imported_count += count( $successful_imports );
				$total_imports += count( $post_datas );
				$total_pages    = $this->get_total_pages( $batch_size );
				$next_page      = $page + 1;
				$has_next_data  = $page < $total_pages;


				wp_send_json( array(
					'imported_count' => $imported_count,
					'total_imports' => $total_imports,
					'next_page' => $has_next_data ? $next_page : null,
					'page' => $page,
					'import_response' => $import_response,
					'import_data_sent' => $post_datas,
				) );

			}



			wp_send_json( array(
				'imported_count' => $imported_count,
				'total_imports' => $total_imports,
				'next_page' => null,
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
					$taxonomies_data[ $taxonomy ][] = [ 
						'name' => $post_term->name,
						'url' => get_term_link( $post_term->term_id ),
						'slug' => $post_term->slug,
					];
				}
			}
			unset( $post_terms );
		}
		unset( $taxonomies );

		return $taxonomies_data;
	}
}
