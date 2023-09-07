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
		add_action('wp_footer', array($this, 'remove_session_id_from_url_script'));
	}

	public function woocommerce_load_cart_from_session()
	{
		// Bail if there isn't any data
		if (!isset($_GET['session_id'])) {
			return;
		}

		$session_id = sanitize_text_field($_GET['session_id']);

		try {
			$handler = new \WC_Session_Handler();
			$session_data = $handler->get_session($session_id);


			// We were passed a session ID, yet no session was found. Let's log this and bail.
			if (empty($session_data)) {
				throw new \Exception('Could not locate WooCommerce session on checkout');
			}

			// Go get the session instance (WC_Session) from the Main WC Class
			$session = WC()->session;

			// Set the session variable
			foreach ($session_data as $key => $value) {
				$session_value = unserialize($value);
				$session->set($key, $session_value);
			}

		} catch (\Exception $exception) {
			// ErrorHandling::capture( $exception );
		}
	}

	public function remove_session_id_from_url_script()
	{
		if (!class_exists('WooCommerce') || !is_checkout() || !isset($_GET['session_id']) || isset($_GET['from_wooless'])) {
			return;
		}

		$url = remove_query_arg('session_id', $_SERVER['REQUEST_URI']);
		wp_redirect(add_filter('blaze_wooless_destination_url_from_frontend', $url));
		exit;
	}
}
