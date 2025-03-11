<?php

namespace BlazeWooless\Extensions;

class OffloadMedia {
	private static $instance = null;

	public static function get_instance() {

	public function __construct() {
		if ( $this->is_plugin_active() ) {
			if ( has_filter( 'blazecommerce/collection/page/typesense_data' ) ) {
				add_filter( 'blaze_wooless_page_data_for_typesense', [ $this, 'page_raw_content_url' ], 10, 1 );
			} else {
				add_filter( 'blaze_wooless_page_data_for_typesense', [ $this, 'page_raw_content_url' ], 10, 1 );
			}
		}
	}

	public function is_plugin_active() {
		return function_exists( 'is_plugin_active' ) && is_plugin_active( 'amazon-s3-and-cloudfront-pro/amazon-s3-and-cloudfront-pro.php' );
	}

	public function __construct() {
		if ( $this->is_plugin_active() ) {
			add_filter( 'blazecommerce/collection/page/typesense_data', array( $this, 'page_raw_content_url' ), 10, 1 );
		}
	}

	public function page_raw_content_url( $page ) {
		// Check if AS3CF_SETTINGS is defined
		if (!defined('AS3CF_SETTINGS')) {
			// Handle the case where AS3CF_SETTINGS is not defined
			return $page; // or you can throw an exception or log an error
		}

		// Extract the settings from the AS3CF_SETTINGS constant
		$settings = unserialize( AS3CF_SETTINGS );
			'/url":"(https?:\/\/[^"]*\/wp-content\/uploads\/[^"]+)"|url\'\'(https?:\/\/[^"]*\/wp-content\/uploads\/[^"]+)\'/i'
				if ( strpos( $url, '/wp-content/uploads' ) !== false ) {
					$updated_url = preg_replace( '/^https?:\/\/[^\/]+/', $new_domain, $url );
					return str_replace( $url, $updated_url, $matches[0] );
				}
				return $matches[0]; // Return unchanged if the condition is not met
			}, $page['rawContent']);
		}

		return $page;
	}
}