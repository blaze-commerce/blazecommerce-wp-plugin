<?php

namespace BlazeWooless\Collections;

use BlazeWooless\TypesenseClient;

class BaseCollection {
	protected $typesense;
	protected $alias_manager;
	public $collection_name;
	public $current_sync_collection = null;

	// Class-level cache for collection access objects
	private static $collection_cache = array();
	private static $collection_cache_times = array();
	private static $collection_cache_ttl = 300; // 5 minutes

	public function __construct() {
		$this->typesense = TypesenseClient::get_instance();
		// Pass TypesenseClient instance to avoid circular dependency
		$this->alias_manager = new CollectionAliasManager( $this->typesense );
	}

	public function collection() {
		// Use class-level static cache instead of method-level static
		$cache_key = $this->collection_name;

		if ( ! isset( self::$collection_cache[ $cache_key ] ) ||
			! isset( self::$collection_cache_times[ $cache_key ] ) ||
			( time() - self::$collection_cache_times[ $cache_key ] ) > self::$collection_cache_ttl ) {
			self::$collection_cache[ $cache_key ]       = $this->alias_manager->get_collection_access( $this->collection_name );
			self::$collection_cache_times[ $cache_key ] = time();
		}

		return self::$collection_cache[ $cache_key ];
	}

	public function client() {
		return $this->typesense->client();
	}

	public function store_id() {
		return $this->typesense->store_id;
	}

	public function collection_name() {
		return $this->collection_name . '-' . $this->typesense->store_id;
	}

	/**
	 * Get alias name for this collection type
	 */
	public function get_alias_name() {
		return $this->alias_manager->get_alias_name( $this->collection_name );
	}

	/**
	 * Get new collection name with suffix (a or b)
	 */
	public function get_new_collection_name( $suffix = null ) {
		return $this->alias_manager->get_collection_name( $this->collection_name, $suffix );
	}

	/**
	 * Get the inactive collection name (for blue-green deployment)
	 */
	public function get_inactive_collection_name() {
		return $this->alias_manager->get_inactive_collection( $this->collection_name );
	}

	/**
	 * Get current live collection name
	 */
	public function get_current_collection() {
		return $this->alias_manager->get_current_collection( $this->collection_name );
	}

	public function create_collection( $args ) {
		return $this->client()->collections->create( $args );
	}

	public function retrieve() {
		return $this->collection()->retrieve();
	}

	public function drop_collection() {
		try {
			return $this->collection()->delete();
		} catch (\Exception $e) {
			return $e;
		}
	}

	public function import( $batch ) {
		// Memory optimization: Process batch in chunks and use streaming
		$batch_files = array();
		foreach ( $batch as $data ) {
			$batch_files[] = json_encode( $data );
			// Free memory immediately after encoding
			unset( $data );
		}
		$to_jsonl = implode( PHP_EOL, $batch_files );

		// Free the batch_files array to save memory
		unset( $batch_files );

		// Determine which collection to use
		$target_collection = $this->get_target_collection_name();

		$curl = curl_init();

		curl_setopt_array( $curl, array(
			CURLOPT_URL => 'https://' . $this->typesense->get_host() . '/collections/' . $target_collection . '/documents/import?action=upsert',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_POSTFIELDS => $to_jsonl,
			CURLOPT_HTTPHEADER => array(
				'X-TYPESENSE-API-KEY: ' . $this->typesense->get_api_key(),
				'Content-Type: text/plain'
			),
		) );

		$response = curl_exec( $curl );

		curl_close( $curl );

		$response_from_jsonl = explode( PHP_EOL, $response );

		$mapped_response = array_map( function ($resp) {
			return json_decode( $resp, true );
		}, $response_from_jsonl );

		return $mapped_response;
	}

	/**
	 * Get the target collection name for operations
	 * Returns the new collection name if using aliases, otherwise the legacy name
	 */
	public function get_target_collection_name() {
		$use_aliases = apply_filters( 'blazecommerce/use_collection_aliases', true );

		if ( $use_aliases && isset( $this->current_sync_collection ) ) {
			return $this->current_sync_collection;
		}

		return $this->collection_name();
	}

