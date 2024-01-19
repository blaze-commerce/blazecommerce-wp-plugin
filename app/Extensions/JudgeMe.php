<?php

namespace BlazeWooless\Extensions;

class JudgeMe {
	private static $instance = null;
	public static $API_URL = 'https://judge.me/api/v1';
	public static $WIDGET_URL = 'https://cache.judge.me/widgets/woocommerce/';
	public static $PRODUCTS_ENDPOINT = '/products/?';

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		if ( is_plugin_active( 'judgeme-product-reviews-woocommerce/judgeme.php' ) ) {
			add_filter( 'blaze_wooless_additional_site_info', array( $this, 'add_review_config_to_site_info' ), 10, 2 );

			add_action( 'blaze_wooless_generate_product_reviews_data', array( $this, 'generate_product_data' ), 10, 1 );

			add_filter( 'blaze_wooless_product_data_for_typesense', array( $this, 'get_product_reviews_data' ), 10, 2 );

			add_filter( 'blaze_wooless_cross_sell_data_for_typesense', array( $this, 'get_cross_sell_reviews_data' ), 10, 2 );
		}
	}

	public function add_review_config_to_site_info( $additional_settings ) {
		if ( $html_miracle = get_option( 'judgeme_widget_html_miracle' ) ) {
			$additional_settings['judgeme_widget_html_miracle'] = $html_miracle;
		}

		if ( $setting = get_option( 'judgeme_widget_settings' ) ) {
			$additional_settings['judgeme_widget_settings'] = $setting;
		}

		return $additional_settings;
	}

	public function generate_product_data() {
		$SHOP_DOMAIN    = $this->reformat_url( bw_get_general_settings( 'shop_domain' ) );
		$products_batch = array();

		if ( $this->get_api_key() ) {
			$finished = false;
			$page     = 1;

			while ( ! $finished ) {
				$params = array(
					'api_token' => $this->get_api_key(),
					'shop_domain' => $SHOP_DOMAIN,
					'page' => $page,
					'per_page' => 100,
				);

				$PRODUCT_PARAMETERS = http_build_query( $params );

				$result = wp_remote_get( self::$API_URL . self::$PRODUCTS_ENDPOINT . $PRODUCT_PARAMETERS );

				$response = json_decode( wp_remote_retrieve_body( $result ), true );

				if ( empty( $response['products'] ) ) {
					$finished = true;
					continue;
				}

				foreach ( $response['products'] as $products ) {
					$products_batch[] = $products;
				}

				unset( $response );

				// Increment the page number
				$page++;
			}
		}

		$product_reviews = $this->generate_product_reviews( $products_batch );

		update_option( 'blaze_commerce_judgeme_product_reviews', $product_reviews );
	}

	public function reformat_url( $url ) {
		$disallowed = array( 'http://', 'https://' );
		foreach ( $disallowed as $d ) {
			if ( strpos( $url, $d ) === 0 ) {
				$removed_http = str_replace( $d, '', $url );
				if ( strpos( $url, 'www.' ) === 0 ) {
					return str_replace( 'www.', '', $removed_http );
				}
				return $removed_http;
			}
		}
		return $url;
	}

	public function get_api_key() {
		return get_option( 'judgeme_shop_token' );
	}

	public function generate_product_reviews( $products ) {
		$SHOP_DOMAIN     = $this->reformat_url( bw_get_general_settings( 'shop_domain' ) );
		$product_reviews = array();

		if ( ! empty( $products ) ) {
			foreach ( $products as $product ) {
				$product_ids[] = $product['external_id'];
			}

			$REVIEWS_WIDGETS_PARAMETERS = 'review_widget_product_ids=' . implode( ",", $product_ids );

			$result = wp_remote_get( self::$WIDGET_URL . $SHOP_DOMAIN . "?" . $REVIEWS_WIDGETS_PARAMETERS );

			$response = json_decode( wp_remote_retrieve_body( $result ), true );

			foreach ( $products as $product ) {
				foreach ( $response['review_widgets'] as $key => $value ) {
					if ( $product['external_id'] === $key ) {
						$average_rating                      = $this->get_reviews_average_rating( $value );
						$rating_count                        = $this->get_reviews_rating_count( $value );
						$percentage                          = $this->get_reviews_rating_percentage( $value );
						$content                             = $this->get_reviews_content( $value );
						$product_reviews[ $product['handle'] ] = array(
							'id' => (int) $product['id'],
							'externalId' => (int) $product['external_id'],
							'average' => (float) $average_rating[1],
							'count' => (int) $rating_count[1],
							'percentage' => $percentage,
							'content' => $content,
						);

						unset( $average_rating );
						unset( $rating_count );
						unset( $percentage );
						unset( $content );
					}
				}
			}

			unset( $response );
		}
		unset( $products );

		return $product_reviews;
	}

	public function get_product_reviews_data( $product_data, $product_id ) {
		$reviews = get_option( 'blaze_commerce_judgeme_product_reviews' );

		if ( ! empty( $reviews[ $product_data['slug'] ] ) ) {
			$product_data['judgemeReviews'] = $reviews[ $product_data['slug'] ];
		}

		unset( $reviews );

		return $product_data;
	}

	public function get_cross_sell_reviews_data( $product_data, $product_id ) {
		$reviews = get_option( 'blaze_commerce_judgeme_product_reviews' );
		$product = array();

		foreach ( $product_data as $product ) {
			if ( ! empty( $reviews[ $product['slug'] ] ) ) {
				$product['judgemeReviews'] = $reviews[ $product['slug'] ];
			}
		}

		unset( $product_data );
		unset( $reviews );

		return $product;
	}

	public function get_reviews_average_rating( $html ) {
		$re = "/data-average-rating='(.*?)'/m";
		preg_match_all( $re, $html, $matches, PREG_SET_ORDER, 0 );

		return $matches[0];
	}

	public function get_reviews_rating_count( $html ) {
		$re = "/data-number-of-reviews='(.*?)'/m";
		preg_match_all( $re, $html, $matches, PREG_SET_ORDER, 0 );

		return $matches[0];
	}

	public function get_reviews_rating_percentage( $html ) {
		$ratings = "/data-rating='(.*?)'/m";
		preg_match_all( $ratings, $html, $ratings_matches, PREG_SET_ORDER, 0 );
		$percent = "/data-percentage='(.*?)'/m";
		preg_match_all( $percent, $html, $percent_matches, PREG_SET_ORDER, 0 );
		$total = "/data-frequency='(.*?)'/m";
		preg_match_all( $total, $html, $total_matches, PREG_SET_ORDER, 0 );

		$ratings_and_percent = array();

		foreach ( $ratings_matches as $key => $value ) {
			$ratings_and_percent[ $value[1] ] = array(
				'total' => (int) $total_matches[ $key ][1],
				'value' => (int) $percent_matches[ $key ][1],
			);
		}

		unset( $ratings_matches );
		unset( $percent_matches );
		unset( $total_matches );

		return $ratings_and_percent;
	}

	public function get_reviews_content( $html ) {
		$verified  = "/data-verified-buyer='(.*?)'/m";
		$icon      = "/jdgm-rev__icon' >(.*?)<\/div>/m";
		$rating    = "/jdgm-rev__rating' data-score='(.*?)'/m";
		$timestamp = "/timestamp jdgm-spinner' data-content='(.*?)'/m";
		$author    = "/jdgm-rev__author'>(.*?)<\/span>/m";
		$title     = "/jdgm-rev__title'>(.*?)<\/b>/m";
		$body      = "/jdgm-rev__body'><p>(.*?)<\/p>/m";

		preg_match_all( $verified, $html, $verified_matches, PREG_SET_ORDER, 0 );
		preg_match_all( $icon, $html, $icon_matches, PREG_SET_ORDER, 0 );
		preg_match_all( $rating, $html, $rating_matches, PREG_SET_ORDER, 0 );
		preg_match_all( $timestamp, $html, $timestamp_matches, PREG_SET_ORDER, 0 );
		preg_match_all( $author, $html, $author_matches, PREG_SET_ORDER, 0 );
		preg_match_all( $title, $html, $title_matches, PREG_SET_ORDER, 0 );
		preg_match_all( $body, $html, $body_matches, PREG_SET_ORDER, 0 );

		$reviews_content = array();

		foreach ( $body_matches as $key => $value ) {
			$reviews_content[] = array(
				'verified' => (bool) $verified_matches[ $key ][1],
				'icon' => $icon_matches[ $key ][1],
				'rating' => (int) $rating_matches[ $key ][1],
				'timestamp' => $timestamp_matches[ $key ][1],
				'author' => $author_matches[ $key ][1],
				'title' => $title_matches[ $key ][1],
				'body' => $body_matches[ $key ][1],
			);
		}

		unset( $verified_matches );
		unset( $icon_matches );
		unset( $rating_matches );
		unset( $timestamp_matches );
		unset( $author_matches );
		unset( $title_matches );
		unset( $body_matches );

		return $reviews_content;
	}
}
