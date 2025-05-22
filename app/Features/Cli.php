<?php

namespace BlazeWooless\Features;

use BlazeWooless\Collections\Menu;
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
			// Start tracking time
			$start_time              = microtime( true );
			$product_collection      = Product::get_instance();
			$batch_size              = Product::BATCH_SIZE;
			$page                    = 1;
			$imported_products_count = 0;
			$total_imports           = 0;

			do {
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

				$products_batch     = $product_collection->prepare_batch_data( $product_ids );
				$successful_imports = $product_collection->import_prepared_batch( $products_batch );

				$imported_products_count += count( $successful_imports ); // Increment the count of imported products
				$total_imports += count( $products_batch ); // Increment the count of imported products


				WP_CLI::success( "Completed batch {$page}..." );
				$page++; // Move to the next batch

			} while ( true );



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
			$this->sync_product_variants_with_timeout_protection( $assoc_args );
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
						'terms' => array('variable'),
						'operator' => 'NOT IN'
					),
				),
			);

			$query = new \WP_Query( $args );
			$nonvariant_product_ids = array();

			if ( $query->have_posts() ) {
				while ( $query->have_posts() ) {
					$query->the_post();
					$nonvariant_product_ids[] = get_the_ID();
				}

				$product_collection = Product::get_instance();

				// Initialize the collection if this is the first batch
				$product_collection->initialize();

				$chunks = array_chunk( $nonvariant_product_ids, 50 );
				$imported_products_count = 0;
				$total_imports = 0;

				foreach ( $chunks as $chunk ) {
					$products_batch = $product_collection->prepare_batch_data( $chunk );
					$successful_imports = $product_collection->import_prepared_batch( $products_batch );

					$imported_products_count += count( $successful_imports );
					$total_imports += count( $products_batch );

					WP_CLI::success( "Completed batch {$page}..." );
					$page++; // Move to the next batch
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

			// Start tracking time
			$start_time     = microtime( true );
			$collection     = Page::get_instance();
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
			// Start tracking time
			$start_time     = microtime( true );
			$collection     = Menu::get_instance();
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
			// Start tracking time
			$start_time = microtime( true );
			$collection = SiteInfo::get_instance();

			$imported_count = 0;
			$total_imports  = 0;

			// recreate the collection to typesense and do some initialization
			$collection->initialize();

			$object_batch       = $collection->prepare_batch_data();
			$successful_imports = $collection->import_prepared_batch( $object_batch );
			$collection->after_site_info_sync();
			$imported_count += count( $successful_imports ); // Increment the count of imported products
			$total_imports += count( $object_batch ); // Increment the count of imported products



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

			// Start tracking time
			$start_time     = microtime( true );
			$collection     = Taxonomy::get_instance();
			$batch_size     = Taxonomy::BATCH_SIZE;
			$page           = 1;
			$imported_count = 0;
			$total_imports  = 0;

			do {
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

				$object_batch       = $collection->prepare_batch_data( $term_query->terms );
				$successful_imports = $collection->import_prepared_batch( $object_batch );

				$imported_count += count( $successful_imports ); // Increment the count of imported products
				$total_imports += count( $object_batch ); // Increment the count of imported products


				WP_CLI::success( "Completed batch {$page}..." );
				$page++; // Move to the next batch

			} while ( true );

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
	 * Sync product variants with timeout protection to avoid "Maximum execution time exceeded" errors
	 *
	 * @param array $assoc_args CLI arguments
	 */
	private function sync_product_variants_with_timeout_protection( $assoc_args ) {
		// Set execution time limit to prevent timeout
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 0 ); // Remove time limit for CLI
		}

		// Increase memory limit if possible
		if ( function_exists( 'ini_set' ) ) {
			ini_set( 'memory_limit', '512M' );
		}

		$start_time = microtime( true );
		$max_execution_time = 240; // 4 minutes safety margin (less than 5 min default)
		$batch_size = 25; // Smaller batch size to reduce memory usage
		$variable_products_per_batch = 10; // Process fewer variable products at once

		WP_CLI::line( "Syncing product variants with timeout protection..." );
		WP_CLI::line( "Batch size: {$batch_size} variations per batch" );
		WP_CLI::line( "Variable products per batch: {$variable_products_per_batch}" );

		$page = 1;
		$total_variations_synced = 0;
		$total_variable_products = 0;

		do {
			$batch_start_time = microtime( true );

			// Check if we're approaching time limit
			$elapsed_time = $batch_start_time - $start_time;
			if ( $elapsed_time > $max_execution_time ) {
				WP_CLI::warning( "Approaching time limit. Stopping sync to prevent timeout." );
				WP_CLI::line( "Resume sync by running the command again." );
				break;
			}

			// Get variable products in smaller batches
			$args = array(
				'post_type' => 'product',
				'post_status' => 'publish',
				'posts_per_page' => $variable_products_per_batch,
				'paged' => $page,
				'tax_query' => array(
					array(
						'taxonomy' => 'product_type',
						'field' => 'slug',
						'terms' => 'variable',
					),
				),
			);

			$query = new \WP_Query( $args );

			if ( ! $query->have_posts() ) {
				break; // No more variable products to process
			}

			$variation_ids = [];
			$current_batch_variable_products = 0;

			while ( $query->have_posts() ) {
				$query->the_post();
				$product = wc_get_product( get_the_ID() );

				if ( $product && $product->is_type( 'variable' ) ) {
					$children = $product->get_children();
					$variation_ids = array_merge( $variation_ids, $children );
					$current_batch_variable_products++;
					$total_variable_products++;
				}

				// Clear memory
				unset( $product );
			}

			wp_reset_postdata();

			if ( ! empty( $variation_ids ) ) {
				// Process variations in smaller chunks
				$variation_chunks = array_chunk( $variation_ids, $batch_size );

				foreach ( $variation_chunks as $chunk_index => $chunk ) {
					// Check time limit before each chunk
					$current_time = microtime( true );
					$elapsed_time = $current_time - $start_time;

					if ( $elapsed_time > $max_execution_time ) {
						WP_CLI::warning( "Time limit reached during chunk processing. Stopping." );
						break 2; // Break out of both loops
					}

					try {
						\BlazeWooless\Woocommerce::get_instance()->variation_update( $chunk );
						$total_variations_synced += count( $chunk );

						WP_CLI::success( "Batch {$page}, Chunk " . ($chunk_index + 1) . ": Synced " . count( $chunk ) . " variations" );

						// Small delay to prevent overwhelming the server
						usleep( 100000 ); // 0.1 second delay

					} catch ( \Exception $e ) {
						WP_CLI::warning( "Error syncing chunk: " . $e->getMessage() );
						continue;
					}

					// Clear memory after each chunk
					unset( $chunk );
				}
			}

			$batch_end_time = microtime( true );
			$batch_duration = $batch_end_time - $batch_start_time;

			WP_CLI::line( sprintf(
				"Page %d completed: %d variable products, %d variations (%.2f seconds)",
				$page,
				$current_batch_variable_products,
				count( $variation_ids ),
				$batch_duration
			) );

			// Clear memory
			unset( $variation_ids, $query );

			// Force garbage collection
			if ( function_exists( 'gc_collect_cycles' ) ) {
				gc_collect_cycles();
			}

			$page++;

		} while ( true );

		// Final summary
		$end_time = microtime( true );
		$execution_time = $end_time - $start_time;
		$formatted_time = gmdate( "H:i:s", (int) $execution_time );

		WP_CLI::success( "Variant sync completed!" );
		WP_CLI::success( "Total variable products processed: " . $total_variable_products );
		WP_CLI::success( "Total variations synced: " . $total_variations_synced );
		WP_CLI::success( "Total time spent: " . $formatted_time . " (hh:mm:ss)" );

		WP_CLI::halt( 0 );
	}
}