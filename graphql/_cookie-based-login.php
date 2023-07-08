<?php

use GraphQL\Error\UserError;

define('GRAPHQL_JWT_AUTH_SET_COOKIES', true);

/**
 * Adding wpgraphql mutation login with cookies.
 * This allows us to share cookies and login state when user access the wp url
 */
add_action('graphql_register_types', function () {
    register_graphql_mutation(
        'loginWithCookies',
        array(
            'inputFields' => array(
                'login'      => array(
                    'type'        => array( 'non_null' => 'String' ),
                    'description' => __('Input your username/email.'),
                ),
                'password'   => array(
                    'type'        => array( 'non_null' => 'String' ),
                    'description' => __('Input your password.'),
                ),
            ),
            'outputFields'        => array(
                'status' => array(
                    'type'        => 'String',
                    'description' => 'Login operation status',
                    'resolve'     => function ($payload) {
                        return $payload['status'];
                    },
                ),
            ),
            'mutateAndGetPayload' => function ($input) {
                $user = wp_signon(
                    array(
                        'user_login'    => wp_unslash($input['login']),
                        'user_password' => $input['password'],
                    ),
                    true
                );

                if (is_wp_error($user)) {
                    throw new UserError(! empty($user->get_error_code()) ? $user->get_error_code() : 'invalid login');
                }

                return array( 'status' => 'SUCCESS' );
            },
        )
    );
});

/**
 * Tells the browser to accept the custom cookie when loggin in from headless site
 *
 */
add_filter('graphql_response_headers_to_send', function ($headers) {
    $http_origin     = get_http_origin();
    $allowed_origins = [
        'http://localhost:3000',
    ];

    // If the request is coming from an allowed origin (HEADLESS_FRONTEND_URL), tell the browser it can accept the response.
    if (in_array($http_origin, $allowed_origins, true)) {
        $headers['Access-Control-Allow-Origin'] = $http_origin;
    }

    // Tells browsers to expose the response to frontend JavaScript code when the request credentials mode is "include".
    $headers['Access-Control-Allow-Credentials'] = 'true';

    return $headers;
}, 20);