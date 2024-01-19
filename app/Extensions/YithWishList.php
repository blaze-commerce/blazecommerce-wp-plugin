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
		} else {
			YITH_WCWL_Session()->init_session_cookie();
			$headers['yith-wcwl-session'] = json_encode( YITH_WCWL_Session()->get_session_cookie() );
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

	/**
	 * Add a product in the wishlist.
	 *
	 * @param array $atts Array of parameters; when not passed, params will be searched in $_REQUEST.
	 * @throws YITH_WCWL_Exception When an error occurs with Add to Wishlist operation.
	 *
	 * @since 1.0.0
	 */
	public function add_to_wishlist( $atts = array() ) {
		$defaults = array(
			'add_to_wishlist' => 0,
			'wishlist_id' => 0,
			'quantity' => 1,
			'user_id' => false,
			'dateadded' => '',
			'wishlist_name' => '',
			'wishlist_visibility' => 0,
		);

		$atts = empty( $atts ) && ! empty( $this->details ) ? $this->details : $atts;
		$atts = ! empty( $atts ) ? $atts : $_REQUEST; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$atts = wp_parse_args( $atts, $defaults );

		// filtering params.
		$prod_id     = apply_filters( 'yith_wcwl_adding_to_wishlist_prod_id', intval( $atts['add_to_wishlist'] ) );
		$wishlist_id = apply_filters( 'yith_wcwl_adding_to_wishlist_wishlist_id', $atts['wishlist_id'] );
		$quantity    = apply_filters( 'yith_wcwl_adding_to_wishlist_quantity', intval( $atts['quantity'] ) );
		$user_id     = apply_filters( 'yith_wcwl_adding_to_wishlist_user_id', intval( $atts['user_id'] ) );
		$dateadded   = apply_filters( 'yith_wcwl_adding_to_wishlist_dateadded', $atts['dateadded'] );

		do_action( 'yith_wcwl_adding_to_wishlist', $prod_id, $wishlist_id, $user_id );
		$yith_wcwl = \YITH_WCWL();

		if ( ! $yith_wcwl->can_user_add_to_wishlist() ) {
			throw new \YITH_WCWL_Exception( apply_filters( 'yith_wcwl_user_cannot_add_to_wishlist_message', __( 'The item cannot be added to this wishlist', 'yith-woocommerce-wishlist' ) ), 1 );
		}

		if ( ! $prod_id ) {
			throw new \YITH_WCWL_Exception( __( 'An error occurred while adding the products to the wishlist.', 'yith-woocommerce-wishlist' ), 0 );
		}

		$wishlist = 'new' === $wishlist_id ? $yith_wcwl->add_wishlist( $atts ) : \YITH_WCWL_Wishlist_Factory::get_wishlist( $wishlist_id, 'edit' );

		if ( ! $wishlist instanceof \YITH_WCWL_Wishlist || ! $wishlist->current_user_can( 'add_to_wishlist' ) ) {
			throw new \YITH_WCWL_Exception( __( 'An error occurred while adding the products to the wishlist.', 'yith-woocommerce-wishlist' ), 0 );
		}

		$yith_wcwl->last_operation_token = $wishlist->get_token();

		if ( $wishlist->has_product( $prod_id ) ) {
			throw new \YITH_WCWL_Exception( apply_filters( 'yith_wcwl_product_already_in_wishlist_message', get_option( 'yith_wcwl_already_in_wishlist_text' ) ), 1 );
		}

		$item = new \YITH_WCWL_Wishlist_Item();

		$item->set_product_id( $prod_id );
		$item->set_quantity( $quantity );
		$item->set_wishlist_id( $wishlist->get_id() );
		$item->set_user_id( $wishlist->get_user_id() );

		if ( $dateadded ) {
			$item->set_date_added( $dateadded );
		}

		$wishlist->add_item( $item );
		$wishlist_id = $wishlist->save();

		wp_cache_delete( 'wishlist-count-' . $wishlist->get_token(), 'wishlists' );

		$user_id = $wishlist->get_user_id();

		if ( $user_id ) {
			wp_cache_delete( 'wishlist-user-total-count-' . $user_id, 'wishlists' );
		}

		do_action( 'yith_wcwl_added_to_wishlist', $prod_id, $item->get_wishlist_id(), $item->get_user_id() );

		return $wishlist;

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
				$error = $e->getMessage();
				return [ 
					'added' => false,
					'productId' => $product_id,
					'error' => $error
				];
			} catch (\Exception $e) {
				$error = $e->getMessage();
				return [ 
					'added' => false,
					'productId' => $product_id,
					'error' => $error
				];
			}

			return [ 
				'added' => true,
				'productId' => $product_id,
			];
		};
	}

	public function remove_to_wishlist_mutation() {
		return function ($input) {



			$product_id  = $input['productId'];
			$wishlist_id = $input['wishlistId'];

			$_REQUEST['remove_from_wishlist'] = $product_id;
			$_REQUEST['wishlist_id']          = $wishlist_id;

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


		register_graphql_mutation(
			'removeProductToWishList',
			array(
				'inputFields' => [ 
					'productId' => [ 
						'type' => 'Int',
						'description' => __( 'Product database ID or global ID to be added into wishlist', 'wp-graphql-woocommerce' ),
					],
					'wishlistId' => [ 
						'type' => 'Int',
						'description' => __( 'This is the database Id of the wishlist item', 'wp-graphql-woocommerce' ),
					],
				],
				'outputFields' => [ 
					'removed' => [ 
						'type' => 'Boolean',
						'description' => __( 'True if the product is removed, false otherwise', 'headless-cms' ),
					],
					'productId' => [ 
						'type' => 'Integer',
						'description' => __( 'The Product id that was added', 'headless-cms' ),
					],
					'error' => [ 
						'type' => 'String',
						'description' => __( 'Description of the error', 'headless-cms' ),
					],
				],
				'mutateAndGetPayload' => $this->remove_to_wishlist_mutation(),
			)
		);
	}
}
