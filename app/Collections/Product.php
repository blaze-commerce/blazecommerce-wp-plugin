<?php

namespace BlazeWooless\Collections;

class Product extends BaseCollection {
	private static $instance = null;
	public $collection_name = 'product';

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function log_failed_product_import( $message ) {
		if ( is_array( $message ) ) {
			$message = json_encode( $message );
		}
		$logger  = wc_get_logger();
		$context = array( 'source' => 'wooless-failed-product-import' );
		$logger->debug( $message, $context );
	}

	public function initialize() {
		$logger  = wc_get_logger();
		$context = array( 'source' => 'wooless-product-collection-initialize' );
		try {
			$this->drop_collection();
		} catch (\Exception $e) {
		}

		try {

			$logger->debug( 'TS Product collection: ' . $this->collection_name(), $context );
			$this->create_collection(
				array(
					'name' => $this->collection_name(),
					'fields' => array(
						[ 'name' => 'id', 'type' => 'string', 'facet' => true ],
						[ 'name' => 'productId', 'type' => 'string', 'facet' => true ],
						[ 'name' => 'shortDescription', 'type' => 'string', 'optional' => true ],
						[ 'name' => 'description', 'type' => 'string' ],
						[ 'name' => 'name', 'type' => 'string', 'facet' => true, 'sort' => true ],
						[ 'name' => 'permalink', 'type' => 'string' ],
						[ 'name' => 'slug', 'type' => 'string', 'facet' => true ],
						[ 'name' => 'seoFullHead', 'type' => 'string', 'optional' => true ],
						[ 'name' => 'sku', 'type' => 'string' ],
						[ 'name' => 'price', 'type' => 'object', 'facet' => true ],
						[ 'name' => 'price.AUD', 'type' => 'float', 'optional' => true, 'facet' => true ],
						[ 'name' => 'price.NZD', 'type' => 'float', 'optional' => true, 'facet' => true ],
						[ 'name' => 'price.USD', 'type' => 'float', 'optional' => true, 'facet' => true ],
						[ 'name' => 'price.GBP', 'type' => 'float', 'optional' => true, 'facet' => true ],
						[ 'name' => 'price.CAD', 'type' => 'float', 'optional' => true, 'facet' => true ],
						[ 'name' => 'price.EUR', 'type' => 'float', 'optional' => true, 'facet' => true ],
						[ 'name' => 'regularPrice', 'type' => 'object' ],
						[ 'name' => 'regularPrice.AUD', 'type' => 'float', 'optional' => true ],
						[ 'name' => 'regularPrice.NZD', 'type' => 'float', 'optional' => true ],
						[ 'name' => 'regularPrice.USD', 'type' => 'float', 'optional' => true ],
						[ 'name' => 'regularPrice.GBP', 'type' => 'float', 'optional' => true ],
						[ 'name' => 'regularPrice.CAD', 'type' => 'float', 'optional' => true ],
						[ 'name' => 'regularPrice.EUR', 'type' => 'float', 'optional' => true ],
						[ 'name' => 'salePrice', 'type' => 'object' ],
						[ 'name' => 'salePrice.AUD', 'type' => 'float', 'optional' => true ],
						[ 'name' => 'salePrice.NZD', 'type' => 'float', 'optional' => true ],
						[ 'name' => 'salePrice.USD', 'type' => 'float', 'optional' => true ],
						[ 'name' => 'salePrice.GBP', 'type' => 'float', 'optional' => true ],
						[ 'name' => 'salePrice.CAD', 'type' => 'float', 'optional' => true ],
						[ 'name' => 'salePrice.EUR', 'type' => 'float', 'optional' => true ],
						[ 'name' => 'onSale', 'type' => 'bool', 'facet' => true ],
						[ 'name' => 'stockQuantity', 'type' => 'int64' ],
						[ 'name' => 'stockStatus', 'type' => 'string', 'sort' => true, 'facet' => true ],
						[ 'name' => 'backorder', 'type' => 'string', 'sort' => true, 'facet' => true ],
						[ 'name' => 'status', 'type' => 'string', 'sort' => true, 'facet' => true ],
						[ 'name' => 'shippingClass', 'type' => 'string' ],
						[ 'name' => 'updatedAt', 'type' => 'int64' ],
						[ 'name' => 'createdAt', 'type' => 'int64' ],
						[ 'name' => 'publishedAt', 'type' => 'int64', 'optional' => true, 'facet' => true ],
						[ 'name' => 'daysPassed', 'type' => 'int64', 'optional' => true, 'facet' => true ],
						[ 'name' => 'isFeatured', 'type' => 'bool', 'facet' => true ],
						[ 'name' => 'totalSales', 'type' => 'int64' ],
						[ 'name' => 'productType', 'type' => 'string', 'facet' => true ],
						[ 'name' => 'taxonomies', 'type' => 'object[]', 'facet' => true, 'optional' => true ],
						// Had to use string[] to type base on https://github.com/typesense/typesense/issues/227#issuecomment-1364072388 because ts is throwing errors after updgrade that the data is not an array
						[ 'name' => 'taxonomies.name', 'type' => 'string[]', 'facet' => true, 'optional' => true ],
						[ 'name' => 'taxonomies.url', 'type' => 'string[]', 'optional' => true ],
						[ 'name' => 'taxonomies.type', 'type' => 'string[]', 'facet' => true, 'optional' => true ],
						[ 'name' => 'taxonomies.slug', 'type' => 'string[]', 'facet' => true, 'optional' => true ],
						[ 'name' => 'taxonomies.nameAndType', 'type' => 'string[]', 'facet' => true, 'optional' => true ],
						[ 'name' => 'taxonomies.childAndParentTerm', 'type' => 'string[]', 'facet' => true, 'optional' => true ],
						[ 'name' => 'taxonomies.parentTerm', 'type' => 'string[]', 'optional' => true ],
						[ 'name' => 'taxonomies.breadcrumbs', 'type' => 'object[]', 'optional' => true ],
						[ 'name' => 'taxonomies.filters', 'type' => 'string[]', 'optional' => true, 'facet' => true ],
						[ 'name' => 'judgemeReviews', 'type' => 'object', 'optional' => true ],
						[ 'name' => 'judgemeReviews.id', 'type' => 'int64', 'optional' => true ],
						[ 'name' => 'judgemeReviews.externalId', 'type' => 'int64', 'optional' => true ],
						[ 'name' => 'judgemeReviews.average', 'type' => 'float', 'optional' => true ],
						[ 'name' => 'judgemeReviews.count', 'type' => 'int32', 'optional' => true ],
						[ 'name' => 'judgemeReviews.percentage', 'type' => 'object[]', 'optional' => true ],
						[ 'name' => 'yotpoReviews', 'type' => 'object', 'optional' => true ],
						[ 'name' => 'yotpoReviews.product_score', 'type' => 'float', 'optional' => true ],
						[ 'name' => 'yotpoReviews.total_reviews', 'type' => 'int64', 'optional' => true ],
						[ 'name' => 'thumbnail', 'type' => 'object' ],
						[ 'name' => 'thumbnail.altText', 'type' => 'string', 'optional' => true ],
						[ 'name' => 'thumbnail.id', 'type' => 'int64', 'optional' => true ],
						[ 'name' => 'menuOrder', 'type' => 'int64', 'optional' => true ],
						[ 'name' => 'thumbnail.src', 'type' => 'string', 'optional' => true ],
						[ 'name' => 'thumbnail.title', 'type' => 'string', 'optional' => true ],
						[ 'name' => 'crossSellData', 'type' => 'object[]', 'optional' => true ],
						[ 'name' => 'crossSellData.price', 'type' => 'object' ],
						[ 'name' => 'crossSellData.price.AUD', 'type' => 'float[]', 'optional' => true ],
						[ 'name' => 'crossSellData.price.NZD', 'type' => 'float[]', 'optional' => true ],
						[ 'name' => 'crossSellData.price.USD', 'type' => 'float[]', 'optional' => true ],
						[ 'name' => 'crossSellData.price.GBP', 'type' => 'float[]', 'optional' => true ],
						[ 'name' => 'crossSellData.price.CAD', 'type' => 'float[]', 'optional' => true ],
						[ 'name' => 'crossSellData.price.EUR', 'type' => 'float[]', 'optional' => true ],
						[ 'name' => 'crossSellData.regularPrice', 'type' => 'object' ],
						[ 'name' => 'crossSellData.regularPrice.AUD', 'type' => 'float[]', 'optional' => true ],
						[ 'name' => 'crossSellData.regularPrice.NZD', 'type' => 'float[]', 'optional' => true ],
						[ 'name' => 'crossSellData.regularPrice.USD', 'type' => 'float[]', 'optional' => true ],
						[ 'name' => 'crossSellData.regularPrice.GBP', 'type' => 'float[]', 'optional' => true ],
						[ 'name' => 'crossSellData.regularPrice.CAD', 'type' => 'float[]', 'optional' => true ],
						[ 'name' => 'crossSellData.regularPrice.EUR', 'type' => 'float[]', 'optional' => true ],
						[ 'name' => 'crossSellData.salePrice', 'type' => 'object', 'optional' => true ],
						[ 'name' => 'crossSellData.salePrice.AUD', 'type' => 'float[]', 'optional' => true ],
						[ 'name' => 'crossSellData.salePrice.NZD', 'type' => 'float[]', 'optional' => true ],
						[ 'name' => 'crossSellData.salePrice.USD', 'type' => 'float[]', 'optional' => true ],
						[ 'name' => 'crossSellData.salePrice.GBP', 'type' => 'float[]', 'optional' => true ],
						[ 'name' => 'crossSellData.salePrice.CAD', 'type' => 'float[]', 'optional' => true ],
						[ 'name' => 'crossSellData.salePrice.EUR', 'type' => 'float[]', 'optional' => true ],
						[ 'name' => 'metaData', 'type' => 'object', 'optional' => true ],
						[ 'name' => 'metaData.priceWithTax', 'type' => 'object', 'optional' => true ],
						[ 'name' => 'metaData.priceWithTax.AUD', 'type' => 'float', 'optional' => true ],
						[ 'name' => 'metaData.priceWithTax.NZD', 'type' => 'float', 'optional' => true ],
						[ 'name' => 'metaData.priceWithTax.USD', 'type' => 'float', 'optional' => true ],
						[ 'name' => 'metaData.priceWithTax.GBP', 'type' => 'float', 'optional' => true ],
						[ 'name' => 'metaData.priceWithTax.CAD', 'type' => 'float', 'optional' => true ],
						[ 'name' => 'metaData.priceWithTax.EUR', 'type' => 'float', 'optional' => true ],
						[ 'name' => 'metaData.productLabel', 'type' => 'string', 'optional' => true ],
					),
					'default_sorting_field' => 'updatedAt',
					'enable_nested_fields' => true
				)
			);
		} catch (\Exception $e) {
			$logger->debug( 'TS Product collection intialize Exception: ' . $e->getMessage(), $context );
		}
	}

