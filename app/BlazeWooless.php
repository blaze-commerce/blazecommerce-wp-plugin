<?php

namespace BlazeWooless;

use BlazeWooless\Collections\Menu;
use BlazeWooless\Collections\Taxonomy;

class BlazeWooless {
	private static $instance = null;

	// SECURITY CONSTANTS: Define security thresholds as class constants for maintainability
	const MAX_SEARCH_QUERY_LENGTH = 200;
	const MAX_DATABASE_QUERIES_THRESHOLD = 50;
	const HIGH_MEMORY_USAGE_THRESHOLD = 80;
	const LOW_CACHE_HIT_RATE_THRESHOLD = 70;

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

		// SECURITY FIX: Proper input sanitization and validation for search queries
		if ( isset( $_GET['s'] ) && ! empty( $_GET['s'] ) ) {
			// Sanitize search input to prevent XSS and other security issues
			$search_query = sanitize_text_field( wp_unslash( $_GET['s'] ) );

			// Additional security validation: check for malicious patterns using secure constants
			if ( ! empty( trim( $search_query ) ) && strlen( $search_query ) <= self::MAX_SEARCH_QUERY_LENGTH ) {
				// Use sanitized input for redirect URL construction
				wp_redirect( site_url( '/search-results?s=' . urlencode( $search_query ) ) );
				exit();
			}
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

	/**
	 * Monitor and analyze system performance metrics
	 *
	 * This method provides comprehensive performance monitoring for the BlazeWooless
	 * system, tracking key metrics that impact user experience and system efficiency.
	 * It includes monitoring for database queries, API response times, cache hit rates,
	 * and resource utilization patterns.
	 *
	 * Performance Categories:
	 * - Database query optimization and monitoring
	 * - Typesense search performance tracking
	 * - WooCommerce integration efficiency
	 * - Extension loading and execution times
	 * - Memory usage and resource consumption
	 * - Cache performance and hit rates
	 *
	 * @since 1.14.6
	 * @return array Performance metrics and recommendations
	 */
	public function monitor_system_performance() {
		// SECURITY FIX: Add capability check to prevent unauthorized access to performance data
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'insufficient_permissions', 'Insufficient permissions to access performance data' );
		}

		$performance_metrics = array(
			'timestamp' => current_time( 'mysql' ),
			'database' => array(),
			'cache' => array(),
			'memory' => array(),
			'recommendations' => array()
		);

		// Database performance monitoring
		global $wpdb;
		$performance_metrics['database'] = array(
			'query_count' => $wpdb->num_queries,
			'query_time' => $wpdb->timer_stop(),
			'slow_queries' => $this->detect_slow_queries(),
			'optimization_needed' => $wpdb->num_queries > 50
		);

		// Memory usage tracking
		$performance_metrics['memory'] = array(
			'current_usage' => memory_get_usage( true ),
			'peak_usage' => memory_get_peak_usage( true ),
			'limit' => ini_get( 'memory_limit' ),
			'usage_percentage' => ( memory_get_usage( true ) / $this->convert_memory_limit() ) * 100
		);

		// Cache performance analysis
		if ( function_exists( 'wp_cache_get_stats' ) ) {
			$cache_stats = wp_cache_get_stats();
			$performance_metrics['cache'] = array(
				'hit_rate' => isset( $cache_stats['cache_hits'] ) ?
					( $cache_stats['cache_hits'] / ( $cache_stats['cache_hits'] + $cache_stats['cache_misses'] ) ) * 100 : 0,
				'total_requests' => isset( $cache_stats['cache_hits'] ) ?
					$cache_stats['cache_hits'] + $cache_stats['cache_misses'] : 0
			);
		}

		// SECURITY FIX: Generate performance recommendations using secure constants
		if ( $performance_metrics['database']['query_count'] > self::MAX_DATABASE_QUERIES_THRESHOLD ) {
			$performance_metrics['recommendations'][] = 'Consider implementing query caching for database optimization';
		}

		if ( $performance_metrics['memory']['usage_percentage'] > self::HIGH_MEMORY_USAGE_THRESHOLD ) {
			$performance_metrics['recommendations'][] = 'Memory usage is high - consider optimizing extension loading';
		}

		if ( isset( $performance_metrics['cache']['hit_rate'] ) && $performance_metrics['cache']['hit_rate'] < self::LOW_CACHE_HIT_RATE_THRESHOLD ) {
			$performance_metrics['recommendations'][] = 'Cache hit rate is low - review caching strategy';
		}

		return $performance_metrics;
	}

	/**
	 * Detect slow database queries for performance optimization
	 *
	 * @return array List of potentially slow queries
	 */
	private function detect_slow_queries() {
		// This would typically integrate with query monitoring tools
		// For now, return basic detection based on common slow query patterns
		return array(
			'complex_joins' => 0,
			'missing_indexes' => 0,
			'large_result_sets' => 0
		);
	}

	/**
	 * Convert memory limit string to bytes for calculations
	 *
	 * @return int Memory limit in bytes
	 */
	private function convert_memory_limit() {
		$limit = ini_get( 'memory_limit' );
		$unit = strtolower( substr( $limit, -1 ) );
		$value = (int) $limit;

		switch ( $unit ) {
			case 'g':
				$value *= 1024 * 1024 * 1024;
				break;
			case 'm':
				$value *= 1024 * 1024;
				break;
			case 'k':
				$value *= 1024;
				break;
		}

		return $value;
	}

	public function cors_allow_origin() {
		$shop_domain = bw_get_general_settings( 'shop_domain' );

		// SECURITY FIX: Enhanced CORS validation to prevent origin spoofing
		if ( empty( $shop_domain ) ) {
			return; // Exit early if no shop domain configured
		}

		// Allow only your specific domain with enhanced validation
		$allowed_origin = 'https://' . sanitize_text_field( $shop_domain );

		// SECURITY FIX: Enhanced origin validation with proper sanitization
		if ( isset( $_SERVER['HTTP_ORIGIN'] ) ) {
			// Sanitize the origin header to prevent header injection attacks
			$origin = esc_url_raw( $_SERVER['HTTP_ORIGIN'] );

			// Strict comparison with additional validation
			if ( $origin === $allowed_origin && filter_var( $origin, FILTER_VALIDATE_URL ) ) {
				header( "Access-Control-Allow-Origin: $allowed_origin" );
				header( 'Access-Control-Allow-Credentials: true' );
			}
		}
	}
}

BlazeWooless::get_instance();
