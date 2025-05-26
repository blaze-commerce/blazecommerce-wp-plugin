<?php

namespace BlazeWooless\Features;

use BlazeWooless\Collections\Menu;
use BlazeWooless\Collections\Navigation;
use BlazeWooless\Collections\Page;
use BlazeWooless\Collections\Product;
use BlazeWooless\Collections\SiteInfo;
use BlazeWooless\Collections\Taxonomy;
use WP_CLI;
use WP_CLI_Command;


class Cli extends WP_CLI_Command {

	/**
	 * Sync all products.
	 *
	 * ## OPTIONS
	 *
	 * [--all]
	 * : Sync all products.
	 *
	 * [--variants]
	 * : Sync all products variants.
	 *
	 * [--nonvariants]
	 * : Sync only non-variant products (simple, bundle, etc.).
	 *
	 * ## EXAMPLES
	 *
	 *     wp bc-sync product --all
	 *     wp bc-sync product --variants
	 *     wp bc-sync product --nonvariants
	 *
	 * @when after_wp_load
	 */
	public function product( $args, $assoc_args ) {
		// the settings to not sync all products. Set to false so that no product syncs happen
		$should_sync = apply_filters( 'blazecommerce/settings/sync/products', true );

		if ( isset( $assoc_args['all'] ) ) {
			WP_CLI::line( "Syncing all products in batches..." );

			// Display the collection name we'll be syncing to
			$product_collection = Product::get_instance();
			$use_aliases        = apply_filters( 'blazecommerce/use_collection_aliases', true );
			if ( $use_aliases ) {
				$target_collection = $product_collection->get_inactive_collection_name();
				WP_CLI::line( "Target collection: " . $target_collection );
			} else {
				$target_collection = $product_collection->collection_name();
				WP_CLI::line( "Target collection: " . $target_collection );
			}

			// Start tracking time
			$start_time              = microtime( true );
			$batch_size              = Product::BATCH_SIZE;
			$page                    = 1;
			$imported_products_count = 0;
			$total_imports           = 0;

			// Add safety counter to prevent infinite loops
			$max_iterations  = 1000; // Reasonable limit for large catalogs
			$iteration_count = 0;

			do {
				$iteration_count++;

				// Safety check to prevent infinite loops
				if ( $iteration_count > $max_iterations ) {
					WP_CLI::warning( "Reached maximum iteration limit ($max_iterations). Stopping sync to prevent memory issues." );
					break;
				}

				if ( $page == 1 ) {
					// recreate the collection to typesense and do some initialization
					$product_collection->initialize();
				}

				if ( ! $should_sync ) {
					// This prevents syncing all products
					break;
				}

				$product_ids = $product_collection->get_product_ids( $page, $batch_size );
				if ( empty( $product_ids ) ) {
					break; // No more data left to sync
				}

				// Memory optimization: Force garbage collection every 10 iterations
				if ( $iteration_count % 10 === 0 ) {
					if ( function_exists( 'gc_collect_cycles' ) ) {
						gc_collect_cycles();
					}
					WP_CLI::line( "Memory usage: " . size_format( memory_get_usage( true ) ) );
				}

				$products_batch     = $product_collection->prepare_batch_data( $product_ids );
				$successful_imports = $product_collection->import_prepared_batch( $products_batch );

				$imported_products_count += count( $successful_imports ); // Increment the count of imported products
				$total_imports += count( $products_batch ); // Increment the count of imported products


				WP_CLI::success( "Completed batch {$page}..." );
				$page++; // Move to the next batch

			} while ( true );

			// Complete the sync by updating alias if using new system
			try {
				$sync_result = $product_collection->complete_product_sync();
				if ( $sync_result ) {
					WP_CLI::success( "Alias updated successfully. New collection: " . $sync_result['new_collection'] );
					if ( ! empty( $sync_result['deleted_collections'] ) ) {
						WP_CLI::success( "Cleaned up old collections: " . implode( ', ', $sync_result['deleted_collections'] ) );
					}
				}
			} catch (\Exception $e) {
				WP_CLI::warning( "Failed to complete sync: " . $e->getMessage() );
			}

			WP_CLI::success( "All products have been synced." );
			WP_CLI::success( "Total batch imported: " . $page );
			WP_CLI::success( "Total import: " . $total_imports );
			WP_CLI::success( "Successful import: " . $imported_products_count );

			// End tracking time
			$end_time       = microtime( true );
			$execution_time = $end_time - $start_time;
			// Convert execution time to hours, minutes, seconds
			$formatted_time = gmdate( "H:i:s", (int) $execution_time );
			WP_CLI::success( "Total time spent: " . $formatted_time . " (hh:mm:ss)" );

			WP_CLI::halt( 0 );
		}

		if ( isset( $assoc_args['variants'] ) && $should_sync ) {
			$start_time = microtime( true );
			$page       = 1;

			WP_CLI::line( "Syncing all product variants in batches..." );

			$args = array(
				'post_type' => 'product',
				'post_status' => 'publish',
				'posts_per_page' => -1,
				'tax_query' => array(
					array(
						'taxonomy' => 'product_type',
						'field' => 'slug',
						'terms' => 'variable',
					),
				),
			);

			$query         = new \WP_Query( $args );
			$variation_ids = [];

			if ( $query->have_posts() ) {
				while ( $query->have_posts() ) {
					$query->the_post();
					$product = wc_get_product( get_the_ID() );

					if ( $product && $product->is_type( 'variable' ) ) {
						$children      = $product->get_children();
						$variation_ids = array_merge( $variation_ids, $children );
					}
				}

				// $event_time = WC()->call_function( 'time' ) + 1;
				$chunks = array_chunk( $variation_ids, 50 );

				foreach ( $chunks as $chunk ) {
					\BlazeWooless\Woocommerce::get_instance()->variation_update( $chunk );

					WP_CLI::success( "Completed batch {$page}..." );
					$page++; // Move to the next batch
				}

				WP_CLI::success( "All product variants have been synced." );
				WP_CLI::success( "Total variation prouct: " . count( $query->posts ) );
				WP_CLI::success( "Total child variation product: " . count( $variation_ids ) );

				// End tracking time
				$end_time       = microtime( true );
				$execution_time = $end_time - $start_time;
				// Convert execution time to hours, minutes, seconds
				$formatted_time = gmdate( "H:i:s", (int) $execution_time );
				WP_CLI::success( "Total time spent: " . $formatted_time . " (hh:mm:ss)" );

				WP_CLI::halt( 0 );
			}
		}

		if ( isset( $assoc_args['nonvariants'] ) && $should_sync ) {
			$start_time = microtime( true );
			$page       = 1;

			WP_CLI::line( "Syncing all non-variant products in batches..." );

			$args = array(
				'post_type' => 'product',
				'post_status' => 'publish',
				'posts_per_page' => -1,
				'tax_query' => array(
					array(
						'taxonomy' => 'product_type',
						'field' => 'slug',
						'terms' => array( 'variable' ),
						'operator' => 'NOT IN'
					),
				),
			);

			$query                  = new \WP_Query( $args );
			$nonvariant_product_ids = array();

			if ( $query->have_posts() ) {
				while ( $query->have_posts() ) {
					$query->the_post();
					$nonvariant_product_ids[] = get_the_ID();
				}

				$product_collection = Product::get_instance();

				// Display the collection name we'll be syncing to
				$use_aliases = apply_filters( 'blazecommerce/use_collection_aliases', true );
				if ( $use_aliases ) {
					$target_collection = $product_collection->get_inactive_collection_name();
					WP_CLI::line( "Target collection: " . $target_collection );
				} else {
					$target_collection = $product_collection->collection_name();
					WP_CLI::line( "Target collection: " . $target_collection );
				}

				// Initialize the collection if this is the first batch
				$product_collection->initialize();

				$chunks                  = array_chunk( $nonvariant_product_ids, 50 );
				$imported_products_count = 0;
				$total_imports           = 0;

				foreach ( $chunks as $chunk ) {
					$products_batch     = $product_collection->prepare_batch_data( $chunk );
					$successful_imports = $product_collection->import_prepared_batch( $products_batch );

					$imported_products_count += count( $successful_imports );
					$total_imports += count( $products_batch );

					WP_CLI::success( "Completed batch {$page}..." );
					$page++; // Move to the next batch
				}

				// Complete the sync by updating alias if using new system
				try {
					$sync_result = $product_collection->complete_product_sync();
					if ( $sync_result ) {
						WP_CLI::success( "Alias updated successfully. New collection: " . $sync_result['new_collection'] );
						if ( ! empty( $sync_result['deleted_collections'] ) ) {
							WP_CLI::success( "Cleaned up old collections: " . implode( ', ', $sync_result['deleted_collections'] ) );
						}
					}
				} catch (\Exception $e) {
					WP_CLI::warning( "Failed to complete sync: " . $e->getMessage() );
				}

				WP_CLI::success( "All non-variant products have been synced." );
				WP_CLI::success( "Total non-variant products: " . count( $nonvariant_product_ids ) );
				WP_CLI::success( "Total import: " . $total_imports );
				WP_CLI::success( "Successful import: " . $imported_products_count );

				// End tracking time
				$end_time       = microtime( true );
				$execution_time = $end_time - $start_time;
				// Convert execution time to hours, minutes, seconds
				$formatted_time = gmdate( "H:i:s", (int) $execution_time );
				WP_CLI::success( "Total time spent: " . $formatted_time . " (hh:mm:ss)" );

				WP_CLI::halt( 0 );
			}
		}

		WP_CLI::error( "Nothing was sync" );
	}