	public function index_to_typesense() {
		//Product indexing
		$logger  = wc_get_logger();
		$context = array( 'source' => 'wooless-product-import' );

		$this->log_failed_product_import( "============================ START OF PRODUCT IMPORT ============================" );

		try {
			// Query judge.me product external_ids and update to options	
			do_action( 'blaze_wooless_generate_product_reviews_data' );
			$page = $_REQUEST['page'] ?? 1;

			if ( $page == 1 ) {
				$this->initialize();
			}

			$batch_size              = 250; // Adjust the batch size depending on your server's capacity
			$imported_products_count = 0;
			$total_imports           = 0;
			$query_args              = array( 'status' => 'publish', 'limit' => $batch_size, 'page' => $page );

			$products = \wc_get_products( $query_args );

			$products_batch = array();

			// Prepare products for indexing in Typesense
			foreach ( $products as $product ) {

				$product_data = array();

				// Get the product data
				if ( ! empty( $product ) ) {
					$product_data = $this->generate_typesense_data( $product );
				}

				if ( empty( $product_data ) ) {
					error_log( "Skipping product ID: " . $product->get_id() );
					continue; // Skip this product if no product data is found
				}

				$products_batch[] = $product_data;

				// Free memory
				unset( $product_data );
			}

			// Log the number of products in the batch
			error_log( "Batch size: " . count( $products_batch ) );

			// Import products to Typesense
			try {
				$result = $this->import( $products_batch );
				// echo "<pre>"; print_r($result); echo "</pre>";
				$successful_imports = array_filter( $result, function ($batch_result) {
					$successful_import = isset ( $batch_result['success'] ) && $batch_result['success'] == true;
					if ( ! $successful_import ) {
						$this->log_failed_product_import( $batch_result );
					}
					return $successful_import;
				} );
				$logger->debug( 'TS Product Import result: ' . print_r( $result, 1 ), $context );
				$imported_products_count += count( $successful_imports ); // Increment the count of imported products
				$total_imports += count( $products_batch ); // Increment the count of imported products
			} catch (\Exception $e) {
				$logger->debug( 'TS Product Import Exception: ' . $e->getMessage(), $context );
				error_log( "Error importing products to Typesense: " . $e->getMessage() );
			}

			// unset($products, $products_batch, $result, $successful_imports);

			// After the while loop, print the number of imported products
			// echo "Imported products count: " . $imported_products_count ."/" . $total_imports . "\n";

			$next_page          = $page + 1;
			$query_args['page'] = $next_page;
			$has_next_data      = ! empty( \wc_get_products( $query_args ) );
			echo json_encode( array(
				'imported_products_count' => count( $successful_imports ),
				'total_imports' => $total_imports,
				'has_next_data' => $has_next_data,
				'next_page' => $has_next_data ? $next_page : null,
			) );
			$this->log_failed_product_import( "============================ END OF PRODUCT IMPORT ============================" );

			wp_die();
		} catch (\Exception $e) {
			$logger->debug( 'TS Batch Exception: ' . $e->getMessage(), $context );
			$error_message = "Error: " . $e->getMessage();
			echo $error_message; // Print the error message for debugging purposes
			echo "<script>
			console.log('Error block executed'); // Log a message to the browser console
			document.getElementById('error_message').innerHTML = '$error_message';
		</script>";
			echo "Error creating collection: " . $e->getMessage() . "\n";
		}
	}

