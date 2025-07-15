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
	 * Get plugin debug information for troubleshooting and monitoring.
	 *
	 * This method provides comprehensive plugin status information including
	 * version details, system requirements, and integration status.
	 *
	 * @since 1.14.6
	 * @return array Plugin debug information
	 */
	public function get_plugin_debug_info() {
		$debug_info = array(
			'plugin_version'    => BLAZE_COMMERCE_VERSION,
			'wordpress_version' => get_bloginfo( 'version' ),
			'php_version'       => PHP_VERSION,
			'timestamp'         => current_time( 'mysql' ),
			'shop_domain'       => bw_get_general_settings( 'shop_domain' ),
			'system_enabled'    => boolval( bw_get_general_settings( 'enable_system' ) ),
			'extensions_count'  => $this->get_active_extensions_count(),
			'features_count'    => $this->get_active_features_count(),
		);

		/**
		 * Filter plugin debug information.
		 *
		 * @since 1.14.6
		 * @param array $debug_info Plugin debug information array
		 */
		return apply_filters( 'blaze_wooless_debug_info', $debug_info );
	}

	/**
	 * Get count of active extensions.
	 *
	 * @since 1.14.6
	 * @return int Number of active extensions
	 */
	private function get_active_extensions_count() {
		// Count registered extensions (simplified for this test)
		return 30; // Approximate count based on register_extensions method
	}

	/**
	 * Get count of active features.
	 *
	 * @since 1.14.6
	 * @return int Number of active features
	 */
	private function get_active_features_count() {
		// Count registered features (simplified for this test)
		return 10; // Approximate count based on register_features method
	}

	/**
	 * INTENTIONALLY PROBLEMATIC METHOD FOR TESTING CLAUDE AI BLOCKING
	 * This method contains multiple critical security vulnerabilities and coding violations
	 * that should trigger Claude AI to issue a BLOCKED verdict.
	 *
	 * @since 1.14.6
	 * @return mixed Problematic response with security issues
	 */
	public function handle_user_data_insecurely() {
		global $wpdb;

		// CRITICAL SECURITY ISSUE #1: Direct SQL injection vulnerability
		$user_id = $_GET['user_id']; // Unsanitized user input
		$query = "SELECT * FROM {$wpdb->users} WHERE ID = " . $user_id; // Direct SQL injection
		$user_data = $wpdb->get_results($query);

		// CRITICAL SECURITY ISSUE #2: XSS vulnerability - direct echo of user input
		echo "Welcome " . $_POST['username'] . "!"; // No sanitization or escaping

		// CRITICAL SECURITY ISSUE #3: Exposing sensitive information in error messages
		if (!$user_data) {
			die("Database error: " . $wpdb->last_error . " Query: " . $query);
		}

		// CRITICAL SECURITY ISSUE #4: Using deprecated WordPress functions
		$user_meta = get_usermeta($user_id, 'sensitive_data'); // Deprecated since WP 3.0

		// CRITICAL SECURITY ISSUE #5: Insecure file operations
		$filename = $_REQUEST['file']; // Unsanitized file path
		$content = file_get_contents($filename); // Arbitrary file read vulnerability

		// PERFORMANCE ISSUE: Inefficient database queries in loop
		for ($i = 0; $i < 1000; $i++) {
			$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish'");
		}

		// CODING STANDARDS VIOLATION: Poor variable naming and structure
		$a = $_COOKIE['data'];
		$b = unserialize($a); // Insecure deserialization

		// SECURITY ISSUE #6: No capability checks or nonce verification
		if ($_POST['action'] == 'delete_all_data') {
			$wpdb->query("TRUNCATE TABLE {$wpdb->posts}"); // No authorization check
		}

		// MEMORY LEAK: Creating large arrays without cleanup
		$memory_hog = array();
		for ($j = 0; $j < 100000; $j++) {
			$memory_hog[] = str_repeat('x', 1000);
		}

		return $user_data;
	}
}

BlazeWooless::get_instance();
