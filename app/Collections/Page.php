<?php

namespace BlazeWooless\Collections;

class Page extends BaseCollection {
	private static $instance = null;
	public $collection_name = 'page';

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
				],
				'default_sorting_field' => 'updatedAt',
				'enable_nested_fields' => true
			] );
		} catch (\Exception $e) {
			echo "Error: " . $e->getMessage() . "\n";
		}
	}

	public function get_data( $page ) {
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
			'publishedAt' => $published_at,
			'content' => $page_content,
		], $page );
	}

	public function index_to_typesense() {
		$this->initialize();
		try {
			$args = [ 
				'post_type' => [ 'post', 'page' ],
				'post_status' => 'publish',
				'posts_per_page' => -1,
			];

			$query = new \WP_Query( $args );

			if ( $query->have_posts() ) {
				while ( $query->have_posts() ) {
					$query->the_post();
					global $post;
					$document = $this->get_data( $post );

					// Index the page/post data in Typesense
					try {
						$this->create( $document );
					} catch (\Exception $e) {
						echo "Error adding page/post to Typesense: " . $e->getMessage() . "\n";
					}

					unset( $document );
				}
			}
			// Restore original post data. 
			wp_reset_postdata();

			echo "Pages and posts are added successfully!\n";
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

		return $thumbnail;
	}

	public function get_taxonomies( $post_id, $post_type ) {
		echo "Registered taxonomies for {$post_type}: " . json_encode( get_object_taxonomies( $post_type ) ) . "\n\n";

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