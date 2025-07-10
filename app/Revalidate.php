<?php

namespace BlazeWooless;

use BlazeWooless\Collections\Product;

class Revalidate {
	private static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		add_action( 'ts_product_update', array( $this, 'revalidate_frontend_path' ), 10, 2 );
		add_action( 'next_js_revalidation_event', array( $this, 'do_next_js_revalidation_event' ), 10, 1 );
	}

	public function get_object_permalink( $id ) {
		if ( function_exists( 'get_sample_permalink' ) ) {
			list( $permalink, $post_name ) = \get_sample_permalink( $id );
		} else {
			list( $permalink, $post_name ) = $this->get_sample_permalink( $id );
		}
		$view_link = str_replace( array( '%pagename%', '%postname%' ), $post_name, $permalink );

		return $view_link;
	}

	public function revalidate_product_page( $product_id ) {
		$product_url = array(
			wp_make_link_relative( $this->get_object_permalink( $product_id ) )
		);

		$product       = wc_get_product( $product_id );
		$taxonomies    = Product::get_instance()->get_taxonomies( $product );
		$taxonomy_urls = array_map( function ($taxonomy) {
			return wp_make_link_relative( $taxonomy['url'] );
		}, $taxonomies );

		$event_time = WC()->call_function( 'time' ) + 1;
		as_unschedule_action( 'next_js_revalidation_event', array( $product_url ), 'blaze-commerce' );
		as_schedule_single_action( $event_time, 'next_js_revalidation_event', array( $product_url ), 'blaze-wooless', false );

		if ( ! empty( $taxonomy_urls ) ) {
			$logger  = wc_get_logger();
			$context = array( 'source' => 'frontend-revalidation-tax-urls' );
			$logger->debug( 'taxonomy-Urls:> ' . print_r( $taxonomy_urls, true ), $context );
			foreach ( $taxonomy_urls as $taxonomy_url ) {
				$logger->debug( 'tax url: ' . $taxonomy_url, $context );

				$event_time = WC()->call_function( 'time' ) + 1;
				as_unschedule_action( 'next_js_revalidation_event', array( array( $taxonomy_url ) ), 'blaze-commerce' );
				as_schedule_single_action( $event_time, 'next_js_revalidation_event', array( array( $taxonomy_url ) ), 'blaze-wooless', false );
			}
		}
	}

	public function revalidate_frontend_path( $product_id, $product ) {
		if ( wp_is_post_revision( $product_id ) || wp_is_post_autosave( $product_id ) ) {
			return;
		}

		// We do not revalidate variation as this doesn't have a url on its own
		if ( $product->is_type( 'variation' ) ) {
			return;
		}

		$this->revalidate_product_page( $product_id );
	}

	public function get_frontend_url() {
		return rtrim( str_replace( '/cart.', '/', site_url() ), '/' );
	}

	/**
	 * This function helps us update the next.js pages to show the updates stock and updated information of the product
	 * @params $urls array of string url endpoints. e.g ["/shop/", "/"]
	 */
	public function request_frontend_page_revalidation( $urls ) {
		$logger  = wc_get_logger();
		$context = array( 'source' => 'frontend-revalidation' );

		$logger->debug( '======= START REVALIDATION =======', $context );


		$wooless_frontend_url  = $this->get_frontend_url();
		$typesense_private_key = bw_get_general_settings( 'typesense_api_key' );

		$logger->debug( print_r( array(
			'wooless_frontend_url' => $wooless_frontend_url,
			'typesense_private_key' => $typesense_private_key,
			'urls' => $urls
		), 1 ), $context );


		if ( empty( $wooless_frontend_url ) || empty( $typesense_private_key ) || ! is_array( $urls ) ) {
			// Dont revalidate because there is no secret token and frontend url for the request. 
			return null;
		}

		$curl         = curl_init();
		$curl_options = array(
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
				'Content-Type: application/json'
			),
		);
		curl_setopt_array(
			$curl,
			$curl_options
		);

		$response = curl_exec( $curl );
		$err      = curl_error( $curl );

		curl_close( $curl );



		$logger->debug( 'Curl Options : ' . print_r( $curl_options, 1 ), $context );

		if ( $err ) {
			$logger->debug( 'Curl Error : ' . print_r( $err, 1 ), $context );

			throw new Exception( "cURL Error #:" . $err, 400 );
		}

		$response = json_decode( $response, true );
		$logger->debug( 'Curl Response : ' . print_r( $response, 1 ), $context );
		$logger->debug( '======= END REVALIDATION =======', $context );
		return $response;
	}

	/**
	 * @pararms $urls array of string url endpoints. e.g ["/shop/", "/"]
	 * @params $time we just use this so that the event will not be ignored by wp https://developer.wordpress.org/reference/functions/wp_schedule_single_event/#description
	 */
	public function do_next_js_revalidation_event( $urls ) {
		$this->request_frontend_page_revalidation( $urls );
	}

	// This is copied from https://developer.wordpress.org/reference/functions/get_sample_permalink/#source
	function get_sample_permalink( $post, $title = null, $name = null ) {
		$post = get_post( $post );

		if ( ! $post ) {
			return array( '', '' );
		}

		$ptype = get_post_type_object( $post->post_type );

		$original_status = $post->post_status;
		$original_date   = $post->post_date;
		$original_name   = $post->post_name;
		$original_filter = $post->filter;

		// Hack: get_permalink() would return plain permalink for drafts, so we will fake that our post is published.
		if ( in_array( $post->post_status, array( 'draft', 'pending', 'future' ), true ) ) {
			$post->post_status = 'publish';
			$post->post_name   = sanitize_title( $post->post_name ? $post->post_name : $post->post_title, $post->ID );
		}

		/*
		 * If the user wants to set a new name -- override the current one.
		 * Note: if empty name is supplied -- use the title instead, see #6072.
		 */
		if ( ! is_null( $name ) ) {
			$post->post_name = sanitize_title( $name ? $name : $title, $post->ID );
		}

		$post->post_name = wp_unique_post_slug( $post->post_name, $post->ID, $post->post_status, $post->post_type, $post->post_parent );

		$post->filter = 'sample';

		$permalink = get_permalink( $post, true );

		// Replace custom post_type token with generic pagename token for ease of use.
		$permalink = str_replace( "%$post->post_type%", '%pagename%', $permalink );

		// Handle page hierarchy.
		if ( $ptype->hierarchical ) {
			$uri = get_page_uri( $post );
			if ( $uri ) {
				$uri = untrailingslashit( $uri );
				$uri = strrev( stristr( strrev( $uri ), '/' ) );
				$uri = untrailingslashit( $uri );
			}

			/** This filter is documented in wp-admin/edit-tag-form.php */
			$uri = apply_filters( 'editable_slug', $uri, $post );
			if ( ! empty( $uri ) ) {
				$uri .= '/';
			}
			$permalink = str_replace( '%pagename%', "{$uri}%pagename%", $permalink );
		}

		/** This filter is documented in wp-admin/edit-tag-form.php */
		$permalink         = array( $permalink, apply_filters( 'editable_slug', $post->post_name, $post ) );
		$post->post_status = $original_status;
		$post->post_date   = $original_date;
		$post->post_name   = $original_name;
		$post->filter      = $original_filter;

		/**
		 * Filters the sample permalink.
		 *
		 * @since 4.4.0
		 *
		 * @param array   $permalink {
		 *     Array containing the sample permalink with placeholder for the post name, and the post name.
		 *
		 *     @type string $0 The permalink with placeholder for the post name.
		 *     @type string $1 The post name.
		 * }
		 * @param int     $post_id Post ID.
		 * @param string  $title   Post title.
		 * @param string  $name    Post name (slug).
		 * @param WP_Post $post    Post object.
		 */
		return apply_filters( 'get_sample_permalink', $permalink, $post->ID, $title, $name, $post );
	}
}

