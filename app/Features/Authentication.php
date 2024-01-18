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

    public function __construct()
    {
		add_action('wp_login', array( $this, 'login_hook' ), 10, 2);
		add_action('user_register', array($this, 'user_register_hook'), 10, 2);
		add_action('init', array( $this, 'init_logout_hook'), 1);
		add_action('wp_logout', array( $this, 'logout_hook' ), 1);
        add_filter('graphql_jwt_auth_secret_key', array( $this, 'auth_secret_key' ), 20);
        add_filter('graphql_woocommerce_secret_key', array( $this, 'auth_secret_key' ), 20);
        add_filter('graphql_jwt_auth_expire', array( $this, 'graphql_jwt_expiration' ), 20);
        // add_action( 'wp_footer', array( $this, 'ajax_sign_in_script' ), 40);
    }

	public function login_hook( $user_login, $user ) {
		add_filter( 'graphql_jwt_auth_token_before_sign', array( $this, 'modify_jwt_auth_token_before_sign' ), 10, 2 );
		add_filter( 'graphql_jwt_auth_secret_key', array( $this, 'auth_secret_key' ), 20 );
		$token = \WPGraphQL\JWT_Authentication\Auth::get_token( $user, false );

        // Set the cookie with a specific domain
		wc_setcookie( 'woo-session', $token );
		wc_setcookie( 'isLoggedIn', 'true' );
		$cart_meta = get_user_meta($user->ID, '_woocommerce_persistent_cart_1', true);
		wc_setcookie( 'woocommerce_total_product_in_cart', count($cart_meta['cart']) );
    }
	
	public function logout_hook()
	{
		wc_setcookie( 'woo-session', '', time() - YEAR_IN_SECONDS );
		wc_setcookie( 'woocommerce_customer_session_id', '', time() - YEAR_IN_SECONDS );
		wc_setcookie( 'woocommerce_total_product_in_cart', '', time() - YEAR_IN_SECONDS );
		wp_set_auth_cookie(0);
	}
	
	public function init_logout_hook() {
		if (isset($_GET)) {
			if (isset($_GET['action'])) {
				if ($_GET['action'] == 'logout') {
					wc_setcookie( 'woo-session', '', time() - YEAR_IN_SECONDS );
					wc_setcookie( 'woocommerce_customer_session_id', '', time() - YEAR_IN_SECONDS );
					wc_setcookie( 'woocommerce_total_product_in_cart', '', time() - YEAR_IN_SECONDS );
					wp_set_auth_cookie(0);
				}
			}
		}	
	}

	public function user_register_hook( $user_id, $userdata )
	{
		$user = get_user_by('ID', $user_id);
		add_filter( 'graphql_jwt_auth_token_before_sign', array( $this, 'modify_jwt_auth_token_before_sign' ), 10, 2);
        add_filter('graphql_jwt_auth_secret_key', array( $this, 'auth_secret_key' ), 20);
        $token = \WPGraphQL\JWT_Authentication\Auth::get_token($user, false);

		wc_setcookie( 'woo-session', $token );
		wc_setcookie( 'isLoggedIn', 'true' );
	}


	public function auth_secret_key() {
		return 'graphql_woocommerce_secret_key';
	}

	public function graphql_jwt_expiration( $expiration )
	{
		return time() + 3600;
	}

	public function modify_jwt_auth_token_before_sign( $token, $user )
    {
        unset($token['data']['user']);
        $token['data']['customer_id'] = $user->data->ID;
        return $token;
    }


	public function ajax_sign_in_script() {
		?>
		<script>
			(function ($) {
				var $form = $('form.woocommerce-form.woocommerce-form-login.login');
				var submitButton = $('button.woocommerce-button.button.woocommerce-form-login__submit');
				if (!$(document.body).hasClass('woocommerce-checkout') && !$(document.body).hasClass('woocommerce-account')) {
					submitButton.on('click', function (e) {
						$form = $(this).closest('form.woocommerce-form.woocommerce-form-login.login')
						e.preventDefault();
						var username = $form.find('input#username');
						var password = $form.find('input#password');

						submitButton.attr('disabled', true).css({ opacity: '0.5' });

                        $.post('https://<?php echo site_url(); ?>/api/login-with-cookies', {
                            login: username.val(),
                            password: password.val(),
                        }, function(response) {
                            if (response.data.loginWithCookies.status === 'SUCCESS') {
                                window.location.reload();
                            } else {
                                submitButton.attr('disabled', false).css({ opacity: '1' });
                            }
                        })
                    });
                }
            })(jQuery)
        </script>
        <?php
    }
}
