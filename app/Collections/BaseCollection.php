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
		return $this->alias_manager->get_collection_access( $this->collection_name );
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
	 * Get new collection name with timestamp
	 */
	public function get_new_collection_name( $timestamp = null ) {
		return $this->alias_manager->get_collection_name( $this->collection_name, $timestamp );
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
		$batch_files = array_map( function ($data) {
			return json_encode( $data );
		}, $batch );
		$to_jsonl    = implode( PHP_EOL, $batch_files );

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
	 * Initialize collection with alias support
	 * This method handles the new alias-based sync process
	 */
	public function initialize_with_alias( $schema ) {
		// Clean up any newer collections from interrupted syncs
		$this->alias_manager->cleanup_newer_collections( $this->collection_name );

		// Create new timestamped collection
		$new_collection_name = $this->get_new_collection_name();
		$schema['name']      = $new_collection_name;

		try {
			$this->create_collection( $schema );
			return $new_collection_name;
		} catch (\Exception $e) {
			throw new \Exception( "Failed to create new collection: " . $e->getMessage() );
		}
	}

	/**
	 * Complete the sync by updating alias and cleaning up
	 */
	public function complete_sync( $new_collection_name ) {
		try {
			// Update alias to point to new collection
			$this->alias_manager->update_alias( $this->collection_name, $new_collection_name );

			// Clean up old collections (keep 1 previous)
			$deleted = $this->alias_manager->cleanup_old_collections( $this->collection_name, 1 );

			return array(
				'success' => true,
				'new_collection' => $new_collection_name,
				'deleted_collections' => $deleted
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
}