	/**
	 * Sync all pages and post.
	 *
	 * ## OPTIONS
	 *
	 * [--all]
	 * : Sync all pages and post.
	 *
	 * ## EXAMPLES
	 *
	 *     wp bc-sync page_and_post --all
	 *
	 * @when after_wp_load
	 */
	public function page_and_post( $args, $assoc_args ) {
		if ( isset( $assoc_args['all'] ) ) {
			WP_CLI::line( "Syncing all pages and posts in batches..." );

			// Display the collection name we'll be syncing to
			$collection  = Page::get_instance();
			$use_aliases = apply_filters( 'blazecommerce/use_collection_aliases', true );
			if ( $use_aliases ) {
				$target_collection = $collection->get_inactive_collection_name();
				WP_CLI::line( "Target collection: " . $target_collection );
			} else {
				$target_collection = $collection->collection_name();
				WP_CLI::line( "Target collection: " . $target_collection );
			}

			// Start tracking time
			$start_time     = microtime( true );
			$batch_size     = Page::BATCH_SIZE;
			$page           = 1;
			$imported_count = 0;
			$total_imports  = 0;

			do {
				if ( $page == 1 ) {
					// recreate the collection to typesense and do some initialization
					$collection->initialize();
				}

				// the settings to not sync all pageAndPost. Set to false so that no pageAndPost syncs happen
				$should_sync = apply_filters( 'blazecommerce/settings/sync/pageAndPost', true );
				if ( ! $should_sync ) {
					// This prevents syncing all pageAndPost
					break;
				}

				$ids = $collection->get_post_ids( $page, $batch_size );
				if ( empty( $ids ) ) {
					break; // No more data left to sync
				}

				$object_batch = $collection->prepare_batch_data( $ids );
				if ( ! empty( $object_batch ) ) {
					$successful_imports = $collection->import_prepared_batch( $object_batch );
					$imported_count += count( $successful_imports ); // Increment the count of imported products
					$total_imports += count( $object_batch ); // Increment the count of imported products
				}



				WP_CLI::success( "Completed batch {$page}..." );
				$page++; // Move to the next batch

			} while ( true );

			// Complete the sync by updating alias if using new system
			try {
				$sync_result = $collection->complete_page_sync();
				if ( $sync_result ) {
					WP_CLI::success( "Alias updated successfully. New collection: " . $sync_result['new_collection'] );
					if ( ! empty( $sync_result['deleted_collections'] ) ) {
						WP_CLI::success( "Cleaned up old collections: " . implode( ', ', $sync_result['deleted_collections'] ) );
					}
				}
			} catch (\Exception $e) {
				WP_CLI::warning( "Failed to complete sync: " . $e->getMessage() );
			}

			WP_CLI::success( "Completed! All page and post have been synced." );
			WP_CLI::success( "Total batch imported: " . $page );
			WP_CLI::success( "Total import: " . $total_imports );
			WP_CLI::success( "Successful import: " . $imported_count );

			// End tracking time
			$end_time       = microtime( true );
			$execution_time = $end_time - $start_time;
			// Convert execution time to hours, minutes, seconds
			$formatted_time = gmdate( "H:i:s", (int) $execution_time );
			WP_CLI::success( "Total time spent: " . $formatted_time . " (hh:mm:ss)" );
			WP_CLI::halt( 0 );
		}

		WP_CLI::error( "Nothing was sync" );
	}


