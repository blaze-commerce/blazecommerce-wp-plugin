<?php

namespace BlazeWooless\Collections;

use BlazeWooless\TypesenseClient;
use Exception;

class CollectionAliasManager {
	private $typesense;
	private $site_url;

	public function __construct() {
		$this->typesense = TypesenseClient::get_instance();
		$this->site_url = $this->typesense->get_site_url();
	}

	/**
	 * Generate alias name based on collection type and site URL
	 * Format: {type}_{site_url}
	 */
	public function get_alias_name( $collection_type ) {
		return $collection_type . '_' . $this->site_url;
	}

	/**
	 * Generate collection name with timestamp
	 * Format: {type}_{site_url}_{timestamp}
	 */
	public function get_collection_name( $collection_type, $timestamp = null ) {
		if ( $timestamp === null ) {
			$timestamp = time();
		}
		return $collection_type . '_' . $this->site_url . '_' . $timestamp;
	}

	/**
	 * Get current live collection name by checking where alias points
	 */
	public function get_current_collection( $collection_type ) {
		try {
			$alias_name = $this->get_alias_name( $collection_type );
			$alias_info = $this->typesense->client()->aliases[ $alias_name ]->retrieve();
			return $alias_info['collection_name'] ?? null;
		} catch ( Exception $e ) {
			// Alias doesn't exist yet
			return null;
		}
	}

	/**
	 * Get all collections for a specific type
	 */
	public function get_all_collections_for_type( $collection_type ) {
		try {
			$all_collections = $this->typesense->client()->collections->retrieve();
			$prefix = $collection_type . '_' . $this->site_url . '_';
			
			$matching_collections = array();
			foreach ( $all_collections['collections'] as $collection ) {
				if ( strpos( $collection['name'], $prefix ) === 0 ) {
					$matching_collections[] = $collection['name'];
				}
			}
			
			// Sort by timestamp (newest first)
			usort( $matching_collections, function( $a, $b ) {
				$timestamp_a = $this->extract_timestamp_from_collection_name( $a );
				$timestamp_b = $this->extract_timestamp_from_collection_name( $b );
				return $timestamp_b - $timestamp_a;
			});
			
			return $matching_collections;
		} catch ( Exception $e ) {
			return array();
		}
	}

	/**
	 * Extract timestamp from collection name
	 */
	private function extract_timestamp_from_collection_name( $collection_name ) {
		$parts = explode( '_', $collection_name );
		return intval( end( $parts ) );
	}

	/**
	 * Determine collections that are newer than the current alias target
	 */
	public function get_newer_collections( $collection_type ) {
		$current_collection = $this->get_current_collection( $collection_type );
		if ( ! $current_collection ) {
			return array();
		}

		$current_timestamp = $this->extract_timestamp_from_collection_name( $current_collection );
		$all_collections = $this->get_all_collections_for_type( $collection_type );
		
		$newer_collections = array();
		foreach ( $all_collections as $collection ) {
			$timestamp = $this->extract_timestamp_from_collection_name( $collection );
			if ( $timestamp > $current_timestamp ) {
				$newer_collections[] = $collection;
			}
		}
		
		return $newer_collections;
	}

	/**
	 * Determine collections that are older than the current alias target
	 */
	public function get_older_collections( $collection_type, $keep_count = 1 ) {
		$current_collection = $this->get_current_collection( $collection_type );
		if ( ! $current_collection ) {
			return array();
		}

		$current_timestamp = $this->extract_timestamp_from_collection_name( $current_collection );
		$all_collections = $this->get_all_collections_for_type( $collection_type );
		
		$older_collections = array();
		foreach ( $all_collections as $collection ) {
			$timestamp = $this->extract_timestamp_from_collection_name( $collection );
			if ( $timestamp < $current_timestamp ) {
				$older_collections[] = $collection;
			}
		}
		
		// Sort by timestamp (newest first) and keep only the ones beyond keep_count
		usort( $older_collections, function( $a, $b ) {
			$timestamp_a = $this->extract_timestamp_from_collection_name( $a );
			$timestamp_b = $this->extract_timestamp_from_collection_name( $b );
			return $timestamp_b - $timestamp_a;
		});
		
		// Return collections to delete (keep the most recent $keep_count)
		return array_slice( $older_collections, $keep_count );
	}

	/**
	 * Create or update alias to point to a collection
	 */
	public function update_alias( $collection_type, $target_collection ) {
		try {
			$alias_name = $this->get_alias_name( $collection_type );
			$mapping = array( 'collection_name' => $target_collection );
			
			return $this->typesense->client()->aliases->upsert( $alias_name, $mapping );
		} catch ( Exception $e ) {
			throw new Exception( "Failed to update alias: " . $e->getMessage() );
		}
	}

	/**
	 * Delete a collection
	 */
	public function delete_collection( $collection_name ) {
		try {
			return $this->typesense->client()->collections[ $collection_name ]->delete();
		} catch ( Exception $e ) {
			// Collection might not exist, which is fine
			return false;
		}
	}

	/**
	 * Clean up old collections for a type
	 */
	public function cleanup_old_collections( $collection_type, $keep_count = 1 ) {
		$collections_to_delete = $this->get_older_collections( $collection_type, $keep_count );
		$deleted_collections = array();
		
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
		$deleted_collections = array();
		
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
		} catch ( Exception $e ) {
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
