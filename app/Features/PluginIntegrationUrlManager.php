<?php

namespace BlazeWooless\Features;

/**
 * Plugin Integration URL Manager
 * 
 * Handles domain mismatch issues for WordPress plugin integrations in headless setups.
 * Ensures plugins use the correct WordPress Site URL instead of Home URL for API callbacks,
 * webhooks, and domain validation.
 */
class PluginIntegrationUrlManager {
	private static $instance = null;
	private static $backtrace_cache = null;
	private static $request_cache = array();

	/**
	 * List of plugin contexts where site_url should be used instead of home_url
	 */
	private $plugin_contexts = array(
		// Payment gateways
		'woocommerce_api',
		'wc-api',
		'paypal',
		'stripe',
		'afterpay',
		'klarna',
		'square',
		
		// Review plugins
		'judgeme',
		'yotpo',
		'trustpilot',
		'reviews',
		
		// Marketing/Analytics
		'mailchimp',
		'klaviyo',
		'facebook',
		'google',
		'analytics',
		
		// Other integrations
		'webhook',
		'callback',
		'api',
		'oauth',
		'auth',
		'integration',
		'connect',
		'sync',
	);

	/**
	 * URL patterns that should use site_url instead of home_url
	 */
	private $url_patterns = array(
		'/wp-json/',
		'/wc-api/',
		'/woocommerce-api/',
		'/wp-admin/',
		'/wp-content/',
		'/wp-includes/',
		'/?wc-api=',
		'/?rest_route=',
	);

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		$this->register_hooks();
	}

	/**
	 * Register WordPress hooks
	 */
	public function register_hooks() {
		// Only activate if we're in a headless setup with different site and home URLs
		if ( ! $this->is_headless_setup() || ! $this->is_plugin_url_override_enabled() ) {
			return;
		}

		// Filter home_url for plugin integrations
		add_filter( 'home_url', array( $this, 'maybe_use_site_url_for_plugins' ), 5, 4 );
		
		// Filter option_home for plugin contexts
		add_filter( 'option_home', array( $this, 'maybe_use_site_url_for_option_home' ), 5, 2 );
		
		// Filter REST URL for plugin integrations
		add_filter( 'rest_url', array( $this, 'ensure_rest_url_uses_site_url' ), 5, 4 );
		
		// Filter WooCommerce API URLs
		add_filter( 'woocommerce_api_request_url', array( $this, 'use_site_url_for_wc_api' ), 10, 3 );
		
		// Add settings to general settings
		add_filter( 'blaze_wooless_general_settings', array( $this, 'add_url_override_settings' ), 10, 1 );
		
		// Add site info for frontend
		add_filter( 'blaze_wooless_additional_site_info', array( $this, 'add_url_info_to_site_data' ), 10, 1 );
	}

	/**
	 * Check if this is a headless setup with different site and home URLs
	 */
	private function is_headless_setup() {
		$site_url = get_option( 'siteurl' );
		$home_url = get_option( 'home' );
		
		return $site_url !== $home_url;
	}

	/**
	 * Check if current context is a plugin integration that should use site_url
	 */
	private function is_plugin_integration_context() {
		// Check if we're in admin area (always use site_url)
		if ( is_admin() ) {
			return true;
		}

		// Check if we're in a REST API request
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}

		// Check if we're in a WooCommerce API request
		if ( isset( $_GET['wc-api'] ) || isset( $_GET['rest_route'] ) ) {
			return true;
		}

		// Check current URL path for plugin patterns
		$request_uri = $_SERVER['REQUEST_URI'] ?? '';
		foreach ( $this->url_patterns as $pattern ) {
			if ( strpos( $request_uri, $pattern ) !== false ) {
				return true;
			}
		}

		// Check backtrace for plugin contexts
		$backtrace = $this->get_cached_backtrace();
		foreach ( $backtrace as $trace ) {
			if ( isset( $trace['file'] ) ) {
				$file_path = strtolower( $trace['file'] );
				
				// Check if call is from a plugin directory
				if ( strpos( $file_path, '/plugins/' ) !== false ) {
					$all_contexts = array_merge( $this->plugin_contexts, $this->get_custom_plugin_contexts() );
					foreach ( $all_contexts as $context ) {
						if ( strpos( $file_path, $context ) !== false ) {
							return true;
						}
					}
				}
			}
			
			if ( isset( $trace['function'] ) ) {
				$function_name = strtolower( $trace['function'] );
				$all_contexts = array_merge( $this->plugin_contexts, $this->get_custom_plugin_contexts() );
				foreach ( $all_contexts as $context ) {
					if ( strpos( $function_name, $context ) !== false ) {
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Maybe use site_url instead of home_url for plugin integrations
	 */
	public function maybe_use_site_url_for_plugins( $url, $path, $scheme, $blog_id ) {
		if ( $this->is_plugin_integration_context() ) {
			return site_url( $path, $scheme, $blog_id );
		}
		
		return $url;
	}

	/**
	 * Maybe use site_url for option_home in plugin contexts
	 */
	public function maybe_use_site_url_for_option_home( $value, $option ) {
		if ( $this->is_plugin_integration_context() ) {
			return get_option( 'siteurl' );
		}
		
		return $value;
	}

	/**
	 * Ensure REST URLs use site_url for plugin integrations
	 */
	public function ensure_rest_url_uses_site_url( $url, $path, $scheme, $blog_id ) {
		if ( $this->is_plugin_integration_context() ) {
			$site_url = get_option( 'siteurl' );
			$rest_url = trailingslashit( $site_url ) . 'wp-json/';
			
			if ( $path ) {
				$rest_url .= ltrim( $path, '/' );
			}
			
			return $rest_url;
		}
		
		return $url;
	}

	/**
	 * Use site_url for WooCommerce API URLs
	 */
	public function use_site_url_for_wc_api( $url, $request, $ssl ) {
		return $this->safely_replace_url_host( $url );
	}

	/**
	 * Add URL override settings to general settings
	 */
	public function add_url_override_settings( $fields ) {
		if ( ! $this->is_headless_setup() ) {
			return $fields;
		}

		$fields['wooless_general_settings_section']['options'][] = array(
			'id' => 'enable_plugin_url_override',
			'label' => 'Enable Plugin URL Override',
			'type' => 'checkbox',
			'args' => array(
				'description' => 'Automatically use WordPress Site URL for plugin integrations instead of Home URL. This fixes domain mismatch issues with payment gateways, webhooks, and API callbacks.',
				'default' => '1'
			),
		);

		$fields['wooless_general_settings_section']['options'][] = array(
			'id' => 'custom_plugin_contexts',
			'label' => 'Custom Plugin Contexts',
			'type' => 'textarea',
			'args' => array(
				'description' => 'Additional plugin contexts (one per line) that should use Site URL. Examples: custom-plugin, my-integration, etc.',
				'rows' => 3
			),
		);

		return $fields;
	}

	/**
	 * Add URL information to site data for frontend
	 */
	public function add_url_info_to_site_data( $additional_data ) {
		$additional_data['wordpress_site_url'] = get_option( 'siteurl' );
		$additional_data['wordpress_home_url'] = get_option( 'home' );
		$additional_data['is_headless_setup'] = $this->is_headless_setup() ? 'true' : 'false';
		
		return $additional_data;
	}

	/**
	 * Get custom plugin contexts from settings
	 */
	private function get_custom_plugin_contexts() {
		$custom_contexts = bw_get_general_settings( 'custom_plugin_contexts' );
		if ( empty( $custom_contexts ) ) {
			return array();
		}
		
		return array_filter( 
			array_map( 
				function( $context ) { 
					return sanitize_text_field( trim( $context ) ); 
				}, 
				explode( "\n", $custom_contexts ) 
			) 
		);
	}

	/**
	 * Check if plugin URL override is enabled
	 */
	private function is_plugin_url_override_enabled() {
		return bw_get_general_settings( 'enable_plugin_url_override' ) == '1';
	}

	/**
	 * Get cached backtrace to avoid expensive repeated calls
	 */
	private function get_cached_backtrace() {
		if ( self::$backtrace_cache === null ) {
			self::$backtrace_cache = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 );
		}
		return self::$backtrace_cache;
	}

	/**
	 * Safely replace URL host with site_url host
	 * 
	 * @param string $url The URL to modify
	 * @return string The URL with replaced host
	 */
	private function safely_replace_url_host( $url ) {
		$site_url = get_option( 'siteurl' );
		$parsed_site_url = parse_url( $site_url );
		$parsed_url = parse_url( $url );
		
		// Validate both URLs are properly parsed
		if ( ! $parsed_site_url || ! $parsed_url ) {
			return $url;
		}
		
		// Only proceed if both have valid hosts
		if ( ! isset( $parsed_site_url['host'] ) || ! isset( $parsed_url['host'] ) ) {
			return $url;
		}
		
		// Validate hosts are different to avoid unnecessary replacement
		if ( $parsed_site_url['host'] === $parsed_url['host'] ) {
			return $url;
		}
		
		// Build new URL with site_url components
		$new_url = $parsed_site_url['scheme'] . '://' . $parsed_site_url['host'];
		
		if ( isset( $parsed_site_url['port'] ) ) {
			$new_url .= ':' . $parsed_site_url['port'];
		}
		
		$new_url .= $parsed_url['path'] ?? '';
		
		if ( isset( $parsed_url['query'] ) ) {
			$new_url .= '?' . $parsed_url['query'];
		}
		
		if ( isset( $parsed_url['fragment'] ) ) {
			$new_url .= '#' . $parsed_url['fragment'];
		}
		
		return $new_url;
	}
}
