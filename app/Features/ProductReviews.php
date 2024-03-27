<?php

namespace BlazeWooless\Features;

class ProductReviews {
	private static $instance = null;
	public static $API_URL = "https://api-cdn.yotpo.com/v1/widget/";
	public static $PRODUCTS_ENDPOINT = '/products/';
	public static $REVIEWS_ENDPOINT = '/reviews.json?';

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_rest_endpoints' ) );
	}

	public function register_rest_endpoints() {
		register_rest_route(
			'wooless-wc/v1',
			'/product-reviews',
			array(
				'methods' => 'POST',
				'callback' => array( $this, 'get_product_reviews_callback' ),
				'args' => array(
					'productIds' => array(
						'required' => true,
					),
				),
			)
		);
	}
	public function get_api_key() {
		$app_key = get_option( 'yotpo_settings' );
		return $app_key['app_key'];
	}

	public function get_woocommerce_products() {
		$batch_size              = 250;
		$page = 1;
		$query_args              = array( 'status' => 'publish', 'limit' => $batch_size, 'page' => $page );
		return \wc_get_products( $query_args );
	}

	public function generate_product_reviews_data( $product_ids_arrays ) {
		$response = array();

		foreach ( $product_ids_arrays as $product_id ) {
			$params = array(
				'page' => 1,
				'per_page' => 10,
				'star' => 5,
				'sort' => 'rating',
				'direction' => 'descending'
			);

			$PRODUCT_PARAMETERS = http_build_query( $params );

			$result = wp_remote_get( self::$API_URL . $this->get_api_key() . self::$PRODUCTS_ENDPOINT . $product_id . self::$REVIEWS_ENDPOINT . $PRODUCT_PARAMETERS );
			
			$reviews_response = json_decode( wp_remote_retrieve_body( $result ), true );

			unset( $result );

			$response[$product_id] = $reviews_response;
		}

		return $response;
	}

	public function get_review_with_long_content( $reviews ) {
		foreach( $reviews as $review ) {
			if( strlen( $review['content'] ) > 10 ) {
				return $review;
			}
		}

		return;
	}

	public function get_product_reviews_callback( \WP_REST_Request $request ) {
		$response = array();
		if ( $this->get_api_key() ) {
			$product_ids  = $request->get_param( 'productIds' );
			$product_ids_arrays = explode( ",", $product_ids );
	
			if ( ! class_exists( 'WooCommerce' ) ) {
				$response = new \WP_REST_Response( 'Error: Woocommerce is not active!' );
				$response->set_status( 400 );
				return;
			}

			$reviews_response = $this->generate_product_reviews_data( $product_ids_arrays );

			$products = $this->get_woocommerce_products();


			if( ! empty( $products ) ) {
				foreach( $products as $product ) {
					$woocommerce_product_id = $product->get_id();
					$review_data = $reviews_response[$woocommerce_product_id]['response']['reviews'];
					$top_reviews = $this->get_review_with_long_content( $review_data );
					$product_name = $product->get_name();

					// // Get the thumbnail
					$thumbnail_src      = get_the_post_thumbnail_url( $woocommerce_product_id );
					$product_permalink = wp_make_link_relative( get_permalink( $woocommerce_product_id ) );

					foreach ( $product_ids_arrays as $product_id ) {

						if( $woocommerce_product_id == $product_id) {
							$response[] = array(
								'score' => $top_reviews['score'],
								'content' => $top_reviews['content'],
								'title' => $top_reviews['title'],
								'verified_buyer' => $top_reviews['verified_buyer'],
								'user' => $top_reviews['user'],
								'product_thumbnail_src' => $thumbnail_src,
								'product_permalink' => $product_permalink,
								'product_name' => $product_name,
							);
						}
					}
				}
			}
		} else {
			$response = new \WP_REST_Response( 'Error: App key not found!' );
			$response->set_status( 400 );
			return;
		}

		return new \WP_REST_Response( $response );
	}
}
