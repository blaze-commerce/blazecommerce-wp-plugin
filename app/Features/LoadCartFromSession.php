<?php


namespace BlazeWooless\Features;

class LoadCartFromSession
{
	private static $instance = null;

	public static function get_instance()
	{
		if (self::$instance === null) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct()
	{
		add_action('woocommerce_load_cart_from_session', array($this, 'woocommerce_load_cart_from_session'));
		add_action('init', array($this, 'load_user_from_session'));
		add_action('wp_footer', array($this, 'remove_session_id_from_url_script'));
		add_action( 'woocommerce_before_thankyou', array($this, 'clear_cart_data') );
		
	}


	public function woocommerce_load_cart_from_session()
	{
		if ($_SERVER['REQUEST_URI'] === '/graphql') {
			return;
		}
		$data_to_store = ['cart', 'applied_coupons', 'coupon_discount_totals', 'coupon_discount_tax_totals'];

		if (is_user_logged_in()) {
			$current_session = WC()->session;

			foreach ($data_to_store as $key) {
				$cookie_name = 'guest_session_' . $key;
				if ( isset( $_COOKIE[$cookie_name] ) ) {
					$unserialized_data = unserialize(urldecode($_COOKIE[$cookie_name]));
					if ('cart' === $key) {
						$account_cart_data = WC()->cart->get_cart();
						$merged_cart_data = array_merge($account_cart_data, $unserialized_data);

						$current_session->set($key, $merged_cart_data);
					} else {
						$current_session->set($key, $unserialized_data);
					}

					wc_setcookie( $cookie_name, '', time() - YEAR_IN_SECONDS );
				}
			}

			WC()->session = $current_session;
			return;
		}

		// Bail if there isn't any data
		if (!isset($_COOKIE['woocommerce_customer_session_id'])) {
			return;
		}

		$session_id = sanitize_text_field($_COOKIE['woocommerce_customer_session_id']);

		try {
			$handler = new \WC_Session_Handler();
			$session_data = $handler->get_session($session_id);

			// We were passed a session ID, yet no session was found. Let's log this and bail.
			if (empty($session_data)) {
				throw new \Exception('Could not locate WooCommerce session on checkout');
			}

			// Go get the session instance (WC_Session) from the Main WC Class
			$session = WC()->session;

			$is_guest = unserialize($session_data['customer'])['id'] == 0;

			// Set the session variable
			foreach ($session_data as $key => $value) {
				$session_value = unserialize($value);
				$session->set($key, $session_value);
				if ($is_guest && in_array($key, $data_to_store)) {
					wc_setcookie( 'guest_session_' . $key,  urlencode($value) );
				}
			}
		} catch (\Exception $exception) {
			// ErrorHandling::capture( $exception );
		}
	}

	public function load_user_from_session()
    {
		if ($_SERVER['REQUEST_URI'] === '/graphql') {
			return;
		}

		if (!isset($_COOKIE['woocommerce_customer_session_id']) || is_user_logged_in()) {
			return;
		}

		$session_id = sanitize_text_field($_COOKIE['woocommerce_customer_session_id']);

		try {
			$handler = new \WC_Session_Handler();
			$session_data = $handler->get_session($session_id);


			// We were passed a session ID, yet no session was found. Let's log this and bail.
			if (empty($session_data)) {
				throw new \Exception('Could not locate WooCommerce session on checkout');
			}

            if ($customer = $session_data['customer']) {
                $customer_data = unserialize($customer);
                $customer_id = $customer_data['id'];

                if ($customer_id) {
                    // Authenticate the user and set the authentication cookies
                    wp_set_auth_cookie($customer_id);
                }
            }
		} catch (\Exception $exception) {
			// ErrorHandling::capture( $exception );
		}
    }

	public function remove_session_id_from_url_script()
	{
        $restricted_pages = apply_filters('blaze_wooless_restricted_pages', is_cart());
        if ( $restricted_pages ) {
            wp_redirect(home_url());
            exit;
        }

        $pages_should_redirect_to_frontend = apply_filters('blaze_wooless_pages_should_redirect_to_frontend', is_shop() || is_product_category() || is_product());
        if ( $pages_should_redirect_to_frontend ) {
            wp_redirect(home_url( $_SERVER['REQUEST_URI'] ));
            exit;
        }

		if (isset($_COOKIE['isLoggedIn']) && $_COOKIE['isLoggedIn'] === 'false') {
			if (is_user_logged_in()) {
				wp_set_auth_cookie(0);
				wp_redirect(home_url( $_SERVER['REQUEST_URI'] ));
				exit;
			}
		}

        // if (!class_exists('WooCommerce') || (!isset($_COOKIE['woocommerce_customer_session_id']) && !isset($_GET['from_wooless']))) {
        //     return;
        // }

		// $url = remove_query_arg(['session_id', 'from_wooless'], $_SERVER['REQUEST_URI']);
		// wp_redirect(apply_filters('blaze_wooless_destination_url_from_frontend', $url));
		// exit;
	}

	function clear_cart_data( $order_id ){
		wc_setcookie( 'woocommerce_total_product_in_cart', '', time() - YEAR_IN_SECONDS );
		if (!is_user_logged_in()) {
			wc_setcookie( 'woo-session', '', time() - YEAR_IN_SECONDS );
		}
	}
}
