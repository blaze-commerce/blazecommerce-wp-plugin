<?php

function custom_jwt_auth_secret_key() {
    $auth_key = wp_salt('auth');
    $jwt_key = get_option('wooless_custom_jwt_secret_key', $auth_key);

	return $jwt_key;
}

add_filter('graphql_jwt_auth_secret_key', 'custom_jwt_auth_secret_key', 10);


function custom_jwt_expiration( $expiration='' ) {
    return 3600;
}

add_filter('graphql_jwt_auth_expire', 'custom_jwt_expiration', 10);