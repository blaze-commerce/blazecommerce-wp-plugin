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
		add_filter( 'blazecommerce/settings', array( $this, 'add_category_page_settings_to_documents' ), 10, 1 );
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
					array(
						'id' => 'container_max_width',
						'label' => 'Container Max Width (px)',
						'type' => 'text',
						'args' => array( 'description' => 'Enter Page Content Max Width', ),
					),
					array(
						'id' => 'container_padding',
						'label' => 'Container Padding (px)',
						'type' => 'text',
						'args' => array( 'description' => 'Enter Page Content Padding', ),
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

		$site_info->upsert(
			array(
				'id' => '10089556',
				'name' => 'category_page_max_width',
				'value' => json_encode( array(
					'container_max_width' => $options['container_max_width']
				) ),
				'updated_at' => time(),
			)
		);

		$site_info->upsert(
			array(
				'id' => '10089557',
				'name' => 'category_page_padding',
				'value' => json_encode( array(
					'container_padding' => $options['container_padding']
				) ),
				'updated_at' => time(),
			)
		);

		do_action( 'blaze_wooless_save_category_page_settings', $options );
	}
	public function add_category_page_settings_to_documents( $documents ) {
		$options = $this->get_option();

		if ( ! empty( $options ) ) {
			// Add category page default banner
			$documents[] = array(
				'id' => '10089554',
				'name' => 'category_page_default_banner',
				'value' => json_encode( array(
					'url' => isset( $options['default_banner_link'] ) ? $options['default_banner_link'] : ''
				) ),
				'updated_at' => time(),
			);

			// Add category page default sort
			$documents[] = array(
				'id' => '10089555',
				'name' => 'category_page_default_sort',
				'value' => json_encode( array(
					'sort_option' => isset( $options['default_product_sorting'] ) ? $options['default_product_sorting'] : ''
				) ),
				'updated_at' => time(),
			);

			// Add category page max width
			$documents[] = array(
				'id' => '10089556',
				'name' => 'category_page_max_width',
				'value' => json_encode( array(
					'container_max_width' => isset( $options['container_max_width'] ) ? $options['container_max_width'] : ''
				) ),
				'updated_at' => time(),
			);

			// Add category page padding
			$documents[] = array(
				'id' => '10089557',
				'name' => 'category_page_padding',
				'value' => json_encode( array(
					'container_padding' => isset( $options['container_padding'] ) ? $options['container_padding'] : ''
				) ),
				'updated_at' => time(),
			);

			do_action( 'blaze_wooless_save_category_page_settings', $options );
		}

		return $documents;
	}
}

CategoryPageSettings::get_instance();
