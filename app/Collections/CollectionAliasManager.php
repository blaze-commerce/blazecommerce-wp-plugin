<?php

namespace BlazeWooless\Collections;

use BlazeWooless\TypesenseClient;
use Exception;

class CollectionAliasManager {
	private $typesense;
	private $site_url;

	// Performance optimization: Cache frequently accessed data
	private static $alias_cache = array();
	private static $current_collection_cache = array();
	private static $alias_exists_cache = array();
	private static $cache_ttl = 300; // 5 minutes cache TTL

	public function __construct( $typesense_client = null ) {
		// Accept TypesenseClient instance to avoid circular dependency
		if ( $typesense_client !== null ) {
			$this->typesense = $typesense_client;
		} else {
			$this->typesense = TypesenseClient::get_instance();
		}
		$this->site_url = $this->typesense->get_site_url();
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
	 * Cached for performance optimization
	 */
	public function get_current_collection( $collection_type ) {
		$cache_key = 'current_collection_' . $collection_type;

		// Check cache first
		if ( isset( self::$current_collection_cache[ $cache_key ] ) ) {
			$cached_data = self::$current_collection_cache[ $cache_key ];
			if ( time() - $cached_data['timestamp'] < self::$cache_ttl ) {
				return $cached_data['value'];
			}
		}

		try {
			$alias_name = $this->get_alias_name( $collection_type );
			$alias_info = $this->typesense->client()->aliases[ $alias_name ]->retrieve();
			$result     = $alias_info['collection_name'] ?? null;

			// Cache the result
			self::$current_collection_cache[ $cache_key ] = array(
				'value' => $result,
				'timestamp' => time()
			);

			return $result;
		} catch (Exception $e) {
			// Cache the null result for failed lookups
			self::$current_collection_cache[ $cache_key ] = array(
				'value' => null,
				'timestamp' => time()
			);
			return null;
		}
	}

	/**
	 * Get all collections for a specific type (both -a and -b)
	 * Optimized to check specific collections instead of loading all collections
	 */
	public function get_all_collections_for_type( $collection_type ) {
		$matching_collections = array();

		// Check for specific -a and -b collections instead of loading all collections
		$collection_a = $this->get_collection_name( $collection_type, 'a' );
		$collection_b = $this->get_collection_name( $collection_type, 'b' );

		try {
			// Check if collection -a exists
			$this->typesense->client()->collections[ $collection_a ]->retrieve();
			$matching_collections[] = $collection_a;
		} catch (Exception $e) {
			// Collection -a doesn't exist, which is fine
		}

		try {
			// Check if collection -b exists
			$this->typesense->client()->collections[ $collection_b ]->retrieve();
			$matching_collections[] = $collection_b;
		} catch (Exception $e) {
			// Collection -b doesn't exist, which is fine
		}

		// Sort alphabetically (a comes before b)
		sort( $matching_collections );

		return $matching_collections;
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

			$result = $this->typesense->client()->aliases->upsert( $alias_name, $mapping );

			// Clear cache for this collection type since alias has changed
			$this->clear_cache_for_collection_type( $collection_type );

			return $result;
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
	 * Cached for performance optimization
	 */
	public function alias_exists( $collection_type ) {
		$cache_key = 'alias_exists_' . $collection_type;

		// Check cache first
		if ( isset( self::$alias_exists_cache[ $cache_key ] ) ) {
			$cached_data = self::$alias_exists_cache[ $cache_key ];
			if ( time() - $cached_data['timestamp'] < self::$cache_ttl ) {
				return $cached_data['value'];
			}
		}

		try {
			$alias_name = $this->get_alias_name( $collection_type );
			$this->typesense->client()->aliases[ $alias_name ]->retrieve();

			// Cache the positive result
			self::$alias_exists_cache[ $cache_key ] = array(
				'value' => true,
				'timestamp' => time()
			);

			return true;
		} catch (Exception $e) {
			// Cache the negative result
			self::$alias_exists_cache[ $cache_key ] = array(
				'value' => false,
				'timestamp' => time()
			);

			return false;
		}
	}

	/**
	 * Get collection access object (for backward compatibility)
	 * Returns the alias if it exists, otherwise falls back to old naming
	 * Cached for performance optimization
	 */
	public function get_collection_access( $collection_type ) {
		$cache_key = 'collection_access_' . $collection_type;

		// Check cache first
		if ( isset( self::$alias_cache[ $cache_key ] ) ) {
			$cached_data = self::$alias_cache[ $cache_key ];
			if ( time() - $cached_data['timestamp'] < self::$cache_ttl ) {
				return $cached_data['value'];
			}
		}

		$alias_name        = $this->get_alias_name( $collection_type );
		$collection_access = null;

		if ( $this->alias_exists( $collection_type ) ) {
			$collection_access = $this->typesense->client()->collections[ $alias_name ];
		} else {
			// Fallback to old naming for backward compatibility
			$old_name          = $collection_type . '-' . $this->typesense->store_id;
			$collection_access = $this->typesense->client()->collections[ $old_name ];
		}

		// Cache the result
		self::$alias_cache[ $cache_key ] = array(
			'value' => $collection_access,
			'timestamp' => time()
		);

		return $collection_access;
	}

	/**
	 * Get all collection alias names based on supported collection types
	 * Returns an array of alias names using the naming convention: {type}-{site_url}
	 */
	public function get_all_alias_names() {
		$collection_types = array( 'product', 'taxonomy', 'page', 'menu', 'site_info', 'navigation' );
		$alias_names      = array();

		foreach ( $collection_types as $type ) {
			$alias_names[ $type ] = $this->get_alias_name( $type );
		}

		return $alias_names;
	}

	/**
	 * Clear cache for a specific collection type
	 */
	private function clear_cache_for_collection_type( $collection_type ) {
		$cache_keys = array(
			'current_collection_' . $collection_type,
			'alias_exists_' . $collection_type,
			'collection_access_' . $collection_type,
		);

		foreach ( $cache_keys as $key ) {
			unset( self::$current_collection_cache[ $key ] );
			unset( self::$alias_exists_cache[ $key ] );
			unset( self::$alias_cache[ $key ] );
		}
	}

	/**
	 * Clear all caches (useful for debugging or when needed)
	 */
	public function clear_all_caches() {
		self::$alias_cache              = array();
		self::$current_collection_cache = array();
		self::$alias_exists_cache       = array();
	}

	/**
	 * Get cache statistics for debugging
	 */
	public function get_cache_stats() {
		return array(
			'alias_cache_count' => count( self::$alias_cache ),
			'current_collection_cache_count' => count( self::$current_collection_cache ),
			'alias_exists_cache_count' => count( self::$alias_exists_cache ),
			'cache_ttl' => self::$cache_ttl,
		);
	}
}
