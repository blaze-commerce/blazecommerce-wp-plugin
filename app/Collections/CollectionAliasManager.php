<?php

namespace BlazeWooless\Collections;

use BlazeWooless\TypesenseClient;
use Exception;

class CollectionAliasManager {
	private $typesense;
	private $site_url;

	public function __construct() {
		$this->typesense = TypesenseClient::get_instance();
		$this->site_url  = $this->typesense->get_site_url();
	}

	/**
	 * Generate alias name based on collection type and site URL
	 * Format: {type}-{site_url}
	 */
	public function get_alias_name( $collection_type ) {
		return $collection_type . '-' . $this->site_url;
	}

	/**
	 * Generate collection name with suffix (a or b)
	 * Format: {type}-{site_url}-a or {type}-{site_url}-b
	 */
	public function get_collection_name( $collection_type, $suffix = null ) {
		if ( $suffix === null ) {
			$suffix = $this->get_next_collection_suffix( $collection_type );
		}
		return $collection_type . '-' . $this->site_url . '-' . $suffix;
	}

	/**
	 * Determine which collection suffix (a or b) to use for the next sync
	 * If current alias points to -a, return 'b'. If points to -b, return 'a'.
	 * If no alias exists, return 'a' as default.
	 */
	public function get_next_collection_suffix( $collection_type ) {
		$current_collection = $this->get_current_collection( $collection_type );

		if ( ! $current_collection ) {
			// No alias exists yet, start with 'a'
			return 'a';
		}

		// Extract suffix from current collection name
		$current_suffix = $this->extract_suffix_from_collection_name( $current_collection );

		// Return the opposite suffix
		return $current_suffix === 'a' ? 'b' : 'a';
	}

	/**
	 * Get current live collection name by checking where alias points
	 */
	public function get_current_collection( $collection_type ) {
		try {
			$alias_name = $this->get_alias_name( $collection_type );
			$alias_info = $this->typesense->client()->aliases[ $alias_name ]->retrieve();
			return $alias_info['collection_name'] ?? null;
		} catch (Exception $e) {
			// Alias doesn't exist yet
			return null;
		}
	}

	/**
	 * Get all collections for a specific type (both -a and -b)
	 */
	public function get_all_collections_for_type( $collection_type ) {
		try {
			$all_collections = $this->typesense->client()->collections->retrieve();
			$prefix          = $collection_type . '-' . $this->site_url . '-';

			$matching_collections = array();
			foreach ( $all_collections['collections'] as $collection ) {
				if ( strpos( $collection['name'], $prefix ) === 0 ) {
					// Only include collections that end with '-a' or '-b'
					$suffix = $this->extract_suffix_from_collection_name( $collection['name'] );
					if ( $suffix === 'a' || $suffix === 'b' ) {
						$matching_collections[] = $collection['name'];
					}
				}
			}

			// Sort alphabetically (a comes before b)
			sort( $matching_collections );

			return $matching_collections;
		} catch (Exception $e) {
			return array();
		}
	}

	/**
	 * Extract suffix (a or b) from collection name
	 */
	private function extract_suffix_from_collection_name( $collection_name ) {
		$parts  = explode( '-', $collection_name );
		$suffix = end( $parts );

		// Validate that it's either 'a' or 'b'
		if ( $suffix === 'a' || $suffix === 'b' ) {
			return $suffix;
		}

		// Fallback for legacy collections or invalid names
		return null;
	}

	/**
	 * Get the inactive collection (the one not currently pointed to by alias)
	 * In blue-green deployment, this is the collection we can safely sync to
	 */
	public function get_inactive_collection( $collection_type ) {
		$current_collection = $this->get_current_collection( $collection_type );

		if ( ! $current_collection ) {
			// No alias exists, return the 'a' collection as default
			return $this->get_collection_name( $collection_type, 'a' );
		}

		$current_suffix  = $this->extract_suffix_from_collection_name( $current_collection );
		$inactive_suffix = $current_suffix === 'a' ? 'b' : 'a';

		return $this->get_collection_name( $collection_type, $inactive_suffix );
	}

