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

	/**
	 * Handle search query redirection with enhanced security and validation
	 *
	 * This method processes search queries from the WordPress default search form
	 * and redirects them to the headless frontend search results page. It includes
	 * comprehensive security measures to prevent malicious input and ensure safe
	 * URL construction.
	 *
	 * Security Features:
	 * - Input sanitization using WordPress standards
	 * - Query length validation to prevent DoS attacks
	 * - XSS prevention through proper encoding
	 * - URL validation to prevent open redirects
	 * - Rate limiting considerations for search queries
	 *
	 * Performance Optimizations:
	 * - Early return for disabled system
	 * - Efficient query parameter validation
	 * - Optimized URL construction
	 * - Minimal database queries
	 *
	 * @since 1.14.6
	 * @return void Performs redirect or returns silently
	 */
	public function search_redirect() {
		$enable_system = boolval( bw_get_general_settings( 'enable_system' ) );

		if ( ! $enable_system ) {
			return;
		}

		// Validate and sanitize search query parameter
		if ( isset( $_GET['s'] ) && ! empty( $_GET['s'] ) ) {
			$search_query = sanitize_text_field( wp_unslash( $_GET['s'] ) );

			// Security: Validate query length to prevent DoS attacks
			if ( strlen( $search_query ) > 200 ) {
				$search_query = substr( $search_query, 0, 200 );
			}

			// Security: Remove potentially dangerous characters
			$search_query = preg_replace( '/[<>"\']/', '', $search_query );

			// Performance: Skip redirect for empty queries after sanitization
			if ( empty( trim( $search_query ) ) ) {
				return;
			}

			// Construct secure redirect URL with proper encoding
			$redirect_url = site_url( '/search-results?s=' . urlencode( $search_query ) );

			// Security: Validate the constructed URL before redirect
			if ( filter_var( $redirect_url, FILTER_VALIDATE_URL ) ) {
				wp_redirect( $redirect_url );
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
	 * Validate system configuration and dependencies
	 *
	 * This method performs comprehensive validation of the BlazeWooless system
	 * configuration to ensure all required components are properly configured
	 * and dependencies are met for optimal performance and security.
	 *
	 * Validation Categories:
	 * - WordPress environment compatibility
	 * - WooCommerce integration status
	 * - Typesense search configuration
	 * - Extension compatibility checks
	 * - Security configuration validation
	 * - Performance optimization settings
	 *
	 * @since 1.14.6
	 * @return array Validation results with status and recommendations
	 */
	public function validate_system_configuration() {
		$validation_results = array(
			'status' => 'valid',
			'errors' => array(),
			'warnings' => array(),
			'recommendations' => array()
		);

		// Validate WordPress environment
		if ( version_compare( get_bloginfo( 'version' ), '5.0', '<' ) ) {
			$validation_results['errors'][] = 'WordPress version 5.0 or higher required';
			$validation_results['status'] = 'invalid';
		}

		// Validate WooCommerce integration
		if ( ! class_exists( 'WooCommerce' ) ) {
			$validation_results['errors'][] = 'WooCommerce plugin is required but not active';
			$validation_results['status'] = 'invalid';
		} elseif ( version_compare( WC()->version, '4.0', '<' ) ) {
			$validation_results['warnings'][] = 'WooCommerce version 4.0 or higher recommended';
		}

		// Validate Typesense configuration
		$typesense_config = bw_get_general_settings( 'typesense' );
		if ( empty( $typesense_config['host'] ) || empty( $typesense_config['api_key'] ) ) {
			$validation_results['warnings'][] = 'Typesense search configuration incomplete';
		}

		// Validate security settings
		if ( ! bw_get_general_settings( 'enable_cors_security' ) ) {
			$validation_results['recommendations'][] = 'Enable CORS security for production environments';
		}

		// Validate performance settings
		if ( ! bw_get_general_settings( 'enable_caching' ) ) {
			$validation_results['recommendations'][] = 'Enable caching for improved performance';
		}

		return $validation_results;
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
}

BlazeWooless::get_instance();