	public function generate_typesense_data( $product ) {
		// Format product data for indexing
		$product_data = array();
		$product_id   = '';

		if ( ! empty( $product ) ) {
			$product_id       = $product->get_id();
			$shortDescription = $product->get_short_description();
			$description      = $product->get_description();
			$attachment_ids   = $product->get_gallery_image_ids();
			$product_gallery  = array_map( function ($attachment_id) {
				$attachment         = get_post( $attachment_id );
				$thumbnail_alt_text = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
				$thumbnail_src = wp_get_attachment_url( $attachment_id );

				return [ 
					'id' => $attachment_id,
					'title' => $attachment->post_title,
					'altText' => $thumbnail_alt_text ? $thumbnail_alt_text : $attachment->post_title,
					'src' => $thumbnail_src ? $thumbnail_src : '',
				];
			}, $attachment_ids );

			// // Get the thumbnail
			$thumbnail_id       = get_post_thumbnail_id( $product_id );
			$attachment         = get_post( $thumbnail_id );
			$thumbnail_alt_text = get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true );
			$thumbnail_src      = get_the_post_thumbnail_url( $product_id );

			$thumbnail = [ 
				'id' => $thumbnail_id,
				'title' => $attachment->post_title,
				'altText' => $thumbnail_alt_text ? $thumbnail_alt_text : $attachment->post_title,
				'src' => $thumbnail_src ? $thumbnail_src : '',
			];

			$stockQuantity = $product->get_stock_quantity();

			$product_type = $product->get_type();

			$currency = get_option( 'woocommerce_currency' );

			$default_price         = [ 
				$currency => floatval( $product->get_price() )
			];
			$default_regular_price = [ 
				$currency => floatval( $product->get_regular_price() )
			];
			$default_sale_price    = [ 
				$currency => floatval( $product->get_sale_price() )
			];

			// Get variations if the product is a variable product
			$variations_data = $default_attributes = [];
			if ( $product_type === 'variable' || $product_type === 'pw-gift-card' ) {
				$variations = $product->get_available_variations();
				foreach ( $variations as $variation ) {
					$variation_obj = wc_get_product( $variation['variation_id'] );
					if ( ! $variation_obj ) {
						continue;
					}

					$variant_thumbnail_id       = get_post_thumbnail_id( $variation['variation_id'] );
					$variant_attachment         = get_post( $variant_thumbnail_id );
					$variant_thumbnail_alt_text = get_post_meta( $variant_thumbnail_id, '_wp_attachment_image_alt', true );
					$variant_thumbnail_src      = get_the_post_thumbnail_url( $variation['variation_id'] );

					$variations_items = [ 
						'variationId' => $variation['variation_id'],
						'attributes' => $variation['attributes'],
						'price' => array(
							$currency => floatval( $variation_obj->get_price() ),
						),
						'regularPrice' => array(
							$currency => floatval( $variation_obj->get_regular_price() ),
						),
						'salePrice' => array(
							$currency => floatval( $variation_obj->get_sale_price() ),
						),
						'stockQuantity' => empty( $variation_obj->get_stock_quantity() ) ? 0 : $variation_obj->get_stock_quantity(),
						'stockStatus' => $variation_obj->get_stock_status(),
						'backorder' => $variation_obj->get_backorders(),
						'onSale' => $variation_obj->is_on_sale(),
						'sku' => $variation_obj->get_sku(),
						'image' => [ 
							'id' => $variant_thumbnail_id,
							'title' => $variant_attachment->post_title,
							'altText' => $variant_thumbnail_id ? $variant_thumbnail_id : $attachment->post_title,
							'src' => $variant_thumbnail_src ? $variant_thumbnail_src : '',
						],
						'metaData' => array(),
					];

					$variations_data[] = apply_filters( 'blaze_commerce_variation_data', $variations_items, $variation['variation_id'], $variation_obj );

					unset( $variations_items, $variation_obj, $variant_thumbnail_id, $variant_attachment, $variant_thumbnail_alt_text, $variant_thumbnail_src );
				}

				unset( $variations );
			}

			$cross_sell_ids  = $product->get_cross_sell_ids();
			$cross_sell_data = [];
			if ( ! empty( $cross_sell_ids ) ) {
				$cross_sell_data = $this->get_cross_sell_products( $cross_sell_ids );
			}

			$upsell_ids  = $product->get_upsell_ids();
			$upsell_data = array();
			if ( ! empty( $upsell_ids ) ) {
				foreach ( $upsell_ids as $upsell_id ) {
					$upsell_product = wc_get_product( $upsell_id );
					if ( $upsell_product ) {
						$upsell_data[] = array(
							'id' => $upsell_product->get_id(),
							'name' => $upsell_product->get_name(),
						);
					}

					unset( $upsell_product );
				}
			}

			// Get the additional product tabs
			$additional_tabs           = get_post_meta( $product_id, '_additional_tabs', true );
			$formatted_additional_tabs = array();

			if ( ! empty( $additional_tabs ) ) {
				foreach ( $additional_tabs as $tab ) {
					$formatted_additional_tabs[] = array(
						'title' => $tab['tab_title'],
						'content' => $tab['tab_content'],
					);
				}
			}

			unset( $additional_tabs );

			$taxonomies = $this->get_taxonomies( $product );

			$related_products = $this->get_related_products( $product_id, $taxonomies );

			$product_slug = $product->get_slug();

			$published_at = strtotime( get_the_date( '', $product->get_id() ) );

			$days_passed = $this->get_days_passed( $published_at );

			$product_data = [ 
				'id' => strval( $product->get_id() ),
				'productId' => strval( $product->get_id() ),
				'shortDescription' => wpautop( $shortDescription ),
				'description' => wpautop( $description ),
				'name' => $product->get_name(),
				'permalink' => wp_make_link_relative( get_permalink( $product->get_id() ) ),
				'slug' => $product->get_slug(),
				'thumbnail' => $thumbnail,
				'sku' => $product->get_sku(),
				'price' => apply_filters( 'wooless_product_price', $default_price, $product_id ),
				'regularPrice' => apply_filters( 'wooless_product_regular_price', $default_regular_price, $product_id ),
				'salePrice' => apply_filters( 'wooless_product_sale_price', $default_sale_price, $product_id ),
				'onSale' => $product->is_on_sale(),
				'stockQuantity' => empty( $stockQuantity ) ? 0 : $stockQuantity,
				'stockStatus' => $product->get_stock_status(),
				'backorder' => $product->get_backorders(),
				'shippingClass' => $product->get_shipping_class(),
				'updatedAt' => strtotime( $product->get_date_modified() ),
				'createdAt' => strtotime( $product->get_date_created() ),
				'publishedAt' => $published_at,
				'daysPassed' => $days_passed,
				'isFeatured' => $product->get_featured(),
				'totalSales' => (int) $product->get_total_sales(),
				'galleryImages' => $product_gallery,
				'taxonomies' => $taxonomies,
				'productType' => $product_type,
				// Add product type
				'variations' => $variations_data,
				// Add variations data
				'crossSellData' => empty( $cross_sell_data ) ? $related_products : $cross_sell_data,
				'upsellData' => $upsell_data,
				'additionalTabs' => apply_filters( 'wooless_product_tabs', $formatted_additional_tabs, $product_id, $product ),
				'status' => $product->get_status(),
				'menuOrder' => $product->get_menu_order(),
				'metaData' => array(),
			];

			unset( $shortDescription, $description, $attachment_ids, $product_gallery, $thumbnail, $thumbnail_id, $attachment, $thumbnail_alt_text, $thumbnail_src, $stockQuantity, $product_type, $currency, $default_price, $default_regular_price, $default_sale_price, $cross_sell_ids, $upsell_ids, $additional_tabs, $taxonomies, $related_products, $cross_sell_data, $variations_data, $formatted_additional_tabs, $upsell_data, $published_at );
		}

