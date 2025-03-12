<?php

namespace BlazeWooless\Extensions;

class RankMath {
	private static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		if ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'seo-by-rank-math/rank-math.php' ) ) {
			add_filter( 'blaze_wooless_product_data_for_typesense', array( $this, 'add_seo_to_product_schema' ), 10, 3 );
			add_filter( 'blazecommerce/collection/page/typesense_data', array( $this, 'add_seo_to_page_schema' ), 10, 2 );
			add_filter( 'blaze_wooless_additional_homepage_seo_info', array( $this, 'homepage_seo_settings' ), 10, 1 );
			add_filter( 'blaze_commerce_taxonomy_data', array( $this, 'add_taxonomy_head' ), 10, 2 );
		}

	}

	public function add_seo_to_page_schema( $document, $page ) {
		if ( ! empty( $page->ID ) ) {
			$document['seoFullHead'] = $this->get_full_head( get_permalink( $page->ID ) );
		}

		return $document;
	}



	public function add_seo_to_product_schema( $product_data, $product_id, $product ) {
		$product_data['seoFullHead'] = '';
		if ( ! $product->is_type( 'variation' ) ) {
			$product_data['seoFullHead'] = $this->get_full_head( get_permalink( $product_id ) );
		}
		return $product_data;

	}

	public function homepage_seo_settings( $additional_settings ) {
		$additional_settings['homepage_seo_fullhead'] = $this->get_full_head( home_url() );
		return $additional_settings;
	}

	public function add_taxonomy_head( $document, $term ) {
		/**
		 * This is useful when someone created a custom taxonomies and it should have an seoFullhead in the front end. 
		 */
		$taxonomies_with_seo     = apply_filters( 'wooless_taxonomies_with_seo', array(
			'product_cat',
			'category',
			'post_tag',
			'product_tag'
		) );
		$document['seoFullHead'] = in_array( $document['type'], $taxonomies_with_seo ) ? $this->get_full_head( get_term_link( $term ) ) : '';

		return $document;
	}

	public function get_full_head( $url ) {
		$home_url     = home_url();
		$curl         = curl_init();
		$url          = urlencode( $url );
		$curl_options = array(
			CURLOPT_URL => "{$home_url}/wp-json/rankmath/v1/getHead?url={$url}",
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'GET',
			CURLOPT_HTTPHEADER => array(
				'Content-Type: application/json'
			),
		);



		curl_setopt_array( $curl, $curl_options );

		$response = curl_exec( $curl );
		curl_close( $curl );
		$response = json_decode( $response, true );

		if ( ! empty( $response['success'] ) && ! empty( $response['head'] ) ) {
			return htmlspecialchars( $response['head'], ENT_QUOTES, 'UTF-8' );
		}

		return '';
	}


}
