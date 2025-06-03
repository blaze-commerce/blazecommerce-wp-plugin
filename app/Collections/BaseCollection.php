<?php

namespace BlazeWooless\Collections;

use BlazeWooless\TypesenseClient;

class BaseCollection {
	protected $typesense;
	protected $alias_manager;
	public $collection_name;
	public $active_sync_collection = null;

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
	 * Get active collection name for this collection type
	 * This is the main method that should be used to determine which collection to target
	 * for update/upsert operations across all collection types (navigation, menu, product, page, site_info, taxonomy)
	 *
	 * @return string The active collection name to use for operations
	 */
	public function get_active_collection_name() {
		$use_aliases = apply_filters( 'blazecommerce/use_collection_aliases', true );

		if ( $use_aliases ) {
			// For CRUD operations, prioritize stored active collection from WordPress options
			// This ensures CRUD operations target the collection that was last synced to
			$stored_collection = $this->get_stored_active_collection();
			if ( $stored_collection ) {
				// Log which collection we're using for debugging
				$logger  = wc_get_logger();
				$context = array( 'source' => 'wooless-active-collection-debug' );
				$logger->debug( 'Using stored active collection for ' . $this->collection_name . ': ' . $stored_collection, $context );
				return $stored_collection;
			}

			// Fallback to alias-pointed collection if no stored collection exists
			$alias_collection = $this->alias_manager->get_current_collection( $this->collection_name );
			if ( $alias_collection ) {
				// Log fallback usage for debugging
				$logger  = wc_get_logger();
				$context = array( 'source' => 'wooless-active-collection-debug' );
				$logger->debug( 'Using alias-pointed collection for ' . $this->collection_name . ': ' . $alias_collection, $context );
				return $alias_collection;
			}
		}

		// Fallback to legacy naming
		return $this->collection_name();
	}

	/**
	 * Get current live collection name (legacy method for backward compatibility)
	 * @deprecated Use get_active_collection_name() instead
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

		// Determine which collection to use for bulk import (sync operations)
		$target_collection = $this->get_sync_collection_name();

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
	 * Get the sync collection name for bulk import operations during full sync
	 * This method should be used during sync operations to target the inactive collection
	 *
	 * @return string The collection name to use for sync operations
	 */
	public function get_sync_collection_name() {
		$use_aliases = apply_filters( 'blazecommerce/use_collection_aliases', true );

		if ( $use_aliases ) {
			// During sync operations, use the sync collection if set
			if ( isset( $this->active_sync_collection ) ) {
				// Log which collection we're using for sync operations
				$logger  = wc_get_logger();
				$context = array( 'source' => 'wooless-sync-collection-debug' );
				$logger->debug( 'Using sync collection for ' . $this->collection_name . ': ' . $this->active_sync_collection, $context );
				return $this->active_sync_collection;
			}

			// If no sync collection is set, get the inactive collection for sync
			$inactive_collection = $this->get_inactive_collection_name();
			$logger              = wc_get_logger();
			$context             = array( 'source' => 'wooless-sync-collection-debug' );
			$logger->debug( 'Using inactive collection for sync ' . $this->collection_name . ': ' . $inactive_collection, $context );
			return $inactive_collection;
		}

		// Fallback to legacy naming for sync operations
		return $this->collection_name();
	}

	/**
	 * Get the target collection name for operations
	 * Returns the active collection name if using aliases, otherwise the legacy name
	 * @deprecated Use get_active_collection_name() instead
	 */
	public function get_target_collection_name() {
		return $this->get_active_collection_name();
	}

	/**
	 * Get the option key for storing active collection name
	 */
	private function get_active_collection_option_key() {
		return 'blazecommerce_active_collection_' . $this->collection_name;
	}

	/**
	 * Store the active collection name in WordPress options
	 * This stores the collection that should be used for CRUD operations
	 */
	public function store_active_collection( $collection_name ) {
		$option_key = $this->get_active_collection_option_key();
		update_option( $option_key, $collection_name );

		// Log the storage for debugging
		$logger  = wc_get_logger();
		$context = array( 'source' => 'wooless-collection-storage' );
		$logger->debug( 'Stored active collection for ' . $this->collection_name . ': ' . $collection_name, $context );
	}

	/**
	 * Retrieve the active collection name from WordPress options
	 */
	public function get_stored_active_collection() {
		$option_key = $this->get_active_collection_option_key();
		return get_option( $option_key, null );
	}

	/**
	 * Clear the stored active collection name from WordPress options
	 */
	public function clear_stored_active_collection() {
		$option_key = $this->get_active_collection_option_key();
		delete_option( $option_key );

		// Log the clearing for debugging
		$logger  = wc_get_logger();
		$context = array( 'source' => 'wooless-collection-storage' );
		$logger->debug( 'Cleared stored active collection for ' . $this->collection_name, $context );
	}

	/**
	 * Clear the sync collection state (used after sync completion)
	 */
	public function clear_sync_collection_state() {
		// Clear the sync collection variable since sync is complete
		unset( $this->active_sync_collection );

		// Log the clearing for debugging
		$logger  = wc_get_logger();
		$context = array( 'source' => 'wooless-sync-state-debug' );
		$logger->debug( 'Cleared sync collection state for ' . $this->collection_name, $context );
	}

