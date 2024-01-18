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
		if ( \is_plugin_active( 'wordpress-seo/wp-seo.php' ) ) {
			add_filter( 'blaze_wooless_product_data_for_typesense', array( $this, 'add_seo_to_product_schema' ), 10, 2 );
			add_filter( 'blaze_wooless_page_data_for_typesense', array( $this, 'add_seo_to_page_schema' ), 10, 2 );
			add_filter( 'blaze_wooless_additional_homepage_seo_info', array( $this, 'homepage_seo_settings' ), 10, 1 );
		}
	}

	public function add_seo_to_page_schema( $document, $page ) {
		$fullHead = '';

		if ( ! empty( $page->ID ) ) {
			// Generate full seo head
			$meta     = \YoastSEO()->meta->for_post( $page->ID );
			$fullHead = $this->get_full_head( $meta );

			$document['seoFullHead'] = htmlspecialchars( $fullHead, ENT_QUOTES, 'UTF-8' );
		}


		return $document;
	}

	public function add_seo_to_product_schema( $product_data, $product_id ) {
		// Generate full seo head
		$meta     = \YoastSEO()->meta->for_post( $product_id );
		$fullHead = $this->get_full_head( $meta );

		$product_data['seoFullHead'] = htmlspecialchars( $fullHead, ENT_QUOTES, 'UTF-8' );

		return $product_data;
	}

	public function get_full_head( $metaForPost ) {
		if ( $metaForPost !== false ) {
			$head = $metaForPost->get_head();

			return is_string( $head ) ? $head : $head->html;
		}

		return '';
	}

	public function homepage_seo_settings( $additional_settings ) {
		if ( $pageID = get_option( 'page_on_front' ) ) {
			// Generate full seo head
			$meta = \YoastSEO()->meta->for_post( $pageID );

			if ( ! empty( $meta ) ) {
				$fullHead = $this->get_full_head( $meta );

				if ( $fullHead ) {
					$additional_settings['homepage_seo_fullhead'] = htmlspecialchars( $fullHead, ENT_QUOTES, 'UTF-8' );
				}
			}
		}

		return $additional_settings;
	}
}
