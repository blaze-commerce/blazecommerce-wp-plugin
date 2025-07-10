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

