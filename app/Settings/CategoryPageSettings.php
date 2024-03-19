<?php

namespace BlazeWooless\Settings;

use BlazeWooless\Features\AttributeSettings;
use BlazeWooless\TypesenseClient;

class CategoryPageSettings extends BaseSettings {
	private static $instance = null;
	public $tab_key = 'category';
	public $page_label = 'Category Page';

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self( 'wooless_settings_category_page_options' );
		}

		return self::$instance;
	}

	public function settings_callback( $options ) {
		try {
			$this->update_fields( $options );
		} catch (\Throwable $th) {

		}

		return $options;
	}

	public function settings() {
		$category_page_settings = [ 
			'wooless_settings_category_page_section' => array(
				'label' => 'Category Page',
				'options' => array(
					array(
						'id' => 'default_banner_link',
						'label' => 'Default Banner Link',
						'type' => 'text',
						'args' => array( 'description' => 'Input the Default Banner Image Link', ),
					),
				)
			),
		];

		return apply_filters( 'blaze_wooless_category_page_settings', $category_page_settings );
	}

	public function section_callback() {
		echo '<p>Select which areas of content you wish to display.</p>';
	}

	public function update_fields( $options ) {
		$site_info = TypesenseClient::get_instance()->site_info();

		$site_info->upsert(
			array(
				'id' => '10089553',
				'name' => 'category_page_default_banner',
				'value' => json_encode( array(
					'url' => $options['default_banner_link']
				) ),
				'updated_at' => time(),
			)
		);

		do_action( 'blaze_wooless_save_category_page_settings', $options );
	}
}

CategoryPageSettings::get_instance();
