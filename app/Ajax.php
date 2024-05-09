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
		add_action( 'wp_ajax_get_typesense_collections', array( $this, 'get_typesense_collections' ) );
		add_action( 'wp_ajax_save_typesense_api_key', 'save_typesense_api_key' );
		add_action( 'wp_ajax_login_to_client', array( $this, 'login_to_client' ) );
		add_action( 'wp_ajax_nopriv_login_to_client', array( $this, 'login_to_client' ) );
	}

	public function get_typesense_collections() {
		if ( isset( $_POST['api_key'] ) ) {
			$encoded_api_key       = sanitize_text_field( $_POST['api_key'] );
			$decoded_api_key       = base64_decode( $encoded_api_key );
			$trimmed_api_key       = explode( ':', $decoded_api_key );
			$typesense_private_key = $trimmed_api_key[0];
			$wooless_site_id       = $trimmed_api_key[1];

			$client = TypesenseCLient::get_instance()->client();


			try {
				$collection_name = 'product-' . $wooless_site_id;
				$collections     = $client->collections[ $collection_name ]->retrieve();
				if ( ! empty( $collections ) ) {
					echo json_encode( [ 'status' => 'success', 'message' => 'Typesense is working!', 'collection' => $collections ] );
				} else {
					echo json_encode( [ 'status' => 'error', 'message' => 'No collection found for store ID: ' . $wooless_site_id ] );
				}
			} catch (Typesense\Exception\ObjectNotFound $e) {
				echo json_encode( [ 'status' => 'error', 'message' => 'Collection not found: ' . $e->getMessage() ] );
			} catch (Typesense\Exception\TypesenseClientError $e) {
				echo json_encode( [ 'status' => 'error', 'message' => 'Typesense client error: ' . $e->getMessage() ] );
			} catch (Exception $e) {
				echo json_encode( [ 'status' => 'error', 'message' => 'There was an error connecting to Typesense: ' . $e->getMessage() ] );
			}
		} else {
			echo json_encode( [ 'status' => 'error', 'message' => 'API key not provided.' ] );
		}

		wp_die();
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

	public function login_to_client() {
		$login    = $_POST['login'];
		$password = $_POST['password'];

		$url = get_home_url() . '/api/login-with-cookies';

		$curl = curl_init();

		curl_setopt_array( $curl, array(
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_POSTFIELDS => '{
				"login": "' . $login . '",
				"password": "' . $password . '"
			}',
			CURLOPT_HTTPHEADER => array( 'Content-Type: application/json' ),
		) );

		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $curl, CURLOPT_HEADER, 1 );

		$response = curl_exec( $curl );

		$header_size = curl_getinfo( $curl, CURLINFO_HEADER_SIZE );
		$header      = substr( $response, 0, $header_size );
		$body        = substr( $response, $header_size );

		$re = '/^Set-Cookie.*$/m';
		preg_match_all( $re, $header, $matches, PREG_SET_ORDER, 0 );
		foreach ( $matches as $matchedHeader ) {
			header( trim( $matchedHeader[0] ) );
		}

		curl_close( $curl );
		wp_die();
	}
}

Ajax::get_instance();
