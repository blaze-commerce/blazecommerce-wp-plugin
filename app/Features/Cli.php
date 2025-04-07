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
	 * ## EXAMPLES
	 *
	 *     wp bc-sync product --all
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

				$object_batch       = $collection->prepare_batch_data( $ids );
				$successful_imports = $collection->import_prepared_batch( $object_batch );

				$imported_count += count( $successful_imports ); // Increment the count of imported products
				$total_imports += count( $object_batch ); // Increment the count of imported products


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
}