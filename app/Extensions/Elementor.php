<?php

namespace BlazeWooless\Extensions;

class Elementor {
	private static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {

		if ( ! defined( "ELEMENTOR__FILE__" ) )
			return;

		add_filter( 'elementor/editor/localize_settings', array( $this, 'fix_elementor_compatibility' ), 99999 );
	}

	/**
	 * Fix issue with elementor editor
	 * Replace home_url with site_url
	 * @param   array $env
	 * @return 	array
	 */
	public function fix_elementor_compatibility( $env ) {


		if ( ! isset( $env['initial_document']['urls'] ) || ! is_array( $env['initial_document']['urls'] ) )
			return $env;

		$site_url = get_site_url();
		$home_url = get_home_url();

		$env['initial_document']['urls'] = array_map( function ($value) use ($site_url, $home_url) {
			return str_replace( $home_url, $site_url, $value );
		}, $env['initial_document']['urls'] );

		$env['home_url'] = $site_url;

		return $env;
	}

}