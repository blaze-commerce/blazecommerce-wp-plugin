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
	 * ## EXAMPLES
	 *
	 *     wp bc-sync product --all
	 *
	 * @when after_wp_load
	 */
	public function product( $args, $assoc_args ) {
		if ( isset( $assoc_args['all'] ) ) {
			WP_CLI::line( "Syncing all products in batches..." );

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
			WP_CLI::halt( 0 );
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
			WP_CLI::halt( 0 );
		}

		WP_CLI::error( "Nothing was sync" );
	}
}