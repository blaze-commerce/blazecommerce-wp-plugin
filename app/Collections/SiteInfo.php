<?php

namespace BlazeWooless\Collections;

use BlazeWooless\Settings\RegionalSettings;

class SiteInfo extends BaseCollection {
	private static $instance = null;
	public $collection_name = 'site_info';

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function initialize() {
		$logger  = wc_get_logger();
		$context = array( 'source' => 'wooless-site-info-collection-initialize' );

		// Check if we should use the new alias system
		$use_aliases = apply_filters( 'blazecommerce/use_collection_aliases', true );

		if ( $use_aliases ) {
			try {
				$schema = array(
					'fields' => array(
						array( 'name' => 'name', 'type' => 'string' ),
						array( 'name' => '.*', 'type' => 'string*' ),
						array( 'name' => 'updated_at', 'type' => 'int64' ),
					),
					'default_sorting_field' => 'updated_at',
				);

				$new_collection_name = $this->initialize_with_alias( $schema );
				$logger->debug( 'TS SiteInfo collection (alias): ' . $new_collection_name, $context );

				// Note: initialize_with_alias() now automatically stores the active sync collection

			} catch (\Exception $e) {
				$logger->debug( 'TS SiteInfo collection alias initialize Exception: ' . $e->getMessage(), $context );
				throw $e;
			}
		} else {
			// Legacy behavior
			// Delete the existing 'site_info' collection (if it exists)
			try {
				$this->drop_collection();
			} catch (\Exception $e) {
				// Don't error out if the collection was not found
			}

			try {
				$logger->debug( 'TS SiteInfo collection: ' . $this->collection_name(), $context );
				// Create the 'site_info' collection with the required schema
				$this->create_collection(
					array(
						'name' => $this->collection_name(),
						'fields' => array(
							array( 'name' => 'name', 'type' => 'string' ),
							array( 'name' => '.*', 'type' => 'string*' ),
							array( 'name' => 'updated_at', 'type' => 'int64' ),
						),
						'default_sorting_field' => 'updated_at',
					),
				);
			} catch (\Exception $e) {
				$logger->debug( 'TS SiteInfo collection initialize Exception: ' . $e->getMessage(), $context );
				echo "Error: " . $e->getMessage() . "\n";
			}
		}
	}

