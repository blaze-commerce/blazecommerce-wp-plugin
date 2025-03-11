<?php

namespace BlazeWooless\Extensions;

class OffloadMedia {
	private static $instance = null;

	/**
	 * Get the singleton instance
	 */
	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Check if WP Offload Media is enabled
	 */
	public static function is_offload_media_enabled() {
		return defined( 'AS3CF_SETTINGS' ) && function_exists( 'is_plugin_active' ) && is_plugin_active( 'amazon-s3-and-cloudfront-pro/amazon-s3-and-cloudfront-pro.php' );
	}

	/**
	 * OffloadMedia constructor: Adds the filter if the plugin is active.
	 */
	public function __construct() {
		if ( ! self::is_offload_media_enabled() ) {
			return; // Exit early if Offload Media is not available
		}

		add_filter( 'blazecommerce/collection/page/typesense_data', array( $this, 'page_raw_content_url' ), 10, 1 );
	}

	/**
	 * Modify raw content to replace media URLs with versioned S3 URLs.
	 */
	public function page_raw_content_url( $page ) {
		if ( ! self::is_offload_media_enabled() ) {
			return $page; // Skip processing if Offload Media is not enabled
		}

		// Extract the settings from the AS3CF_SETTINGS constant
		$settings = unserialize( AS3CF_SETTINGS );

		// Check if bucket and region are set and not empty
		if ( ! empty( $settings['bucket'] ) && ! empty( $settings['region'] ) ) {
			$new_domain = 'https://' . $settings['bucket'] . '.s3.' . $settings['region'] . '.amazonaws.com';

			// Pattern to match img src attributes
			$img_pattern = '/<img[^>]+src=[\'"]([^\'"]+)[\'"]/i';
			// Pattern to match URLs in the specified format, including SVG
			$url_pattern = '/"url":"(https?:\/\/[^"]+\.(jpg|jpeg|png|gif|webp|ico|svg))"/i';

			// Replace the domain in img src attributes
			$page['rawContent'] = preg_replace_callback( $img_pattern, function( $matches ) use ( $new_domain ) {
				$url = $matches[1];
				if ( strpos( $url, '/wp-content/uploads' ) !== false ) {
					$updated_url = preg_replace( '/^https?:\/\/[^\/]+/', $new_domain, $url );
					return str_replace( $url, $updated_url, $matches[0] );
				}
				return $matches[0]; // Return unchanged if the condition is not met
			}, $page['rawContent']);

			// Replace the specified URL pattern
			$page['rawContent'] = preg_replace_callback( $url_pattern, function( $matches ) use ( $new_domain ) {
				$url = $matches[1];
				return str_replace($url, preg_replace('/^https?:\/\/[^\/]+/', $new_domain, $url), $matches[0]);
			}, $page['rawContent']);
		}

		return $page;
	}

	/**
	 * Replaces image URLs in raw content with their versioned S3 URLs.
	 */
	private static function replace_with_versioned_urls( $content ) {
		if ( ! self::is_offload_media_enabled() ) {
			return $content;
		}

		$s3_domain = self::get_s3_domain();
		if ( ! $s3_domain ) {
			return $content;
		}

		return preg_replace_callback('/' . preg_quote($s3_domain, '/') . '\/wp-content\/uploads\/[^"]+/i', function( $matches ) {
			$original_url = $matches[0];

			// Try to get the attachment ID
			$attachment_id = self::get_attachment_id_by_url( $original_url );
			if ( $attachment_id ) {
				$versioned_url = self::get_versioned_s3_url( $attachment_id );
				if ( $versioned_url ) {
					return $versioned_url; // ✅ Replace with versioned S3 URL
				}
			}

			return $original_url; // Keep original if no version found
		}, $content);
	}

	/**
	 * Get versioned S3 URL for an attachment.
	 */
	private static function get_versioned_s3_url( $attachment_id ) {
		if ( ! class_exists( 'AS3CF_Plugin' ) ) {
			return false; // WP Offload Media is not active
		}

		$file = get_post_meta( $attachment_id, '_wp_attached_file', true );

		if ( ! $file ) {
			return false;
		}

		// Fetch versioned URL dynamically
		$s3_url = apply_filters( 'as3cf_get_attached_file', $file, $attachment_id );

		// Ensure it's a full URL using dynamic S3 domain
		$s3_domain = self::get_s3_domain();
		if ( $s3_url && strpos( $s3_url, 'https://' ) !== 0 && $s3_domain ) {
			$s3_url = $s3_domain . '/' . ltrim($s3_url, '/');
		}

		return esc_url( $s3_url );
	}

	/**
	 * Extract S3 domain dynamically from WP Offload Media settings.
	 */
	private static function get_s3_domain() {
		if ( defined( 'AS3CF_SETTINGS' ) ) {
			$settings = unserialize( AS3CF_SETTINGS );
			if ( ! empty( $settings['bucket'] ) && ! empty( $settings['region'] ) ) {
				return 'https://' . $settings['bucket'] . '.s3.' . $settings['region'] . '.amazonaws.com';
			}
		}
		return false;
	}
}