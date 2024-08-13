<?php
namespace BlazeWooless\Extensions;

use BlazeWooless\TypesenseClient;

class ACFProductTabs {
	private static $instance = null;

	private $tabs = [ 
		1 => "one",
		2 => "two",
		3 => "three",
	];

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		add_filter( 'wooless_product_tabs', array( $this, 'sync_product_tabs' ), 999, 3 );
	}

	public function sync_product_tabs( $additional_tabs, $product_id, $product ) {

		foreach ( $this->tabs as $i => $key ) {
			$title = get_post_meta( $product_id, 'title_' . $key, true );
			$content = get_post_meta( $product_id, 'text_' . $key, true );

			if ( ! empty( $title ) ) {
				$additional_tabs[] = array(
					'title' => $title,
					'content' => $content,
				);
			}
		}

		return $additional_tabs;
	}
}