	public function prepare_batch_data() {
		$documents = array();

		$update_at      = time();
		$shop_page      = get_option( 'woocommerce_shop_page_id' );
		$blog_page      = get_option( 'page_for_posts' );
		$cart_page_id   = wc_get_page_id( 'cart' );
		$home_page_id   = get_option( 'page_on_front' );
		$home_page_slug = $home_page_id ? get_post_field( 'post_name', $home_page_id ) : '';

		$datas = array(
			array(
				'name' => 'site_title',
				'value' => get_bloginfo( 'name' ),
			),
			array(
				'name' => 'Site_tagline',
				'value' => get_bloginfo( 'description' ),
			),
			array(
				'name' => 'stock_display_format',
				'value' => $this->get_stock_display_format(),
			),
			array(
				'name' => 'product_attributes',
				'value' => $this->get_product_attributes(),
			),
			array(
				'name' => 'permalink_structure',
				'value' => $this->get_permalink_structure(),
			),
			array(
				'name' => 'reviews_plugin',
				'value' => $this->get_active_reviews_plugin(),
			),
			array(
				'name' => 'payment_methods',
				'value' => $this->get_available_payment_methods(),
			),
			array(
				'name' => 'time_format',
				'value' => get_option( 'time_format' ),
			),
			array(
				'name' => 'search_engine',
				'value' => get_option( 'blog_public' ),
			),
			array(
				'name' => 'site_id',
				'value' => strval( get_current_blog_id() ),
			),
			array(
				'name' => 'wordpress_address_url',
				'value' => get_site_url(),
			),
			array(
				'name' => 'admin_email_address',
				'value' => get_option( 'admin_email' ),
				'updated_at' => intval( get_option( 'admin_email_last_updated', $update_at ) ),
			),
			array(
				'name' => 'language',
				'value' => get_locale(),
				'updated_at' => intval( get_option( 'locale_last_updated', $update_at ) ),
			),
			array(
				'name' => 'time_zone',
				'value' => date_default_timezone_get(),
				'updated_at' => intval( get_option( 'timezone_last_updated', $update_at ) ),
			),
			array(
				'name' => 'date_format',
				'value' => get_option( 'date_format' ),
				'updated_at' => intval( get_option( 'date_format_last_updated', $update_at ) ),
			),
			array(
				'name' => 'woocommerce_calc_taxes',
				'value' => get_option( 'woocommerce_calc_taxes', 'no' ),
			),
			array(
				'name' => 'woocommerce_prices_include_tax',
				'value' => get_option( 'woocommerce_prices_include_tax', 'no' ),
			),
			array(
				'name' => 'site_icon_url',
				'value' => get_site_icon_url( 512, 'https://blazecommerce.io/wp-content/uploads/2023/09/blaze_commerce_favicon-1.png' ),
			),
			array(
				'name' => 'theme_json',
				'value' => wp_get_global_settings(),
			),
			array(
				'name' => 'woocommerce_tax_setup',
				'value' => [ 
					'displayPricesIncludingTax' => get_option( 'woocommerce_tax_display_shop' ),
					'priceDisplaySuffix' => get_option( 'woocommerce_price_display_suffix' ),
				]
			),
			array(
				'name' => 'shop_page_slug',
				'value' => $shop_page ? get_post_field( 'post_name', $shop_page ) : '',
			),
			array(
				'name' => 'blog_page_slug',
				'value' => $blog_page ? get_post_field( 'post_name', $blog_page ) : '',
			),
			array(
				'name' => 'cart_page_slug',
				'value' => $cart_page_id ? get_post_field( 'post_name', $cart_page_id ) : '',
			),
			array(
				'name' => 'woocommerce_permalinks',
				'value' => get_option( 'woocommerce_permalinks' ),
			),
			array(
				'name' => 'wp_page_headless',
				'value' => 'yes',
			),
			array(
				'name' => 'wp_post_headless',
				'value' => 'yes',
			),
			array(
				'name' => 'cookie_domain',
				'value' => defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : get_graphql_setting( 'cookie_domain', '', 'graphql_cors_settings' ),
			),
			array(
				'name' => 'homepage_slug',
				'value' => $home_page_slug,
			),
		);

		// Cache alias names to avoid multiple calls
		static $cached_alias_names = null;
		if ( $cached_alias_names === null ) {
			$cached_alias_names = $this->alias_manager->get_all_alias_names();
		}

		$datas[] = array(
			'name' => 'collections',
			'value' => $cached_alias_names,
		);
		$datas[] = $this->site_logo_settings();
		$datas[] = $this->store_notice_settings();
		$datas[] = $this->favicon_settings();

		foreach ( $datas as $data ) {
			if ( ! isset( $data['updated_at'] ) ) {
				$data['updated_at'] = $update_at;
			}

			if ( is_array( $data['value'] ) ) {
				$data['value'] = json_encode( $data['value'] );
			}

			$data['value'] = apply_filters( 'blazecommerce/settings/' . $data['name'], $data['value'] );
			$documents[]   = $data;
		}

		unset( $datas );

		$initial_additional_data = array();

		$site_currency                         = get_woocommerce_currency();
		$base_currency                         = WC()->countries->get_base_country();
		$currencies                            = array(
			'countries' => [ $base_currency ],
			'baseCountry' => $base_currency,
			'currency' => $site_currency,
			'symbol' => html_entity_decode( get_woocommerce_currency_symbol( $site_currency ) ),
			'symbolPosition' => get_option( 'woocommerce_currency_pos' ),
			'thousandSeparator' => get_option( 'woocommerce_price_thousand_sep' ),
			'decimalSeparator' => get_option( 'woocommerce_price_decimal_sep' ),
			'precision' => wc_get_price_decimals(),
			'default' => true,
		);
		$initial_additional_data['currencies'] = array( $currencies );
		$initial_additional_data['regions']    = RegionalSettings::get_selected_regions();

		$additional_data = apply_filters( 'blaze_wooless_additional_site_info', $initial_additional_data );
		foreach ( $additional_data as $key => $value ) {
			if ( empty( $value ) ) {
				continue;
			}

			if ( is_array( $value ) ) {
				$value = json_encode( $value );
			}

			// If it's 'popular_categories', decode it from a JSON string
			if ( $key == 'popular_categories' ) {
				$value = json_decode( $value, true );
			}

			$documents[] = array(
				'name' => $key,
				'value' => (string) apply_filters( 'blazecommerce/settings/' . $key, $value ),
				'updated_at' => time(),
			);
		}

		$category_setttings = apply_filters( 'blazecommerce/settings/category', array(
			'productPerPage' => apply_filters( 'loop_shop_per_page', wc_get_default_products_per_row() * wc_get_default_product_rows_per_page() )
		) );

		$documents[] = array(
			'name' => 'category',
			'value' => (string) json_encode( $category_setttings, true ),
			'updated_at' => time(),
		);

		$tax_settings = apply_filters( 'blazecommerce/settings/tax', array(
			'prices_include_tax' => get_option( 'woocommerce_prices_include_tax' ),
			'tax_based_on' => get_option( 'woocommerce_tax_based_on' ),
			'shipping_tax_class' => get_option( 'woocommerce_shipping_tax_class' ),
			'tax_round_at_subtotal' => get_option( 'woocommerce_tax_round_at_subtotal' ),
			'tax_classes' => array_filter( array_map( 'trim', explode( "\n", get_option( 'woocommerce_tax_classes' ) ) ) ),
			'tax_display_shop' => get_option( 'woocommerce_tax_display_shop' ),
			'tax_display_cart' => get_option( 'woocommerce_tax_display_cart' ),
			'price_display_suffix' => get_option( 'woocommerce_price_display_suffix' ),
			'tax_total_display' => get_option( 'woocommerce_tax_total_display' ),
		) );

		$documents[] = array(
			'name' => 'woocommerce_tax_settings',
			'value' => (string) json_encode( $tax_settings, true ),
			'updated_at' => time(),
		);

		$tax_rates = apply_filters( 'blazecommerce/settings/tax_rates', array() );

		$documents[] = array(
			'name' => 'woocommerce_tax_rates',
			'value' => (string) json_encode( $tax_rates, true ),
			'updated_at' => time(),
		);

		$product_setttings = apply_filters( 'blazecommerce/settings/product', array(
			'productDetails' => array(
				'showSku' => false,
				'showBrand' => false,
				'showStockInformation' => true,
				"emailWhenAvailable" => true,
				"showCategories" => false,
				"newsletter" => true,
				"brandTaxonomyIdentifier" => "",
				"stockDisplayFormat" => "low_amount"
			),
			'features' => array(
				'shortDescription' => array(
					"enabled" => true
				),
				'recentlyViewed' => array(
					"enabled" => true,
					"showNumProducts" => 4
				),
				"additionalWarningMessage" => array(
					"enabled" => false
				),
				"calculateShipping" => array(
					"enabled" => true
				),
				"averageRatingText" => array(
					"enabled" => true
				)
			),
			'productGallery' => array(
				'newProductBadgeThreshold' => "30",
				'showNewProductBadge' => true,
				'zoomType' => '2',
				'isGrid' => false
			),
			'layout' => array(
				"wishlist" => array(
					"buttonType" => "1"
				),
				"descriptionTabLocation" => "1",
				"tabsCase" => "2",
				"productTabs" => "accordion",
				"recentlyViewedAndCrossSellAlignment" => "left"
			),
			"descriptionAfterContent" => "",
			"usingCompositeProduct" => false,
			"freeShippingThreshold" => array()
		) );

		$documents[] = array(
			'name' => 'product',
			'value' => (string) json_encode( $product_setttings, true ),
			'updated_at' => time(),
		);

		$allowed_permalinks = apply_filters( 'blazecommerce/settings/allowed_permalinks', array() );

		$documents[] = array(
			'name' => 'allowedPermalinks',
			'value' => (string) json_encode( $allowed_permalinks, true ),
			'updated_at' => time(),
		);

		return apply_filters( 'blazecommerce/settings', $documents );
	}