	public function create( $args ) {
		return $this->collection()->documents->create( $args );
	}

	public function update( $id, $document_data ) {
		return $this->collection()->documents[ $id ]->update( $document_data );
	}

	public function upsert( $document_data ) {
		return $this->collection()->documents->upsert( $document_data );
	}

	/**
	 * Initialize collection with alias support using blue-green deployment
	 * This method handles the new alias-based sync process
	 */
	public function initialize_with_alias( $schema ) {
		// Get the inactive collection name (the one we'll sync to)
		$inactive_collection_name = $this->get_inactive_collection_name();
		$schema['name']           = $inactive_collection_name;

		try {
			// Try to delete the inactive collection if it exists (cleanup from previous sync)
			$this->alias_manager->delete_collection( $inactive_collection_name );

			// Create the new collection
			$this->create_collection( $schema );
			return $inactive_collection_name;
		} catch (\Exception $e) {
			throw new \Exception( "Failed to create new collection: " . $e->getMessage() );
		}
	}

	/**
	 * Complete the sync by updating alias (blue-green deployment)
	 */
	public function complete_sync( $new_collection_name ) {
		try {
			// Update alias to point to new collection (atomic switch)
			$this->alias_manager->update_alias( $this->collection_name, $new_collection_name );

			// In blue-green deployment, we don't clean up the old collection immediately
			// It becomes the inactive collection for the next sync
			return array(
				'success' => true,
				'new_collection' => $new_collection_name,
				'switched_alias' => true
			);
		} catch (\Exception $e) {
			throw new \Exception( "Failed to complete sync: " . $e->getMessage() );
		}
	}

	/**
	 * Unified method to complete collection sync with standardized logging and cleanup
	 *
	 * @param string $collection_type The type of collection (e.g., 'product', 'taxonomy', 'page', etc.)
	 * @param array $options Optional parameters for specific collection needs
	 *                      - 'clear_transient' => string: transient key to clear (used by Page collection)
	 *                      - 'fallback_message' => string: custom message when no alias sync needed
	 * @return array|null Result of sync operation or null if no sync needed
	 */
	public function complete_collection_sync( $collection_type, $options = array() ) {
		$use_aliases = apply_filters( 'blazecommerce/use_collection_aliases', true );

		if ( $use_aliases && isset( $this->current_sync_collection ) ) {
			$logger  = wc_get_logger();
			$context = array( 'source' => 'wooless-' . $collection_type . '-collection-complete' );

			try {
				$result = $this->complete_sync( $this->current_sync_collection );
				$logger->debug( 'TS ' . ucfirst( $collection_type ) . ' sync completed: ' . print_r( $result, true ), $context );

				// Clear the current sync collection
				unset( $this->current_sync_collection );

				// Handle optional transient cleanup (used by Page collection)
				if ( isset( $options['clear_transient'] ) ) {
					delete_transient( $options['clear_transient'] );
				}

				return $result;
			} catch (\Exception $e) {
				$logger->debug( 'TS ' . ucfirst( $collection_type ) . ' sync completion failed: ' . $e->getMessage(), $context );
				throw $e;
			}
		}

		// Return custom fallback message or default null
		if ( isset( $options['fallback_message'] ) ) {
			return array( 'success' => true, 'message' => $options['fallback_message'] );
		}

		return null;
	}



	/**
	 * Check if using alias system
	 */
	public function is_using_aliases() {
		return $this->alias_manager->alias_exists( $this->collection_name );
	}

	/**
	 * Get collection for direct operations (bypassing alias)
	 */
	public function get_direct_collection( $collection_name ) {
		return $this->client()->collections[ $collection_name ];
	}

	/**
	 * Clear collection cache (useful when aliases change)
	 */
	public static function clear_collection_cache() {
		// Clear the class-level static cache
		self::$collection_cache       = array();
		self::$collection_cache_times = array();
	}
}