	/**
	 * Sync all menus.
	 *
	 * ## OPTIONS
	 *
	 * [--all]
	 * : Sync all menus.
	 *
	 * ## EXAMPLES
	 *
	 *     wp bc-sync menu --all
	 *
	 * @when after_wp_load
	 */
	public function menu( $args, $assoc_args ) {
		if ( isset( $assoc_args['all'] ) ) {
			WP_CLI::line( "Syncing all menus in batches..." );

			// Display the collection name we'll be syncing to
			$collection        = Menu::get_instance();
			$target_collection = $collection->collection_name();
			WP_CLI::line( "Target collection: " . $target_collection );

			// Start tracking time
			$start_time     = microtime( true );
			$imported_count = 0;
			$total_imports  = 0;

			// recreate the collection to typesense and do some initialization
			$collection->initialize();
			$object_batch       = $collection->prepare_batch_data();
			$successful_imports = $collection->import_prepared_batch( $object_batch );

			$imported_count += count( $successful_imports ); // Increment the count of imported products
			$total_imports += count( $object_batch ); // Increment the count of imported products

			WP_CLI::success( "Completed! All menus have been synced." );
			WP_CLI::success( "Total import: " . $total_imports );
			WP_CLI::success( "Successful import: " . $imported_count );

			// End tracking time
			$end_time       = microtime( true );
			$execution_time = $end_time - $start_time;
			// Convert execution time to hours, minutes, seconds
			$formatted_time = gmdate( "H:i:s", (int) $execution_time );
			WP_CLI::success( "Total time spent: " . $formatted_time . " (hh:mm:ss)" );
			WP_CLI::halt( 0 );
		}

		WP_CLI::error( "Nothing was sync" );
	}

