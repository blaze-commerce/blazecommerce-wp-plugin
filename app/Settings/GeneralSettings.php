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
	}

	public function settings_callback( $options ) {
		if ( isset( $options['api_key'] ) ) {
			$encoded_api_key   = sanitize_text_field( $options['api_key'] );
			$decoded_api_key   = base64_decode( $encoded_api_key );
			$trimmed_api_key   = explode( ':', $decoded_api_key );
			$typesense_api_key = $trimmed_api_key[0];
			$store_id          = $trimmed_api_key[1];

			$typesense_client = TypesenseClient::get_instance();
			$connection       = $typesense_client->test_connection( $typesense_api_key, $store_id, $options['environment'] );

			if ( 'success' === $connection['status'] ) {
				// TODO: remove private_key_master eventually
				update_option( 'private_key_master', $options['api_key'] );
				update_option( 'typesense_api_key', $typesense_api_key );
				update_option( 'store_id', $store_id );

				$variant_as_cards = false;
				if ( isset( $_POST['wooless_general_settings_options']['display_variant_as_separate_product_cards'] ) ) {
					$variant_as_cards = (bool) $_POST['wooless_general_settings_options']['display_variant_as_separate_product_cards'];
				}

				$typesense_client->site_info()->upsert( [ 
					'id' => '1002457',
					'name' => 'display_variant_as_separate_product_cards',
					'value' => json_encode( $variant_as_cards ),
					'updated_at' => time(),
				] );

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
		$fields = array(
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
				)
			),
		);

		if ( $this->connected() ) {
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
				'id' => 'display_variant_as_separate_product_cards',
				'label' => 'Display separate variant product cards',
				'type' => 'checkbox',
				'args' => array(
					'description' => 'Check this to show variant as product cards in catalog pages or in any product list.'
				),
			);
		}



		return $fields;
	}

	public function connected() {
		$typesense_api_key = get_option( 'typesense_api_key' );
		$store_id          = get_option( 'store_id' );
		$environment       = bw_get_general_settings( 'environment' );

		if ( empty( $typesense_api_key ) || empty( $store_id ) || empty( $environment ) ) {
			return false;
		}

		try {
			$connection = TypesenseClient::get_instance()->test_connection( $typesense_api_key, $store_id, $environment );
			return 'success' === $connection['status'];
		} catch (\Throwable $th) {
			return false;
		}
	}

	public function section_callback() {
		echo '<p>Select which areas of content you wish to display.</p>';
	}

	public function footer_callback() {
		$api_key = bw_get_general_settings( 'api_key' );
		if ( null !== $api_key && ! empty( $api_key ) ) :
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
		$additional_data['show_free_shipping_banner']                 = json_encode( $this->get_option( 'show_free_shipping_banner' ) == 1 ?: false );
		$additional_data['show_free_shipping_minicart_component']     = json_encode( $this->get_option( 'show_free_shipping_minicart_component' ) == 1 ?: false );
		$additional_data['display_variant_as_separate_product_cards'] = json_encode( $this->get_option( 'display_variant_as_separate_product_cards' ) == 1 ?: false );

		return $additional_data;
	}
}

GeneralSettings::get_instance();
