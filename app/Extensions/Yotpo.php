<?php

namespace BlazeWooless\Extensions;

class Yotpo {
	private static $instance = null;
	public static $API_URL = 'https://api.yotpo.com/v1';

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		if ( is_plugin_active( 'yotpo-social-reviews-for-woocommerce/wc_yotpo.php' ) ) {
			add_action( 'blaze_wooless_generate_product_reviews_data', array( $this, 'generate_product_data' ), 10, 1 );

			add_filter( 'blaze_wooless_product_data_for_typesense', array( $this, 'generate_product_reviews_stats' ), 10, 2 );

			add_filter( 'blaze_wooless_cross_sell_data_for_typesense', array( $this, 'generate_cross_sell_reviews_stats' ), 10, 2 );
		}
	}

	public function get_api_key() {
		$yotpo_settings = get_option( 'yotpo_settings' );

		return $yotpo_settings['app_key'];
	}

	public function generate_product_data() {
		$page            = 1;
		$batch_size      = 100;
		$finished        = false;
		$product_reviews = array();

		while ( ! $finished ) {
			$params = array(
				'page' => $page,
				'count' => $batch_size,
			);

			$QUERY_PARAMETERS = http_build_query( $params );

			$result = wp_remote_get( self::$API_URL . '/apps/' . $this->get_api_key() . '/bottom_lines?' . $QUERY_PARAMETERS );

			$response = json_decode( wp_remote_retrieve_body( $result ), true );

			if ( empty( $response['response']['bottomlines'] ) ) {
				$finished = true;
				continue;
			}

			foreach ( $response['response']['bottomlines'] as $stats ) {
				$product_reviews[ $stats['domain_key'] ] = array(
					'product_score' => (float) $stats['product_score'],
					'total_reviews' => (int) $stats['total_reviews'],
				);
			}

			$page++;

			unset( $params, $QUERY_PARAMETERS, $result, $response );
		}

		update_option( 'blaze_commerce_yotpo_product_reviews', $product_reviews );
	}

	public function generate_product_reviews_stats( $product_data, $product_id ) {
		if ( ! empty( $product_data ) && $product_id ) {
			$reviews = get_option( 'blaze_commerce_yotpo_product_reviews' );

			if ( ! empty( $reviews[ $product_id ] ) ) {
				$product_data['yotpoReviews'] = $reviews[ $product_id ];
			}
		}

		return $product_data;
	}

	public function generate_cross_sell_reviews_stats( $product_data, $product_id ) {
		$product = array();

		if ( ! empty( $product_data ) ) {
			$reviews = get_option( 'blaze_commerce_yotpo_product_reviews' );

			foreach ( $product_data as $product ) {
				if ( ! empty( $reviews[ $product['id'] ] ) ) {
					$product['yotpoReviews'] = $reviews[ $product['id'] ];
				}
			}
		}

		unset( $product_data );
		unset( $reviews );

		return $product;
	}
}