	public function import_prepared_batch( $documents ) {
		$logger  = wc_get_logger();
		$context = array( 'source' => 'blazecommerce-site-info-import' );

		// Import site info to Typesense using the correct collection (alias-aware)
		try {
			$result             = $this->import( $documents );
			$successful_imports = array_filter( $result, function ($batch_result) {
				return isset( $batch_result['success'] ) && $batch_result['success'] == true;
			} );
			$logger->debug( 'TS SiteInfo Import result: ' . print_r( $result, 1 ), $context );

			return $successful_imports;
		} catch (\Exception $e) {
			$logger->debug( 'TS SiteInfo Import Exception: ' . $e->getMessage(), $context );
			error_log( "Error importing site info to Typesense: " . $e->getMessage() );
			return array();
		}
	}



	public function after_site_info_sync() {
		do_action( 'blaze_wooless_after_site_info_sync' );
	}

	public function index_to_typesense() {
		$logger  = wc_get_logger();
		$context = array( 'source' => 'wooless-site-info-collection-index' );

		//Indexing Site Info
		try {
			$this->initialize();

			$documents = $this->prepare_batch_data();
			$this->import_prepared_batch( $documents );

			// Complete the sync if using aliases
			$use_aliases = apply_filters( 'blazecommerce/use_collection_aliases', true );
			if ( $use_aliases && isset( $this->active_sync_collection ) ) {
				$sync_result = $this->complete_collection_sync();
				$logger->debug( 'TS SiteInfo sync result: ' . json_encode( $sync_result ), $context );
			}

			$this->after_site_info_sync();

			echo "Site info added successfully!";
		} catch (\Exception $e) {
			$logger->debug( 'TS SiteInfo index Exception: ' . $e->getMessage(), $context );
			echo $e->getMessage();
		}
	}

