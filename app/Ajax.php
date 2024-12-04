<?php

namespace BlazeWooless;

use BlazeWooless\Collections\Product;
use BlazeWooless\Collections\SiteInfo;
use BlazeWooless\Collections\Taxonomy;
use BlazeWooless\Collections\Page;
use BlazeWooless\Collections\Menu;

class Ajax {
	private static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		add_action( 'wp_ajax_index_data_to_typesense', array( $this, 'index_data_to_typesense' ) );

		add_action( 'wp_ajax_check_product_sync_data', array( $this, 'check_product_sync_data' ) );

		add_action( 'wp_ajax_redeploy_store_front', array( $this, 'redeploy_store_front' ) );
		add_action( 'wp_ajax_check_deployment', array( $this, 'check_deployment' ) );
	}

	public function check_product_sync_data() {
		if ( ! empty( $_REQUEST['product_id'] ) ) {
			$product = wc_get_product( $_REQUEST['product_id'] );
			if ( $product ) {
				$product_document = Product::get_instance()->generate_typesense_data( $product );
				wp_send_json( $product_document );
			}
		}
	}

	public function get_headers() {
		$api_key = bw_get_general_settings( 'typesense_api_key' );
		$store_id = bw_get_general_settings( 'store_id' );
		return array(
			'x-wooless-secret-token: ' . base64_encode( $api_key . ':' . $store_id )
		);
	}

	public function check_deployment() {
		$api_key = bw_get_general_settings( 'typesense_api_key' );
		if ( empty( $api_key ) ) {
			wp_send_json( array(
				'error' => 'Empty api key.',
				'message' => 'Empty api key.'
			) );
		}
		$curl = curl_init();
		curl_setopt_array( $curl, array(
			CURLOPT_URL => 'https://my-wooless-admin-portal.vercel.app/api/deployments?checkDeployment=1',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'GET',
			CURLOPT_HTTPHEADER => $this->get_headers(),
		) );
		$response = curl_exec( $curl );
		curl_close( $curl );
		wp_send_json( json_decode( $response ) );
	}

	public function redeploy_store_front() {
		$api_key = bw_get_general_settings( 'typesense_api_key' );
		if ( empty( $api_key ) ) {
			wp_send_json( array(
				'error' => 'Empty api key.',
				'message' => 'Empty api key.'
			) );
		}
		$curl = curl_init();
		curl_setopt_array( $curl, array(
			CURLOPT_URL => 'https://my-wooless-admin-portal.vercel.app/api/deployments',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_HTTPHEADER => $this->get_headers(),
		) );
		$response = curl_exec( $curl );
		curl_close( $curl );
		wp_send_json( json_decode( $response ) );
	}

	public function prepare_curl( $url_endpoint, $method ) {
		$curl = curl_init();
		curl_setopt_array( $curl, array(
			CURLOPT_URL => 'https://my-wooless-admin-portal.vercel.app/' . $url_endpoint,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => $method,
			CURLOPT_HTTPHEADER => $this->get_headers(),
		) );

		return $curl;
	}
	public function index_data_to_typesense() {
		$collection_name = ! ( empty( $_REQUEST['collection_name'] ) ) ? $_REQUEST['collection_name'] : '';
		if ( $collection_name == 'products' ) {
			Product::get_instance()->index_to_typesense();
		} else if ( $collection_name == 'site_info' ) {
			SiteInfo::get_instance()->index_to_typesense();
		} else if ( $collection_name == 'taxonomy' ) {
			Taxonomy::get_instance()->index_to_typesense();
		} else if ( $collection_name == 'menu' ) {
			Menu::get_instance()->index_to_typesense();
		} else if ( $collection_name == 'page' ) {
			Page::get_instance()->index_to_typesense();
		} else {
			echo "Collection name not found";
		}
		wp_die();
	}

}

Ajax::get_instance();
