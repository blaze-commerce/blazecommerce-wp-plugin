<?php

namespace BlazeWooless\Extensions;

class AdvancedCustomFields {
	private static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		if ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'advanced-custom-fields/acf.php' ) ) {
			add_action( 'admin_init', array( $this, 'test' ) );
		}
	}

}