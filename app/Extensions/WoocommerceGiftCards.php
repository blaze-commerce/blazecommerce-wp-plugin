<?php

namespace BlazeWooless\Extensions;

class WoocommerceGiftCards {
	private static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		if ( is_plugin_active( 'pw-gift-cards/pw-gift-cards.php' ) ) {
			add_filter( 'blaze_commerce_giftcard_info', array( $this, 'giftcard_email_content' ), 10, 1 );
		}
	}

	public function giftcard_email_content( $additional_settings ) {
		if ( $ec_supreme_all_header_logo = get_option( 'ec_supreme_all_header_logo' ) ) {
			$additional_settings['ec_supreme_all_header_logo'] = $ec_supreme_all_header_logo;
		}

		if ( $ec_supreme_all_footer_text = get_option( 'ec_supreme_all_footer_text' ) ) {
			$additional_settings['ec_supreme_all_footer_text'] = wpautop( $ec_supreme_all_footer_text );
		}

		return $additional_settings;
	}
}
