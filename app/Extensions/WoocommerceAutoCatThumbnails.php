<?php

namespace BlazeWooless\Extensions;

class WoocommerceAutoCatThumbnails {
	public static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		if ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'woocommerce-auto-category-thumbnails/woocommerce-auto-cat-thumbnails.php' ) ) {
			add_filter( 'blaze_commerce_taxonomy_data', array( $this, 'set_default_taxonomy_thumbnail' ), 10, 2 );
		}
	}

	public function set_default_taxonomy_thumbnail( $taxonomy_data, $term ) {
		// If there's no thumbnail then we will get the thumbnail from the first product it hits
		if ( ! get_woocommerce_term_meta( $term->term_id, 'thumbnail_id', true ) ) {
			$query_args = array(
				'post_status' => 'publish',
				'post_type' => 'product',
				'posts_per_page' => 1,
				'tax_query' => array(
					'relation' => 'AND',
					array(
						'field' => 'id',
						'taxonomy' => 'product_cat',
						'terms' => $term->term_id
					),
					array(
						'field' => 'slug',
						'taxonomy' => 'product_visibility',
						'terms' => 'exclude-from-catalog',
						'operator' => 'NOT IN',
					),
				)
			);

			$wcact_settings = get_option( 'wcact_settings' );

			//Random or latest image?
			$query_args['orderby'] = $wcact_settings['orderby'];

			//Query DB
			$products = get_posts( $query_args );

			//If matching product found, check for a thumbnail, otherwise fall back
			if ( $products && has_post_thumbnail( $products[0]->ID ) ) {
				$thumbnail_id = get_post_thumbnail_id( $products[0]->ID );
				$attachment = get_post( $thumbnail_id );

				$taxonomy_data['thumbnail'] = array(
					'id' => (string) $thumbnail_id,
					'title' => $attachment->post_title,
					'altText' => get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ),
					'src' => wp_get_attachment_url( $thumbnail_id ),
				);
			}
		}

		return $taxonomy_data;
	}
}