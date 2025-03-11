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

		$new_domain = sprintf( 'https://%s.s3.%s.amazonaws.com', $settings['bucket'], $settings['region'] );
		$page['rawContent'] = $this->replace_urls( $page['rawContent'], $new_domain );

		return $page;
	}

	private function replace_urls( $content, $new_domain ) {
		$patterns = [
			'/<img[^>]+src=[\'"]([^\'"]+)[\'"]/i',
			'/url":"(https?:\/\/[^"]*\/wp-content\/uploads\/[^"]+)"|url\'\'(https?:\/\/[^"]*\/wp-content\/uploads\/[^"]+)\'/i'
		];

		foreach ( $patterns as $pattern ) {
			$content = preg_replace_callback( $pattern, function( $matches ) use ( $new_domain ) {
				$url = $matches[1] ?? $matches[2];
				if ( strpos( $url, '/wp-content/uploads' ) !== false ) {
					$updated_url = preg_replace( '/^https?:\/\/[^\/]+/', $new_domain, $url );
					return str_replace( $url, $updated_url, $matches[0] );
				}
				return $matches[0];
			}, $content );
		}

		return $content;
	}
}