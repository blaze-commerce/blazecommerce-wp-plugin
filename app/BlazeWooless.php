<?php

namespace BlazeWooless;

use BlazeWooless\Collections\Menu;
use BlazeWooless\Collections\Taxonomy;

class BlazeWooless {
	private static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function init() {
		add_action( 'init', array( $this, 'register_extensions' ) );
		add_action( 'init', array( $this, 'cors_allow_origin' ) );
		add_action( 'edited_term', array( Taxonomy::get_instance(), 'update_typesense_document_on_taxonomy_edit' ), 10, 3 );

		add_filter( 'blaze_wooless_generate_breadcrumbs', array( Taxonomy::get_instance(), 'generate_breadcrumbs' ), 10, 2 );

		TypesenseClient::get_instance();
		Revalidate::get_instance();

		$this->register_settings();

		$this->register_features();

		Ajax::get_instance();
		Woocommerce::get_instance();
		PostType::get_instance();

		add_action( 'template_redirect', array( $this, 'search_redirect' ) );
	}

	public function search_redirect() {
		$enable_system = boolval( bw_get_general_settings( 'enable_system' ) );

		if ( ! $enable_system ) {
			return;
		}

		if (
			isset( $_GET['s'] ) && ! empty( $_GET['s'] )
		) {
			wp_redirect( site_url( '/search-results?s=' . urlencode( $_GET['s'] ) ) );
			exit();
		}
	}

	public function register_settings() {
		$settings = array(
			'\\BlazeWooless\\Settings\\GeneralSettings',
			'\\BlazeWooless\\Settings\\RegionalSettings',
			'\\BlazeWooless\\Settings\\ProductFilterSettings',
			'\\BlazeWooless\\Settings\\ProductPageSettings',
			'\\BlazeWooless\\Settings\\CategoryPageSettings',
			'\\BlazeWooless\\Settings\\SynonymSettings',
			'\\BlazeWooless\\Settings\\ExportImportSettings',
		);

		foreach ( $settings as $setting ) {
			// Instantiating the settings will register an admin_init hook to add the configuration
			// See here BlazeWooless\Settings\BaseSettings.php @ line 18
			$setting::get_instance();
		}
	}

