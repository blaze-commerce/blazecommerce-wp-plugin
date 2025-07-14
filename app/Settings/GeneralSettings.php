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

		add_action( 'wp_enqueue_scripts', function () {
			$theme = wp_get_theme();
			wp_enqueue_style(
				'blazecommerce-frontend-style',
				$this->remove_cart_from_url( home_url( 'frontend.css' ) ),
				[],
				$theme->get( 'Version' ) // Cache-busting with child theme's version
			);
		}, 999 );
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

<<<<<<< HEAD
		$is_local = array_key_exists( 'HTTP_X_FORWARDED_HOST', $_SERVER ) && strpos( $_SERVER['HTTP_X_FORWARDED_HOST'], 'localhost' ) !== false;
=======
		$forwarded_host = isset( $_SERVER['HTTP_X_FORWARDED_HOST'] ) ? sanitize_text_field( $_SERVER['HTTP_X_FORWARDED_HOST'] ) : '';
		$is_local = strpos( $forwarded_host, 'localhost' ) !== false;
>>>>>>> main
		if ( isset( $_REQUEST['no-redirect'] ) || $is_local ) {
			return;
		}

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

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( $_SERVER['REQUEST_URI'] ) : '';
		$is_my_account_page = strpos( $request_uri, 'my-account' ) !== false;
		$exclude_page_redirect_to_frontend = apply_filters( 'blaze_wooless_exclude_page_redirect_to_frontend', is_checkout() );
		if ( $exclude_page_redirect_to_frontend || $is_my_account_page ) {
			//Since the page is excluded from redirecting to frontend then we just end the function here
			return;
		}

		$server_name = isset( $_SERVER['SERVER_NAME'] ) ? sanitize_text_field( $_SERVER['SERVER_NAME'] ) : '';
		$has_cart_in_url = strpos( $server_name, 'cart.' ) !== false;
		$from_vercel_proxy_request = isset( $_SERVER['HTTP_X_VERCEL_PROXY_SIGNATURE'] ) ? true : false;

		// if the url has cart. on it and the request is not from vercel then we redirect it to frontend page without cart in the url
		if ( $has_cart_in_url && ! $from_vercel_proxy_request ) {
			wp_redirect( $this->remove_cart_from_url( home_url( $request_uri ) ) );
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
		$fields = array(
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
					array(
						'id' => 'klaviyo_api_key',
						'label' => 'Klaviyo API Key',
						'type' => 'password',
						'args' => array(
							'description' => 'Klaviyo API key for integration. Leave empty to disable Klaviyo tracking.'
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

			$fields['wooless_general_settings_section']['options'][] = array(
				'id' => 'enable_geo_restrictions',
				'label' => 'Enable Geo Restrictions',
				'type' => 'checkbox',
				'args' => array(
					'description' => 'Check this to enable geo restrictions.'
				),
			);

			$fields['wooless_general_settings_section']['options'][] = array(
				'id' => 'enable_override_best_seller',
				'label' => 'Override Best Seller Functionality',
				'type' => 'checkbox',
				'args' => array(
					'description' => 'Check this to override the best seller products functionality.'
				),
			);
		}
		;

		return apply_filters( 'blazecommerce/settings/general/fields', $fields );
	}



	public function section_callback() {
		echo '<p>Select which areas of content you wish to display.</p>';
	}

	public function field_callback_password( $args ) {
		// Add capability check for sensitive API key fields
		if ( isset( $args['id'] ) && in_array( $args['id'], array( 'klaviyo_api_key', 'typesense_api_key' ) ) ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				echo '<p><em>You do not have sufficient permissions to access this setting.</em></p>';
				return;
			}
		}

		$value = $this->get_option( $args['id'] );
		$html = '<input type="password" id="' . $args['id'] . '" name="' . $this->option_key . '[' . $args['id'] . ']" value="' . sanitize_text_field( $value ) . '" />';
		$html .= $this->render_field_description( $args );
		echo $html;
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
		$additional_data['show_free_shipping_banner'] = json_encode( $this->get_option( 'show_free_shipping_banner' ) == 1 ?: false );
		$additional_data['show_free_shipping_minicart_component'] = json_encode( $this->get_option( 'show_free_shipping_minicart_component' ) == 1 ?: false );
		$additional_data['show_variant_as_separate_product_cards'] = json_encode( $this->get_option( 'show_variant_as_separate_product_cards' ) == 1 ?: false );
		$additional_data['enable_geo_restrictions'] = json_encode( $this->get_option( 'enable_geo_restrictions' ) == 1 ?: false );
		$additional_data['enable_override_best_seller'] = json_encode( $this->get_option( 'enable_override_best_seller' ) == 1 ?: false );
		$additional_data['font_family'] = apply_filters( 'blazecommerce/settings/site/font_family', $this->get_option( 'font_family' ) );

		return $additional_data;
	}
}

GeneralSettings::get_instance();
