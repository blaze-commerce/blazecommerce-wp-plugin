<?php

namespace BlazeWooless\Features;

use BlazeWooless\Collections\Menu;
use BlazeWooless\Collections\Page;
use BlazeWooless\Collections\Product;
use BlazeWooless\Collections\SiteInfo;
use BlazeWooless\Collections\Taxonomy;
use WP;
use WP_CLI;
use WP_CLI_Command;


class Cli extends WP_CLI_Command {

	private $page = 1;



	/**
	 * Sync all products.
	 * @return void
	 */
	protected function sync_products( $type = "all" ) {

		switch ( $type ) {
			case 'non-variants':
				$product_type = "all non-variant";
				break;
			case 'variants':
				$product_type = "all variant";
				break;
			default:
				$product_type = "all";
				break;
		}

		WP_CLI::success( sprintf( "Syncing %s products in batches...", $product_type ) );

		// Start tracking time
		$start_time = microtime( true );
		$product_collection = Product::get_instance();
		$batch_size = Product::BATCH_SIZE;
		$page = $this->page;
		$imported_products_count = 0;
		$total_imports = 0;

		if ( $page == 1 && $type !== "variants" ) {
			// recreate the collection to typesense and do some initialization
			$product_collection->initialize();
			WP_CLI::success( "Dropping all products..." );
		}

		do {
			$product_ids = $product_collection->get_product_ids( $page, $batch_size, $type );

			if ( empty( $product_ids ) ) {
				break; // No more data left to sync
			}

			$products_batch = $product_collection->prepare_batch_data( $product_ids );
			$successful_imports = $product_collection->import_prepared_batch( $products_batch );

			$imported_products_count += count( $successful_imports ); // Increment the count of imported products
			$total_imports += count( $products_batch ); // Increment the count of imported products


			WP_CLI::success( "Completed batch process {$page}..." );
			$page++; // Move to the next batch
			sleep( 1 ); // Sleep for 1 second to avoid overwhelming the server
		} while ( true );



		WP_CLI::success( sprintf( "All %s products have been synced.", $product_type ) );
		WP_CLI::success( "Total batch imported: " . $page );
		WP_CLI::success( "Total import: " . $total_imports );
		WP_CLI::success( "Successful import: " . $imported_products_count );

		// End tracking time
		$end_time = microtime( true );
		$execution_time = $end_time - $start_time;
		// Convert execution time to hours, minutes, seconds
		$formatted_time = gmdate( "H:i:s", (int) $execution_time );
		WP_CLI::success( "Total time spent: " . $formatted_time . " (hh:mm:ss)" );

		WP_CLI::halt( 0 );
	}

	/**
	 * Sync all product variants.
	 */

	protected function sync_variants() {
		$start_time = microtime( true );
		$page = $this->page;

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
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false,
			'no_found_rows' => true,
			'ignore_sticky_posts' => true,
			'field' => 'ids',
		);

		$query = new \WP_Query( $args );
		$variation_ids = [];

		if ( $query->posts ) {
			foreach ( $query->posts as $post_id ) {
				$product = wc_get_product( $post_id );

				if ( $product && $product->is_type( 'variable' ) ) {
					$children = $product->get_children();
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
			WP_CLI::success( "Total variation product: " . count( $query->posts ) );
			WP_CLI::success( "Total child variation product: " . count( $variation_ids ) );

			// End tracking time
			$end_time = microtime( true );
			$execution_time = $end_time - $start_time;
			// Convert execution time to hours, minutes, seconds
			$formatted_time = gmdate( "H:i:s", (int) $execution_time );
			WP_CLI::success( "Total time spent: " . $formatted_time . " (hh:mm:ss)" );

			WP_CLI::halt( 0 );
		}
	}

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
	 * : Sync all non products variants.
	 *
	 * [--page]
	 * : Set current page number to start syncing from.
	 * 
	 * ## EXAMPLES
	 *
	 * wp bc-sync product --all
	 * wp bc-sync product --nonvariants
	 *
	 * @when after_wp_load
	 */
	public function product( $args, $assoc_args ) {
		// Disable PHP warnings and notices
		error_reporting( E_ERROR );

		$assoc_args = wp_parse_args( $assoc_args, [ 
			'page' => 1
		] );

		$this->page = $assoc_args['page'];

		WP_CLI::success( "Start page : " . $this->page );

		if ( isset( $assoc_args['all'] ) ) {
			$this->sync_products( 'all' );
		}

		if ( isset( $assoc_args['variants'] ) ) {
			$this->sync_variants();
		}

		if ( isset( $assoc_args['nonvariants'] ) ) {
			$this->sync_products( 'non-variants' );
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
			$start_time = microtime( true );
			$collection = Page::get_instance();
			$batch_size = Page::BATCH_SIZE;
			$page = 1;
			$imported_count = 0;
			$total_imports = 0;

			do {
				if ( $page == 1 ) {
					// recreate the collection to typesense and do some initialization
					$collection->initialize();
				}

				$ids = $collection->get_post_ids( $page, $batch_size );
				if ( empty( $ids ) ) {
					break; // No more data left to sync
				}

				$object_batch = $collection->prepare_batch_data( $ids );
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
			$end_time = microtime( true );
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
			$start_time = microtime( true );
			$collection = Menu::get_instance();
			$imported_count = 0;
			$total_imports = 0;

			// recreate the collection to typesense and do some initialization
			$collection->initialize();
			$object_batch = $collection->prepare_batch_data();
			$successful_imports = $collection->import_prepared_batch( $object_batch );

			$imported_count += count( $successful_imports ); // Increment the count of imported products
			$total_imports += count( $object_batch ); // Increment the count of imported products

			WP_CLI::success( "Completed! All menus have been synced." );
			WP_CLI::success( "Total import: " . $total_imports );
			WP_CLI::success( "Successful import: " . $imported_count );

			// End tracking time
			$end_time = microtime( true );
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
			$total_imports = 0;

			// recreate the collection to typesense and do some initialization
			$collection->initialize();

			$object_batch = $collection->prepare_batch_data();
			$successful_imports = $collection->import_prepared_batch( $object_batch );
			$collection->after_site_info_sync();
			$imported_count += count( $successful_imports ); // Increment the count of imported products
			$total_imports += count( $object_batch ); // Increment the count of imported products



			WP_CLI::success( "Completed! All site info have been synced." );
			WP_CLI::success( "Total import: " . $total_imports );
			WP_CLI::success( "Successful import: " . $imported_count );

			// End tracking time
			$end_time = microtime( true );
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
			$start_time = microtime( true );
			$collection = Taxonomy::get_instance();
			$batch_size = Taxonomy::BATCH_SIZE;
			$page = 1;
			$imported_count = 0;
			$total_imports = 0;

			do {
				if ( $page == 1 ) {
					// recreate the collection to typesense and do some initialization
					$collection->initialize();
				}

				$query_args = $collection->get_query_args( $page, $batch_size );
				$term_query = new \WP_Term_Query( $query_args );

				if ( is_wp_error( $term_query->terms ) || empty( $term_query->terms ) ) {
					break; // No more data left to sync
				}

				$object_batch = $collection->prepare_batch_data( $term_query->terms );
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
			$end_time = microtime( true );
			$execution_time = $end_time - $start_time;
			// Convert execution time to hours, minutes, seconds
			$formatted_time = gmdate( "H:i:s", (int) $execution_time );
			WP_CLI::success( "Total time spent: " . $formatted_time . " (hh:mm:ss)" );
			WP_CLI::halt( 0 );
		}

		WP_CLI::error( "Nothing was sync" );
	}
}