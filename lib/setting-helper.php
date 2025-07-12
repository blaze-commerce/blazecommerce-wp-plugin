<?php

use BlazeWooless\Settings\GeneralSettings;

function bw_get_general_settings( $key = false ) {
	$default_value = array(
		'api_key' => '',
		'environment' => 'test',
		'shop_domain' => '',
		'klaviyo_api_key' => '',
	);
	return GeneralSettings::get_instance()->get_option( $key, $default_value );
}

/**
 * Get Klaviyo API key securely from settings or environment variable
 *
 * @return string|null The Klaviyo API key or null if not configured
 */
function bw_get_klaviyo_api_key() {
	// First try to get from environment variable (highest priority)
	$api_key = getenv('KLAVIYO_API_KEY');
	if ( ! empty( $api_key ) ) {
		return sanitize_text_field( $api_key );
	}

	// Fallback to WordPress option
	$settings = bw_get_general_settings();
	$api_key = $settings['klaviyo_api_key'] ?? '';

	if ( ! empty( $api_key ) ) {
		return sanitize_text_field( $api_key );
	}

	return null;
}

