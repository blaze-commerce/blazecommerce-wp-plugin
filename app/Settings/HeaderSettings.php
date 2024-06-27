<?php

namespace BlazeWooless\Settings;

use BlazeWooless\TypesenseClient;

class HeaderSettings extends BaseSettings {
	private static $instance = null;
	public $tab_key = 'header';
	public $page_label = 'Header';
	public $setting_page_name = 'blaze-settings-header';

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self( 'wooless_header_settings_options' );
		}

		return self::$instance;
	}

	public function settings_callback( $options ) {
		return $options;
	}

	public function settings() {
		return array();
	}

	public function get_header_post() {
		$args = array(
			'post_type' => 'blaze_settings',
			'name' => $this->setting_page_name,
		);
		$the_query = new \WP_Query( $args );
		
		if (!$the_query->have_posts()) {
			return null;
		}

		return $the_query->posts[0];
	}

	public function footer_callback() {

		$post = $this->get_header_post();

		if (!$post) {
			$new_post = array(
				'post_title' => 'Header',
				'post_type' => 'blaze_settings',
				'post_name' => $this->setting_page_name,
				'post_category' => array(0)
			);
			$post_id = wp_insert_post($new_post);
		} else {
			$post_id = $post->ID;
		}

		$edit_link = get_edit_post_link( $post_id, '&' );
		wp_redirect( $edit_link );
	}

	public function section_callback() {
		echo '<p>Select which areas of content you wish to display.</p>';
	}

	public function register_hooks()
	{
		add_filter( 'set_blaze_setting_data', array( $this, 'set_blaze_setting_data' ), 10, 2 );
		remove_action( 'generate_header', 'generate_construct_header' );
		add_action( 'generate_header', array( $this, 'render_wp_header' ), 10 );
		add_action( 'blaze_wooless_after_site_info_sync', array( $this, 'save_on_site_info_sync' ), 10 );
	}

	public function set_blaze_setting_data( $blaze_settings, $post_id ) {
		$blaze_settings[ $this->setting_page_name ] = array(
			'id' => (string) $post_id,
			'name' => 'site-header',
			'value' => get_post_field( 'post_content', $post_id ),
			'updated_at' => time(),
		);
		return $blaze_settings;
	}

	public function render_wp_header() {
		$post = $this->get_header_post();

		$post_content = $post->post_content;

		echo apply_filters('the_content', $post_content);
	}

	public function save_on_site_info_sync() {
		$post = $this->get_header_post();
		if ($post) {
			$post_id = $post->ID;
			TypesenseClient::get_instance()
                ->site_info()
                ->upsert( array(
					'id' => (string) $post_id,
					'name' => 'site-header',
					'value' => get_post_field( 'post_content', $post_id ),
					'updated_at' => time(),
				) );
		}
	}
}

HeaderSettings::get_instance();