	public function get_stock_display_format() {
		// Get WooCommerce stock settings
		$manage_stock = get_option( 'woocommerce_manage_stock' ); // 'yes' or 'no'
		$stock_format = get_option( 'woocommerce_stock_format' ); // 'always', 'never', or 'low_amount'

		// If stock management is disabled, the stock display format will be empty
		if ( $manage_stock === 'no' ) {
			return '';
		}

		return $stock_format;
	}

	public function get_product_attributes() {
		// TODO: Make that it will get the variants present on woocommerce
		// Prepare the product attributes
		$product_attributes = [ 
			[ "name" => "size", "type" => "select" ],
			[ "name" => "color", "type" => "swatch" ],
			[ "name" => "style", "type" => "image" ]
		];

		// Convert the attributes to a JSON string
		return $product_attributes;
	}

	public function get_permalink_structure() {
		$permalink_structure = get_option( 'woocommerce_permalinks' );
		$product_base        = $permalink_structure['product_base'] ?: '';

		// If the product base does not start with a slash, add one
		if ( $product_base && $product_base[0] !== '/' ) {
			$product_base = '/' . $product_base;
		}

		$category_base            = get_option( 'category_base', 'category' );
		$tag_base                 = get_option( 'tag_base', 'tag' );
		$base_permalink_structure = get_option( 'permalink_structure' );

		return array(
			'product' => $product_base . '/%postname%',
			'category' => '/' . $category_base . '/%categoryname%',
			'tag' => '/' . $tag_base . '/%tagname%',
			'base' => $base_permalink_structure . '/%postname%',
			'posts' => '/blog/%postname%',
			'pages' => $base_permalink_structure . '/%pagename%',
		);
	}

