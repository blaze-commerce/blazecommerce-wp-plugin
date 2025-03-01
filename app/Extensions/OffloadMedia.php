<?php

namespace BlazeWooless\Extensions;

class OffloadMedia {
	private static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function is_plugin_active() {
		return function_exists( 'is_plugin_active' ) && is_plugin_active( 'xanda-offload-media-extender/xanda-offload-media-extender.php' );
	}

	public function __construct() {
		if ( $this->is_plugin_active() ) {
			add_filter( 'blazecommerce/collection/page/typesense_data', array( $this, 'page_raw_content_url' ), 10, 1 );
		}
	}

	public function page_raw_content_url( $page ) {
		// Extract the settings from the AS3CF_SETTINGS constant
		$settings = unserialize(AS3CF_SETTINGS);

		// Check if bucket and region are set and not empty
		if (!empty($settings['bucket']) && !empty($settings['region'])) {
			$new_domain = 'https://' . $settings['bucket'] . '.s3.' . $settings['region'] . '.amazonaws.com';

			$pattern = '/<img[^>]+src=[\'"]([^\'"]+)[\'"]/i';
			
			// Replace the domain in img src attributes only if it contains /wp-content/uploads
			$page['rawContent'] = preg_replace_callback($pattern, function($matches) use ($new_domain) {
				$url = $matches[1];
				if (strpos($url, '/wp-content/uploads') !== false) {
					$updated_url = preg_replace('/^https?:\/\/[^\/]+/', $new_domain, $url);
					return str_replace($url, $updated_url, $matches[0]);
				}
				return $matches[0]; // Return unchanged if the condition is not met
			}, $page['rawContent']);
		}

		return $page;
	}
}