	/**
	 * Sync all site info needed by blazecommerce.
	 *
	 * ## OPTIONS
	 *
	 * [--all]
	 * : Sync all site_info.
	 *
	 * ## EXAMPLES
	 *
	 *     wp bc-sync site_info --all
	 *
	 * @when after_wp_load
	 */
	public function site_info( $args, $assoc_args ) {
		if ( isset( $assoc_args['all'] ) ) {
			WP_CLI::line( "Syncing all site info in batches..." );

			// Display the collection name we'll be syncing to
			$collection  = SiteInfo::get_instance();
			$use_aliases = apply_filters( 'blazecommerce/use_collection_aliases', true );
			if ( $use_aliases ) {
				$target_collection = $collection->get_inactive_collection_name();
				WP_CLI::line( "Target collection: " . $target_collection );
			} else {
				$target_collection = $collection->collection_name();
				WP_CLI::line( "Target collection: " . $target_collection );
			}

			// Start tracking time
			$start_time = microtime( true );

			$imported_count = 0;
			$total_imports  = 0;

			// recreate the collection to typesense and do some initialization
			$collection->initialize();

			$object_batch       = $collection->prepare_batch_data();
			$successful_imports = $collection->import_prepared_batch( $object_batch );
			$imported_count += count( $successful_imports ); // Increment the count of imported products
			$total_imports += count( $object_batch ); // Increment the count of imported products

			// Complete the sync by updating alias if using new system
			try {
				$sync_result = $collection->complete_site_info_sync();
				if ( $sync_result ) {
					WP_CLI::success( "Alias updated successfully. New collection: " . $sync_result['new_collection'] );
					if ( ! empty( $sync_result['deleted_collections'] ) ) {
						WP_CLI::success( "Cleaned up old collections: " . implode( ', ', $sync_result['deleted_collections'] ) );
					}
				}
			} catch (\Exception $e) {
				WP_CLI::warning( "Failed to complete sync: " . $e->getMessage() );
			}

			$collection->after_site_info_sync();

			WP_CLI::success( "Completed! All site info have been synced." );
			WP_CLI::success( "Total import: " . $total_imports );
			WP_CLI::success( "Successful import: " . $imported_count );

			// End tracking time
			$end_time       = microtime( true );
			$execution_time = $end_time - $start_time;
			// Convert execution time to hours, minutes, seconds
			$formatted_time = gmdate( "H:i:s", (int) $execution_time );
			WP_CLI::success( "Total time spent: " . $formatted_time . " (hh:mm:ss)" );
			WP_CLI::halt( 0 );
		}

		WP_CLI::error( "Nothing was sync" );
	}


