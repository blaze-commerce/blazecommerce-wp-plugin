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
		if ( function_exists( 'is_plugin_active' ) && (
			is_plugin_active( 'advanced-custom-fields/acf.php' ) ||
			is_plugin_active( 'advanced-custom-fields-pro/acf.php' ) ||
			is_plugin_active( 'secure-custom-fields/secure-custom-fields.php' )
		)
		) {
			add_filter( 'blaze_wooless_product_for_typesense_fields', array( $this, 'set_fields' ), 10, 1 );
			add_filter( 'blaze_wooless_product_data_for_typesense', array( $this, 'sync_product_data' ), 99, 3 );

			add_filter( 'blazecommerce/collection/page/typesense_fields', array( $this, 'set_page_fields' ), 10, 1 );
			add_filter( 'blazecommerce/collection/page/typesense_data', array( $this, 'set_page_data' ), 10, 2 );

			add_filter( 'blazecommerce/settings', array( $this, 'sync_setting_data' ), 10, 1 );
		}
	}

	public function set_fields( $fields ) {
		$fields[] = [ 'name' => 'metaData.acf', 'type' => 'object[]', 'optional' => true ];
		$fields[] = [ 'name' => 'metaData.acf.field_name', 'type' => 'string', 'optional' => true ];
		$fields[] = [ 'name' => 'metaData.acf.field_value', 'type' => 'string', 'optional' => true ];

		return $fields;
	}

	public function set_page_fields( $fields ) {
		$fields[] = [ 'name' => 'metaData.acf', 'type' => 'auto', 'optional' => true ];

		return $fields;
	}

	public function get_acf_fields_values( $object_id ) {
		if ( ! function_exists( 'acf_get_field_groups' ) ) {
			return null;
		}
		$field_groups = acf_get_field_groups( [ 
			'post_id' => $object_id
		] );

		$fields = [];

		foreach ( $field_groups as $field_group ) {
			$fields = array_merge( $fields, acf_get_fields( $field_group['key'] ) );
		}

		$field_values = [];
		foreach ( $fields as $field ) {
			$field_values[ $field['name'] ] = get_field( $field['name'], $object_id );
		}


		return $field_values;
	}

	public function sync_product_data( $product_data, $product_id, $product ) {

		if ( ! function_exists( 'acf_get_field_groups' ) ) {
			return $product_data;
		}
		$product_data['metaData']['acf'] = $this->get_acf_fields_values( $product_id );

		return $product_data;
	}

	public function set_page_data( $document, $page ) {
		if ( ! function_exists( 'acf_get_field_groups' ) ) {
			return $document;
		}
		$document['metaData']['acf'] = $this->get_acf_fields_values( $page->ID );

		return $document;
	}

	/**
	 * Sync ACF options page data to Typesense
	 * @param array $document
	 * @return array
	 */
	public function sync_setting_data( $document ) {
		if ( function_exists( 'get_field' ) ) {

			$theme_options = array(
				'product_tabs' => get_field( 'product_tabs', 'option' ),
				'shipping_informations' => get_field( 'shipping_informations', 'option' )
			);

			$document[] = array(
				'name' => 'acf_options',
				'value' => (string) json_encode( $theme_options ),
				'updated_at' => time(),
			);

		}
		return $document;
	}
}