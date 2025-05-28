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

	public function get_vercel_headers() {
		$vercel_token = bw_get_general_settings( 'vercel_access_token' );
		if ( empty( $vercel_token ) ) {
			// Fallback to default token if not configured
			$vercel_token = 'a3alWY2RDSCThmlGk7AzNhrY';
		}
		return array(
			'Authorization: Bearer ' . $vercel_token,
			'Content-Type: application/json'
		);
	}

	public function get_vercel_team_id() {
		$team_id = bw_get_general_settings( 'vercel_team_id' );
		if ( empty( $team_id ) ) {
			// Fallback to default team ID
			$team_id = 'team_zL2lRpvMuf07cC4lm4uHtkQy';
		}
		return $team_id;
	}

	public function get_project_id() {
		$project_id = bw_get_general_settings( 'vercel_project_id' );
		if ( ! empty( $project_id ) ) {
			return $project_id;
		}

		error_log( 'BlazeCommerce: No Vercel project ID configured' );
		return null;
	}





	public function get_latest_deployment() {
		$project_id = $this->get_project_id();
		if ( empty( $project_id ) ) {
			return null;
		}

		$team_id = $this->get_vercel_team_id();
		$url = 'https://api.vercel.com/v6/deployments?teamId=' . $team_id . '&projectId=' . $project_id . '&state=BUILDING,READY&target=production';

		$curl = curl_init();
		curl_setopt_array( $curl, array(
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'GET',
			CURLOPT_HTTPHEADER => $this->get_vercel_headers(),
		) );

		$response = curl_exec( $curl );
		$http_code = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
		$curl_error = curl_error( $curl );
		curl_close( $curl );

		if ( $curl_error || $http_code !== 200 ) {
			error_log( 'BlazeCommerce: Failed to get latest deployment - HTTP: ' . $http_code . ', Error: ' . $curl_error );
			return null;
		}

		$decoded_response = json_decode( $response, true );
		if ( json_last_error() !== JSON_ERROR_NONE || ! isset( $decoded_response['deployments'] ) || count( $decoded_response['deployments'] ) === 0 ) {
			error_log( 'BlazeCommerce: No deployments found or invalid response' );
			return null;
		}

		return $decoded_response['deployments'][0];
	}

	public function check_deployment() {
		$project_id = $this->get_project_id();
		if ( empty( $project_id ) ) {
			wp_send_json( array(
				'error' => 'Configuration error',
				'message' => 'Vercel project ID not configured'
			) );
		}

		$team_id = $this->get_vercel_team_id();
		$url = 'https://api.vercel.com/v6/deployments?teamId=' . $team_id . '&projectId=' . $project_id . '&state=BUILDING,READY&target=production';

		$curl = curl_init();
		curl_setopt_array( $curl, array(
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 30, // Set 30 second timeout
			CURLOPT_CONNECTTIMEOUT => 10, // Set 10 second connection timeout
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'GET',
			CURLOPT_HTTPHEADER => $this->get_vercel_headers(),
		) );

		$response = curl_exec( $curl );
		$http_code = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
		$curl_error = curl_error( $curl );
		curl_close( $curl );

		// Log for debugging
		error_log( 'BlazeCommerce Check Deployment - URL: ' . $url );
		error_log( 'BlazeCommerce Check Deployment - HTTP Code: ' . $http_code );
		error_log( 'BlazeCommerce Check Deployment - Response: ' . $response );
		if ( $curl_error ) {
			error_log( 'BlazeCommerce Check Deployment - cURL Error: ' . $curl_error );
		}

		if ( $curl_error ) {
			wp_send_json( array(
				'error' => 'Connection error',
				'message' => 'Failed to connect to Vercel API: ' . $curl_error
			) );
		}

		if ( $http_code !== 200 ) {
			wp_send_json( array(
				'error' => 'HTTP error',
				'message' => 'Vercel API returned HTTP ' . $http_code,
				'response' => $response
			) );
		}

		$decoded_response = json_decode( $response, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			wp_send_json( array(
				'error' => 'Invalid response',
				'message' => 'Invalid JSON response from Vercel API',
				'raw_response' => $response
			) );
		}

		// Get the latest deployment state
		if ( isset( $decoded_response['deployments'] ) && count( $decoded_response['deployments'] ) > 0 ) {
			$latest_deployment = $decoded_response['deployments'][0];
			wp_send_json( array(
				'state' => $latest_deployment['state'],
				'deployment' => $latest_deployment
			) );
		} else {
			wp_send_json( array(
				'error' => 'No deployments found',
				'message' => 'No deployments found for this project'
			) );
		}
	}

	public function redeploy_store_front() {
		$project_id = $this->get_project_id();
		if ( empty( $project_id ) ) {
			wp_send_json( array(
				'error' => 'Configuration error',
				'message' => 'Vercel project ID not configured'
			) );
		}

		// First, get the latest deployment to use as a base for the new deployment
		$latest_deployment = $this->get_latest_deployment();
		if ( ! $latest_deployment ) {
			wp_send_json( array(
				'error' => 'No deployments found',
				'message' => 'No existing deployments found for this project'
			) );
		}

		// Check if deployment is already in progress
		if ( $latest_deployment['state'] === 'BUILDING' ) {
			wp_send_json( array(
				'error' => 'Deployment in progress',
				'message' => 'Redeploy is in progress. Wait at least 10 minutes before trying again.'
			) );
		}

		// Trigger new deployment
		$team_id = $this->get_vercel_team_id();
		$url = 'https://api.vercel.com/v13/deployments?forceNew=1&skipAutoDetectionConfirmation=1&teamId=' . $team_id;

		$deployment_data = array(
			'name' => $latest_deployment['name'],
			'deploymentId' => $latest_deployment['uid'],
			'projectSettings' => array(
				'commandForIgnoringBuildStep' => ''
			),
			'target' => 'production'
		);

		$curl = curl_init();
		curl_setopt_array( $curl, array(
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 60, // Set 60 second timeout for deployment trigger
			CURLOPT_CONNECTTIMEOUT => 10, // Set 10 second connection timeout
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_POSTFIELDS => json_encode( $deployment_data ),
			CURLOPT_HTTPHEADER => $this->get_vercel_headers(),
		) );

		$response = curl_exec( $curl );
		$http_code = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
		$curl_error = curl_error( $curl );
		curl_close( $curl );

		// Log for debugging
		error_log( 'BlazeCommerce Redeploy Store Front - URL: ' . $url );
		error_log( 'BlazeCommerce Redeploy Store Front - Payload: ' . json_encode( $deployment_data ) );
		error_log( 'BlazeCommerce Redeploy Store Front - HTTP Code: ' . $http_code );
		error_log( 'BlazeCommerce Redeploy Store Front - Response: ' . $response );
		if ( $curl_error ) {
			error_log( 'BlazeCommerce Redeploy Store Front - cURL Error: ' . $curl_error );
		}

		if ( $curl_error ) {
			wp_send_json( array(
				'error' => 'Connection error',
				'message' => 'Failed to connect to Vercel API: ' . $curl_error
			) );
		}

		if ( $http_code !== 200 && $http_code !== 201 ) {
			wp_send_json( array(
				'error' => 'HTTP error',
				'message' => 'Vercel API returned HTTP ' . $http_code,
				'response' => $response
			) );
		}

		$decoded_response = json_decode( $response, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			wp_send_json( array(
				'error' => 'Invalid response',
				'message' => 'Invalid JSON response from Vercel API',
				'raw_response' => $response
			) );
		}

		if ( isset( $decoded_response['error'] ) ) {
			wp_send_json( array(
				'error' => 'Deployment failed',
				'message' => 'Failed to trigger deployment: ' . $decoded_response['error']['message'],
				'details' => $decoded_response
			) );
		}

		wp_send_json( array(
			'message' => 'Redeploying the store front',
			'deploymentId' => $decoded_response['uid'] ?? $decoded_response['id'] ?? null,
			'deployment' => $decoded_response
		) );
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
