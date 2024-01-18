<?php

namespace BlazeWooless\Extensions;

use GraphQL\Error\UserError;

class GraphQL {
	private static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {

		if ( $this->is_extension_active() ) {
			add_filter( 'graphql_jwt_auth_secret_key', [ $this, 'auth_secret_key' ], 10 );
			add_filter( 'graphql_jwt_auth_expire', [ $this, 'auth_expiration' ], 10 );

			$this->maybe_define_auth_cookies();
			add_action( 'graphql_register_types', [ $this, 'register_types' ] );

			add_filter( 'graphql_response_headers_to_send', [ $this, 'modify_response_headers' ], 20 );
			add_filter( 'graphql_access_control_allow_headers', [ $this, 'modify_access_control_allow_headers' ], 20, 1 );

			add_filter( 'blaze_wooless_additional_graphql_info', [ $this, 'woographql_is_composite_enabled' ], 10, 1 );

			add_action( 'init', [ $this, 'maybe_save_jwt_secret' ] );
		}
	}

	public function maybe_save_jwt_secret() {
		$jwt_key = get_option( 'wooless_custom_jwt_secret_key' );

		if ( ! $jwt_key ) {
			$auth_key = wp_salt( 'auth' );
			add_option( 'wooless_custom_jwt_secret_key', $auth_key );
		}
	}

	/**
	 * Tells the browser to accept the custom cookie when loggin in from headless site
	 */
	public function modify_response_headers( $headers ) {
		$http_origin     = get_http_origin();
		$allowed_origins = [ 
			'http://localhost:3000',
			'https://blaze-commerce-live-simulation.blz.onl',
			home_url(),
			site_url(),
		];

		// If the request is coming from an allowed origin (HEADLESS_FRONTEND_URL), tell the browser it can accept the response.
		if ( in_array( $http_origin, $allowed_origins, true ) ) {
			$headers['Access-Control-Allow-Origin'] = $http_origin;
		}

		// Tells browsers to expose the response to frontend JavaScript code when the request credentials mode is "include".
		$headers['Access-Control-Allow-Credentials'] = 'true';
		$headers['Access-Control-Expose-Headers']    = $headers['Access-Control-Expose-Headers'] . ', set-cookie, woocommerce-session';

		return $headers;
	}


	public function register_types() {
		$this->login_mutation();
		$this->logout_mutation();
	}

	public function logout_mutation() {
		register_graphql_mutation(
			'logout',
			array(
				'inputFields' => array(),
				'outputFields' => array(
					'status' => array(
						'type' => 'String',
						'description' => 'Logout operation status',
						'resolve' => function ($payload) {
							return $payload['status'];
						},
					),
				),
				'mutateAndGetPayload' => function () {
					// Logout and destroy session.
					wp_set_auth_cookie( 0 );
					wp_logout();

					return array( 'status' => 'SUCCESS' );
				},
			)
		);
	}

	public function login_mutation() {
		register_graphql_mutation(
			'loginWithCookies',
			array(
				'inputFields' => array(
					'login' => array(
						'type' => array( 'non_null' => 'String' ),
						'description' => __( 'Input your username/email.' ),
					),
					'password' => array(
						'type' => array( 'non_null' => 'String' ),
						'description' => __( 'Input your password.' ),
					),
				),
				'outputFields' => array(
					'status' => array(
						'type' => 'String',
						'description' => 'Login operation status',
						'resolve' => function ($payload) {
							return $payload['status'];
						},
					),
				),
				'mutateAndGetPayload' => function ($input) {
					$user = wp_signon(
						array(
							'user_login' => wp_unslash( $input['login'] ),
							'user_password' => $input['password'],
						),
						true
					);

					if ( is_wp_error( $user ) ) {
						throw new UserError( ! empty( $user->get_error_code() ) ? $user->get_error_code() : 'invalid login' );
					}

					return array( 'status' => 'SUCCESS' );
				},
			)
		);
	}

	public function maybe_define_auth_cookies() {
		if ( ! defined( 'GRAPHQL_JWT_AUTH_SET_COOKIES' ) ) {
			/**
			 * We need this constant to be true so that cookies when logging in can be shared to the frontend app
			 */
			define( 'GRAPHQL_JWT_AUTH_SET_COOKIES', true );
		}
	}

	public function is_extension_active() {
		return is_plugin_active( 'wp-graphql/wp-graphql.php' );
	}

	public function auth_expiration( $expiration = '' ) {
		return 3600;
	}

	public function auth_secret_key() {
		$auth_key = wp_salt( 'auth' );
		$jwt_key  = get_option( 'wooless_custom_jwt_secret_key', $auth_key );

		return $jwt_key;
	}

	public function modify_access_control_allow_headers( $allowed_headers ) {
		$allowed_headers[] = 'woocommerce-session';
		$allowed_headers[] = 'x-requested-with';
		return $allowed_headers;
	}

	public function woographql_is_composite_enabled( $additional_settings ) {
		if ( $woographql_settings = get_option( 'woographql_settings' ) ) {
			if ( $woographql_settings['composite_products'] === 'on' ) {
				$additional_settings['woographql_is_composite_enabled'] = true;
			}
		}

		return $additional_settings;
	}
}
