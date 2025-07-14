<?php

namespace BlazeWooless\Features;

class Authentication {
	private static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		add_action( 'wp_login', array( $this, 'login_hook' ), 10, 2 );
		add_action( 'user_register', array( $this, 'user_register_hook' ), 10, 2 );
		add_action( 'init', array( $this, 'init_logout_hook' ), 1 );
		add_action( 'wp_logout', array( $this, 'destroy_cookies' ), 1 );
		add_filter( 'graphql_jwt_auth_expire', array( $this, 'graphql_jwt_expiration' ), 20 );
	}

	public function has_ql_session_class() {
		if ( class_exists( '\WPGraphQL\WooCommerce\Utils\QL_Session_Handler' ) ) {
			return true;
		}
		return false;
	}

	public function set_cookies( $token, $user ) {
		// Set the cookie with a specific domain
		wc_setcookie( 'woo-session', $token );
		wc_setcookie( 'isLoggedIn', 'true' );
		$cart_meta = get_user_meta( $user->ID, '_woocommerce_persistent_cart_1', true );
		if ( ! empty( $cart_meta ) && is_array( $cart_meta ) ) {
			wc_setcookie( 'woocommerce_total_product_in_cart', count( $cart_meta['cart'] ) );
		}
	}

	public function destroy_cookies() {
		wc_setcookie( 'isLoggedIn', 'false' );
		wc_setcookie( 'didFetchUser', 'false' );
		wc_setcookie( 'woo-session', '', time() - YEAR_IN_SECONDS );
		wc_setcookie( 'woocommerce_customer_session_id', '', time() - YEAR_IN_SECONDS );
		wc_setcookie( 'woocommerce_total_product_in_cart', '', time() - YEAR_IN_SECONDS );
		wp_set_auth_cookie( 0 );
	}

	public function get_token() {
		if ( $this->has_ql_session_class() ) {
			$session = new \WPGraphQL\WooCommerce\Utils\QL_Session_Handler();
			$session->init_session_token();
			$token = $session->build_token();

			return $token;
		}

		return null;
	}

	public function login_hook( $user_login, $user ) {

		if ( $this->has_ql_session_class() ) {
			$token = $this->get_token();
			$this->set_cookies( $token, $user );
		}
	}

	public function init_logout_hook() {
		if ( isset( $_GET['action'] ) && $_GET['action'] == 'logout' ) {
			$this->destroy_cookies();
		}
	}

	public function user_register_hook( $user_id, $userdata ) {
		$user = get_user_by( 'ID', $user_id );

		if ( $this->has_ql_session_class() ) {
			$token = $this->get_token();
			$this->set_cookies( $token, $user );
		}
	}


	public function graphql_jwt_expiration( $expiration ) {
		return time() + 3600;
	}

}