	/**
	 * Sync all taxonomies.
	 *
	 * ## OPTIONS
	 *
	 * [--all]
	 * : Sync all taxonomies.
	 *
	 * ## EXAMPLES
	 *
	 *     wp bc-sync taxonomy --all
	 *
	 * @when after_wp_load
	 */
	public function taxonomy( $args, $assoc_args ) {
		if ( isset( $assoc_args['all'] ) ) {
			WP_CLI::line( "Syncing all taxonomies in batches..." );

			// Display the collection name we'll be syncing to
			$collection  = Taxonomy::get_instance();
			$use_aliases = apply_filters( 'blazecommerce/use_collection_aliases', true );
			if ( $use_aliases ) {
				$target_collection = $collection->get_inactive_collection_name();
				WP_CLI::line( "Target collection: " . $target_collection );
			} else {
				$target_collection = $collection->collection_name();
				WP_CLI::line( "Target collection: " . $target_collection );
			}

			// Start tracking time
			$start_time     = microtime( true );
			$batch_size     = Taxonomy::BATCH_SIZE;
			$page           = 1;
			$imported_count = 0;
			$total_imports  = 0;

			// Add safety counter to prevent infinite loops
			$max_iterations  = 500; // Reasonable limit for taxonomy terms
			$iteration_count = 0;

			do {
				$iteration_count++;

				// Safety check to prevent infinite loops
				if ( $iteration_count > $max_iterations ) {
					WP_CLI::warning( "Reached maximum iteration limit ($max_iterations). Stopping sync to prevent memory issues." );
					break;
				}

				if ( $page == 1 ) {
					// recreate the collection to typesense and do some initialization
					$collection->initialize();
				}

				// the settings to not sync all taxonomy terms. Set to false so that no taxonomy syncs happen
				$should_sync = apply_filters( 'blazecommerce/settings/sync/taxonomies', true );
				if ( ! $should_sync ) {
					break;
				}

				$query_args = $collection->get_query_args( $page, $batch_size );
				$term_query = new \WP_Term_Query( $query_args );

				if ( is_wp_error( $term_query->terms ) || empty( $term_query->terms ) ) {
					break; // No more data left to sync
				}

				// Memory optimization: Force garbage collection every 10 iterations
				if ( $iteration_count % 10 === 0 ) {
					if ( function_exists( 'gc_collect_cycles' ) ) {
						gc_collect_cycles();
					}
					WP_CLI::line( "Memory usage: " . size_format( memory_get_usage( true ) ) );
				}

				$object_batch       = $collection->prepare_batch_data( $term_query->terms );
				$successful_imports = $collection->import_prepared_batch( $object_batch );

				$imported_count += count( $successful_imports ); // Increment the count of imported products
				$total_imports += count( $object_batch ); // Increment the count of imported products


				WP_CLI::success( "Completed batch {$page}..." );
				$page++; // Move to the next batch

			} while ( true );

			// Complete the sync by updating alias if using new system
			try {
				$sync_result = $collection->complete_taxonomy_sync();
				if ( $sync_result ) {
					WP_CLI::success( "Alias updated successfully. New collection: " . $sync_result['new_collection'] );
					if ( ! empty( $sync_result['deleted_collections'] ) ) {
						WP_CLI::success( "Cleaned up old collections: " . implode( ', ', $sync_result['deleted_collections'] ) );
					}
				}
			} catch (\Exception $e) {
				WP_CLI::warning( "Failed to complete sync: " . $e->getMessage() );
			}

			WP_CLI::success( "Completed! All taxonomies have been synced." );
			WP_CLI::success( "Total batch imported: " . $page );
			WP_CLI::success( "Total import: " . $total_imports );
			WP_CLI::success( "Successful import: " . $imported_count );

			// End tracking time
			$end_time       = microtime( true );
			$execution_time = $end_time - $start_time;
			// Convert execution time to hours, minutes, seconds
			$formatted_time = gmdate( "H:i:s", (int) $execution_time );
			WP_CLI::success( "Total time spent: " . $formatted_time . " (hh:mm:ss)" );
			WP_CLI::halt( 0 );
		}

		WP_CLI::error( "Nothing was sync" );
	}

