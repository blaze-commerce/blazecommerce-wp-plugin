<?php

namespace BlazeWooless\Features;

use BlazeWooless\Collections\Product;
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
					// $product_collection->initialize();
				}

				$product_ids = $product_collection->get_product_ids( $page, $batch_size );
				if ( empty( $product_ids ) ) {
					break; // No more products left to sync
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
		}

		WP_CLI::error( "Nothing was sync" );
	}
}