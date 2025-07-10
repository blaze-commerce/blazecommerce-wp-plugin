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

	public function register_hooks() {
		add_filter( 'blaze_wooless_additional_site_info', array( $this, 'register_additional_site_info' ), 10, 1 );
	}

	public function settings() {
		if ( class_exists( 'WooCommerce' ) ) {

			$fields = array(
				array(
					'id' => 'regions',
					'label' => 'Regions',
					'type' => 'multiselect',
					'args' => array(
						'options' => \WC()->countries->get_countries(),
						'placeholder' => 'Select countries',
					),
				),
			);


			if ( ! empty( $regions = $this->get_option( 'regions' ) ) ) {
				$fields[] = array(
					'id' => 'regions_header',
					'label' => 'Map Regions',
					'type' => 'heading',
				);

				foreach ( $regions as $region ) {
					$fields[] = array(
						'id' => 'region_' . $region,
						'label' => $region,
						'type' => 'multiselect',
						'args' => array(
							'options' => \WC()->countries->get_countries(),
							'placeholder' => 'Select countries',
							'description' => 'Select countries you want to map to the front-end. If a country is not found it will use the next country.',
						),
					);
				}
			}

			return $fields = array(
				'wooless_regional_settings_section' => array(
					'label' => 'Regional Data',
					'options' => $fields,
				),
			);

		}

		return [];
	}

	public static function get_selected_regions() {
		return self::get_instance()->get_option( 'regions' );
	}

	public function section_callback() {
		echo '<p>Select which areas of content you wish to display.</p>';
	}

	public function register_additional_site_info( $additional_site_info ) {
		$regions = $this->get_option( 'regions' );
		if ( count( $additional_site_info ) > 0 ) {
			$additional_site_info['regions'] = array();
			foreach ( $regions as $region ) {
				$region_mappings = $this->get_option( 'region_' . $region );
				$additional_site_info['regions'][ $region ] = $region_mappings ?? [];
			}
		}
		return $additional_site_info;
	}
}
