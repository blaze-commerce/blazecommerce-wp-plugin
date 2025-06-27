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

		add_filter( 'blazecommerce/settings/product_page', array( $this, 'add_settings' ), 10, 2 );

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
				array(
					'id' => 'force_login_to_review',
					'label' => 'Force Login to Review',
					'type' => 'checkbox',
					'args' => array( 'description' => 'Check this to force login to review' ),
				),
				array(
					'id' => 'review_tab_title',
					'label' => 'Review Tab Title',
					'type' => 'text',
					'default' => 'Customer Review',
				),
				array(
					'id' => 'hide_rating_distribution',
					'label' => 'Hide Rating Distribution',
					'type' => 'checkbox',
					'args' => array( 'description' => 'Check this to hide rating distribution' ),
				)

			),

		);

		$product_page_settings['wooless_settings_review_section']['options'] =
			apply_filters(
				'blaze_wooless_review_setting_options',
				$product_page_settings['wooless_settings_review_section']['options']
			);

		return $product_page_settings;
	}

	public function add_settings( $documents, $options ) {

		$documents[] = array(
			'id' => '1002457',
			'name' => 'hide_review_tab',
			'value' => (bool) $options['hide_review_tab'],
			'updated_at' => time(),
		);

		$documents[] = array(
			'id' => '1002458',
			'name' => 'force_login_to_review',
			'value' => (bool) $options['force_login_to_review'],
			'updated_at' => time(),
		);

		$documents[] = array(
			'id' => '1002459',
			'name' => 'review_tab_title',
			'value' => $options['review_tab_title'],
			'updated_at' => time(),
		);

		$documents[] = array(
			'id' => '1002460',
			'name' => 'hide_rating_distribution',
			'value' => (bool) $options['hide_rating_distribution'],
			'updated_at' => time(),
		);

		return $documents;
	}
}
