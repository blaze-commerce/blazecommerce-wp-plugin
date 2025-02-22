<?php

namespace BlazeWooless\Extensions;

class WoocommerceVariationSwatches {
	private static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		if ( $this->is_active() ) {
			add_filter( 'blaze_wooless_product_attribute_for_typesense', array( $this, 'add_swatches_data' ), 10, 2 );

			add_filter( 'blaze_commerce_taxonomy_fields', array( $this, 'add_taxonomy_fields' ) );
			add_filter( 'blaze_commerce_taxonomy_data', array( $this, 'add_taxonomy_fields_data' ), 10, 2 );
			add_filter( 'blaze_wooless_product_taxonomy_item', array( $this, 'modify_product_taxonomy_item' ), 10, 2 );
		}
	}

	public function get_raw_attribute( $taxonomy_slug ) {

		if ( 'pa_' !== substr( $taxonomy_slug, 0, 3 ) ) {
			return false;
		}
		$transient_key      = 'wooless_attribute_' . $taxonomy_slug;
		$attribute_name     = str_replace( 'pa_', '', wc_sanitize_taxonomy_name( $taxonomy_slug ) );
		$attribute_taxonomy = get_transient( $transient_key );
		if ( false === $attribute_taxonomy ) {

			global $wpdb;

			$attribute_taxonomy = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_name = %s", esc_sql( $attribute_name ) ) );

			set_transient( $transient_key, $attribute_taxonomy );
		}

		return $attribute_taxonomy;

	}
	public function is_active() {
		return function_exists( 'is_plugin_active' ) && is_plugin_active( 'woo-variation-swatches/woo-variation-swatches.php' );
	}

	public function add_taxonomy_fields( $fields ) {
		return array_merge_recursive( $fields, array(
			array( 'name' => 'componentType', 'type' => 'string', 'facet' => true, 'optional' => true ),
			array( 'name' => 'componentValue', 'type' => 'string', 'facet' => true, 'optional' => true ),
		) );
	}

	public function modify_product_taxonomy_item( $term_data, $term ) {
		$term_data = $this->add_taxonomy_fields_data( $term_data, $term );

		if ( ! empty( $term_data['filters'] ) && ! empty( $term_data['componentType'] ) ) {
			$term_data['filters'] = $term_data['filters'] . '|' . $term_data['componentType'] . '|' . $term_data['componentValue'];
		}

		return $term_data;
	}
	public function add_taxonomy_fields_data( $document, $term ) {
		$attribute_taxonomy = $this->get_raw_attribute( $term->taxonomy );
		if ( ! empty( $attribute_taxonomy->attribute_type ) ) {
			$document['componentType']  = $attribute_taxonomy->attribute_type;
			$document['componentValue'] = $this->get_option_value( $attribute_taxonomy->attribute_type, $term->term_id, (array) $term, );

		}
		return $document;
	}

	public function add_swatches_data( $attribute_to_register, $attribute ) {
		// Set default attribute type to select
		$attribute_to_register['type'] = 'select';
		if ( $attribute->is_taxonomy() ) {
			$taxonomy_id      = $attribute->get_id();
			$swatch_attribute = woo_variation_swatches()->get_frontend()->get_attribute_taxonomy_by_id( $taxonomy_id );
			if ( $swatch_attribute->attribute_type ) {
				// Set type depending on what is selected for the woocommerce attribute in wp admin
				$attribute_to_register['type'] = $swatch_attribute->attribute_type;
				$attribute_to_register         = $this->get_options_value( $attribute_to_register, $attribute );
			}

		}

		return $attribute_to_register;
	}

	public function get_options_value( $attribute_to_register, $attribute ) {
		$type = $attribute_to_register['type'];
		foreach ( $attribute_to_register['options'] as $key => $option ) {
			$attribute_to_register['options'][ $key ]['value'] = $this->get_option_value( $type, $option['term_id'], $option );
		}
		return $attribute_to_register;
	}

	public function get_option_value( $type, $term_id, $option ) {
		// default value will be the option name 
		$value = isset( $option['label'] ) ? $option['label'] : '';
		if ( ! empty( $term_id ) ) {
			switch ( $type ) {
				case "color":
					$value = $this->get_color_hex( $term_id );
					break;
				case "image":
					//TODO supply correct value
					$value = $option['label'];
					break;
				case "button":
					//TODO supply correct value
					$value = $option['label'];
					break;
				case "radio":
					//TODO supply correct value
					$value = $option['label'];
					break;
				default:
					$value = isset( $option['label'] ) ? $option['label'] : '';
			}
		}

		return $value;
	}

	public function get_color_hex( $term_id ) {
		$swatch_frontend = woo_variation_swatches()->get_frontend();
		$value           = sanitize_hex_color( $swatch_frontend->get_product_attribute_color( $term_id ) );

		return $value;
	}

	public function get_image_src( $term ) {

	}
}


