<?php

use BlazeWooless\Settings\GeneralSettings;

function bw_get_general_settings( $key = false ) {
	$default_value = array(
		'api_key' => '',
		'environment' => 'test',
		'shop_domain' => '',
	);
	return GeneralSettings::get_instance()->get_option( $key, $default_value );
}

/**
 * Get Klaviyo API key from settings with fallback for migration
 *
 * @return string|null The Klaviyo API key or null if not set
 */
function bw_get_klaviyo_api_key() {
	$api_key = bw_get_general_settings( 'klaviyo_api_key' );

	// Migration: If no API key is set in settings but we have the old hardcoded value,
	// automatically migrate it to the settings system
	if ( empty( $api_key ) ) {
		$legacy_key = 'W7A7kP'; // The previously hardcoded key

		// Only migrate if this appears to be an existing installation
		if ( get_option( 'wooless_general_settings_options' ) !== false ) {
			// Set the legacy key in settings for seamless migration
			$current_settings = bw_get_general_settings();
			$current_settings['klaviyo_api_key'] = $legacy_key;
			update_option( 'wooless_general_settings_options', $current_settings );

			return $legacy_key;
		}
	}

	return $api_key;
}