	public function register_features() {
		$features = array(
			'\\BlazeWooless\\Features\\ContentBuilder',
			'\\BlazeWooless\\Features\\AttributeSettings',
			'\\BlazeWooless\\Features\\CalculateShipping',
			'\\BlazeWooless\\Features\\DraggableContent',
			'\\BlazeWooless\\Features\\LoadCartFromSession',
			'\\BlazeWooless\\Features\\Authentication',
			'\\BlazeWooless\\Features\\CategoryBanner',
			'\\BlazeWooless\\Features\\TemplateBuilder',
			'\\BlazeWooless\\Features\\Review',
			'\\BlazeWooless\\Features\\Tax',
			'\\BlazeWooless\\Features\\PluginIntegrationUrlManager',
			'\\BlazeWooless\\Features\\EditCartCheckout',
			'\\BlazeWooless\\Features\\WooCommerceCheckout',
		);

		foreach ( $features as $feature ) {
			$feature::get_instance();
		}

		// Register the CLI command
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			add_action( 'cli_init', function () {
				\WP_CLI::add_command( 'bc-sync', '\BlazeWooless\Features\Cli' );
			} );
		}
	}

	public function register_extensions() {
		$extensions = array(
			'\\BlazeWooless\\Extensions\\ACFProductTabs',
			'\\BlazeWooless\\Extensions\\BusinessReviewsBundle',
			'\\BlazeWooless\\Extensions\\CustomProductTabsManager',
			'\\BlazeWooless\\Extensions\\CustomProductTabsForWoocommerce',
			'\\BlazeWooless\\Extensions\\JudgeMe',
			'\\BlazeWooless\\Extensions\\Yotpo',
			'\\BlazeWooless\\Extensions\\YithWishList',
			'\\BlazeWooless\\Extensions\\WoocommerceAeliaCurrencySwitcher',
			'\\BlazeWooless\\Extensions\\WoocommerceAfterpay',
			'\\BlazeWooless\\Extensions\\WoocommerceGiftCards',
			'\\BlazeWooless\\Extensions\\YoastSEO',
			'\\BlazeWooless\\Extensions\\RankMath',
			'\\BlazeWooless\\Extensions\\GraphQL',
			'\\BlazeWooless\\Extensions\\WoocommerceAllProductsForSubscriptions',
			'\\BlazeWooless\\Extensions\\WoocommerceVariationSwatches',
			'\\BlazeWooless\\Extensions\\WoocommercePhotoReviews',
			'\\BlazeWooless\\Extensions\\WoocommerceProducts',
			'\\BlazeWooless\\Extensions\\WoocommerceProductLabel',
			'\\BlazeWooless\\Extensions\\WoocommerceProductAddons',
			'\\BlazeWooless\\Extensions\\WoocommerceSubscriptions',
			'\\BlazeWooless\\Extensions\\WoocommerceAutoCatThumbnails',
			'\\BlazeWooless\\Extensions\\WooDiscountRules',
			'\\BlazeWooless\\Extensions\\Gutenberg\\Blocks\\Product',
			'\\BlazeWooless\\Extensions\\OffloadMedia',
			'\\BlazeWooless\\Extensions\\MegaMenu',
			'\\BlazeWooless\\Extensions\\WoocommerceBundle',
			'\\BlazeWooless\\Extensions\\Elementor',
			'\\BlazeWooless\\Extensions\\SmartCoupons',
			'\\BlazeWooless\\Extensions\\NiWooCommerceProductVariationsTable',
			'\\BlazeWooless\\Extensions\\B2BWholesaleSuite',
			'\\BlazeWooless\\Extensions\\Pinterest',
			'\\BlazeWooless\\Extensions\\AdvancedCustomFields',
			'\\BlazeWooless\\Extensions\\PageMetaFields',
			'\\BlazeWooless\\Extensions\\CountrySpecificImages',
		);

		foreach ( $extensions as $extension ) {
			// Instantiating the extension will run all hooks in it's constructor
			$extension::get_instance();
		}
	}

	public function cors_allow_origin() {
		$shop_domain = bw_get_general_settings( 'shop_domain' );
		// Allow only your specific domain
		$allowed_origin = 'https://' . $shop_domain;

		// Check if the current request is from the allowed origin
		if ( isset( $_SERVER['HTTP_ORIGIN'] ) && $_SERVER['HTTP_ORIGIN'] === $allowed_origin ) {
			header( "Access-Control-Allow-Origin: $allowed_origin" );
			header( 'Access-Control-Allow-Credentials: true' );
		}
	}

	/**
	 * Get plugin version information
	 *
	 * @return array Plugin version details
	 * @since 1.15.0
	 */
	public function get_version_info() {
		return array(
			'version' => BLAZE_COMMERCE_VERSION,
			'plugin_name' => 'Blaze Commerce',
			'build_date' => date( 'Y-m-d H:i:s' ),
			'php_version' => PHP_VERSION,
			'wp_version' => get_bloginfo( 'version' )
		);
	}

	/**
	 * Check if plugin version is compatible with requirements
	 *
	 * @param string $min_version Minimum required version
	 * @return bool True if compatible, false otherwise
	 * @since 1.15.0
	 */
	public function is_version_compatible( $min_version ) {
		return version_compare( BLAZE_COMMERCE_VERSION, $min_version, '>=' );
	}

	/**
	 * Enhanced logging utility for debugging and monitoring
	 *
	 * @param string $message Log message
	 * @param string $level Log level (info, warning, error, debug)
	 * @param array $context Additional context data
	 * @return void
	 */
	public function log_debug( $message, $level = 'info', $context = array() ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$timestamp = current_time( 'Y-m-d H:i:s' );
		$formatted_message = sprintf(
			'[%s] [%s] BlazeCommerce: %s',
			$timestamp,
			strtoupper( $level ),
			$message
		);

		if ( ! empty( $context ) ) {
			$formatted_message .= ' | Context: ' . wp_json_encode( $context );
		}

		error_log( $formatted_message );

		// Also log to WordPress debug.log if available
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( $formatted_message );
		}
	}

	/**
	 * Log performance metrics for optimization analysis
	 *
	 * @param string $operation Operation name
	 * @param float $execution_time Execution time in seconds
	 * @param array $metrics Additional performance metrics
	 * @return void
	 */
	public function log_performance( $operation, $execution_time, $metrics = array() ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$performance_data = array_merge( array(
			'operation' => $operation,
			'execution_time' => round( $execution_time, 4 ),
			'memory_usage' => memory_get_usage( true ),
			'peak_memory' => memory_get_peak_usage( true ),
		), $metrics );

		$this->log_debug(
			sprintf( 'Performance: %s completed in %s seconds', $operation, $execution_time ),
			'debug',
			$performance_data
		);
	}

	/**
	 * Smart cache utility for API responses and expensive operations
	 *
	 * @param string $cache_key Unique cache identifier
	 * @param callable $callback Function to execute if cache miss
	 * @param int $expiration Cache expiration in seconds (default: 1 hour)
	 * @return mixed Cached or fresh data
	 */
	public function get_cached_data( $cache_key, $callback, $expiration = 3600 ) {
		// Sanitize cache key
		$cache_key = 'blaze_commerce_' . sanitize_key( $cache_key );

		// Try to get from cache first
		$cached_data = get_transient( $cache_key );

		if ( false !== $cached_data ) {
			return $cached_data;
		}

		// Cache miss - execute callback to get fresh data
		if ( is_callable( $callback ) ) {
			$fresh_data = call_user_func( $callback );

			// Store in cache for future requests
			set_transient( $cache_key, $fresh_data, $expiration );

			return $fresh_data;
		}

		return null;
	}

	/**
	 * Clear specific cache entries or all plugin caches
	 *
	 * @param string|null $cache_key Specific cache key to clear, or null for all
	 * @return bool True if cache was cleared successfully
	 */
	public function clear_cache( $cache_key = null ) {
		if ( $cache_key ) {
			// Clear specific cache entry
			$cache_key = 'blaze_commerce_' . sanitize_key( $cache_key );
			return delete_transient( $cache_key );
		}

		// Clear all plugin caches
		global $wpdb;

		$cache_prefix = 'blaze_commerce_';
		$transient_pattern = '_transient_' . $cache_prefix . '%';
		$timeout_pattern = '_transient_timeout_' . $cache_prefix . '%';

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$transient_pattern,
				$timeout_pattern
			)
		);

		return $deleted !== false;
	}

	/**
	 * Get cache statistics and health information
	 *
	 * @return array Cache statistics including hit/miss ratios and storage info
	 */
	public function get_cache_stats() {
		global $wpdb;

		$cache_prefix = 'blaze_commerce_';
		$transient_pattern = '_transient_' . $cache_prefix . '%';

		// Count active cache entries
		$cache_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
				$transient_pattern
			)
		);

		// Calculate total cache size (approximate)
		$cache_size = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE option_name LIKE %s",
				$transient_pattern
			)
		);

		return array(
			'active_entries' => (int) $cache_count,
			'total_size_bytes' => (int) $cache_size,
			'total_size_mb' => round( $cache_size / 1024 / 1024, 2 ),
			'cache_prefix' => $cache_prefix,
			'timestamp' => current_time( 'timestamp' )
		);
	}
}

BlazeWooless::get_instance();