	/**
	 * Get collections that should be cleaned up
	 * In blue-green deployment, we only keep the current active collection
	 * and clean up any orphaned collections from interrupted syncs
	 */
	public function get_collections_to_cleanup( $collection_type ) {
		$current_collection = $this->get_current_collection( $collection_type );
		$all_collections    = $this->get_all_collections_for_type( $collection_type );

		if ( ! $current_collection ) {
			// No alias exists, clean up all collections
			return $all_collections;
		}

		$collections_to_cleanup = array();
		foreach ( $all_collections as $collection ) {
			if ( $collection !== $current_collection ) {
				$collections_to_cleanup[] = $collection;
			}
		}

		return $collections_to_cleanup;
	}

	/**
	 * Legacy method for backward compatibility - now returns empty array
	 * In blue-green deployment, "newer" collections don't apply
	 */
	public function get_newer_collections( $collection_type ) {
		// Suppress unused parameter warning - kept for backward compatibility
		unset( $collection_type );

		// In blue-green deployment, "newer" doesn't apply
		// Return empty array to maintain compatibility
		return array();
	}

	/**
	 * Legacy method for backward compatibility - now returns collections to cleanup
	 */
	public function get_older_collections( $collection_type, $keep_count = 1 ) {
		if ( $keep_count >= 1 ) {
			// Keep current collection, return others for cleanup
			return $this->get_collections_to_cleanup( $collection_type );
		}

		// If keep_count is 0, return all collections
		return $this->get_all_collections_for_type( $collection_type );
	}

	/**
	 * Create or update alias to point to a collection
	 */
	public function update_alias( $collection_type, $target_collection ) {
		try {
			$alias_name = $this->get_alias_name( $collection_type );
			$mapping    = array( 'collection_name' => $target_collection );

			return $this->typesense->client()->aliases->upsert( $alias_name, $mapping );
		} catch (Exception $e) {
			throw new Exception( "Failed to update alias: " . $e->getMessage() );
		}
	}

	/**
	 * Delete a collection
	 */
	public function delete_collection( $collection_name ) {
		try {
			return $this->typesense->client()->collections[ $collection_name ]->delete();
		} catch (Exception $e) {
			// Collection might not exist, which is fine
			return false;
		}
	}

	/**
	 * Clean up old collections for a type
	 */
	public function cleanup_old_collections( $collection_type, $keep_count = 1 ) {
		$collections_to_delete = $this->get_older_collections( $collection_type, $keep_count );
		$deleted_collections   = array();

		foreach ( $collections_to_delete as $collection ) {
			if ( $this->delete_collection( $collection ) ) {
				$deleted_collections[] = $collection;
			}
		}

		return $deleted_collections;
	}

	/**
	 * Clean up newer collections (for interrupted syncs)
	 */
	public function cleanup_newer_collections( $collection_type ) {
		$collections_to_delete = $this->get_newer_collections( $collection_type );
		$deleted_collections   = array();

		foreach ( $collections_to_delete as $collection ) {
			if ( $this->delete_collection( $collection ) ) {
				$deleted_collections[] = $collection;
			}
		}

		return $deleted_collections;
	}

	/**
	 * Check if alias exists
	 */
	public function alias_exists( $collection_type ) {
		try {
			$alias_name = $this->get_alias_name( $collection_type );
			$this->typesense->client()->aliases[ $alias_name ]->retrieve();
			return true;
		} catch (Exception $e) {
			return false;
		}
	}

	/**
	 * Get collection access object (for backward compatibility)
	 * Returns the alias if it exists, otherwise falls back to old naming
	 */
	public function get_collection_access( $collection_type ) {
		$alias_name = $this->get_alias_name( $collection_type );

		if ( $this->alias_exists( $collection_type ) ) {
			return $this->typesense->client()->collections[ $alias_name ];
		}

		// Fallback to old naming for backward compatibility
		$old_name = $collection_type . '-' . $this->typesense->store_id;
		return $this->typesense->client()->collections[ $old_name ];
	}
}
