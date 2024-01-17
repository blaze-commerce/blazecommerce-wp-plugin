<?php

namespace BlazeWooless\Extensions;

use WPGraphQL\AppContext;
use GraphQL\Type\Definition\ResolveInfo;

class YithWishList {
	private static $instance = null;
	private static $session_header;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {

		if ( function_exists( 'YITH_WCWL' ) ) {
			self::$session_header = apply_filters( 'graphql_yith_wcwl_session_http_header', 'yith-wcwl-session' );

			add_action( 'graphql_register_types', [ $this, 'register_types' ] );

			add_filter( 'graphql_response_headers_to_send', [ $this, 'add_session_header_to_expose_headers' ] );
			add_filter( 'graphql_access_control_allow_headers', [ $this, 'add_session_header_to_allow_headers' ] );


			add_filter(
				'graphql_response_headers_to_send',
				[ $this, 'response_headers' ]
			);

			add_action( 'graphql_process_http_request', [ $this, 'http_request' ] );
		}

	}

	public function http_request() {
		$all_headers = getallheaders();
		if ( ! empty( $all_headers['yith-wcwl-session'] ) ) {
			$yith_session = json_decode( $all_headers['yith-wcwl-session'], true );

			$cookie_value = [ 
				'session_id' => $yith_session['session_id'],
				'session_expiration' => $yith_session['session_expiration'],
				'session_expiring' => $yith_session['session_expiring'],
				'cookie_hash' => $yith_session['cookie_hash'],
			];

			$use_secure_cookie = apply_filters( 'yith_wcwl_session_use_secure_cookie', wc_site_is_https() && is_ssl() );
			$cookie_name       = apply_filters( 'yith_wcwl_session_cookie', 'yith_wcwl_session_' . $yith_session['cookie_hash'] );
			yith_setcookie( $cookie_name, $cookie_value, $yith_session['session_expiration'], $use_secure_cookie, true );

		}
	}

	public function response_headers( $headers ) {
		if ( ! empty( $headers['yith-wcwl-session'] ) ) {
			$headers['yith-wcwl-session-prev'] = $headers['yith-wcwl-session'];
		}
		$headers['yith-wcwl-session'] = json_encode( YITH_WCWL_Session()->get_session_cookie() );

		return $headers;
	}

	public function add_session_header_to_expose_headers( array $headers ) {
		if ( empty( $headers['Access-Control-Expose-Headers'] ) ) {
			$headers['Access-Control-Expose-Headers'] = self::$session_header;
		} else {
			$headers['Access-Control-Expose-Headers'] .= ', ' . self::$session_header;
		}

		return $headers;
	}

	public function add_session_header_to_allow_headers( array $allowed_headers ) {
		$allowed_headers[] = self::$session_header;
		return $allowed_headers;
	}



	public function add_to_wishlist_mutation() {
		return function ($input) {

			$product_id                  = $input['productId'];
			$_REQUEST['add_to_wishlist'] = $product_id;
			$_REQUEST['wishlist_id']     = 0;

			$args = [ 
				'add_to_wishlist' => $product_id
			];

			try {
				\YITH_WCWL()->add( $args );
			} catch (\YITH_WCWL_Exception $e) {
				// wp_send_json([
				// 	array('status' => 422, 'error' => $e->getMessage())
				// ]);
				$error = $e->getMessage();
				return [ 
					'added' => false,
					'productId' => $product_id,
					'error' => $error
				];
			} catch (\Exception $e) {
				// wp_send_json([
				// 	array('status' => 500, 'error' => $e->getMessage())
				// ]);
				$error = $e->getMessage();
				return [ 
					'added' => false,
					'productId' => $product_id,
					'error' => $error
				];
			}



			$wishlists = \YITH_WCWL_Wishlist_Factory::get_wishlists();


			return [ 
				'added' => true,
				'productId' => $product_id,
			];
		};
	}

	public function register_types() {
		register_graphql_mutation(
			'addProductToWishList',
			array(
				'inputFields' => [ 
					'productId' => [ 
						'type' => 'Int',
						'description' => __( 'Product database ID or global ID to be added into wishlist', 'wp-graphql-woocommerce' ),
					],
				],
				'outputFields' => [ 
					'added' => [ 
						'type' => 'Boolean',
						'description' => __( 'True if the product is removed, false otherwise', 'headless-cms' ),
					],
					'productId' => [ 
						'type' => 'Integer',
						'description' => __( 'The Product id that was added', 'headless-cms' ),
					],
					// 'wishlistProductIds' => [
					// 	'type' => ['list_of' => 'Integer'],
					// 	'description' => __('The Product ids in the wishlist', 'headless-cms'),
					// ],
					'error' => [ 
						'type' => 'String',
						'description' => __( 'Description of the error', 'headless-cms' ),
					],
				],
				'mutateAndGetPayload' => $this->add_to_wishlist_mutation(),
			)
		);
	}
}
