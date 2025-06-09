<?php

namespace BlazeWooless\Extensions;

class CountrySpecificImages {
	private static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		// Add settings filter to register our setting
		add_filter( 'blaze_wooless_general_settings', array( $this, 'add_country_images_setting' ), 10, 1 );

		// Add additional site info filter
		add_filter( 'blaze_wooless_additional_site_info', array( $this, 'add_country_images_site_info' ), 10, 1 );

		// Only initialize if Aelia Currency Switcher is active and feature is enabled
		if ( $this->is_feature_enabled() ) {
			// Add meta box to product edit page
			add_action( 'add_meta_boxes', array( $this, 'add_country_images_meta_box' ) );

			// Save meta box data
			add_action( 'woocommerce_process_product_meta', array( $this, 'save_country_images_meta' ) );

			// Enqueue admin scripts and styles
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

			// Filter product thumbnail to use country-specific image
			add_filter( 'wooless_product_thumbnail', array( $this, 'get_country_specific_thumbnail' ), 10, 2 );

			// Add country-specific images to Typesense product data
			add_filter( 'blaze_wooless_product_data_for_typesense', array( $this, 'add_country_images_to_typesense' ), 10, 3 );
		}
	}

	/**
	 * Add country-specific images setting to general settings
	 */
	public function add_country_images_setting( $fields ) {
		// Only add setting if Aelia Currency Switcher is active
		if ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'woocommerce-aelia-currencyswitcher/woocommerce-aelia-currencyswitcher.php' ) ) {
			$fields['wooless_general_settings_section']['options'][] = array(
				'id' => 'enable_country_specific_images',
				'label' => 'Enable Country-Specific Product Images',
				'type' => 'checkbox',
				'args' => array(
					'description' => 'Check this to enable setting different primary images for different countries using Aelia Currency Switcher regions.'
				),
			);
		}

		return $fields;
	}

	/**
	 * Add country-specific images info to additional site info
	 */
	public function add_country_images_site_info( $additional_data ) {
		$general_settings = bw_get_general_settings();
		$additional_data['enable_country_specific_images'] = json_encode( ! empty( $general_settings['enable_country_specific_images'] ) );

		return $additional_data;
	}

	/**
	 * Check if the feature is enabled and Aelia Currency Switcher is active
	 */
	private function is_feature_enabled() {
		// Check if Aelia Currency Switcher is active
		if ( ! function_exists( 'is_plugin_active' ) || ! is_plugin_active( 'woocommerce-aelia-currencyswitcher/woocommerce-aelia-currencyswitcher.php' ) ) {
			return false;
		}

		// Check if feature is enabled in settings
		$general_settings = bw_get_general_settings();
		return ! empty( $general_settings['enable_country_specific_images'] );
	}

	/**
	 * Get available countries from Aelia Currency Switcher
	 */
	private function get_available_countries() {
		$countries = array();

		// Get Aelia currency switcher options
		$aelia_options = get_option( 'wc_aelia_currency_switcher', false );
		if ( ! $aelia_options || ! isset( $aelia_options['currency_countries_mappings'] ) ) {
			return $countries;
		}

		$currency_mappings = $aelia_options['currency_countries_mappings'];
		$all_countries = \WC()->countries->get_countries();

		foreach ( $currency_mappings as $currency => $mapping ) {
			if ( isset( $mapping['countries'] ) && is_array( $mapping['countries'] ) ) {
				foreach ( $mapping['countries'] as $country_code ) {
					if ( isset( $all_countries[ $country_code ] ) ) {
						$countries[ $country_code ] = $all_countries[ $country_code ] . ' (' . $currency . ')';
					}
				}
			}
		}

		return $countries;
	}

	/**
	 * Add meta box for country-specific images
	 */
	public function add_country_images_meta_box() {
		add_meta_box(
			'blaze-country-images',
			'Country-Specific Images',
			array( $this, 'render_country_images_meta_box' ),
			'product',
			'side',
			'default'
		);
	}

	/**
	 * Render the country-specific images meta box
	 */
	public function render_country_images_meta_box( $post ) {
		// Add nonce for security
		wp_nonce_field( 'blaze_country_images_meta_box', 'blaze_country_images_nonce' );

		// Get existing country images
		$country_images = get_post_meta( $post->ID, '_blaze_country_images', true );
		if ( ! is_array( $country_images ) ) {
			$country_images = array();
		}

		// Get available countries
		$countries = $this->get_available_countries();

		if ( empty( $countries ) ) {
			echo '<p>No countries found in Aelia Currency Switcher configuration.</p>';
			return;
		}

		echo '<div id="blaze-country-images-container">';
		echo '<p><strong>Set different primary images for different countries:</strong></p>';

		foreach ( $countries as $country_code => $country_name ) {
			$image_id = isset( $country_images[ $country_code ] ) ? $country_images[ $country_code ] : '';
			$image_url = '';
			$image_title = 'Select Image';

			if ( $image_id ) {
				$image_url = wp_get_attachment_image_url( $image_id, 'thumbnail' );
				$attachment = get_post( $image_id );
				if ( $attachment ) {
					$image_title = $attachment->post_title;
				}
			}

			echo '<div class="blaze-country-image-row" data-country="' . esc_attr( $country_code ) . '">';
			echo '<label><strong>' . esc_html( $country_name ) . '</strong></label>';
			echo '<div class="blaze-image-selector">';
			echo '<input type="hidden" name="blaze_country_images[' . esc_attr( $country_code ) . ']" value="' . esc_attr( $image_id ) . '" class="blaze-image-id" />';
			echo '<div class="blaze-image-preview">';
			if ( $image_url ) {
				echo '<img src="' . esc_url( $image_url ) . '" alt="' . esc_attr( $image_title ) . '" />';
			}
			echo '</div>';
			echo '<div class="blaze-image-actions">';
			echo '<button type="button" class="button blaze-select-image">' . ( $image_id ? 'Change Image' : 'Select Image' ) . '</button>';
			if ( $image_id ) {
				echo '<button type="button" class="button blaze-remove-image">Remove</button>';
			}
			echo '</div>';
			echo '</div>';
			echo '</div>';
		}

		echo '</div>';
	}

	/**
	 * Save country-specific images meta data
	 */
	public function save_country_images_meta( $post_id ) {
		// Check nonce
		if ( ! isset( $_POST['blaze_country_images_nonce'] ) || ! wp_verify_nonce( $_POST['blaze_country_images_nonce'], 'blaze_country_images_meta_box' ) ) {
			return;
		}

		// Check user permissions
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save country images
		if ( isset( $_POST['blaze_country_images'] ) && is_array( $_POST['blaze_country_images'] ) ) {
			$country_images = array();
			foreach ( $_POST['blaze_country_images'] as $country_code => $image_id ) {
				$image_id = absint( $image_id );
				if ( $image_id > 0 ) {
					$country_images[ sanitize_text_field( $country_code ) ] = $image_id;
				}
			}
			update_post_meta( $post_id, '_blaze_country_images', $country_images );
		} else {
			delete_post_meta( $post_id, '_blaze_country_images' );
		}
	}

	/**
	 * Enqueue admin assets
	 */
	public function enqueue_admin_assets( $hook ) {
		// Only load on product edit screens
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ) ) ) {
			return;
		}

		// Only load for products
		global $post;
		if ( ! $post || $post->post_type !== 'product' ) {
			return;
		}

		// Enqueue media library
		wp_enqueue_media();

		// Enqueue our custom script
		wp_enqueue_script(
			'blaze-country-images-admin',
			BLAZE_WOOLESS_PLUGIN_URL . 'assets/js/country-images-admin.js',
			array( 'jquery' ),
			BLAZE_WOOLESS_VERSION,
			true
		);

		// Enqueue custom CSS
		wp_enqueue_style(
			'blaze-country-images-admin',
			BLAZE_WOOLESS_PLUGIN_URL . 'assets/css/country-images-admin.css',
			array(),
			BLAZE_WOOLESS_VERSION
		);
	}

	/**
	 * Get country-specific thumbnail
	 */
	public function get_country_specific_thumbnail( $thumbnail, $product ) {
		// Get current country from Aelia Currency Switcher
		$current_country = $this->get_current_country();
		
		if ( ! $current_country ) {
			return $thumbnail; // Return default thumbnail
		}

		// Get country-specific images for this product
		$product_id = $product->get_id();
		$country_images = get_post_meta( $product_id, '_blaze_country_images', true );

		if ( ! is_array( $country_images ) || ! isset( $country_images[ $current_country ] ) ) {
			return $thumbnail; // Return default thumbnail
		}

		$country_image_id = $country_images[ $current_country ];
		if ( ! $country_image_id ) {
			return $thumbnail; // Return default thumbnail
		}

		// Get the country-specific image data
		$attachment = get_post( $country_image_id );
		$image_alt_text = get_post_meta( $country_image_id, '_wp_attachment_image_alt', true );
		$image_src = wp_get_attachment_url( $country_image_id );

		if ( ! $image_src ) {
			return $thumbnail; // Return default thumbnail if image not found
		}

		$attachment_title = ( $attachment && ! empty( $attachment->post_title ) ) ? $attachment->post_title : '';

		return array(
			'id' => $country_image_id,
			'title' => $attachment_title,
			'altText' => $image_alt_text ? $image_alt_text : $attachment_title,
			'src' => $image_src,
		);
	}

	/**
	 * Get current country from Aelia Currency Switcher
	 */
	private function get_current_country() {
		// Try to get country from various sources
		$country = null;

		// Check if we have a selected currency and can map it to country
		if ( isset( $_COOKIE['aelia_cs_selected_currency'] ) ) {
			$selected_currency = sanitize_text_field( $_COOKIE['aelia_cs_selected_currency'] );
			$country = $this->get_country_from_currency( $selected_currency );
		}

		// Fallback to WooCommerce customer country
		if ( ! $country && function_exists( 'WC' ) && WC()->customer ) {
			$country = WC()->customer->get_billing_country();
			if ( ! $country ) {
				$country = WC()->customer->get_shipping_country();
			}
		}

		// Fallback to Aelia's GeoIP detection
		if ( ! $country && class_exists( '\Aelia\WC\CurrencySwitcher\WC_Aelia_CurrencySwitcher' ) ) {
			$cs_settings = \Aelia\WC\CurrencySwitcher\WC_Aelia_CurrencySwitcher::settings();
			if ( method_exists( $cs_settings, 'get_visitor_country' ) ) {
				$country = $cs_settings->get_visitor_country();
			}
		}

		return $country;
	}

	/**
	 * Get country from currency using Aelia mappings
	 */
	private function get_country_from_currency( $currency ) {
		$aelia_options = get_option( 'wc_aelia_currency_switcher', false );
		if ( ! $aelia_options || ! isset( $aelia_options['currency_countries_mappings'] ) ) {
			return null;
		}

		$currency_mappings = $aelia_options['currency_countries_mappings'];
		if ( isset( $currency_mappings[ $currency ] ) && isset( $currency_mappings[ $currency ]['countries'] ) ) {
			$countries = $currency_mappings[ $currency ]['countries'];
			return is_array( $countries ) && ! empty( $countries ) ? $countries[0] : null;
		}

		return null;
	}

	/**
	 * Add country-specific images data to Typesense product data
	 */
	public function add_country_images_to_typesense( $product_data, $product_id, $product ) {
		// Get country-specific images for this product
		$country_images = get_post_meta( $product_id, '_blaze_country_images', true );

		if ( ! is_array( $country_images ) || empty( $country_images ) ) {
			return $product_data; // No country-specific images, return unchanged
		}

		// Initialize metaData if it doesn't exist
		if ( ! isset( $product_data['metaData'] ) ) {
			$product_data['metaData'] = array();
		}

		// Build the primaryImages object with country codes as keys and image URLs as values
		$primary_images = array();

		foreach ( $country_images as $country_code => $image_id ) {
			if ( $image_id ) {
				$image_url = wp_get_attachment_url( $image_id );
				if ( $image_url ) {
					$primary_images[ $country_code ] = $image_url;
				}
			}
		}

		// Only add to metaData if we have images
		if ( ! empty( $primary_images ) ) {
			$product_data['metaData']['primaryImages'] = $primary_images;
		}

		return $product_data;
	}
}
