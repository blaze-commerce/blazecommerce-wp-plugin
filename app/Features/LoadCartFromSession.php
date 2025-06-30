<?php


namespace BlazeWooless\Features;

class LoadCartFromSession {
	private static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		add_action( 'woocommerce_load_cart_from_session', array( $this, 'woocommerce_load_cart_from_session' ) );
		add_action( 'init', array( $this, 'load_user_from_session' ) );
		add_action( 'wp_footer', array( $this, 'remove_session_id_from_url_script' ) );
		add_action( 'woocommerce_before_thankyou', array( $this, 'clear_cart_data' ) );
	}

	/**
	 * Check if current environment is staging
	 * Used to determine environment-specific behavior for session handling
	 *
	 * @return bool True if staging environment, false otherwise
	 */
	private function is_staging_environment() {
		return isset( $_SERVER['HTTP_HOST'] ) && strpos( $_SERVER['HTTP_HOST'], '.blz.onl' ) !== false;
	}

	/**
	 * Log environment-specific messages for debugging
	 *
	 * @param string $message The message to log
	 * @param string $context Additional context information
	 */
	private function log_environment_message( $message, $context = '' ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$environment = $this->is_staging_environment() ? 'STAGING' : 'PRODUCTION';
		$log_message = "BlazeCommerce [{$environment}]: {$message}";

		if ( ! empty( $context ) ) {
			$log_message .= " | Context: {$context}";
		}

		error_log( $log_message );
	}

	/**
	 * Get session ID from request parameters
	 * Sanitizes and validates the session ID from GET parameters
	 *
	 * @return string|null The sanitized session ID or null if not available
	 */
	private function get_session_id_from_request() {
		if ( ! isset( $_GET['session_id'] ) ) {
			return null;
		}

		return sanitize_text_field( $_GET['session_id'] );
	}

	/**
	 * Validate session ID format based on environment
	 * Environment-specific validation rules for security
	 *
	 * @param string $session_id The session ID to validate
	 * @return bool True if valid, false otherwise
	 */
	private function validate_session_id( $session_id ) {
		if ( empty( $session_id ) ) {
			return false;
		}

		if ( $this->is_staging_environment() ) {
			// In staging, allow more flexible session ID formats for testing
			// Basic validation to prevent obvious security issues
			return strlen( $session_id ) > 0 && strlen( $session_id ) <= 255;
		}

		// In production, apply stricter validation
		// Only allow alphanumeric characters, underscores, and hyphens
		return preg_match( '/^[a-zA-Z0-9_-]+$/', $session_id ) && strlen( $session_id ) <= 128;
	}


	public function woocommerce_load_cart_from_session() {
		$enable_system = boolval( bw_get_general_settings( 'enable_system' ) );

		if ( ! $enable_system ) {
			return;
		}

		if ( isset( $_SERVER['REQUEST_URI'] ) && $_SERVER['REQUEST_URI'] === '/graphql' ) {
			return;
		}
		$data_to_store = [ 'cart', 'applied_coupons', 'coupon_discount_totals', 'coupon_discount_tax_totals' ];

		if ( is_user_logged_in() ) {
			$current_session = WC()->session;

			foreach ( $data_to_store as $key ) {
				$cookie_name = 'guest_session_' . $key;
				if ( isset( $_COOKIE[ $cookie_name ] ) ) {
					$cookie_data = urldecode( $_COOKIE[ $cookie_name ] );

					// Environment-specific unserialize handling
					// Maintains backward compatibility while adding validation
					$unserialized_data = unserialize( $cookie_data );

					// Validate unserialization was successful
					if ( $unserialized_data !== false ) {
						if ( 'cart' === $key ) {
							// Preserve existing cart merging behavior for backward compatibility
							$account_cart_data = WC()->cart->get_cart();
							$merged_cart_data  = array_merge( $account_cart_data, $unserialized_data );

							$current_session->set( $key, $merged_cart_data );

							// Environment-specific logging
							if ( $this->is_staging_environment() ) {
								$this->log_environment_message( 'Cart data merged from guest session', 'items: ' . count( $merged_cart_data ) );
							}
						} else {
							$current_session->set( $key, $unserialized_data );
						}
					} else {
						// Log failed unserialization but continue processing (graceful degradation)
						$this->log_environment_message( 'Failed to unserialize cookie data', 'cookie: ' . $cookie_name );
					}

					// Always clear the guest session cookie (maintains existing behavior)
					wc_setcookie( $cookie_name, '', time() - YEAR_IN_SECONDS );
				}
			}

			WC()->session = $current_session;
			return;
		}

		// Handle missing session ID with environment-specific validation
		if ( ! isset( $_COOKIE['woocommerce_customer_session_id'] ) ) {
			$session_id_from_get = $this->get_session_id_from_request();

			if ( ! $session_id_from_get ) {
				// No session ID available, bail early (maintains backward compatibility)
				$this->log_environment_message( 'No session ID available, returning early' );
				return;
			}

			// Validate and set session ID based on environment
			if ( $this->validate_session_id( $session_id_from_get ) ) {
				$_COOKIE['woocommerce_customer_session_id'] = $session_id_from_get;
				$this->log_environment_message( 'Session ID set from GET parameter', 'session_id: ' . substr( $session_id_from_get, 0, 8 ) . '...' );
			} else {
				$this->log_environment_message( 'Invalid session ID format rejected', 'session_id format validation failed' );
				return;
			}
		}

		$session_id = sanitize_text_field( $_COOKIE['woocommerce_customer_session_id'] );

		// Bail if there isn't any data
		if ( empty( $session_id ) ) {
			return;
		}

		try {
			$handler      = new \WC_Session_Handler();
			$session_data = $handler->get_session( $session_id );

			// We were passed a session ID, yet no session was found. Let's log this and bail.
			if ( empty( $session_data ) ) {
				throw new \Exception( 'Could not locate WooCommerce session on checkout' );
			}

			// Go get the session instance (WC_Session) from the Main WC Class
			$session = WC()->session;

			// Check if customer data exists and determine if user is guest
			$is_guest = false;
			if ( isset( $session_data['customer'] ) ) {
				$customer_data = unserialize( $session_data['customer'] );
				$is_guest = isset( $customer_data['id'] ) && $customer_data['id'] == 0;
			}

			// Set the session variable
			foreach ( $session_data as $key => $value ) {
				$session_value = unserialize( $value );

				// Validate unserialization was successful
				if ( $session_value !== false ) {
					$session->set( $key, $session_value );
					if ( $is_guest && in_array( $key, $data_to_store, true ) ) {
						wc_setcookie( 'guest_session_' . $key, urlencode( $value ) );
					}
				}
			}
		} catch (\Exception $exception) {
			// Environment-specific exception handling
			// Maintains backward compatibility by not throwing exceptions
			if ( $this->is_staging_environment() ) {
				// In staging, log detailed exception information for debugging
				$this->log_environment_message( 'Session loading exception: ' . $exception->getMessage(), 'trace: ' . $exception->getTraceAsString() );
			} else {
				// In production, log minimal information for security
				$this->log_environment_message( 'Session loading failed', 'exception occurred' );
			}

			// Maintain backward compatibility - don't re-throw exception
			// ErrorHandling::capture( $exception );
		}
	}

	public function load_user_from_session() {

		$enable_system = boolval( bw_get_general_settings( 'enable_system' ) );

		if ( ! $enable_system ) {
			return;
		}

		if ( isset( $_SERVER['REQUEST_URI'] ) && $_SERVER['REQUEST_URI'] === '/graphql' ) {
			return;
		}

		if ( ! isset( $_COOKIE['woocommerce_customer_session_id'] ) || is_user_logged_in() ) {
			return;
		}

		$session_id = sanitize_text_field( $_COOKIE['woocommerce_customer_session_id'] );

		try {
			$handler      = new \WC_Session_Handler();
			$session_data = $handler->get_session( $session_id );


			// We were passed a session ID, yet no session was found. Let's log this and bail.
			if ( empty( $session_data ) ) {
				throw new \Exception( 'Could not locate WooCommerce session on checkout' );
			}

			if ( isset( $session_data['customer'] ) ) {
				$customer = $session_data['customer'];
				$customer_data = unserialize( $customer );

				// Validate unserialization was successful and customer data exists
				if ( $customer_data !== false && isset( $customer_data['id'] ) ) {
					$customer_id = $customer_data['id'];

					if ( $customer_id ) {
						// Authenticate the user and set the authentication cookies
						wp_set_auth_cookie( $customer_id );
					}
				}
			}
		} catch (\Exception $exception) {
			// Environment-specific exception handling for user authentication
			// Maintains backward compatibility by not throwing exceptions
			if ( $this->is_staging_environment() ) {
				// In staging, log detailed exception information for debugging
				$this->log_environment_message( 'User authentication exception: ' . $exception->getMessage(), 'method: load_user_from_session' );
			} else {
				// In production, log minimal information for security
				$this->log_environment_message( 'User authentication failed', 'exception occurred' );
			}

			// Maintain backward compatibility - don't re-throw exception
			// ErrorHandling::capture( $exception );
		}
	}

	public function remove_session_id_from_url_script() {
		$enable_system = boolval( bw_get_general_settings( 'enable_system' ) );

		if ( ! $enable_system ) {
			return;
		}

		?>
		<script>
			window.mobileCheck = function () {
				let check = false;
				(function (a) { if (/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge|maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm(os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i.test(a) || /1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i.test(a.substr(0, 4))) check = true; })(navigator.userAgent || navigator.vendor || window.opera);
				return check;
			};
			function get_cookie(name) {
				return document.cookie.split(';').some(c => {
					return c.trim().startsWith(name + '=');
				});
			}
			function delete_cookie(name, path, domain) {
				if (get_cookie(name)) {
					document.cookie = name + "=" +
						((path) ? ";path=" + path : "") +
						((domain) ? ";domain=" + domain : "") +
						";expires=Thu, 01 Jan 1970 00:00:01 GMT";
				}
			}
			(function ($) {
				$(document).ready(function () {
					delete_cookie('woocommerce_customer_session_id', '/', window.location.hostname.replace('cart', ''));
					if (!window.mobileCheck()) return false;

					var maxMegaMenuToggle = $('.max-mega-menu-toggle');
					maxMegaMenuToggle.append($('#mega-menu-wrap-primary'));

					$('.mobile-search-icon').append($('.dgwt-wcas-search-wrapp'));
				});
			})(jQuery)
		</script>
		<?php
	}


	public function clear_cart_data( $order_id ) {
		$enable_system = boolval( bw_get_general_settings( 'enable_system' ) );

		if ( ! $enable_system ) {
			return;
		}

		wc_setcookie( 'woocommerce_total_product_in_cart', '', time() - YEAR_IN_SECONDS );
		if ( ! is_user_logged_in() ) {
			wc_setcookie( 'woo-session', '', time() - YEAR_IN_SECONDS );
		}
	}
}
