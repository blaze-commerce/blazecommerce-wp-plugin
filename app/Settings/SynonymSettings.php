<?php

namespace BlazeWooless\Settings;

use BlazeWooless\TypesenseClient;

class SynonymSettings extends BaseSettings {
	private static $instance = null;
	public $tab_key = 'synonyms';
	public $page_label = 'Synonyms Settings';

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self( 'wooless_synonym_settings_options' );
		}

		return self::$instance;
	}

	public function register_hooks() {
		// add_filter( 'blaze_wooless_additional_site_info', array( $this, 'register_additional_site_info' ), 10, 1 );
	}

	public function settings_callback( $options ) {

		if ( $_POST['synonym'] ) {
			$synonyms = array();
			foreach ( $_POST['synonym'] as $synonym ) {

				$words = array_map( 'trim', explode( ',', $synonym['words'] ) );

				if ( count( $words ) === 0 )
					return;

				$synonyms[] = array(
					'type' => $synonym['type'],
					'key' => $synonym['key'],
					'words' => array_map( 'trim', explode( ',', $synonym['words'] ) )
				);

				TypesenseClient::get_instance()->set_synonym( $synonym['type'], $words, $synonym['key'] );
			}

			update_option( 'wooless_synonyms', $synonyms );
		}

		return $options;
	}

	public function settings() {
		return [];
	}

	public function footer_callback() {
		require_once BLAZE_WOOLESS_PLUGIN_DIR . 'views/synonym-management.php';
	}

	public static function get_selected_synonyms() {
		return self::get_instance()->get_option( 'synonyms' );
	}

	public function register_additional_site_info( $additional_site_info ) {
		$synonyms = $this->get_option( 'synonyms' );
		if ( count( $additional_site_info ) > 0 ) {
			$additional_site_info['synonyms'] = array();
			foreach ( $synonyms as $region ) {
				$region_mappings = $this->get_option( 'region_' . $region );
				$additional_site_info['synonyms'][ $region ] = $region_mappings ?? [];
			}
		}
		return $additional_site_info;
	}
}
