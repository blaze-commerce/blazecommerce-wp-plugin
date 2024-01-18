<?php

namespace BlazeWooless\Settings;

use BlazeWooless\TypesenseClient;

class RegionalSettings extends BaseSettings {
	private static $instance = null;
	public $tab_key = 'regions';
	public $page_label = 'Regional Settings';

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self( 'wooless_regional_settings_options', 'wooless_regional_settings_section', 'Regional Settings' );
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
			'wooless_regional_settings_section' => array(
				'label' => 'Regional Data',
				'options' => array(
					array(
						'id' => 'regions',
						'label' => 'Regions',
						'type' => 'multiselect',
						'args' => array(
							'options' => \WC()->countries->get_countries(),
							'placeholder' => 'Select countries',
						),
					),
				),
			),
		);
	}

	public static function get_selected_regions() {
		return self::get_instance()->get_option( 'regions' );
	}

	public function section_callback() {
		echo '<p>Select which areas of content you wish to display.</p>';
	}
}