	/**
	 * Sync all wp_navigation posts.
	 *
	 * ## OPTIONS
	 *
	 * [--all]
	 * : Sync all published wp_navigation posts.
	 *
	 * ## EXAMPLES
	 *
	 *     wp bc-sync navigation --all
	 *
	 * @when after_wp_load
	 */
	public function navigation( $args, $assoc_args ) {
		if ( isset( $assoc_args['all'] ) ) {
			WP_CLI::line( "Syncing all published wp_navigation posts in batches..." );

			// Display the collection name we'll be syncing to
			$collection        = Navigation::get_instance();
			$target_collection = $collection->collection_name();
			WP_CLI::line( "Target collection: " . $target_collection );

			// Start tracking time
			$start_time     = microtime( true );
			$batch_size     = Navigation::BATCH_SIZE;
			$page           = 1;
			$imported_count = 0;
			$total_imports  = 0;

			do {
				if ( $page == 1 ) {
					// recreate the collection to typesense and do some initialization
					$collection->initialize();
				}

				// the settings to not sync all navigation. Set to false so that no navigation syncs happen
				$should_sync = apply_filters( 'blazecommerce/settings/sync/navigation', true );
				if ( ! $should_sync ) {
					// This prevents syncing all navigation
					break;
				}

				$navigation_ids = $collection->get_navigation_ids( $page, $batch_size );
				if ( empty( $navigation_ids ) ) {
					break; // No more data left to sync
				}

				$object_batch = $collection->prepare_batch_data( $navigation_ids );
				if ( ! empty( $object_batch ) ) {
					$successful_imports = $collection->import_prepared_batch( $object_batch );
					$imported_count += count( $successful_imports ); // Increment the count of imported items
					$total_imports += count( $object_batch ); // Increment the count of imported items
				}

				WP_CLI::success( "Completed batch {$page}..." );
				$page++; // Move to the next batch

			} while ( true );

			WP_CLI::success( "Completed! All published wp_navigation posts have been synced." );
			WP_CLI::success( "Total batch imported: " . $page );
			WP_CLI::success( "Total import: " . $total_imports );
			WP_CLI::success( "Successful import: " . $imported_count );

			// End tracking time
			$end_time       = microtime( true );
			$execution_time = $end_time - $start_time;
			// Convert execution time to hours, minutes, seconds
			$formatted_time = gmdate( "H:i:s", (int) $execution_time );
			WP_CLI::success( "Total time spent: " . $formatted_time . " (hh:mm:ss)" );
			WP_CLI::halt( 0 );
		}

		WP_CLI::error( "Nothing was sync" );
	}

