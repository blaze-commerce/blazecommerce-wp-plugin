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
 * Get Klaviyo API key from settings with proper validation
 *
 * @return string|null The Klaviyo API key or null if not set
 */
function bw_get_klaviyo_api_key() {
	$api_key = bw_get_general_settings( 'klaviyo_api_key' );

	// Validate API key format if present
	if ( ! empty( $api_key ) && ! preg_match( '/^[A-Za-z0-9]{6,}$/', $api_key ) ) {
		error_log( 'Invalid Klaviyo API key format detected' );
		return null;
	}

	// If no API key is set, check if this is an existing installation that needs configuration
	if ( empty( $api_key ) && get_option( 'wooless_general_settings_options' ) !== false ) {
		// Log that configuration is needed - do not auto-populate any values
		error_log( 'Klaviyo API key needs to be configured in BlazeCommerce settings' );
	}

	return $api_key;
}

