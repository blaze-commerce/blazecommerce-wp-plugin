<?php

namespace BlazeWooless\Extensions;

class YoastSEO {
	private static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		if ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'wordpress-seo/wp-seo.php' ) ) {
			add_filter( 'blaze_wooless_product_data_for_typesense', array( $this, 'add_seo_to_product_schema' ), 10, 2 );
			add_filter( 'blazecommerce/collection/page/typesense_data', array( $this, 'add_seo_to_page_schema' ), 10, 2 );
			add_filter( 'blaze_wooless_additional_homepage_seo_info', array( $this, 'homepage_seo_settings' ), 10, 1 );
			add_filter( 'blaze_commerce_taxonomy_data', array( $this, 'add_taxonomy_head' ), 10, 2 );
			add_theme_support( 'title-tag' );
		}
	}

	public function add_seo_to_page_schema( $document, $page ) {

		if ( ! empty( $page->ID ) ) {
			$meta                    = \YoastSEO()->meta->for_post( $page->ID );
			$document['seoFullHead'] = $this->get_full_head( $meta );
		}


		return $document;
	}

	public function add_seo_to_product_schema( $product_data, $product_id ) {
		$meta                        = \YoastSEO()->meta->for_post( $product_id );
		$product_data['seoFullHead'] = $this->get_full_head( $meta );

		return $product_data;
	}

	public function get_full_head( $metaForPost ) {
		if ( $metaForPost !== false ) {
			$head = $metaForPost->get_head();

			$final_head = is_string( $head ) ? $head : $head->html;
			$final_head = htmlspecialchars( $final_head, ENT_QUOTES, 'UTF-8' );
			return $final_head;
		}

		return '';
	}

	public function homepage_seo_settings( $additional_settings ) {
		if ( $pageID = get_option( 'page_on_front' ) ) {
			// Generate full seo head
			$meta = \YoastSEO()->meta->for_post( $pageID );

			if ( ! empty( $meta ) ) {
				$additional_settings['homepage_seo_fullhead'] = $this->get_full_head( $meta );
			}
		}

		return $additional_settings;
	}

	public function add_taxonomy_head( $document, $term ) {

		$yoastMeta               = \YoastSEO()->meta->for_term( $term->term_id );
		$termHead                = $yoastMeta && method_exists( $yoastMeta, 'get_head' ) ? $yoastMeta->get_head() : '';
		$document['seoFullHead'] = is_string( $termHead ) ? $termHead : ( isset( $termHead->html ) ? $termHead->html : '' );

		return $document;
	}
}
