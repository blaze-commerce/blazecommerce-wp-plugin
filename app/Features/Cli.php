<?php

namespace BlazeWooless\Features;

use BlazeWooless\Woocommerce;

class Cli {
	private static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			add_action( 'cli_init', array( $this, 'init' ) );
		}
	}

	public function init() {

	}


}