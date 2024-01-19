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

	public function settings_callback( $options ) {
		if ( isset( $options['api_key'] ) ) {
			$encoded_api_key   = sanitize_text_field( $options['api_key'] );
			$decoded_api_key   = base64_decode( $encoded_api_key );
			$trimmed_api_key   = explode( ':', $decoded_api_key );
			$typesense_api_key = $trimmed_api_key[0];
			$store_id          = $trimmed_api_key[1];

			$connection = TypesenseClient::get_instance()->test_connection( $typesense_api_key, $store_id, $options['environment'] );
			// var_dump($connection); exit;
			if ( $connection['status'] === 'success' ) {
				// TODO: remove private_key_master eventually
				update_option( 'private_key_master', $options['api_key'] );
				update_option( 'typesense_api_key', $typesense_api_key );
				update_option( 'store_id', $store_id );
			} else {
				add_settings_error(
					'blaze_settings_error',
					esc_attr( 'settings_updated' ),
					$connection['message'],
					'error'
				);
			}
		}

		return $options;
	}

	public function settings() {
		return array(
			'wooless_general_settings_section' => array(
				'label' => 'General Settings',
				'options' => array(
					array(
						'id' => 'environment',
						'label' => 'Environment',
						'type' => 'select',
						'args' => array(
								'description' => 'Select which environment to use.',
								'options' => array(
									'test' => 'Test',
									'live' => 'Live',
								),
							),
					),
					array(
						'id' => 'api_key',
						'label' => 'API Key',
						'type' => 'password',
						'args' => array(
								'description' => 'API Key generated from the Blaze Commerce Admin Portal.'
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
						'id' => 'show_free_shipping_banner',
						'label' => 'Show free shipping banner',
						'type' => 'checkbox',
						'args' => array(
							'description' => 'Check this to show shipping banner dynamically based on nearest free shipping rate.'
						),
					),
				)
			),
		);
	}

	public function section_callback() {
		echo '<p>Select which areas of content you wish to display.</p>';
	}

	public function footer_callback() {
		$api_key = bw_get_general_settings( 'api_key' );
		if ( $api_key !== null && $api_key !== '' ) :
			?>
			<a href="#" id="sync-product-link">Sync Products</a><br />
			<a href="#" id="sync-taxonomies-link">Sync Taxonomies</a><br />
			<a href="#" id="sync-menus-link">Sync Menus</a><br />
			<a href="#" id="sync-pages-link">Sync Pages</a><br />
			<a href="#" id="sync-site-info-link">Sync Site Info</a><br />
			<a href="#" id="sync-all-link">Sync All</a>
			<div id="sync-results-container"></div>
			<?php
		endif;
	}
}

GeneralSettings::get_instance();
