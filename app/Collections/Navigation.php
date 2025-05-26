<?php

namespace BlazeWooless\Collections;

class Navigation extends BaseCollection {
	private static $instance = null;
	public $collection_name = 'navigation';

	const BATCH_SIZE = 5;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function get_fields() {
		$fields = array(
			array( 'name' => 'objectId', 'type' => 'string' ),
			array( 'name' => 'name', 'type' => 'string' ),
			array( 'name' => 'content', 'type' => 'string', 'optional' => true ),
			array( 'name' => 'status', 'type' => 'string', 'facet' => true ),
			array( 'name' => 'updatedAt', 'type' => 'int64' ),
			array( 'name' => 'createdAt', 'type' => 'int64' ),
		);

		return apply_filters( 'blazecommerce/collection/navigation/typesense_fields', $fields );
	}

	public function initialize() {
		$logger  = wc_get_logger();
		$context = array( 'source' => 'wooless-navigation-collection-initialize' );

		$use_aliases = apply_filters( 'blazecommerce/use_collection_aliases', true );

		if ( $use_aliases ) {
			try {
				$schema = array(
					'fields' => $this->get_fields(),
					'default_sorting_field' => 'updatedAt',
					'enable_nested_fields' => true
				);

				$new_collection_name = $this->initialize_with_alias( $schema );
				$logger->debug( 'TS Navigation collection (alias): ' . $new_collection_name, $context );

				// Store the new collection name for later use in complete_sync
				$this->current_sync_collection = $new_collection_name;

			} catch (\Exception $e) {
				$logger->debug( 'TS Navigation collection alias initialize Exception: ' . $e->getMessage(), $context );
				throw $e;
			}
		} else {
			// Legacy behavior
			try {
				$this->drop_collection();
			} catch (\Exception $e) {
				// Don't error out if the collection was not found
			}

			$logger->debug( 'TS Navigation collection: ' . $this->collection_name(), $context );

			try {
				$this->create_collection( [ 
					'name' => $this->collection_name(),
					'fields' => $this->get_fields(),
					'default_sorting_field' => 'updatedAt',
					'enable_nested_fields' => true
				] );
			} catch (\Exception $e) {
				$logger->debug( 'TS Navigation collection initialize Exception: ' . $e->getMessage(), $context );
				echo "Error: " . $e->getMessage() . "\n";
			}
		}
	}

	/**
	 * Complete the navigation sync by updating alias
	 * This should be called after all navigation items have been synced
	 */
	public function complete_navigation_sync() {
		$use_aliases = apply_filters( 'blazecommerce/use_collection_aliases', true );

		if ( $use_aliases && isset( $this->current_sync_collection ) ) {
			try {
				$result = $this->complete_sync( $this->current_sync_collection );

				$logger  = wc_get_logger();
				$context = array( 'source' => 'wooless-navigation-collection-complete' );
				$logger->debug( 'TS Navigation sync completed: ' . print_r( $result, true ), $context );

				// Clear the current sync collection
				unset( $this->current_sync_collection );

				return $result;
			} catch (\Exception $e) {
				$logger  = wc_get_logger();
				$context = array( 'source' => 'wooless-navigation-collection-complete' );
				$logger->debug( 'TS Navigation sync completion failed: ' . $e->getMessage(), $context );
				throw $e;
			}
		}

		return null;
	}

	public function get_data( $navigation ) {
		$navigation_id = $navigation->ID;

		$data = array(
			'objectId' => (string) $navigation_id,
			'name' => $navigation->post_title,
			'content' => $navigation->post_content,
			'status' => $navigation->post_status,
			'updatedAt' => (int) strtotime( get_the_modified_date( 'c', $navigation_id ) ),
			'createdAt' => (int) strtotime( get_the_date( 'c', $navigation_id ) ),
		);

		return apply_filters( 'blazecommerce/collection/navigation/typesense_data', $data, $navigation );
	}

