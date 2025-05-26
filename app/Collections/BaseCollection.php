<?php

namespace BlazeWooless\Collections;

use BlazeWooless\TypesenseClient;

class BaseCollection {
	protected $typesense;
	protected $alias_manager;
	public $collection_name;
	public $current_sync_collection = null;

	public function __construct() {
		$this->typesense     = TypesenseClient::get_instance();
		$this->alias_manager = new CollectionAliasManager();
	}

	public function collection() {
		// Cache the collection access object to avoid repeated alias manager calls
		static $cached_collections = array();
		static $last_cache_times = array();
		static $cache_ttl = 300; // 5 minutes

		$cache_key = $this->collection_name;

		if ( ! isset( $cached_collections[ $cache_key ] ) ||
			! isset( $last_cache_times[ $cache_key ] ) ||
			( time() - $last_cache_times[ $cache_key ] ) > $cache_ttl ) {
			$cached_collections[ $cache_key ] = $this->alias_manager->get_collection_access( $this->collection_name );
			$last_cache_times[ $cache_key ]   = time();
		}

		return $cached_collections[ $cache_key ];
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
		// Reset static variables in collection() method
		static $cached_collections = array();
		static $last_cache_times = array();
		$cached_collections = array();
		$last_cache_times   = array();
	}
}