	/**
	 * Manage Typesense collection aliases.
	 *
	 * ## OPTIONS
	 *
	 * [--list]
	 * : List all aliases and their target collections.
	 *
	 * [--get-aliases]
	 * : Get all collection alias names only.
	 *
	 * [--status]
	 * : Show status of collection aliases for all types.
	 *
	 * [--cleanup=<type>]
	 * : Clean up old collections for a specific type (product, taxonomy, page, menu, site_info, navigation).
	 *
	 * [--force-alias=<type>]
	 * : Force create alias for existing collection type.
	 *
	 * ## EXAMPLES
	 *
	 *     wp bc-sync alias --list
	 *     wp bc-sync alias --get-aliases
	 *     wp bc-sync alias --status
	 *     wp bc-sync alias --cleanup=product
	 *     wp bc-sync alias --force-alias=product
	 *
	 * @when after_wp_load
	 */
	public function alias( $args, $assoc_args ) {
		$alias_manager = new \BlazeWooless\Collections\CollectionAliasManager();

		if ( isset( $assoc_args['list'] ) ) {
			WP_CLI::line( "Listing all Typesense aliases..." );

			try {
				$typesense = \BlazeWooless\TypesenseClient::get_instance();
				$aliases   = $typesense->client()->aliases->retrieve();

				if ( empty( $aliases['aliases'] ) ) {
					WP_CLI::success( "No aliases found." );
					return;
				}

				WP_CLI::line( sprintf( "%-30s %-50s", "Alias Name", "Target Collection" ) );
				WP_CLI::line( str_repeat( "-", 80 ) );

				foreach ( $aliases['aliases'] as $alias ) {
					WP_CLI::line( sprintf( "%-30s %-50s", $alias['name'], $alias['collection_name'] ) );
				}

				WP_CLI::success( "Found " . count( $aliases['aliases'] ) . " aliases." );
			} catch (\Exception $e) {
				WP_CLI::error( "Failed to retrieve aliases: " . $e->getMessage() );
			}
		}

		if ( isset( $assoc_args['get-aliases'] ) ) {
			WP_CLI::line( "Getting all collection alias names..." );

			$alias_names = $alias_manager->get_all_alias_names();

			if ( empty( $alias_names ) ) {
				WP_CLI::success( "No alias names configured." );
				return;
			}

			WP_CLI::line( "Collection Alias Names:" );
			WP_CLI::line( str_repeat( "-", 30 ) );

			foreach ( $alias_names as $alias_name ) {
				WP_CLI::line( $alias_name );
			}

			WP_CLI::success( "Found " . count( $alias_names ) . " alias names." );
		}

		if ( isset( $assoc_args['status'] ) ) {
			WP_CLI::line( "Checking alias status for all collection types..." );

			$collection_types = array( 'product', 'taxonomy', 'page', 'menu', 'site_info', 'navigation' );

			foreach ( $collection_types as $type ) {
				$alias_name         = $alias_manager->get_alias_name( $type );
				$current_collection = $alias_manager->get_current_collection( $type );
				$all_collections    = $alias_manager->get_all_collections_for_type( $type );

				WP_CLI::line( "\n" . strtoupper( $type ) . " Collections:" );
				WP_CLI::line( "  Alias: " . $alias_name );
				WP_CLI::line( "  Current: " . ( $current_collection ?: 'No alias found' ) );
				WP_CLI::line( "  All collections: " . ( empty( $all_collections ) ? 'None' : implode( ', ', $all_collections ) ) );

				if ( $current_collection ) {
					$newer = $alias_manager->get_newer_collections( $type );
					$older = $alias_manager->get_older_collections( $type, 1 );

					if ( ! empty( $newer ) ) {
						WP_CLI::line( "  ⚠️  Newer collections (should be cleaned): " . implode( ', ', $newer ) );
					}

					if ( ! empty( $older ) ) {
						WP_CLI::line( "  🗑️  Old collections (can be cleaned): " . implode( ', ', $older ) );
					}
				}
			}
		}

		if ( isset( $assoc_args['cleanup'] ) ) {
			$type = $assoc_args['cleanup'];
			WP_CLI::line( "Cleaning up old collections for type: " . $type );

			try {
				$deleted = $alias_manager->cleanup_old_collections( $type, 1 );

				if ( empty( $deleted ) ) {
					WP_CLI::success( "No old collections to clean up for " . $type );
				} else {
					WP_CLI::success( "Deleted old collections: " . implode( ', ', $deleted ) );
				}
			} catch (\Exception $e) {
				WP_CLI::error( "Failed to cleanup collections: " . $e->getMessage() );
			}
		}

		if ( isset( $assoc_args['force-alias'] ) ) {
			$type = $assoc_args['force-alias'];
			WP_CLI::line( "Force creating alias for type: " . $type );

			try {
				$all_collections = $alias_manager->get_all_collections_for_type( $type );

				if ( empty( $all_collections ) ) {
					WP_CLI::error( "No collections found for type: " . $type );
					return;
				}

				// Use the newest collection
				$target_collection = $all_collections[0]; // Already sorted newest first

				$result = $alias_manager->update_alias( $type, $target_collection );
				WP_CLI::success( "Created alias " . $alias_manager->get_alias_name( $type ) . " pointing to " . $target_collection );

			} catch (\Exception $e) {
				WP_CLI::error( "Failed to create alias: " . $e->getMessage() );
			}
		}

		if ( empty( $assoc_args ) ) {
			WP_CLI::error( "Please specify an option. Use --help for available options." );
		}
	}

