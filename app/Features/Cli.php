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
	 * Display target collection name for CLI sync operations
	 *
	 * @param object $collection Collection instance
	 * @return string Target collection name
	 */
	private function display_target_collection( $collection ) {
		$use_aliases = apply_filters( 'blazecommerce/use_collection_aliases', true );
		if ( $use_aliases ) {
			$target_collection = $collection->get_inactive_collection_name();
			WP_CLI::line( "Target collection: " . $target_collection );
		} else {
			$target_collection = $collection->collection_name();
			WP_CLI::line( "Target collection: " . $target_collection );
		}
		return $target_collection;
	}

	/**
	 * Complete sync operation with standardized error handling and success messages
	 *
	 * @param object $collection Collection instance
	 * @param array $options Optional parameters to pass to the sync method
	 * @return void
	 */
	private function complete_collection_sync( $collection, $options = array() ) {
		try {
			$sync_result = $collection->complete_collection_sync( $options );
			if ( $sync_result ) {
				WP_CLI::success( "Alias updated successfully. New collection: " . $sync_result['new_collection'] );
				if ( ! empty( $sync_result['deleted_collections'] ) ) {
					WP_CLI::success( "Cleaned up old collections: " . implode( ', ', $sync_result['deleted_collections'] ) );
				}
			}
		} catch (\Exception $e) {
			WP_CLI::warning( "Failed to complete sync: " . $e->getMessage() );
		}
	}

	/**
	 * Display standardized sync completion statistics
	 *
	 * @param int $start_time Start time in microtime
	 * @param int $total_imports Total number of items processed
	 * @param int $successful_imports Number of successfully imported items
	 * @param int $page_count Number of batches processed (optional)
	 * @param string $item_type Type of items synced (e.g., 'products', 'pages')
	 * @return void
	 */
	private function display_sync_stats( $start_time, $total_imports, $successful_imports, $page_count = null, $item_type = 'items' ) {
		if ( $page_count !== null ) {
			WP_CLI::success( "Total batch imported: " . $page_count );
		}
		WP_CLI::success( "Total import: " . $total_imports );
		WP_CLI::success( "Successful import: " . $successful_imports );

		// End tracking time
		$end_time       = microtime( true );
		$execution_time = $end_time - $start_time;
		// Convert execution time to hours, minutes, seconds
		$formatted_time = gmdate( "H:i:s", (int) $execution_time );
		WP_CLI::success( "Total time spent: " . $formatted_time . " (hh:mm:ss)" );
	}

	/**
	 * Sync all products.
	 *
	 * ## OPTIONS
	 *
	 * [--all]
	 * : Sync all products including their variations to the same collection.
	 *
	 * [--variants]
	 * : Sync all products variants only.
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
			$this->display_target_collection( $product_collection );

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

			// After syncing all products, sync their variations to the same collection
			WP_CLI::line( "Syncing product variations to the same collection..." );
			try {
				$this->sync_variations_to_current_collection( $product_collection );
			} catch ( \Exception $e ) {
				WP_CLI::warning( 'Variation sync encountered an error but product sync will continue: ' . $e->getMessage() );
			}

			// Complete the sync by updating alias if using new system
			$this->complete_collection_sync( $product_collection );

			WP_CLI::success( "All products and variations have been synced." );
			$this->display_sync_stats( $start_time, $total_imports, $imported_products_count, $page );

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
				$this->display_target_collection( $product_collection );

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
				$this->complete_collection_sync( $product_collection );

				WP_CLI::success( "All non-variant products have been synced." );
				WP_CLI::success( "Total non-variant products: " . count( $nonvariant_product_ids ) );
				$this->display_sync_stats( $start_time, $total_imports, $imported_products_count );

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
			$collection = Page::get_instance();
			$this->display_target_collection( $collection );

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
			$this->complete_collection_sync( $collection );

			WP_CLI::success( "Completed! All page and post have been synced." );
			$this->display_sync_stats( $start_time, $total_imports, $imported_count, $page );
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
			$collection = Menu::get_instance();
			$this->display_target_collection( $collection );

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

			// Complete the sync by updating alias if using new system
			$this->complete_collection_sync( $collection );

			WP_CLI::success( "Completed! All menus have been synced." );
			$this->display_sync_stats( $start_time, $total_imports, $imported_count );
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
			$collection = SiteInfo::get_instance();
			$this->display_target_collection( $collection );

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
			$this->complete_collection_sync( $collection );

			$collection->after_site_info_sync();

			WP_CLI::success( "Completed! All site info have been synced." );
			$this->display_sync_stats( $start_time, $total_imports, $imported_count );
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
			$collection = Taxonomy::get_instance();
			$this->display_target_collection( $collection );

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
			$this->complete_collection_sync( $collection );

			WP_CLI::success( "Completed! All taxonomies have been synced." );
			$this->display_sync_stats( $start_time, $total_imports, $imported_count, $page );
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
			$collection = Navigation::get_instance();
			$this->display_target_collection( $collection );

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

			// Complete the sync by updating alias if using new system
			$this->complete_collection_sync( $collection );

			WP_CLI::success( "Completed! All published wp_navigation posts have been synced." );
			$this->display_sync_stats( $start_time, $total_imports, $imported_count, $page );
			WP_CLI::halt( 0 );
		}

		WP_CLI::error( "Nothing was sync" );
	}

	/**
	 * Sync all Typesense collections in the specified order.
	 *
	 * ## OPTIONS
	 *
	 * [--all]
	 * : Sync all collections in order: site_info, products, taxonomy, menu, page_and_post, navigation.
	 *
	 * ## EXAMPLES
	 *
	 *     wp bc-sync collections --all
	 *
	 * @when after_wp_load
	 */
	public function collections( $args, $assoc_args ) {
		if ( ! isset( $assoc_args['all'] ) ) {
			WP_CLI::error( "Please specify --all to sync all collections." );
			return;
		}

		WP_CLI::line( "Starting sync of all Typesense collections..." );

		$total_start_time = microtime( true );
		$total_imports = 0;
		$total_successful_imports = 0;
		$collection_results = array();

		$collections_to_sync = $this->get_collections_configuration();

		foreach ( $collections_to_sync as $collection_type => $collection_info ) {
			$collection_start_time = microtime( true );

			$this->display_collection_header( $collection_info['name'] );

			try {
				$sync_result = $this->execute_collection_sync( $collection_type, $collection_info );
				$collection_results[] = $sync_result;
				$total_imports += $sync_result['total_imports'];
				$total_successful_imports += $sync_result['successful_imports'];

				$this->display_collection_success( $collection_info['name'], $sync_result, $collection_start_time );

			} catch ( \Exception $e ) {
				$error_result = $this->handle_sync_error( $collection_info['name'], $e );
				$collection_results[] = $error_result;
			}
		}

		$this->display_collections_summary( $collection_results, $total_start_time, $total_imports, $total_successful_imports );

		WP_CLI::halt( 0 );
	}

	/**
	 * Get the configuration for all collections to sync in the required order
	 *
	 * @return array Collections configuration with class, display name, and sync type
	 */
	private function get_collections_configuration() {
		$config = array(
			'site_info' => array(
				'class' => SiteInfo::class,
				'name' => 'Site Info',
				'sync_type' => 'single_batch',
				'post_sync_callback' => 'after_site_info_sync'
			),
			'products' => array(
				'class' => Product::class,
				'name' => 'Products',
				'sync_type' => 'batch_with_variations',
				'id_method' => 'get_product_ids'
			),
			'taxonomy' => array(
				'class' => Taxonomy::class,
				'name' => 'Taxonomy',
				'sync_type' => 'batch_with_query',
				'query_method' => 'get_query_args',
				'filter_key' => 'blazecommerce/settings/sync/taxonomies'
			),
			'menu' => array(
				'class' => Menu::class,
				'name' => 'Menu',
				'sync_type' => 'single_batch'
			),
			'page_and_post' => array(
				'class' => Page::class,
				'name' => 'Pages and Posts',
				'sync_type' => 'batch_with_ids',
				'id_method' => 'get_post_ids',
				'filter_key' => 'blazecommerce/settings/sync/pageAndPost'
			),
			'navigation' => array(
				'class' => Navigation::class,
				'name' => 'Navigation',
				'sync_type' => 'batch_with_ids',
				'id_method' => 'get_navigation_ids',
				'filter_key' => 'blazecommerce/settings/sync/navigation'
			)
		);
		
		// Validate configuration before returning
		$this->validate_collections_configuration( $config );
		
		return $config;
	}
	
	/**
	 * Validate collections configuration to ensure all required components exist
	 *
	 * @param array $collections_to_sync Collections configuration array
	 * @throws \Exception If configuration is invalid
	 */
	private function validate_collections_configuration( $collections_to_sync ) {
		if ( empty( $collections_to_sync ) ) {
			throw new \Exception( 'Collections configuration is empty' );
		}
		
		foreach ( $collections_to_sync as $type => $info ) {
			// Validate required keys
			if ( ! isset( $info['class'] ) || ! isset( $info['name'] ) || ! isset( $info['sync_type'] ) ) {
				throw new \Exception( "Collection configuration for '{$type}' is missing required keys (class, name, sync_type)" );
			}
			
			// Validate class existence
			if ( ! class_exists( $info['class'] ) ) {
				throw new \Exception( "Collection class '{$info['class']}' does not exist for type '{$type}'" );
			}
			
			// Validate sync type
			$valid_sync_types = array( 'single_batch', 'batch_with_ids', 'batch_with_query', 'batch_with_variations' );
			if ( ! in_array( $info['sync_type'], $valid_sync_types, true ) ) {
				throw new \Exception( "Invalid sync_type '{$info['sync_type']}' for collection '{$type}'. Valid types: " . implode( ', ', $valid_sync_types ) );
			}
			
			// Validate type-specific requirements
			if ( in_array( $info['sync_type'], array( 'batch_with_ids', 'batch_with_variations' ), true ) ) {
				if ( ! isset( $info['id_method'] ) ) {
					throw new \Exception( "Collection '{$type}' with sync_type '{$info['sync_type']}' requires 'id_method' parameter" );
				}
				
				// Validate method existence
				if ( ! method_exists( $info['class'], $info['id_method'] ) ) {
					throw new \Exception( "Method '{$info['id_method']}' does not exist in class '{$info['class']}' for collection '{$type}'" );
				}
			}
			
			if ( $info['sync_type'] === 'batch_with_query' ) {
				if ( ! isset( $info['query_method'] ) ) {
					throw new \Exception( "Collection '{$type}' with sync_type 'batch_with_query' requires 'query_method' parameter" );
				}
				
				// Validate method existence
				if ( ! method_exists( $info['class'], $info['query_method'] ) ) {
					throw new \Exception( "Method '{$info['query_method']}' does not exist in class '{$info['class']}' for collection '{$type}'" );
				}
			}
			
			// Validate callback methods if specified
			if ( isset( $info['post_sync_callback'] ) && ! method_exists( $info['class'], $info['post_sync_callback'] ) ) {
				throw new \Exception( "Post sync callback method '{$info['post_sync_callback']}' does not exist in class '{$info['class']}' for collection '{$type}'" );
			}
		}
	}

	/**
	 * Display header for individual collection sync (reuses existing pattern)
	 *
	 * @param string $collection_name Display name of the collection
	 */
	private function display_collection_header( $collection_name ) {
		WP_CLI::line( "\n" . str_repeat( "=", 50 ) );
		WP_CLI::line( "Syncing {$collection_name} collection..." );
		WP_CLI::line( str_repeat( "=", 50 ) );
	}

	/**
	 * Display success message for individual collection sync (reuses existing timing logic)
	 *
	 * @param string $collection_name Display name of the collection
	 * @param array $sync_result Result from sync operation
	 * @param float $collection_start_time Start time for this collection
	 */
	private function display_collection_success( $collection_name, $sync_result, $collection_start_time ) {
		$collection_end_time = microtime( true );
		$collection_time = $collection_end_time - $collection_start_time;
		$formatted_time = gmdate( "H:i:s", (int) $collection_time );

		WP_CLI::success( "{$collection_name} sync completed in {$formatted_time}" );
		WP_CLI::success( "Imported: {$sync_result['successful_imports']}/{$sync_result['total_imports']} items" );
	}

	/**
	 * Handle error during collection sync (reuses existing error handling pattern)
	 *
	 * @param string $collection_name Display name of the collection
	 * @param \Exception $exception The exception that occurred
	 * @return array Error result array
	 */
	private function handle_sync_error( $collection_name, $exception ) {
		WP_CLI::warning( "Failed to sync {$collection_name}: " . $exception->getMessage() );

		return array(
			'collection' => $collection_name,
			'total_imports' => 0,
			'successful_imports' => 0,
			'error' => $exception->getMessage()
		);
	}

	/**
	 * Display final summary of all collection syncs (reuses existing display_sync_stats)
	 *
	 * @param array $collection_results Results from all collection syncs
	 * @param float $total_start_time Start time for entire operation
	 * @param int $total_imports Total number of items processed
	 * @param int $total_successful_imports Total number of items successfully imported
	 */
	private function display_collections_summary( $collection_results, $total_start_time, $total_imports, $total_successful_imports ) {
		WP_CLI::line( "\n" . str_repeat( "=", 60 ) );
		WP_CLI::line( "ALL COLLECTIONS SYNC SUMMARY" );
		WP_CLI::line( str_repeat( "=", 60 ) );

		$success_count = 0;
		$error_count = 0;
		$skipped_count = 0;
		
		foreach ( $collection_results as $result ) {
			if ( isset( $result['error'] ) ) {
				WP_CLI::line( "❌ {$result['collection']}: FAILED - {$result['error']}" );
				$error_count++;
			} elseif ( isset( $result['skipped'] ) && $result['skipped'] ) {
				WP_CLI::line( "⏭️ {$result['collection']}: SKIPPED - {$result['reason']}" );
				$skipped_count++;
			} else {
				WP_CLI::line( "✅ {$result['collection']}: {$result['successful_imports']}/{$result['total_imports']} items" );
				$success_count++;
			}
		}

		WP_CLI::line( "\nOverall Statistics:" );
		WP_CLI::line( "Collections processed: " . count( $collection_results ) );
		WP_CLI::line( "Successful: {$success_count}" );
		WP_CLI::line( "Failed: {$error_count}" );
		WP_CLI::line( "Skipped: {$skipped_count}" );
		
		$this->display_sync_stats( $total_start_time, $total_imports, $total_successful_imports, null, 'collections' );

		if ( $error_count > 0 ) {
			WP_CLI::warning( "Collections sync completed with {$error_count} errors. Review the logs above for details." );
		} else {
			WP_CLI::success( "All collections sync process completed successfully!" );
		}
	}

	/**
	 * Execute sync for a single collection using reusable patterns
	 *
	 * @param string $collection_type The type of collection (site_info, products, etc.)
	 * @param array $collection_info Collection configuration array
	 * @return array Statistics about the sync operation
	 * @throws \Exception If collection type is unknown or sync fails
	 */
	private function execute_collection_sync( $collection_type, $collection_info ) {
		$collection_class = $collection_info['class'];
		$collection = $collection_class::get_instance();
		
		// Validate collection instance
		if ( ! $collection ) {
			throw new \Exception( "Failed to get instance of collection class '{$collection_class}' for type '{$collection_type}'" );
		}
		
		$this->display_target_collection( $collection );

		// Check if sync is enabled via filter
		if ( isset( $collection_info['filter_key'] ) ) {
			$should_sync = apply_filters( $collection_info['filter_key'], true );
			if ( ! $should_sync ) {
				// Enhanced logging for disabled filters
				WP_CLI::warning( "{$collection_info['name']} sync is disabled by filter '{$collection_info['filter_key']}'." );
				WP_CLI::line( "To enable {$collection_info['name']} sync, ensure the filter '{$collection_info['filter_key']}' returns true." );
				return array(
					'collection' => $collection_info['name'],
					'total_imports' => 0,
					'successful_imports' => 0,
					'skipped' => true,
					'reason' => 'disabled_by_filter'
				);
			}
		}

		// Execute sync based on type using existing patterns
		switch ( $collection_info['sync_type'] ) {
			case 'single_batch':
				$sync_stats = $this->execute_single_batch_sync( $collection, $collection_info );
				break;

			case 'batch_with_ids':
				$sync_stats = $this->execute_batch_sync_with_ids( $collection, $collection_info, $collection_type );
				break;

			case 'batch_with_query':
				$sync_stats = $this->execute_batch_sync_with_query( $collection, $collection_info, $collection_type );
				break;

			case 'batch_with_variations':
				$sync_stats = $this->execute_products_sync_with_variations( $collection, $collection_type );
				break;

			default:
				throw new \Exception( "Unknown sync type: {$collection_info['sync_type']}" );
		}

		return array(
			'collection' => $collection_info['name'],
			'total_imports' => $sync_stats['total_imports'],
			'successful_imports' => $sync_stats['imported_count']
		);
	}
	
	/**
	 * Get adaptive safety limit based on collection type
	 *
	 * @param string $collection_type The type of collection
	 * @return int Safety limit for iterations
	 */
	private function get_safety_limit( $collection_type ) {
		$limits = array(
			'products' => 2000,    // Higher limit for products (large catalogs)
			'taxonomy' => 800,     // Higher limit for taxonomy terms
			'page_and_post' => 1500, // Higher limit for pages and posts
			'navigation' => 200,   // Lower limit for navigation
			'menu' => 100,         // Lower limit for menus
			'site_info' => 50,     // Lowest limit for site info
			'default' => 1000      // Default fallback
		);
		
		return isset( $limits[$collection_type] ) ? $limits[$collection_type] : $limits['default'];
	}
	
	/**
	 * Calculate progress percentage for long operations
	 *
	 * @param int $current_iteration Current iteration number
	 * @param int $estimated_total Estimated total iterations
	 * @return float Progress percentage (0-100)
	 */
	private function calculate_progress_percentage( $current_iteration, $estimated_total ) {
		if ( $estimated_total <= 0 ) {
			return 0;
		}
		
		return min( 100, ( $current_iteration / $estimated_total ) * 100 );
	}
	
	/**
	 * Check if memory-based garbage collection is needed
	 *
	 * @return bool True if garbage collection should be triggered
	 */
	private function should_trigger_gc() {
		$current_memory = memory_get_usage( true );
		$memory_limit = wp_convert_hr_to_bytes( ini_get( 'memory_limit' ) );
		
		// Trigger GC if we're using more than 70% of memory limit
		if ( $memory_limit > 0 && $current_memory > ( $memory_limit * 0.7 ) ) {
			return true;
		}
		
		// Also trigger if we're using more than 512MB regardless of limit
		if ( $current_memory > ( 512 * 1024 * 1024 ) ) {
			return true;
		}
		
		return false;
	}

	/**
	 * Execute single batch sync (reuses existing menu/site_info pattern)
	 *
	 * @param object $collection Collection instance
	 * @param array $collection_info Collection configuration
	 * @return array Sync statistics
	 */
	private function execute_single_batch_sync( $collection, $collection_info ) {
		$collection->initialize();
		$object_batch = $collection->prepare_batch_data();
		$successful_imports = $collection->import_prepared_batch( $object_batch );
		$this->complete_collection_sync( $collection );

		// Execute post-sync callback if defined
		if ( isset( $collection_info['post_sync_callback'] ) ) {
			$callback_method = $collection_info['post_sync_callback'];
			if ( method_exists( $collection, $callback_method ) ) {
				$collection->$callback_method();
			}
		}

		return array(
			'imported_count' => count( $successful_imports ),
			'total_imports' => count( $object_batch )
		);
	}

	/**
	 * Execute batch sync with ID retrieval (reuses existing page/navigation pattern)
	 *
	 * @param object $collection Collection instance
	 * @param array $collection_info Collection configuration
	 * @param string $collection_type Collection type for adaptive limits
	 * @return array Sync statistics
	 */
	private function execute_batch_sync_with_ids( $collection, $collection_info, $collection_type ) {
		$collection->initialize();
		$batch_size_constant = $collection_info['class'] . '::BATCH_SIZE';
		$batch_size = defined( $batch_size_constant ) ? constant( $batch_size_constant ) : 50;
		$id_method = $collection_info['id_method'];

		return $this->execute_batch_processing_loop( $collection, $batch_size, function( $page, $batch_size ) use ( $collection, $id_method ) {
			return $collection->$id_method( $page, $batch_size );
		}, $collection_type );
	}

	/**
	 * Execute batch sync with query args (reuses existing taxonomy pattern)
	 *
	 * @param object $collection Collection instance
	 * @param array $collection_info Collection configuration
	 * @param string $collection_type Collection type for adaptive limits
	 * @return array Sync statistics
	 */
	private function execute_batch_sync_with_query( $collection, $collection_info, $collection_type ) {
		$collection->initialize();
		$batch_size_constant = $collection_info['class'] . '::BATCH_SIZE';
		$batch_size = defined( $batch_size_constant ) ? constant( $batch_size_constant ) : 50;
		$query_method = $collection_info['query_method'];

		return $this->execute_batch_processing_loop( $collection, $batch_size, function( $page, $batch_size ) use ( $collection, $query_method ) {
			$query_args = $collection->$query_method( $page, $batch_size );
			$term_query = new \WP_Term_Query( $query_args );

			if ( is_wp_error( $term_query->terms ) || empty( $term_query->terms ) ) {
				return array();
			}

			return $term_query->terms;
		}, $collection_type );
	}

	/**
	 * Execute products sync with variations (reuses existing product pattern)
	 *
	 * @param object $collection Product collection instance
	 * @param string $collection_type Collection type for adaptive limits
	 * @return array Sync statistics
	 */
	private function execute_products_sync_with_variations( $collection, $collection_type ) {
		$collection->initialize();
		$batch_size = Product::BATCH_SIZE;

		$sync_stats = $this->execute_batch_processing_loop( $collection, $batch_size, function( $page, $batch_size ) use ( $collection ) {
			return $collection->get_product_ids( $page, $batch_size );
		}, $collection_type );

		// Sync variations using existing method
		WP_CLI::line( "Syncing product variations to the same collection..." );
		try {
			$this->sync_variations_to_current_collection( $collection );
		} catch ( \Exception $e ) {
			WP_CLI::warning( 'Variation sync encountered an error but product sync will continue: ' . $e->getMessage() );
		}

		return $sync_stats;
	}

	/**
	 * Execute batch processing loop (consolidates all existing batch patterns)
	 * This method reuses the exact patterns from existing individual sync methods
	 *
	 * @param object $collection Collection instance
	 * @param int $batch_size Batch size for processing
	 * @param callable $data_retriever Function to retrieve data for each batch
	 * @param string $collection_type Collection type for adaptive limits (optional)
	 * @return array Sync statistics
	 */
	private function execute_batch_processing_loop( $collection, $batch_size, $data_retriever, $collection_type = 'default' ) {
		$page = 1;
		$imported_count = 0;
		$total_imports = 0;

		// Use adaptive safety counter based on collection type
		$max_iterations = $this->get_safety_limit( $collection_type );
		$iteration_count = 0;
		
		// Estimate total iterations for progress calculation
		$estimated_total = $this->estimate_total_iterations( $collection_type );

		do {
			$iteration_count++;
			if ( $iteration_count > $max_iterations ) {
				WP_CLI::warning( "Reached maximum iteration limit ({$max_iterations}) for {$collection_type} collection. Stopping to prevent infinite loop." );
				break;
			}

			// Retrieve data using the provided callback
			$data = $data_retriever( $page, $batch_size );
			if ( empty( $data ) ) {
				break;
			}

			// Enhanced memory optimization with threshold-based garbage collection
			if ( $iteration_count % 10 === 0 || $this->should_trigger_gc() ) {
				if ( function_exists( 'gc_collect_cycles' ) ) {
					gc_collect_cycles();
				}
				
				// Enhanced progress reporting with percentage
				if ( $estimated_total > 0 ) {
					$progress_pct = $this->calculate_progress_percentage( $iteration_count, $estimated_total );
					WP_CLI::line( "Progress: {$progress_pct}% - Memory: " . size_format( memory_get_usage( true ) ) );
				} else {
					WP_CLI::line( "Batch {$iteration_count} - Memory: " . size_format( memory_get_usage( true ) ) );
				}
			}

			$object_batch = $collection->prepare_batch_data( $data );
			if ( ! empty( $object_batch ) ) {
				$successful_imports = $collection->import_prepared_batch( $object_batch );
				$imported_count += count( $successful_imports );
				$total_imports += count( $object_batch );
			}

			WP_CLI::success( "Completed batch {$page}..." );
			$page++;

		} while ( true );

		$this->complete_collection_sync( $collection );

		return array(
			'imported_count' => $imported_count,
			'total_imports' => $total_imports
		);
	}
	
	/**
	 * Estimate total iterations for progress calculation
	 *
	 * @param string $collection_type Collection type
	 * @return int Estimated total iterations (0 if unknown)
	 */
	private function estimate_total_iterations( $collection_type ) {
		// This is a rough estimation based on collection type
		// In a real implementation, you might query the actual counts
		$estimates = array(
			'products' => 200,     // Estimate based on typical product catalogs
			'taxonomy' => 50,      // Estimate based on typical taxonomy terms
			'page_and_post' => 100, // Estimate based on typical page/post counts
			'navigation' => 10,    // Usually fewer navigation items
			'menu' => 5,           // Usually fewer menu items
			'site_info' => 1,      // Single batch operation
			'default' => 0         // Unknown, disable progress percentage
		);
		
		return isset( $estimates[$collection_type] ) ? $estimates[$collection_type] : $estimates['default'];
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

	/**
	 * Sync product variations to the current active sync collection
	 * This ensures variations are synced to the same collection as their parent products
	 *
	 * @param \BlazeWooless\Collections\Product $product_collection The product collection instance
	 */
	private function sync_variations_to_current_collection( $product_collection ) {
		// Validate input
		if ( ! $product_collection || ! is_object( $product_collection ) ) {
			WP_CLI::warning( 'Invalid product collection instance provided for variation sync.' );
			return;
		}

		// Check if WooCommerce is available
		if ( ! function_exists( 'wc_get_product' ) ) {
			WP_CLI::warning( 'WooCommerce is not available. Skipping variation sync.' );
			return;
		}

		$start_time = microtime( true );
		$page = 1;

		try {
			// Query for all variable products
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

			$query = new \WP_Query( $args );
			$variation_ids = array();

			// Check for WP_Query errors
			if ( is_wp_error( $query ) ) {
				WP_CLI::warning( 'Failed to query variable products: ' . $query->get_error_message() );
				return;
			}

			if ( $query->have_posts() ) {
				while ( $query->have_posts() ) {
					$query->the_post();
					$product_id = get_the_ID();

					// Validate product ID
					if ( ! $product_id ) {
						continue;
					}

					$product = wc_get_product( $product_id );

					if ( $product && $product->is_type( 'variable' ) ) {
						$children = $product->get_children();
						if ( is_array( $children ) && ! empty( $children ) ) {
							$variation_ids = array_merge( $variation_ids, $children );
						}
					}
				}

				if ( ! empty( $variation_ids ) ) {
					WP_CLI::line( "Found " . count( $variation_ids ) . " variations to sync..." );

					// Process variations in chunks, but sync to the current collection
					$chunks = array_chunk( $variation_ids, 50 );

					foreach ( $chunks as $chunk ) {
						if ( ! empty( $chunk ) ) {
							// Use the same collection instance to ensure variations go to the same collection
							$this->sync_variations_to_collection( $chunk, $product_collection );

							WP_CLI::success( "Completed variation batch {$page}..." );
							$page++;
						}
					}

					WP_CLI::success( "All " . count( $variation_ids ) . " product variations synced to the same collection." );

					// Display timing
					$end_time = microtime( true );
					$execution_time = $end_time - $start_time;
					$formatted_time = gmdate( "H:i:s", (int) $execution_time );
					WP_CLI::line( "Variation sync time: " . $formatted_time . " (hh:mm:ss)" );
				} else {
					WP_CLI::line( "No product variations found to sync." );
				}
			} else {
				WP_CLI::line( "No variable products found." );
			}

		} catch ( \Exception $e ) {
			WP_CLI::warning( 'Error during variation sync: ' . $e->getMessage() );
		} finally {
			// Always reset post data
			wp_reset_postdata();
		}
	}

	/**
	 * Sync variation IDs to a specific collection instance
	 * This is a modified version of the variation_update method that uses a specific collection
	 *
	 * @param array $variation_ids Array of variation IDs to sync
	 * @param \BlazeWooless\Collections\Product $product_collection The product collection instance to sync to
	 */
	private function sync_variations_to_collection( $variation_ids, $product_collection ) {
		// Validate inputs
		if ( ! is_array( $variation_ids ) || empty( $variation_ids ) ) {
			WP_CLI::warning( 'No variation IDs provided for sync.' );
			return;
		}

		if ( ! $product_collection || ! is_object( $product_collection ) ) {
			WP_CLI::warning( 'Invalid product collection instance provided.' );
			return;
		}

		$logger = null;
		$context = array( 'source' => 'wooless-variations-sync-to-collection' );

		try {
			$logger = wc_get_logger();
			$variations_data = array();
			$parent_product_ids = array();

			foreach ( $variation_ids as $variation_id ) {
				// Validate variation ID
				if ( ! is_numeric( $variation_id ) || $variation_id <= 0 ) {
					continue;
				}

				$wc_variation = wc_get_product( $variation_id );

				if ( $wc_variation && $wc_variation->is_type( 'variation' ) ) {
					try {
						$variation_data = $product_collection->generate_typesense_data( $wc_variation );
						if ( ! empty( $variation_data ) ) {
							$variations_data[] = $variation_data;

							// Collect parent product ID for revalidation
							$parent_id = $wc_variation->get_parent_id();
							if ( $parent_id && ! in_array( $parent_id, $parent_product_ids ) ) {
								$parent_product_ids[] = $parent_id;
							}
						}
					} catch ( \Exception $e ) {
						if ( $logger ) {
							$logger->debug( 'Failed to generate data for variation ' . $variation_id . ': ' . $e->getMessage(), $context );
						}
						WP_CLI::warning( 'Failed to process variation ' . $variation_id . ': ' . $e->getMessage() );
					}
				}
			}

			if ( ! empty( $variations_data ) ) {
				// Get sync collection and validate it exists
				$sync_collection = $product_collection->get_sync_collection();
				if ( ! $sync_collection ) {
					throw new \Exception( 'Unable to get sync collection for variations.' );
				}

				// Import to the current sync collection (same as parent products)
				$import = $sync_collection->documents->import( $variations_data, array(
					'action' => 'upsert'
				) );

				if ( $logger ) {
					$logger->debug( 'Synced ' . count( $variations_data ) . ' variations to collection: ' . print_r( $import, 1 ), $context );
				}

				// Schedule revalidation for parent products if class exists
				if ( class_exists( '\BlazeWooless\Revalidate' ) && ! empty( $parent_product_ids ) ) {
					try {
						$revalidate = \BlazeWooless\Revalidate::get_instance();
						foreach ( $parent_product_ids as $parent_id ) {
							if ( is_numeric( $parent_id ) && $parent_id > 0 ) {
								$revalidate->revalidate_product_page( $parent_id );
								if ( $logger ) {
									$logger->debug( "Scheduled revalidation for parent product ID: {$parent_id}", $context );
								}
							}
						}
					} catch ( \Exception $e ) {
						if ( $logger ) {
							$logger->debug( 'Failed to schedule revalidation: ' . $e->getMessage(), $context );
						}
						// Don't fail the entire sync for revalidation issues
						WP_CLI::warning( 'Failed to schedule revalidation for some parent products.' );
					}
				}
			} else {
				WP_CLI::line( 'No valid variation data to sync.' );
			}

		} catch ( \Exception $e ) {
			if ( ! $logger ) {
				$logger = wc_get_logger();
			}
			$error_context = array( 'source' => 'wooless-variations-sync-to-collection-error' );
			$logger->debug( 'Variation sync to collection failed: ' . $e->getMessage(), $error_context );
			WP_CLI::warning( 'Failed to sync some variations: ' . $e->getMessage() );
		}
	}
}