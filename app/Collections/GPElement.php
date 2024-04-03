<?php

namespace BlazeWooless\Collections;

class GPElement extends BaseCollection {
	private static $instance = null;
	public $collection_name = 'page-element';

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
					[ 'name' => 'type', 'type' => 'string', 'facet' => true ],
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
		$page_id = $page->ID;

		$published_at = strtotime( get_the_date( '', $page_id ) );

		$content                 = $page->post_content;
		$strip_shortcode_content = preg_replace( '#\[[^\]]+\]#', '', $content );
		$page_content            = wp_strip_all_tags( apply_filters( 'the_content', $strip_shortcode_content ) );

		return apply_filters( 'blaze_wooless_page_data_for_typesense', [ 
			'id' => (string) $page_id,
			'name' => $page->post_title,
			'type' => $page->post_type,
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
				'post_type' => [ 'gp_elements' ],
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
						echo "Error adding gp_elements to Typesense: " . $e->getMessage() . "\n";
					}

					unset( $document );
				}
			}
			// Restore original post data. 
			wp_reset_postdata();

			echo "Gutenber Elements are added successfully!\n";
		} catch (\Exception $e) {
			echo "Error: " . $e->getMessage() . "\n";
		}
	}

}