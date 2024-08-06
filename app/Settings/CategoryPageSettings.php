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

	public function register_hooks() {
		add_filter( 'blaze_wooless_additional_site_info', array( $this, 'register_additional_site_info' ), 10, 2 );
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
					array(
						'id' => 'default_product_sorting',
						'label' => 'Default Product Sorting',
						'type' => 'select',
						'args' => array( 
							'description' => 'Select the default sorting for products',
							'options' => array(
								'sort_0' => 'Sort By None',
								'sort_1' => 'Sort By Popularity',
								'sort_2' => 'Sort By Latest',
								'sort_3' => 'Sort By Price: low to high',
								'sort_4' => 'Sort By Price: high to low',
								'sort_5' => 'Sort By Alphabetical: A-Z',
								'sort_6' => 'Sort By Alphabetical: Z-A',
							), 
						),
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
				'id' => '10089554',
				'name' => 'category_page_default_banner',
				'value' => json_encode( array(
					'url' => $options['default_banner_link']
				) ),
				'updated_at' => time(),
			)
		);

		$site_info->upsert(
			array(
				'id' => '10089555',
				'name' => 'category_page_default_sort',
				'value' => json_encode( array(
					'sort_option' => $options['default_product_sorting']
				) ),
				'updated_at' => time(),
			)
		);

		do_action( 'blaze_wooless_save_category_page_settings', $options );
	}
	public function register_additional_site_info( $additional_data ) {
		$category_options = get_option( 'wooless_settings_category_page_options' );
		$default_banner_link = json_encode( $category_options['default_banner_link'] );
		$default_product_sorting = json_encode( $category_options['default_product_sorting'] );
		if( $default_banner_link ) {
			$additional_data['category_page_default_banner']              =  $default_banner_link;
		}
		if( $default_product_sorting ) {
			$additional_data['category_page_default_sort']              = $default_product_sorting;
		}

		return $additional_data;
	}
}

CategoryPageSettings::get_instance();
