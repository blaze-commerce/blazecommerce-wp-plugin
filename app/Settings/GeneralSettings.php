<?php

namespace BlazeWooless\Settings;

use BlazeWooless\TypesenseClient;

class GeneralSettings extends BaseSettings {
	private static $instance = null;
	public $tab_key = 'general';
	public $page_label = 'General';

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self( 'wooless_general_settings_options' );
		}

		return self::$instance;
	}

	public function register_hooks() {
		add_filter( 'blaze_wooless_additional_site_info', array( $this, 'register_additional_site_info' ), 10, 1 );
		add_action( 'template_redirect', array( $this, 'redirect_non_admin_user' ), -1 );
		add_filter( 'rest_url', array( $this, 'overwrite_rest_url' ), 10 );

		add_filter( 'option_home', array( $this, 'maybe_remove_cart_from_site_address_url' ), 10, 2 );

		add_filter( 'post_link', array( $this, 'remove_cart_from_url' ), 10, 1 );
		add_filter( 'post_type_link', array( $this, 'remove_cart_from_url' ), 10, 1 );
		add_filter( 'page_link', array( $this, 'remove_cart_from_url' ), 10, 1 );
		add_filter( 'term_link', array( $this, 'remove_cart_from_url' ), 10, 1 );
	}

	/**
	 * Helper method to remove "cart." in url 
	 * @param mixed $url
	 * @return string
	 */
	public function remove_cart_from_url( $url ) {
		return rtrim( str_replace( '/cart.', '/', $url ), '/' );
	}

	/**
	 * Removes all "cart." in all links when the user is not on admin pages. 
	 * This function dynamically changes the wordpress site address url in general settings via option_home filter
	 * 
	 * @param mixed $value
	 * @param mixed $option
	 * @return mixed
	 */
	public function maybe_remove_cart_from_site_address_url( $value, $option ) {

		// skip the filter if the user is on admin pages
		if ( is_admin() ) {
			return $value;
		}

		return $this->remove_cart_from_url( $value );
	}

	/**
	 * Redirect non admin user to non cart.* url
	 * Hooked into template_redirect, priority -1
	 * 
	 * @since   1.5.0
	 * @return  void
	 */
	public function redirect_non_admin_user() {

		// skip redirect for administrator
		if ( is_user_logged_in() && current_user_can( 'manage_options' ) )
			return;

		// skip redirect for ajax request
		if ( function_exists( 'is_ajax' ) && is_ajax() ) {
			return;
		}

		if ( ! function_exists( 'is_checkout' ) || ! function_exists( 'is_cart' ) ) {
			return;
		}


		// Redirect to home page if the user is not logged in and the page is cart 
		$restricted_pages = apply_filters( 'blaze_wooless_restricted_pages', is_cart() );
		if ( $restricted_pages ) {
			wp_redirect( $this->remove_cart_from_url( home_url() ) );
			exit;
		}

		$is_my_account_page                = strpos( $_SERVER['REQUEST_URI'], 'my-account' ) !== false;
		$exclude_page_redirect_to_frontend = apply_filters( 'blaze_wooless_exclude_page_redirect_to_frontend', is_checkout() );
		if ( $exclude_page_redirect_to_frontend || $is_my_account_page ) {
			//Since the page is excluded from redirecting to frontend then we just end the function here
			return;
		}

		$has_cart_in_url           = strpos( $_SERVER['SERVER_NAME'], 'cart.' ) !== false;
		$from_vercel_proxy_request = isset( $_SERVER['HTTP_X_VERCEL_PROXY_SIGNATURE'] ) ? true : false;

		// if the url has cart. on it and the request is not from vercel then we redirect it to frontend page without cart in the url
		if ( $has_cart_in_url && ! $from_vercel_proxy_request ) {
			wp_redirect( $this->remove_cart_from_url( home_url( $_SERVER['REQUEST_URI'] ) ) );
			exit;
		}

	}

	/**
	 * Overwrite rest url, so we can use guttenberg editor when the site url is different
	 * Hooked into rest_url, priority 10
	 * @param string $url
	 * @return string
	 */
	public function overwrite_rest_url( $url ) {
		$new_url = trailingslashit( get_option( 'siteurl' ) ) . 'wp-json';

		$url = str_replace( home_url( '/wp-json' ), $new_url, $url );

		return $url;
	}


	public function settings() {

		$font_families = array(
			'Arimo' => 'Arimo',
			'Barlow' => 'Barlow',
			'DM Sans' => 'DM Sans',
			'Dosis' => 'Dosis',
			'Fira Sans' => 'Fira Sans',
			'Futura Book' => 'Futura Book',
			'Heebo' => 'Heebo',
			'Hind Siliguri' => 'Hind Siliguri',
			'IBM Plex Sans' => 'IBM Plex Sans',
			'Inconsolata' => 'Inconsolata',
			'Inter' => 'Inter',
			'Josefin Sans' => 'Josefin Sans',
			'Kanit' => 'Kanit',
			'Karla' => 'Karla',
			'Lato' => 'Lato',
			'Libre Baskerville' => 'Libre Baskerville',
			'Libre Franklin' => 'Libre Franklin',
			'Lora' => 'Lora',
			'Manrope' => 'Manrope',
			'Material Icons' => 'Material Icons',
			'Material Icons Outlined' => 'Material Icons Outlined',
			'Merriweather' => 'Merriweather',
			'Montserrat' => 'Montserrat',
			'Mukta' => 'Mukta',
			'Mulish' => 'Mulish',
			'Nanum Gothic' => 'Nanum Gothic',
			'Noto Sans' => 'Noto Sans',
			'Noto Sans JP' => 'Noto Sans JP',
			'Noto Sans KR' => 'Noto Sans KR',
			'Noto Sans TC' => 'Noto Sans TC',
			'Noto Serif' => 'Noto Serif',
			'Nunito' => 'Nunito',
			'Nunito Sans' => 'Nunito Sans',
			'Open Sans' => 'Open Sans',
			'Oswald' => 'Oswald',
			'Playfair Display' => 'Playfair Display',
			'Poppins' => 'Poppins',
			'PT Sans' => 'PT Sans',
			'PT Serif' => 'PT Serif',
			'Quicksand' => 'Quicksand',
			'Raleway' => 'Raleway',
			'Roboto' => 'Roboto',
			'Roboto Condensed' => 'Roboto Condensed',
			'Roboto Mono' => 'Roboto Mono',
			'Roboto Slab' => 'Roboto Slab',
			'Rubik' => 'Rubik',
			'Source Sans Pro' => 'Source Sans Pro',
			'Titillium Web' => 'Titillium Web',
			'Ubuntu' => 'Ubuntu',
			'Work Sans' => 'Work Sans',
		);
		$fields        = array(
			'wooless_general_settings_section' => array(
				'label' => 'General Settings',
				'options' => array(
					array(
						'id' => 'enable_system',
						'label' => 'Enable System',
						'type' => 'checkbox',
						'args' => array(
							'description' => 'Check this to enable the system.'
						),
					),
					array(
						'id' => 'typesense_api_key',
						'label' => 'API Key',
						'type' => 'password',
						'args' => array(
							'description' => 'API Key generated from typesense cloud API keys page.'
						),
					),
					array(
						'id' => 'typesense_host',
						'label' => 'Typesense Host',
						'type' => 'text',
						'args' => array(
							'description' => 'This is the host url found in your cluster overview page in typesense clould'
						),
					),
					array(
						'id' => 'store_id',
						'label' => 'Store Id',
						'type' => 'number',
						'args' => array(
							'description' => 'We use store id to identify a store collection in your typesense cluster. This allows you to use one cluster for different websites'
						),
					),
					array(
						'id' => 'shop_domain',
						'label' => 'Shop Domain',
						'type' => 'text',
						'args' => array(
							'description' => 'Live site domain. (e.g. website.com.au)'
						),
					),
				)
			),
			'vercel_deployment_settings_section' => array(
				'label' => 'Vercel Deployment Settings',
				'options' => array(
					array(
						'id' => 'enable_direct_vercel_deployment',
						'label' => 'Enable Direct Vercel Deployment',
						'type' => 'checkbox',
						'args' => array(
							'description' => 'Use direct Vercel API instead of BlazeCommerce middleware for deployments.'
						),
					),
					array(
						'id' => 'vercel_deployment_token',
						'label' => 'Vercel Deployment Token',
						'type' => 'password',
						'args' => array(
							'description' => 'Vercel API token for deployment operations. Get this from your Vercel dashboard under Settings > Tokens.'
						),
					),
					array(
						'id' => 'vercel_project_id',
						'label' => 'Vercel Project ID',
						'type' => 'text',
						'args' => array(
							'description' => 'Your Vercel project ID. Found in your Vercel project settings.'
						),
					),
					array(
						'id' => 'vercel_team_id',
						'label' => 'Vercel Team ID (Optional)',
						'type' => 'text',
						'args' => array(
							'description' => 'Your Vercel team ID if deploying to a team project. Leave empty for personal projects.'
						),
					),
				)
			),
		);

		if ( $this->is_typesense_connected() ) {
			$fields['wooless_general_settings_section']['options'][] = array(
				'id' => 'show_free_shipping_banner',
				'label' => 'Show free shipping banner',
				'type' => 'checkbox',
				'args' => array(
					'description' => 'Check this to show shipping banner dynamically based on nearest free shipping rate.'
				),
			);

			$fields['wooless_general_settings_section']['options'][] = array(
				'id' => 'show_free_shipping_minicart_component',
				'label' => 'Show free shipping minicart component',
				'type' => 'checkbox',
				'args' => array(
					'description' => 'Check this to show shipping minicart component dynamically based on nearest free shipping rate.'
				),
			);

			$fields['wooless_general_settings_section']['options'][] = array(
				'id' => 'show_variant_as_separate_product_cards',
				'label' => 'Display separate variant product cards',
				'type' => 'checkbox',
				'args' => array(
					'description' => 'Check this to show variant as product cards in catalog pages or in any product list.'
				),
			);

			$fields['wooless_general_settings_section']['options'][] = array(
				'id' => 'font_family',
				'label' => 'Font Family',
				'type' => 'select',
				'args' => array(
					'options' => $font_families,
					'description' => 'Select the font family for your frontend pages',
				),
			);

		}



		return $fields;
	}



	public function section_callback() {
		echo '<p>Select which areas of content you wish to display.</p>';
	}

	public function footer_callback() {
		if ( $this->is_typesense_connected() ) :
			?>
			<a href="#" id="sync-product-link">Sync Products</a><br />
			<a href="#" id="sync-taxonomies-link">Sync Taxonomies</a><br />
			<a href="#" id="sync-menus-link">Sync Menus</a><br />
			<a href="#" id="sync-pages-link">Sync Pages</a><br />
			<a href="#" id="sync-site-info-link">Sync Site Info</a><br />
			<a href="#" id="sync-all-link">Sync All</a>
			<div id="sync-results-container"></div>

			<button id="redeploy" class="button button-primary">Redeploy Store Front</button>

			<?php
		endif;
	}

	public function register_additional_site_info( $additional_data ) {
		$additional_data['show_free_shipping_banner']              = json_encode( $this->get_option( 'show_free_shipping_banner' ) == 1 ?: false );
		$additional_data['show_free_shipping_minicart_component']  = json_encode( $this->get_option( 'show_free_shipping_minicart_component' ) == 1 ?: false );
		$additional_data['show_variant_as_separate_product_cards'] = json_encode( $this->get_option( 'show_variant_as_separate_product_cards' ) == 1 ?: false );
		$additional_data['font_family']                            = apply_filters( 'blazecommerce/settings/site/font_family', $this->get_option( 'font_family' ) );

		return $additional_data;
	}

	public function settings_callback( $input ) {
		$sanitized_input = array();

		// Sanitize all text inputs
		foreach ( $input as $key => $value ) {
			if ( $key === 'vercel_deployment_token' ) {
				// Special handling for Vercel token - encrypt it
				$sanitized_input[ $key ] = $this->encrypt_token( sanitize_text_field( $value ) );
			} elseif ( in_array( $key, array( 'typesense_api_key' ) ) ) {
				// Keep existing password fields as-is for backward compatibility
				$sanitized_input[ $key ] = sanitize_text_field( $value );
			} elseif ( $key === 'vercel_project_id' || $key === 'vercel_team_id' ) {
				// Validate Vercel IDs (alphanumeric with hyphens)
				$sanitized_value = sanitize_text_field( $value );
				if ( preg_match( '/^[a-zA-Z0-9\-_]+$/', $sanitized_value ) || empty( $sanitized_value ) ) {
					$sanitized_input[ $key ] = $sanitized_value;
				} else {
					add_settings_error( 'vercel_settings', $key, 'Invalid ' . $key . ' format. Only alphanumeric characters, hyphens, and underscores are allowed.' );
				}
			} else {
				// Default sanitization for other fields
				$sanitized_input[ $key ] = sanitize_text_field( $value );
			}
		}

		return $sanitized_input;
	}

	private function encrypt_token( $token ) {
		if ( empty( $token ) ) {
			return '';
		}

		// Use WordPress salts for encryption key
		$key = wp_salt( 'auth' );
		$iv = openssl_random_pseudo_bytes( 16 );
		$encrypted = openssl_encrypt( $token, 'AES-256-CBC', $key, 0, $iv );

		// Return base64 encoded IV + encrypted data
		return base64_encode( $iv . $encrypted );
	}

	private function decrypt_token( $encrypted_token ) {
		if ( empty( $encrypted_token ) ) {
			return '';
		}

		$key = wp_salt( 'auth' );
		$data = base64_decode( $encrypted_token );
		$iv = substr( $data, 0, 16 );
		$encrypted = substr( $data, 16 );

		return openssl_decrypt( $encrypted, 'AES-256-CBC', $key, 0, $iv );
	}

	public function get_option( $key = false, $default = false ) {
		$options = parent::get_option( $key, $default );

		// Decrypt Vercel token when retrieving
		if ( $key === 'vercel_deployment_token' && ! empty( $options ) ) {
			return $this->decrypt_token( $options );
		}

		return $options;
	}
}

GeneralSettings::get_instance();
