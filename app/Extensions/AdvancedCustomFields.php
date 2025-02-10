<?php

namespace BlazeWooless\Extensions;

class AdvancedCustomFields {
	private static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		if ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'advanced-custom-fields/acf.php' ) ) {
			add_filter( 'blaze_wooless_product_for_typesense_fields', array( $this, 'set_fields' ), 10, 1 );
			add_filter( 'blaze_wooless_product_data_for_typesense', array( $this, 'sync_product_data' ), 99, 3 );
			add_action( 'admin_init', array( $this, 'test' ) );
		}
	}

	public function set_fields( $fields ) {
		$fields[] = [ 'name' => 'metaData.acf', 'type' => 'object[]', 'optional' => true ];
		$fields[] = [ 'name' => 'metaData.acf.field_name', 'type' => 'string[]', 'optional' => true ];
		$fields[] = [ 'name' => 'metaData.acf.field_value', 'type' => 'auto', 'optional' => true ];

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

}