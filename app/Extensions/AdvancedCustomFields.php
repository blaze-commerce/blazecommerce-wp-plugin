<?php

namespace BlazeWooless\Extensions;

use BlazeWooless\Collections\Taxonomy;

class AdvancedCustomFields {
	private static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		if ( function_exists( 'is_plugin_active' )
			&& ( is_plugin_active( 'advanced-custom-fields/acf.php' ) || is_plugin_active( 'advanced-custom-fields-pro/acf.php' ) ) ) {
			add_filter( 'blaze_wooless_product_for_typesense_fields', array( $this, 'set_fields' ), 10, 1 );
			add_filter( 'blaze_wooless_product_data_for_typesense', array( $this, 'sync_product_data' ), 99, 3 );
			// add_action( 'admin_init', array( $this, 'test' ) );

			add_filter( 'blazecommerce/collection/taxonomy/typesense_fields', array( $this, 'set_fields' ), 10, 1 );
			add_filter( 'blazecommerce/collection/taxonomy/typesense_data', array( $this, 'taxonomy_data' ), 10, 2 );

			add_action( 'acf/save_post', array( $this, 'acf_save_data' ) );
		}
	}

	public function set_fields( $fields ) {
		$fields[] = [ 'name' => 'metaData.acf', 'type' => 'object[]', 'optional' => true ];

		return $fields;
	}

	public function sync_product_data( $product_data, $product_id, $product ) {

		if ( ! function_exists( 'acf_get_field_groups' ) ) {
			return $product_data;
		}

		$field_groups = acf_get_field_groups( [ 
			'post_id' => $product_id
		] );

		$fields = [];

		foreach ( $field_groups as $field_group ) {
			$fields = array_merge( $fields, acf_get_fields( $field_group['key'] ) );
		}

		$field_values = [];
		foreach ( $fields as $field ) {
			$field_values[ $field['name'] ] = get_field( $field['name'], $product_id );
		}

		$product_data['metaData']['acf'] = $field_values;

		return $product_data;
	}

	public function taxonomy_data( $document, $term ) {
		if ( ! function_exists( 'acf_get_field_groups' ) ) {
			return $document;
		}

		$field_values = [];
		$field_groups = acf_get_field_groups( array( 'taxonomy' => $term->taxonomy ) );
		foreach ( $field_groups as $field_group ) {
			$fields = acf_get_fields( $field_group['key'] );

			if ( $fields ) {
				foreach ( $fields as $field ) {
					$field_values[ $field['name'] ] = get_field( $field['name'], "{$term->taxonomy}_{$term->term_id}" );
				}
			}
		}

		$document['metaData']['acf'] = $field_values;

		return $document;
	}

	public function acf_save_data( $post_id ) {

		if ( strpos( $post_id, 'term_' ) === 0 ) {
			$term_id = (int) str_replace( 'term_', '', $post_id ); // Extract term ID
			$term    = get_term( $term_id );

			$import_logger  = wc_get_logger();
			$import_context = array( 'source' => 'acf_save_data' );

			if ( $term && ! is_wp_error( $term ) ) {
				// Prepare the data to be updated
				$collection = Taxonomy::get_instance();
				$document   = $collection->generate_typesense_data( $term );
				// Update the term data in Typesense
				try {
					$import_logger->debug( 'document ' . $term_id . ' => ' . print_r( $document, 1 ), $import_context );

					$collection->upsert( $document );
					do_action( 'blaze_wooless_after_term_update', $document );
				} catch (\Exception $e) {
					$import_logger->debug( 'Error ' . $e->getMessage(), $import_context );
				}
			}
		}

	}
}