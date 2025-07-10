<?php

namespace BlazeWooless\Features;

use BlazeWooless\TypesenseClient;

class Review {
	private static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		// add_filter( 'blaze_wooless_additional_site_info', array( $this, 'register_additional_site_info' ), 10, 1 );
		add_filter( 'blaze_wooless_product_page_settings', array( $this, 'register_settings' ) );
		add_action( 'blaze_wooless_save_product_page_settings', array( $this, 'save_settings' ) );
	}

	public function register_settings( $product_page_settings ) {
		$product_page_settings['wooless_settings_review_section'] = array(
			'label' => 'Review',
			'options' => array(
				array(
					'id' => 'hide_review_tab',
					'label' => 'Hide from Tab',
					'type' => 'checkbox',
					'args' => array( 'description' => 'Check this to hide review section in tab. You can display it manually from page builder' ),
				),

			),

		);

		$product_page_settings['wooless_settings_review_section']['options'] =
			apply_filters(
				'blaze_wooless_review_setting_options',
				$product_page_settings['wooless_settings_review_section']['options']
			);

		return $product_page_settings;
	}

	public function save_settings( $options ) {
		$site_info = TypesenseClient::get_instance()->site_info();

		$site_info->upsert( [ 
			'id' => '1002457',
			'name' => 'hide_review_tab',
			'value' => (bool) $options['hide_review_tab'],
			'updated_at' => time(),
		] );
	}
}