		return apply_filters( 'blaze_wooless_product_data_for_typesense', $product_data, $product_id, $product );
	}

	public function get_taxonomies( $product ) {
		$taxonomies_data = [];
		$taxonomies      = get_object_taxonomies( 'product' );

		foreach ( $taxonomies as $taxonomy ) {
			// Exclude taxonomies based on their names
			if ( preg_match( '/^(ef_|elementor|pa_|nav_|ml-|ufaq|translation_priority|wpcode_)/', $taxonomy ) ) {
				continue;
			}

			$product_terms = get_the_terms( $product->get_id(), $taxonomy );

			if ( ! empty( $product_terms ) && ! is_wp_error( $product_terms ) ) {
				foreach ( $product_terms as $product_term ) {

					$term_name = $product_term->name;
					$term_slug = $product_term->slug;
					// Get Parent Term
					$parentTerm       = get_term( $product_term->parent, $taxonomy );
					$term_parent      = isset( $parentTerm->name ) ? $parentTerm->name : '';
					$termOrder        = is_plugin_active( 'taxonomy-terms-order/taxonomy-terms-order.php' ) ? $product_term->term_order : 0;
					$term_permalink   = wp_make_link_relative( get_term_link( $product_term->term_id ) );
					$term_parent_slug = $parentTerm->slug;

					// Get the thumbnail
					$term_thumbnail_id = get_term_meta( $product_term->term_id, 'thumbnail_id', true );
					$term_attachment   = get_post( $term_thumbnail_id );

					$term_thumbnail = [ 
						'id' => $term_thumbnail_id,
						'title' => $term_attachment->post_title,
						'altText' => get_post_meta( $term_thumbnail_id, '_wp_attachment_image_alt', true ),
						'src' => wp_get_attachment_url( $term_thumbnail_id ),
					];

					$taxonomies_data[] = [ 
						'name' => $term_name,
						'url' => get_term_link( $product_term->term_id ),
						'type' => $taxonomy,
						'slug' => $term_slug,
						'nameAndType' => $product_term->name . '|' . $taxonomy,
						'childAndParentTerm' => $term_parent ? $product_term->name . '|' . $term_parent : '',
						'parentTerm' => $term_parent,
						'breadcrumbs' => apply_filters( 'blaze_wooless_generate_breadcrumbs', $product_term->term_id, $taxonomy ),
						// Search Parameter Filter Values
						'filters' => $term_name . '|' . $taxonomy . '|' . $term_slug . '|' . $term_parent . '|' . $termOrder . '|' . $term_permalink . '|' . $term_parent_slug . '|' . $term_thumbnail['src'],
					];

					unset( $parentTerm, $term_name, $term_slug, $term_parent, $termOrder );
				}

				unset( $product_terms );
			}
		}

		unset( $taxonomies );

		return $taxonomies_data;
	}

	public function get_related_products( $product_id, $taxonomies ) {
		$category = array();
		foreach ( $taxonomies as $taxonomy ) {
			if ( $taxonomy['type'] == 'product_cat' ) {
				$category[] = $taxonomy['name'];
			}
		}

		unset( $taxonomies );

		// Get products that aren't the current product.
		$args        = array(
			'exclude' => array( $product_id ),
			'limit' => 10,
			'page' => 1,
			'status' => 'publish',
			'return' => 'ids',
			'category' => $category,
			'stock_status' => 'instock',
		);
		$product_ids = wc_get_products( $args );

		return $this->get_cross_sell_products( $product_ids );
	}

	public function get_cross_sell_products( $product_ids ) {
		$product_data            = array();
		$cross_sell_product_data = array();

		if ( ! empty( $product_ids ) ) {
			foreach ( $product_ids as $product_id ) {
				if ( $product_id ) {
					$product = wc_get_product( $product_id );

					if ( $product ) {
						$attachment_ids  = $product->get_gallery_image_ids();
						$product_gallery = array_map( function ($attachment_id) {
							$attachment         = get_post( $attachment_id );
							$thumbnail_alt_text = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
							$thumbnail_src = wp_get_attachment_url( $attachment_id );

							return [ 
								'id' => $attachment_id,
								'title' => $attachment->post_title,
								'altText' => $thumbnail_alt_text ? $thumbnail_alt_text : $attachment->post_title,
								'src' => $thumbnail_src ? $thumbnail_src : '',
							];
						}, $attachment_ids );

						// Get the thumbnail
						$thumbnail_id       = get_post_thumbnail_id( $product_id );
						$attachment         = get_post( $thumbnail_id );
						$thumbnail_alt_text = get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true );
						$thumbnail_src      = get_the_post_thumbnail_url( $product_id );
						$currency           = get_option( 'woocommerce_currency' );

						$product_type = $product->get_type();
						// Get variations if the product is a variable product
						$variations_data = $default_attributes = [];
						if ( $product_type === 'variable' || $product_type === 'pw-gift-card' ) {
							$variations = $product->get_available_variations();
							foreach ( $variations as $variation ) {
								$variation_obj = wc_get_product( $variation['variation_id'] );
								if ( ! $variation_obj ) {
									continue;
								}

								$variant_thumbnail_id       = get_post_thumbnail_id( $variation['variation_id'] );
								$variant_attachment         = get_post( $variant_thumbnail_id );
								$variant_thumbnail_alt_text = get_post_meta( $variant_thumbnail_id, '_wp_attachment_image_alt', true );
								$variant_thumbnail_src      = get_the_post_thumbnail_url( $variation['variation_id'] );

								$variations_items = [ 
									'variationId' => $variation['variation_id'],
									'attributes' => $variation['attributes'],
									'price' => array(
										$currency => floatval( $variation_obj->get_price() ),
									),
									'regularPrice' => array(
										$currency => floatval( $variation_obj->get_regular_price() ),
									),
									'salePrice' => array(
										$currency => floatval( $variation_obj->get_sale_price() ),
									),
									'stockQuantity' => empty( $variation_obj->get_stock_quantity() ) ? 0 : $variation_obj->get_stock_quantity(),
									'stockStatus' => $variation_obj->get_stock_status(),
									'backorder' => $variation_obj->get_backorders(),
									'onSale' => $variation_obj->is_on_sale(),
									'sku' => $variation_obj->get_sku(),
									'image' => [ 
										'id' => $variant_thumbnail_id,
										'title' => $variant_attachment->post_title,
										'altText' => $variant_thumbnail_id ? $variant_thumbnail_id : $attachment->post_title,
										'src' => $variant_thumbnail_src ? $variant_thumbnail_src : '',
									],
								];

								$variations_data[] = apply_filters( 'blaze_commerce_variation_data', $variations_items, $variation['variation_id'], $variation_obj );

								unset( $variations_items, $variation_obj, $variant_thumbnail_id, $variant_attachment, $variant_thumbnail_alt_text, $variant_thumbnail_src );
							}

							unset( $variations );
						}

						$thumbnail = [ 
							'id' => $thumbnail_id,
							'title' => $attachment->post_title,
							'altText' => $thumbnail_alt_text ? $thumbnail_alt_text : $attachment->post_title,
							'src' => $thumbnail_src ? $thumbnail_src : '',
						];

						$currency = get_option( 'woocommerce_currency' );

						$default_price         = [ 
							$currency => floatval( $product->get_price() )
						];
						$default_regular_price = [ 
							$currency => floatval( $product->get_regular_price() )
						];
						$default_sale_price    = [ 
							$currency => floatval( $product->get_sale_price() )
						];

						$stockQuantity = $product->get_stock_quantity();

						$published_at = strtotime( get_the_date( '', $product_id ) );

						$product_data = array(
							'id' => $product->get_id(),
							'name' => $product->get_name(),
							'permalink' => wp_make_link_relative( get_permalink( $product->get_id() ) ),
							'slug' => $product->get_slug(),
							'thumbnail' => $thumbnail,
							'price' => floatval( apply_filters( 'wooless_product_price', $default_price, $product_id ) ),
							'regularPrice' => floatval( apply_filters( 'wooless_product_regular_price', $default_regular_price, $product_id ) ),
							'salePrice' => floatval( apply_filters( 'wooless_product_sale_price', $default_sale_price, $product_id ) ),
							'onSale' => $product->is_on_sale(),
							'stockStatus' => $product->get_stock_status(),
							'backorder' => $product->get_backorders(),
							'createdAt' => strtotime( $product->get_date_created() ),
							'publishedAt' => $published_at,
							'daysPassed' => $this->get_days_passed( $published_at ),
							'galleryImages' => $product_gallery,
							'productType' => $product->get_type(),
							'stockQuantity' => empty( $stockQuantity ) ? 0 : $stockQuantity,
							'variations' => $variations_data,
						);

						$cross_sell_product_data[] = apply_filters( 'blaze_wooless_cross_sell_data_for_typesense', $product_data, $product_id, $product );

						unset( $product_data, $product, $attachment_ids, $product_gallery, $thumbnail_id, $attachment, $thumbnail_alt_text, $thumbnail_src, $currency, $default_price, $default_regular_price, $default_sale_price, $stockQuantity, $published_at );
					}
				}
			}
		}

		return $cross_sell_product_data;
	}

	public function get_days_passed( $date ) {
		$current_date = strtotime( date( 'Y-m-d H:i:s' ) );
		$diff         = $current_date - $date;
		$days         = floor( $diff / ( 60 * 60 * 24 ) );

		return $days;
	}
}
