<?php

namespace BlazeWooless;

use Exception;
use Symfony\Component\HttpClient\HttplugClient;
use Typesense\Client;
use BlazeWooless\Collections\CollectionAliasManager;

class TypesenseClient {
	private static $instance = null;
	protected $api_key = null;
	protected $host = null;
	public $store_id = null;
	private $client = null;
	private $site_url = null;
	private $alias_manager = null;

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

			// Validate and sanitize settings using DataValidator if available
			if ( class_exists( '\\BlazeWooless\\Features\\DataValidator' ) ) {
				$validator = \BlazeWooless\Features\DataValidator::get_instance();

				// Sanitize API key and host
				$api_key = $validator->sanitize_text( $settings['typesense_api_key'] );
				$store_id = $validator->sanitize_text( $settings['store_id'] );
				$host = $validator->validate_url( $settings['typesense_host'] );

				$this->api_key = $api_key;
				$this->store_id = $store_id;
				$this->host = $host;
			} else {
				// Fallback to basic sanitization if DataValidator is not available
				$this->api_key = sanitize_text_field( $settings['typesense_api_key'] );
				$this->store_id = sanitize_text_field( $settings['store_id'] );
				$this->host = esc_url_raw( $settings['typesense_host'] );
			}

			$this->site_url = $this->normalize_site_url( site_url() );

			try {
				$client = $this->get_client( $this->api_key, $this->host );
			} catch (\Throwable $th) {
				$client = null;
			}

			$this->client = $client;
		} catch (\Throwable $th) {
			$this->client = null;
		}

		// Don't initialize alias manager in constructor to avoid circular dependency
		// It will be initialized lazily when needed
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

	public function get_site_url() {
		return $this->site_url;
	}

	/**
	 * Validate connection parameters
	 *
	 * @param string $api_key API key to validate
	 * @param string $host Host URL to validate
	 * @param string $store_id Store ID to validate
	 * @return array Validation result with status and message
	 */
	public function validate_connection_params( $api_key, $host, $store_id ) {
		$errors = array();

		// Validate API key
		if ( empty( $api_key ) ) {
			$errors[] = 'API key is required';
		} elseif ( strlen( $api_key ) < 10 ) {
			$errors[] = 'API key appears to be too short';
		}

		// Validate host
		if ( empty( $host ) ) {
			$errors[] = 'Host URL is required';
		} elseif ( ! filter_var( $host, FILTER_VALIDATE_URL ) ) {
			$errors[] = 'Host URL is not valid';
		}

		// Validate store ID
		if ( empty( $store_id ) ) {
			$errors[] = 'Store ID is required';
		} elseif ( ! preg_match( '/^[a-zA-Z0-9_-]+$/', $store_id ) ) {
			$errors[] = 'Store ID contains invalid characters';
		}

		if ( empty( $errors ) ) {
			return array(
				'status' => 'valid',
				'message' => 'Connection parameters are valid'
			);
		} else {
			return array(
				'status' => 'invalid',
				'message' => 'Validation errors: ' . implode( ', ', $errors ),
				'errors' => $errors
			);
		}
	}

	/**
	 * Normalize site URL for collection naming
	 * Removes protocol, www, and trailing slashes
	 */
	public function normalize_site_url( $url ) {
		// Remove protocol
		$url = preg_replace( '/^https?:\/\//', '', $url );

		// Remove www
		$url = preg_replace( '/^www\./', '', $url );

		// Remove trailing slash
		$url = rtrim( $url, '/' );

		// Replace special characters except dots and hyphens with underscores for collection naming
		$url = preg_replace( '/[^a-zA-Z0-9_.-]/', '_', $url );

		return $url;
	}

	public function get_documents( $collection ) {
		return $this->client->collections[ $collection ]->documents;
	}

	/**
	 * Get alias manager instance (lazy initialization to avoid circular dependency)
	 */
	private function get_alias_manager() {
		if ( $this->alias_manager === null && $this->client !== null ) {
			// Pass $this to avoid circular dependency
			$this->alias_manager = new CollectionAliasManager( $this );
		}
		return $this->alias_manager;
	}

	/**
	 * Get collection access object using active collection system
	 * This method uses the active collection name from BaseCollection to ensure
	 * CRUD operations target the correct live collection
	 */
	private function get_active_collection_access( $collection_type ) {
		$alias_manager = $this->get_alias_manager();
		$use_aliases   = apply_filters( 'blazecommerce/use_collection_aliases', true );

		if ( $alias_manager !== null && $use_aliases ) {
			// Create a temporary BaseCollection instance to get active collection name
			$temp_collection                  = new \BlazeWooless\Collections\BaseCollection();
			$temp_collection->collection_name = $collection_type;

			$active_collection_name = $temp_collection->get_active_collection_name();
			return $this->client->collections[ $active_collection_name ];
		}

		// Fallback to legacy naming when aliases are disabled
		$legacy_name = $collection_type . '-' . $this->store_id;
		return $this->client->collections[ $legacy_name ];
	}

	/**
	 * Get collection access object with alias support (backward compatibility)
	 * Falls back to legacy naming if alias manager is not available
	 * @deprecated Use get_active_collection_access() for CRUD operations
	 */
	private function get_collection_access( $collection_type ) {
		return $this->get_active_collection_access( $collection_type );
	}

	public function site_info() {
		return $this->get_active_collection_access( 'site_info' )->documents;
	}

	public function taxonomy() {
		return $this->get_active_collection_access( 'taxonomy' )->documents;
	}

	public function product() {
		return $this->get_active_collection_access( 'product' )->documents;
	}

	public function menu() {
		return $this->get_active_collection_access( 'menu' )->documents;
	}

	public function navigation() {
		return $this->get_active_collection_access( 'navigation' )->documents;
	}

	public function page() {
		return $this->get_active_collection_access( 'page' )->documents;
	}

	public function test_connection( $api_key, $store_id, $environment ) {
		// First validate connection parameters
		$validation = $this->validate_connection_params( $api_key, $environment, $store_id );
		if ( $validation['status'] !== 'valid' ) {
			return array( 'status' => 'error', 'message' => $validation['message'] );
		}

		// If using DataValidator, sanitize inputs
		if ( class_exists( '\\BlazeWooless\\Features\\DataValidator' ) ) {
			$validator = \BlazeWooless\Features\DataValidator::get_instance();
			$api_key = $validator->sanitize_text( $api_key );
			$store_id = $validator->sanitize_text( $store_id );
			$environment = $validator->validate_url( $environment );
		}

		$client = $this->get_client( $api_key, $environment );
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

			$synonims = $this->get_active_collection_access( 'product' )->synonyms->retrieve();
			if ( ! isset( $synonims['synonyms'] ) || count( $synonims['synonyms'] ) === 0 ) {
				throw new Exception( 'No synonyms found' );
			}

			$delete_report = array();

			foreach ( $synonims['synonyms'] as $synonym ) {
				$delete_report = $this->get_active_collection_access( 'product' )->synonyms[ $synonym['id'] ]->delete();
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

			$response = $this->get_active_collection_access( 'product' )->synonyms->upsert( $synonym_key, $synonym_data );

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