	public function get_active_reviews_plugin() {
		global $wpdb;

		// Fetch the 'active_plugins' option from the WordPress options table
		$active_plugins_serialized = $wpdb->get_var( "SELECT option_value FROM " . $wpdb->options . " WHERE option_name = 'active_plugins'" );
		$active_plugins            = unserialize( $active_plugins_serialized );

		// List of known review plugin slugs
		$review_plugin_slugs = array(
			'reviewscouk-for-woocommerce',
			'wp-review',
			'wp-product-review-lite',
			'all-in-one-schemaorg-rich-snippets',
			'site-reviews',
			'ultimate-reviews',
			'taqyeem',
			'author-hreview',
			'rich-reviews',
			'customer-reviews-for-woocommerce',
			'reviewer',
			'yelp-widget-pro',
			'testimonials-widget',
			'google-reviews-widget',
			'reviewer-plugin',
			'wp-customer-reviews',
			'starcat-reviews',
			'trustpilot-reviews',
			'tripadvisor-reviews',
			'facebook-reviews-pro',
			'wp-reviews',
			'multi-rating-pro',
			'business-reviews-bundle'
		);

		// Filter the active plugins by the known review plugin slugs
		$filtered_plugins = array_filter( $active_plugins, function ($plugin) use ($review_plugin_slugs) {
			foreach ( $review_plugin_slugs as $slug ) {
				if ( strpos( $plugin, $slug ) !== false ) {
					return true;
				}
			}
			return false;
		} );

		// Extract the plugin directory names
		$filtered_plugin_directories = array_map( function ($plugin) {
			return dirname( $plugin );
		}, $filtered_plugins );

		// Convert the filtered plugin directory names array to a string
		return implode( ', ', $filtered_plugin_directories );
	}

	public function get_available_payment_methods() {
		$available_gateways = \WC()->payment_gateways->get_available_payment_gateways();
		return array_map( function ($gateway) {
			return $gateway->id;
		}, $available_gateways );
	}

	public function site_logo_settings() {
		$logo_id         = get_theme_mod( 'custom_logo' );
		$logo_image      = wp_get_attachment_image_src( $logo_id, 'full' );
		$logo_metadata   = wp_get_attachment_metadata( $logo_id );
		$logo_updated_at = isset( $logo_metadata['file'] ) ? strtotime( date( "Y-m-d H:i:s", filemtime( get_attached_file( $logo_id ) ) ) ) : null;

		return array(
			'name' => 'site_logo',
			'value' => $logo_image ? $logo_image[0] : '',
			'updated_at' => $logo_updated_at,
		);
	}

	public function store_notice_settings() {
		global $wpdb;

		$store_notice            = get_option( 'woocommerce_demo_store_notice' );
		$store_notice_updated_at = $wpdb->get_var( "SELECT UNIX_TIMESTAMP(option_value) FROM {$wpdb->options} WHERE option_name = '_transient_timeout_woocommerce_demo_store_notice'" ) ?: time();

		return array(
			'name' => 'store_notice',
			'value' => $store_notice,
			'updated_at' => intval( $store_notice_updated_at ),
		);
	}

	public function favicon_settings() {
		$site_icon_id = get_option( 'site_icon' );
		$favicon_url  = $site_icon_id ? wp_get_attachment_image_url( $site_icon_id, 'full' ) : '';

		if ( $site_icon_id ) {
			$favicon_updated_at = strtotime( get_the_modified_date( 'Y-m-d H:i:s', $site_icon_id ) );
		} else {
			$favicon_updated_at = 0;
		}

		return array(
			'name' => 'favicon',
			'value' => $favicon_url,
			'updated_at' => intval( $favicon_updated_at ),
		);
	}
}