	/**
	 * Legacy methods for backward compatibility
	 */
	private function get_current_collection_option_key() {
		return $this->get_active_collection_option_key();
	}

	public function store_current_collection( $collection_name ) {
		return $this->store_active_collection( $collection_name );
	}

	public function get_stored_current_collection() {
		return $this->get_stored_active_collection();
	}

	public function clear_stored_current_collection() {
		return $this->clear_stored_active_collection();
	}

	/**
	 * CRUD operations - these always target the ACTIVE collection (not the sync collection)
	 * Use these for individual document operations during normal application flow
	 */
	public function create( $args ) {
		return $this->get_active_collection()->documents->create( $args );
	}

	public function update( $id, $document_data ) {
		return $this->get_active_collection()->documents[ $id ]->update( $document_data );
	}

	public function upsert( $document_data ) {
		return $this->get_active_collection()->documents->upsert( $document_data );
	}

	/**
	 * Get the ACTIVE collection object for CRUD operations
	 * This always returns the live/active collection, never the sync collection
	 * Uses stored active collection from WordPress options to ensure CRUD operations
	 * target the collection that was last synced to
	 */
	private function get_active_collection() {
		$use_aliases = apply_filters( 'blazecommerce/use_collection_aliases', true );

		if ( $use_aliases ) {
			// For CRUD operations, prioritize stored active collection from WordPress options
			$stored_collection = $this->get_stored_active_collection();
			if ( $stored_collection ) {
				// Log which collection we're using for CRUD operations
				$logger  = wc_get_logger();
				$context = array( 'source' => 'wooless-crud-collection-debug' );
				$logger->debug( 'Using stored active collection for CRUD on ' . $this->collection_name . ': ' . $stored_collection, $context );
				return $this->get_direct_collection( $stored_collection );
			}

			// Fallback to alias-pointed collection if no stored collection exists
			$active_collection_name = $this->alias_manager->get_current_collection( $this->collection_name );
			if ( $active_collection_name ) {
				// Log fallback usage for debugging
				$logger  = wc_get_logger();
				$context = array( 'source' => 'wooless-crud-collection-debug' );
				$logger->debug( 'Using alias-pointed collection for CRUD on ' . $this->collection_name . ': ' . $active_collection_name, $context );
				return $this->get_direct_collection( $active_collection_name );
			}
		}

		// Fallback to legacy collection access
		return $this->collection();
	}

	/**
	 * Get the appropriate collection object for SYNC operations
	 * During sync operations, returns the sync collection directly
	 * Otherwise, returns the alias-based collection
	 */
	private function get_target_collection() {
		$use_aliases = apply_filters( 'blazecommerce/use_collection_aliases', true );

		// If using aliases and we're in a sync operation, use the sync collection directly
		if ( $use_aliases && isset( $this->active_sync_collection ) ) {
			// Debug logging to help identify collection names and API key issues
			$logger  = wc_get_logger();
			$context = array( 'source' => 'wooless-collection-target-debug' );
			$logger->debug( 'Targeting sync collection: ' . $this->active_sync_collection . ' for type: ' . $this->collection_name, $context );

			return $this->get_direct_collection( $this->active_sync_collection );
		}

		// Otherwise, use the normal alias-based collection access
		return $this->collection();
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

			// Store this as the active sync collection (but don't store in WordPress options yet)
			// We'll store it in options only after the sync is complete and alias is updated
			$this->active_sync_collection = $inactive_collection_name;

			// Log the sync collection setup
			$logger  = wc_get_logger();
			$context = array( 'source' => 'wooless-sync-init-debug' );
			$logger->debug( 'Initialized sync collection for ' . $this->collection_name . ': ' . $inactive_collection_name, $context );

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
	 * Automatically uses the child class's $collection_name property
	 *
	 * @param array $options Optional parameters for specific collection needs
	 *                      - 'clear_transient' => string: transient key to clear (used by Page collection)
	 *                      - 'fallback_message' => string: custom message when no alias sync needed
	 * @return array|null Result of sync operation or null if no sync needed
	 */
	public function complete_collection_sync( $options = array() ) {
		$use_aliases = apply_filters( 'blazecommerce/use_collection_aliases', true );

		if ( $use_aliases && isset( $this->active_sync_collection ) ) {
			$logger  = wc_get_logger();
			$context = array( 'source' => 'wooless-' . $this->collection_name . '-collection-complete' );

			try {
				$result = $this->complete_sync( $this->active_sync_collection );
				$logger->debug( 'TS ' . ucfirst( $this->collection_name ) . ' sync completed: ' . print_r( $result, true ), $context );

				// Store the new active collection name in options (this is the collection that was synced to)
				if ( isset( $result['new_collection'] ) ) {
					$this->store_active_collection( $result['new_collection'] );
					$logger->debug( 'Stored new active collection for ' . $this->collection_name . ': ' . $result['new_collection'], $context );
				}

				// Clear the sync collection state since sync is complete
				$this->clear_sync_collection_state();

				// Handle optional transient cleanup (used by Page collection)
				if ( isset( $options['clear_transient'] ) ) {
					delete_transient( $options['clear_transient'] );
				}

				return $result;
			} catch (\Exception $e) {
				$logger->debug( 'TS ' . ucfirst( $this->collection_name ) . ' sync completion failed: ' . $e->getMessage(), $context );
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
