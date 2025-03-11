<?php

namespace BlazeWooless\Extensions;

class OffloadMedia {
	private static $instance = null;

	public static function get_instance() {
		return self::$instance ?? (self::$instance = new self());
	}

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

	public function page_raw_content_url( $page ) {
		if ( ! defined( 'AS3CF_SETTINGS' ) ) {
			return $page;
		}

		$settings = unserialize( AS3CF_SETTINGS );
		if ( empty( $settings['bucket'] ) || empty( $settings['region'] ) ) {
			return $page;
		}

		// Apply the as3cf_filter_post_local_to_provider filter
		$page['rawContent'] = apply_filters( 'as3cf_filter_post_local_to_provider', $page['rawContent'] );

		return $page;
	}
}