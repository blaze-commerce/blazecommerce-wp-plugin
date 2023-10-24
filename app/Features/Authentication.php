<?php


namespace BlazeWooless\Features;

class Authentication
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
		add_action('wp_login', array( $this, 'login_hook' ), 10, 2);
        add_filter('graphql_jwt_auth_secret_key', array( $this, 'auth_secret_key' ), 20);
        add_filter('graphql_woocommerce_secret_key', array( $this, 'auth_secret_key' ), 20);
        add_action( 'wp_footer', array( $this, 'ajax_sign_in_script' ), 40);
    }

	public function login_hook( $user_login, $user )
    {
        add_filter( 'graphql_jwt_auth_token_before_sign', array( $this, 'modify_jwt_auth_token_before_sign' ), 10, 2);
        add_filter('graphql_jwt_auth_secret_key', array( $this, 'auth_secret_key' ), 20);
        $token = \WPGraphQL\JWT_Authentication\Auth::get_token($user, false);

        // Set the cookie with a specific domain
		BlazeCommerce()->cookie->set('woo-session', $token);
		BlazeCommerce()->cookie->set('isLoggedIn', 'true');
    }


    public function auth_secret_key()
	{
		return 'graphql_woocommerce_secret_key';
	}

	public function modify_jwt_auth_token_before_sign( $token, $user )
    {
        unset($token['data']['user']);
        $token['data']['customer_id'] = $user->data->ID;
        return $token;
    }


    public function ajax_sign_in_script()
    {
        ?>
        <script>
            (function($) {
                var $form = $('form.woocommerce-form.woocommerce-form-login.login');
                var submitButton = $('button.woocommerce-button.button.woocommerce-form-login__submit');
				if ( ! $(document.body).hasClass('woocommerce-checkout') && ! $(document.body).hasClass('woocommerce-account') ) {
                    submitButton.on('click', function(e) {
                        $form = $(this).closest('form.woocommerce-form.woocommerce-form-login.login')
                        e.preventDefault();
                        var username = $form.find('input#username');
                        var password = $form.find('input#password');

                        submitButton.attr('disabled', true).css({ opacity: '0.5' });

                        $.post('https://<?php echo BlazeCommerce()->cookie->main_domain(); ?>/api/login-with-cookies', {
                            login: username.val(),
                            password: password.val(),
                        }, function(response) {
                            if (response.data.loginWithCookies.status === 'SUCCESS') {
                                document.cookie = 'loginSessionId=' + response.data.loginWithCookies.clientMutationId + '; SameSite=None; Secure; domain=.pv-commerce-simulation-v4.blz.onl'
                                console.log(response.data.loginWithCookies.clientMutationId)
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
