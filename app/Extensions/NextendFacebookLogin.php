<?php

namespace BlazeWooless\Extensions;

class NextendFacebookLogin {
	private static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		if ( function_exists( 'is_plugin_active' ) &&
			(
				is_plugin_active( 'nextend-facebook-login/nextend-facebook-login.php' ) ||
				is_plugin_active( 'nextend-social-login-pro/nextend-social-login-pro.php' )
			)
		) {
			add_filter( 'blazecommerce/settings/product_page', array( $this, 'add_settings' ), 10, 2 );
		}
	}

	public function add_settings( $documents ) {

		$nextend_social_setup = [];

		// Make sure the class exists
		if ( class_exists( 'NextendSocialProviderFacebook' ) ) {

			// Get facebook provider instance
			$provider = \NextendSocialLogin::$providers['facebook'];

			if ( $provider ) {

				// Get settings
				$settings = $provider->settings;

				// Get app id and app secret
				$app_id = $settings->get( 'appid' );
				$app_secret = $settings->get( 'secret' );

				$nextend_social_setup['facebook'] = array(
					'app_id' => $app_id,
					'app_secret' => $app_secret
				);
			}
		}

		// Make sure the class exists
		if ( class_exists( 'NextendSocialProviderGoogle' ) ) {

			// Get google provider instance
			$provider = \NextendSocialLogin::$providers['google'];

			if ( $provider ) {

				// Dapatkan settings dari provider
				$settings = $provider->settings;

				// Ambil Client ID dan Client Secret
				$client_id = $settings->get( 'client_id' );
				$client_secret = $settings->get( 'client_secret' );

				$nextend_social_setup['google'] = array(
					'client_id' => $client_id,
					'client_secret' => $client_secret
				);

			}
		}

		$documents[] = array(
			'id' => '1002461',
			'name' => 'nextend_social_login',
			'value' => json_encode( $nextend_social_setup ),
			'updated_at' => time(),
		);

		return $documents;
	}
}