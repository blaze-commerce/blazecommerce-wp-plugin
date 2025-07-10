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
		add_action( 'wp_ajax_nopriv_index_data_to_typesense', array( $this, 'index_data_to_typesense' ) );

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
		$api_key  = bw_get_general_settings( 'typesense_api_key' );
		$store_id = bw_get_general_settings( 'store_id' );
		return array(
			'x-wooless-secret-token: ' . base64_encode( $api_key . ':' . $store_id ),
			'Content-Type: application/json'
		);
	}

	public function get_vercel_headers() {
		$vercel_token = bw_get_general_settings( 'vercel_deployment_token' );
		$headers = array(
			'Authorization: Bearer ' . $vercel_token,
			'Content-Type: application/json'
		);

		// Add team ID if specified
		$team_id = bw_get_general_settings( 'vercel_team_id' );
		if ( ! empty( $team_id ) ) {
			$headers[] = 'X-Vercel-Team-Id: ' . $team_id;
		}

		return $headers;
	}

	public function is_direct_vercel_enabled() {
		return bw_get_general_settings( 'enable_direct_vercel_deployment' ) == 1;
	}

	public function check_deployment() {
		// Check if direct Vercel deployment is enabled
		if ( $this->is_direct_vercel_enabled() ) {
			// For Vercel API, we need the deployment ID to check status
			$deployment_id = isset( $_POST['deployment_id'] ) ? sanitize_text_field( $_POST['deployment_id'] ) : '';
			if ( ! empty( $deployment_id ) ) {
				$this->check_vercel_deployment( $deployment_id );
			} else {
				wp_send_json( array(
					'error' => 'Deployment ID required for Vercel API',
					'state' => 'ERROR'
				) );
			}
		} else {
			$this->check_deployment_via_blazecommerce_middleware();
		}
	}

	public function check_deployment_via_blazecommerce_middleware() {
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
			CURLOPT_TIMEOUT => 30,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'GET',
			CURLOPT_HTTPHEADER => $this->get_headers(),
		) );

		$response = curl_exec( $curl );
		$http_code = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
		$curl_error = curl_error( $curl );
		curl_close( $curl );

		// Handle cURL errors
		if ( $curl_error ) {
			wp_send_json( array(
				'error' => 'Network error: ' . $curl_error,
				'state' => 'ERROR'
			) );
		}

		// Handle HTTP errors
		if ( $http_code !== 200 ) {
			wp_send_json( array(
				'error' => 'HTTP error: ' . $http_code,
				'state' => 'ERROR'
			) );
		}

		// Parse and validate response
		$decoded_response = json_decode( $response, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			wp_send_json( array(
				'error' => 'Invalid JSON response: ' . json_last_error_msg(),
				'state' => 'ERROR'
			) );
		}

		wp_send_json( $decoded_response );
	}

	public function redeploy_store_front() {
		// Check if direct Vercel deployment is enabled
		if ( $this->is_direct_vercel_enabled() ) {
			$this->redeploy_via_vercel_api();
		} else {
			$this->redeploy_via_blazecommerce_middleware();
		}
	}

	public function redeploy_via_vercel_api() {
		$vercel_token = bw_get_general_settings( 'vercel_deployment_token' );
		$project_id = bw_get_general_settings( 'vercel_project_id' );

		if ( empty( $vercel_token ) ) {
			wp_send_json( array(
				'error' => 'Vercel deployment token is required.',
				'state' => 'ERROR'
			) );
		}

		if ( empty( $project_id ) ) {
			wp_send_json( array(
				'error' => 'Vercel project ID is required.',
				'state' => 'ERROR'
			) );
		}

		// Create deployment via Vercel API
		$deployment_data = array(
			'name' => get_bloginfo( 'name' ) . '-' . time(),
			'project' => $project_id,
			'target' => 'production',
			'meta' => array(
				'source' => 'blazecommerce-plugin',
				'wordpress_site' => home_url()
			)
		);

		$curl = curl_init();
		curl_setopt_array( $curl, array(
			CURLOPT_URL => 'https://api.vercel.com/v13/deployments',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 30,
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

		// Handle cURL errors
		if ( $curl_error ) {
			wp_send_json( array(
				'error' => 'Network error: ' . $curl_error,
				'state' => 'ERROR'
			) );
		}

		// Handle HTTP errors
		if ( $http_code !== 200 && $http_code !== 201 ) {
			$error_response = json_decode( $response, true );
			$error_message = isset( $error_response['error']['message'] )
				? $error_response['error']['message']
				: 'HTTP error: ' . $http_code;

			wp_send_json( array(
				'error' => $error_message,
				'state' => 'ERROR'
			) );
		}

		// Parse response
		$decoded_response = json_decode( $response, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			wp_send_json( array(
				'error' => 'Invalid JSON response',
				'state' => 'ERROR'
			) );
		}

		// Transform Vercel response to match expected format
		$transformed_response = array(
			'state' => $this->map_vercel_state( $decoded_response['readyState'] ?? 'BUILDING' ),
			'url' => $decoded_response['url'] ?? '',
			'deployment_id' => $decoded_response['uid'] ?? '',
			'created_at' => $decoded_response['createdAt'] ?? time() * 1000
		);

		wp_send_json( $transformed_response );
	}

	public function redeploy_via_blazecommerce_middleware() {
		$api_key = bw_get_general_settings( 'typesense_api_key' );
		if ( empty( $api_key ) ) {
			wp_send_json( array(
				'error' => 'Empty api key.',
				'message' => 'Empty api key.'
			) );
		}

		// Prepare the payload with required "files" field as an array
		$deployment_payload = array(
			'files' => array(), // Required by middleware API as an array
			'source' => 'blazecommerce-plugin',
			'wordpress_site' => home_url(),
			'store_id' => bw_get_general_settings( 'store_id' ),
			'timestamp' => time()
		);

		$curl = curl_init();
		curl_setopt_array( $curl, array(
			CURLOPT_URL => 'https://my-wooless-admin-portal.vercel.app/api/deployments',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_POSTFIELDS => json_encode( $deployment_payload ),
			CURLOPT_HTTPHEADER => $this->get_headers(),
		) );

		$response = curl_exec( $curl );
		$http_code = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
		$curl_error = curl_error( $curl );
		curl_close( $curl );

		// Handle cURL errors
		if ( $curl_error ) {
			wp_send_json( array(
				'error' => 'Network error: ' . $curl_error,
				'message' => 'Failed to trigger redeploy due to network error'
			) );
		}

		// Handle HTTP errors
		if ( $http_code !== 200 ) {
			// Include response body for better debugging
			$error_details = 'HTTP error: ' . $http_code;
			if ( ! empty( $response ) ) {
				$error_details .= ' - Response: ' . $response;
			}

			wp_send_json( array(
				'error' => $error_details,
				'message' => 'Failed to trigger redeploy due to HTTP error',
				'http_code' => $http_code,
				'response_body' => $response
			) );
		}

		// Parse and validate response
		$decoded_response = json_decode( $response, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			wp_send_json( array(
				'error' => 'Invalid JSON response: ' . json_last_error_msg(),
				'message' => 'Failed to parse redeploy response'
			) );
		}

		// Ensure response has a message
		if ( ! isset( $decoded_response['message'] ) ) {
			$decoded_response['message'] = 'Redeploy triggered successfully';
		}

		wp_send_json( $decoded_response );
	}

	public function map_vercel_state( $vercel_state ) {
		$state_mapping = array(
			'BUILDING' => 'BUILDING',
			'READY' => 'READY',
			'ERROR' => 'ERROR',
			'CANCELED' => 'ERROR',
			'QUEUED' => 'BUILDING'
		);

		return isset( $state_mapping[ $vercel_state ] )
			? $state_mapping[ $vercel_state ]
			: 'BUILDING';
	}

	public function check_vercel_deployment( $deployment_id ) {
		$vercel_token = bw_get_general_settings( 'vercel_deployment_token' );

		if ( empty( $vercel_token ) || empty( $deployment_id ) ) {
			wp_send_json( array(
				'error' => 'Missing Vercel token or deployment ID',
				'state' => 'ERROR'
			) );
		}

		$curl = curl_init();
		curl_setopt_array( $curl, array(
			CURLOPT_URL => 'https://api.vercel.com/v13/deployments/' . $deployment_id,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 30,
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
			wp_send_json( array(
				'error' => 'Failed to check deployment status',
				'state' => 'ERROR'
			) );
		}

		$decoded_response = json_decode( $response, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			wp_send_json( array(
				'error' => 'Invalid JSON response',
				'state' => 'ERROR'
			) );
		}

		$transformed_response = array(
			'state' => $this->map_vercel_state( $decoded_response['readyState'] ?? 'BUILDING' ),
			'url' => $decoded_response['url'] ?? '',
			'deployment_id' => $decoded_response['uid'] ?? '',
			'created_at' => $decoded_response['createdAt'] ?? time() * 1000
		);

		wp_send_json( $transformed_response );
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
