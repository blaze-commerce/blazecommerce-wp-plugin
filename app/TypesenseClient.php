<?php

namespace BlazeWooless;

use Exception;
use Symfony\Component\HttpClient\HttplugClient;
use Typesense\Client;

class TypesenseClient {
	private static $instance = null;
	protected $api_key = null;
	protected $host = null;
	public $store_id = null;
	private $client = null;

	/**
	 * Returns the current class
	 *
	 * @return TypesenseClient
	 */
	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self( bw_get_general_settings() );
		}

		return self::$instance;
	}

	public function __construct( $settings ) {
		try {
			if ( ! (
				array_key_exists( 'typesense_api_key', $settings ) &&
				array_key_exists( 'store_id', $settings ) &&
				array_key_exists( 'typesense_host', $settings )
			)
			) {
				throw new Exception( 'Typesense settings not found' );
			}
			$this->api_key  = $settings['typesense_api_key'];
			$this->store_id = $settings['store_id'];
			$this->host     = $settings['typesense_host'];

			try {
				$client = $this->get_client( $this->api_key, $settings['typesense_host'] );
			} catch (\Throwable $th) {
				$client = null;
			}

			$this->client = $client;
		} catch (\Throwable $th) {
			$this->client = null;
		}

	}

	public function can_connect() {

		if ( ! empty( $this->api_key ) && ! empty( $this->host ) ) {
			return true;
		}

		return false;
	}

	public function debug() {
		return array(
			$this->api_key,
			$this->store_id,
			$this->host,
		);
	}

	public function get_client( $api_key, $host ) {
		$this->host = $host;
		return new Client( [ 
			'api_key' => $api_key,
			'nodes' => [ 
				[ 
					'host' => $this->host,
					'port' => '443',
					'protocol' => 'https',
				],
			],
			'client' => new HttplugClient(),
		] );
	}

	public function client() {
		return $this->client;
	}

	public function get_api_key() {
		return $this->api_key;
	}

	public function get_host() {
		return $this->host;
	}

	public function get_documents( $collection ) {
		return $this->client->collections[ $collection ]->documents;
	}

	public function site_info() {
		return $this->get_documents( 'site_info-' . $this->store_id );
	}

	public function taxonomy() {
		return $this->get_documents( 'taxonomy-' . $this->store_id );
	}

	public function product() {
		return $this->get_documents( 'product-' . $this->store_id );
	}

	public function menu() {
		return $this->get_documents( 'menu-' . $this->store_id );
	}

	public function test_connection( $api_key, $store_id, $environement ) {
		$client = $this->get_client( $api_key, $environement );
		try {
			$collections = $client->collections->retrieve();
			return array( 'status' => 'success', 'message' => 'Typesense is working!', 'collection' => $collections );
		} catch (\Typesense\Exception\ObjectNotFound $e) {
			return array( 'status' => 'error', 'message' => 'Collection not found: ' . $e->getMessage() );
		} catch (\Typesense\Exception\TypesenseClientError $e) {
			return array( 'status' => 'error', 'message' => 'Typesense client error: ' . $e->getMessage() );
		} catch (\Exception $e) {
			return array( 'status' => 'error', 'message' => 'There was an error connecting to Typesense: ' . $e->getMessage() );
		}
	}

	public function delete_all_synonyms() {
		try {
			if ( is_null( $this->client ) ) {
				throw new Exception( 'TypesenseClient is not initialized' );
			}

			$synonims = $this->client->collections[ 'product-' . $this->store_id ]->synonyms->retrieve();
			if ( ! isset( $synonims['synonyms'] ) || count( $synonims['synonyms'] ) === 0 ) {
				throw new Exception( 'No synonyms found' );
			}

			$delete_report = array();

			foreach ( $synonims['synonyms'] as $synonym ) {
				$delete_report = $this->client->collections[ 'product-' . $this->store_id ]->synonyms[ $synonym['id'] ]->delete();
			}

			do_action(
				"inspect",
				array(
					"delete_all_symptons",
					array(
						"report" => $delete_report,
						"synonyms" => $synonims,
					)
				)
			);

		} catch (Exception $e) {
			do_action(
				"inspect",
				array(
					"delete_all_symptons",
					array(
						"error" => $e->getMessage(),
					)
				)
			);
		}
	}

	public function set_synonym( $type, $value, $key = '' ) {

		try {
			if ( is_null( $this->client ) ) {
				throw new Exception( 'TypesenseClient is not initialized' );
			}

			if ( $type === 'multi-way' ) {
				$synonym_key  = $value[0] . '-synonyms';
				$synonym_data = array(
					"synonyms" => $value
				);

			} else {
				$synonym_key  = sanitize_title( $key ) . '-synonyms';
				$synonym_data = array(
					'root' => $key,
					'synonyms' => $value
				);
			}

			$response = $this->client->collections[ 'product-' . $this->store_id ]->synonyms->upsert( $synonym_key, $synonym_data );

			do_action(
				"inspect",
				array(
					"add_sympton",
					array(
						"type" => $type,
						"value" => $value,
						"key" => $key,
						"synonym_key" => $synonym_key,
						"synonym_data" => $synonym_data,
						"response" => $response,
					)
				)
			);
		} catch (Exception $e) {
			do_action(
				"inspect",
				array(
					"add_sympton",
					array(
						"error" => $e->getMessage(),
					)
				)
			);
		}
	}
}