	public function get_navigation_ids( $page, $batch_size = 20 ) {
		global $wpdb;
		// Calculate the offset
		$offset = ( $page - 1 ) * $batch_size;

		// Query to select post IDs from the posts table with pagination
		$query = $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'wp_navigation' AND post_status = 'publish' LIMIT %d OFFSET %d",
			$batch_size,
			$offset
		);

		// Get the results as an array of IDs
		return $wpdb->get_col( $query );
	}

	public function get_total_pages( $batch_size = 20 ) {
		global $wpdb;
		$query       = "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'wp_navigation' AND post_status = 'publish'";
		$total_posts = $wpdb->get_var( $query );
		$total_pages = ceil( $total_posts / $batch_size );
		return $total_pages;
	}

	public function prepare_batch_data( $navigation_ids ) {
		$navigation_datas = array();
		if ( empty( $navigation_ids ) ) {
			return $navigation_datas;
		}

		foreach ( $navigation_ids as $navigation_id ) {
			$navigation = get_post( $navigation_id );
			if ( $navigation ) {
				$document = $this->get_data( $navigation );
				if ( ! empty( $document ) ) {
					$navigation_datas[] = $document;
				}
				unset( $document );
			}
		}
		// Restore original post data.
		wp_reset_postdata();
		wp_reset_query();

		return $navigation_datas;
	}

	public function import_prepared_batch( $navigations_batch ) {
		$import_response = $this->import( $navigations_batch );

		$successful_imports = array_filter( $import_response, function ($batch_result) {
			return isset( $batch_result['success'] ) && $batch_result['success'] == true;
		} );

		return $successful_imports;
	}

	public function index_to_typesense() {
		$batch_size      = $_REQUEST['batch_size'] ?? 20;
		$page            = $_REQUEST['page'] ?? 1;
		$imported_count  = $_REQUEST['imported_count'] ?? 0;
		$total_imports   = $_REQUEST['total_imports'] ?? 0;
		$import_response = array();

		$navigation_datas = array();
		if ( 1 == $page ) {
			$this->initialize();
		}

		// the settings to not sync all navigation. Set to false so that no navigation syncs happen
		$should_sync = apply_filters( 'blazecommerce/settings/sync/navigation', true );
		if ( ! $should_sync ) {
			// This prevents syncing all navigation
			wp_send_json( array(
				'imported_count' => 0,
				'total_imports' => 0,
				'next_page' => null,
				'page' => 1,
				'import_response' => [],
				'import_data_sent' => [],
			) );
		}

		try {
			$navigation_ids = $this->get_navigation_ids( $page, $batch_size );
			if ( ! empty( $navigation_ids ) ) {

				$navigation_datas = $this->prepare_batch_data( $navigation_ids );
				if ( ! empty( $navigation_datas ) ) {
					$successful_imports = $this->import_prepared_batch( $navigation_datas );
					$imported_count += count( $successful_imports );
				}

				$total_imports += count( $navigation_datas );
				$total_pages   = $this->get_total_pages( $batch_size );
				$next_page     = $page + 1;
				$has_next_data = $page < $total_pages;

				wp_send_json( array(
					'imported_count' => $imported_count,
					'total_imports' => $total_imports,
					'next_page' => $has_next_data ? $next_page : null,
					'page' => $page,
					'import_response' => $import_response,
					'import_data_sent' => $navigation_datas,
				) );
			}

			wp_send_json( array(
				'imported_count' => $imported_count,
				'total_imports' => $total_imports,
				'next_page' => null,
				'page' => $page,
				'import_response' => $import_response,
				'import_data_sent' => $navigation_datas,
			) );
		} catch (\Exception $e) {
			wp_send_json( array(
				'error' => $e->getMessage(),
				'imported_count' => $imported_count,
				'total_imports' => $total_imports,
				'next_page' => null,
				'page' => $page,
				'import_response' => $import_response,
				'import_data_sent' => $navigation_datas,
			) );
		}
	}
}
