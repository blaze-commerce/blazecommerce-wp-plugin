<?php

use BlazeWooless\Settings\GeneralSettings;

function bw_get_general_settings( $key = false ) {
    return GeneralSettings::get_instance()->get_option( $key );
}

function bw_get_decoded_api_data( $api_key = false ) {
    if (!$api_key) {
        $api_key = bw_get_general_settings( 'api_key' );
    }
    $decoded_api_key = base64_decode($api_key);
    $trimmed_api_key = explode(':', $decoded_api_key);
    $typesense_api_key = $trimmed_api_key[0];
    $store_id = $trimmed_api_key[1];

    return array(
        'private_key' => $typesense_api_key,
        'store_id' => $store_id,
    );
}

function bw_get_private_key() {
    return bw_get_decoded_api_data()['private_key'];
}

function bw_get_store_id() {
    return bw_get_decoded_api_data()['store_id'];
}