	/**
	 * Show cache statistics for performance debugging.
	 *
	 * ## OPTIONS
	 *
	 * [--clear]
	 * : Clear all caches.
	 *
	 * ## EXAMPLES
	 *
	 *     wp bc-sync cache
	 *     wp bc-sync cache --clear
	 *
	 * @when after_wp_load
	 */
	public function cache( $args, $assoc_args ) {
		$alias_manager = new \BlazeWooless\Collections\CollectionAliasManager();

		if ( isset( $assoc_args['clear'] ) ) {
			WP_CLI::line( "Clearing all caches..." );

			// Clear alias manager caches
			$alias_manager->clear_all_caches();

			// Clear BaseCollection caches
			\BlazeWooless\Collections\BaseCollection::clear_collection_cache();

			WP_CLI::success( "All caches cleared successfully." );
			return;
		}

		// Show cache statistics
		WP_CLI::line( "Cache Statistics:" );
		WP_CLI::line( str_repeat( "-", 40 ) );

		$stats = $alias_manager->get_cache_stats();

		WP_CLI::line( "Alias Manager Caches:" );
		WP_CLI::line( "  - Alias cache entries: " . $stats['alias_cache_count'] );
		WP_CLI::line( "  - Current collection cache entries: " . $stats['current_collection_cache_count'] );
		WP_CLI::line( "  - Alias exists cache entries: " . $stats['alias_exists_cache_count'] );
		WP_CLI::line( "  - Cache TTL: " . $stats['cache_ttl'] . " seconds" );

		WP_CLI::line( "\nMemory Usage:" );
		WP_CLI::line( "  - Current: " . size_format( memory_get_usage( true ) ) );
		WP_CLI::line( "  - Peak: " . size_format( memory_get_peak_usage( true ) ) );

		$total_cache_entries = $stats['alias_cache_count'] + $stats['current_collection_cache_count'] + $stats['alias_exists_cache_count'];

		if ( $total_cache_entries > 0 ) {
			WP_CLI::success( "Total cache entries: " . $total_cache_entries );
		} else {
			WP_CLI::line( "No cache entries found." );
		}
	}
}