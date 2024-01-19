<?php

if ( ! class_exists( 'Blaze_Wooless_Revalidate' ) ) {
	class Blaze_Wooless_Revalidate {
		private static $instance = null;

		public static function get_instance() {
			if ( self::$instance === null ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		public function __construct() {
			add_action( 'ts_product_update', array( $this, 'revalidate_frontend_path' ), 10, 1 );
			add_action( 'next_js_revalidation_event', array( $this, 'do_next_js_revalidation_event' ), 10, 2 );
		}

		public function get_object_permalink( $id ) {
			list( $permalink, $post_name ) = get_sample_permalink( $id );
			$view_link                   = str_replace( array( '%pagename%', '%postname%' ), $post_name, $permalink );

			return $view_link;
		}

		public function revalidate_product_page( $product_id ) {
			$product_url = array(
				wp_make_link_relative( $this->get_object_permalink( $product_id ) )
			);
			wp_schedule_single_event( time() + 15, 'next_js_revalidation_event', [ $product_url, time() + 1 ] );
		}

		public function revalidate_frontend_path( $product_id ) {
			if ( wp_is_post_revision( $product_id ) || wp_is_post_autosave( $product_id ) ) {
				return;
			}

			$this->revalidate_product_page( $product_id );
		}

		/**
		 * This function helps us update the next.js pages to show the updates stock and updated information of the product
		 * @params $urls array of string url endpoints. e.g ["/shop/", "/"]
		 */
		public function request_frontend_page_revalidation( $urls ) {
			$wooless_frontend_url  = get_option( 'wooless_frontend_url', '' );
			$typesense_private_key = get_option( 'typesense_api_key' );

			if ( empty( $wooless_frontend_url ) || empty( $typesense_private_key ) ) {
				// Dont revalidate because there is no secret token and frontend url for the request. 
				return null;
			}

			$curl = curl_init();
			curl_setopt_array(
				$curl,
				array(
					CURLOPT_URL => $wooless_frontend_url . '/api/revalidate',
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_ENCODING => '',
					CURLOPT_MAXREDIRS => 10,
					CURLOPT_TIMEOUT => 0,
					CURLOPT_FOLLOWLOCATION => true,
					CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
					CURLOPT_CUSTOMREQUEST => 'POST',
					CURLOPT_POSTFIELDS => '["' . implode( '","', $urls ) . '"]',
					CURLOPT_HTTPHEADER => array(
							'api-secret-token: ' . $typesense_private_key,
							'Content-Type: text/plain'
						),
				)
			);

			$response = curl_exec( $curl );
			$err      = curl_error( $curl );

			curl_close( $curl );

			if ( $err ) {
				throw new Exception( "cURL Error #:" . $err, 400 );
			}

			$response = json_decode( $response, true );
			return $response;
		}

		/**
		 * @pararms $urls array of string url endpoints. e.g ["/shop/", "/"]
		 * @params $time we just use this so that the event will not be ignored by wp https://developer.wordpress.org/reference/functions/wp_schedule_single_event/#description
		 */
		public function do_next_js_revalidation_event( $urls, $time ) {
			$this->request_frontend_page_revalidation( $urls );
		}

	}

	Blaze_Wooless_Revalidate::get_instance();
}
