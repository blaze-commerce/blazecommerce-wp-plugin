<?php
namespace BlazeWooless\Extensions;

class Pinterest {
	private static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		add_filter( 'blazecommerce/settings/general/fields', array( $this, 'register_settings' ), 999, 1 );
		add_filter( 'blaze_wooless_additional_site_info', array( $this, 'register_additional_site_info' ), 10, 1 );
	}

	public function register_settings( $fields ) {
		$fields['wooless_general_settings_section']['options'][] = array(
			'id' => 'show_share_to_pinterest_button',
			'label' => 'Enable Pinterest Share',
			'type' => 'checkbox',
			'args' => array(
				'description' => 'Check this to show pinterest share button on the product cards image.'
			),
		);

		return $fields;
	}

	public function register_additional_site_info( $additional_data ) {
		$show_share_to_pinterest_button = bw_get_general_settings( 'show_share_to_pinterest_button');
		$additional_data['show_share_to_pinterest_button'] = json_encode( $show_share_to_pinterest_button == 1 ?: false );

		return $additional_data;
	}
}