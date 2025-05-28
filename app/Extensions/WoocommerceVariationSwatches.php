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
		// Only register these filters if the plugin is active
		if ( $this->is_active() ) {
			add_filter( 'blaze_commerce_taxonomy_fields', array( $this, 'add_taxonomy_fields' ) );
			add_filter( 'blaze_commerce_taxonomy_data', array( $this, 'add_taxonomy_fields_data' ), 10, 2 );
			add_filter( 'blaze_wooless_product_taxonomy_item', array( $this, 'modify_product_taxonomy_item' ), 10, 2 );
			add_filter( 'blaze_wooless_product_attribute_for_typesense', array( $this, 'add_swatches_data' ), 10, 2 );
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
			$taxonomy_id = $attribute->get_id();
			$attribute_name = $attribute->get_name();

			// Try to get attribute type from WooCommerce Variation Swatches plugin first
			if ( function_exists( 'woo_variation_swatches' ) ) {
				$swatch_attribute = woo_variation_swatches()->get_frontend()->get_attribute_taxonomy_by_id( $taxonomy_id );
				if ( $swatch_attribute->attribute_type ) {
					// Set type depending on what is selected for the woocommerce attribute in wp admin
					$attribute_to_register['type'] = $swatch_attribute->attribute_type;
					$attribute_to_register = $this->get_options_value( $attribute_to_register, $attribute );
				}
			} else {
				// Fallback: detect color attributes by name when plugin is not active
				if ( $this->is_color_attribute( $attribute_name ) ) {
					$attribute_to_register['type'] = 'color';
					$attribute_to_register = $this->get_options_value( $attribute_to_register, $attribute );
				}
			}
		}

		return $attribute_to_register;
	}

	public function get_options_value( $attribute_to_register, $attribute ) {
		$type = $attribute_to_register['type'];

		foreach ( $attribute_to_register['options'] as $key => $option ) {
			$term_id = $option['term_id'];
			$value = $this->get_option_value( $type, $term_id, $option );
			$attribute_to_register['options'][ $key ]['value'] = $value;
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
					// If no hex color found in database, leave value empty
					if ( empty( $value ) ) {
						$value = '';
					}
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
		// First try to get color from WooCommerce Variation Swatches plugin if available
		if ( function_exists( 'woo_variation_swatches' ) ) {
			$swatch_frontend = woo_variation_swatches()->get_frontend();
			$value           = sanitize_hex_color( $swatch_frontend->get_product_attribute_color( $term_id ) );
			if ( ! empty( $value ) ) {
				return $value;
			}
		}

		// Only try to get color from wp_termmeta - no fallbacks
		$color_hex = $this->get_color_from_termmeta( $term_id );
		if ( ! empty( $color_hex ) ) {
			return sanitize_hex_color( $color_hex );
		}

		// Return empty string if no hex value found in database
		return '';
	}

	public function get_image_src( $term ) {

	}

	/**
	 * Get color hex value from wp_termmeta table using pa_colour_swatches_id_color meta key
	 * This is the primary fallback method when WooCommerce Variation Swatches plugin is not active
	 */
	public function get_color_from_termmeta( $term_id ) {
		// Try multiple possible meta keys for color values
		$possible_meta_keys = array(
			'pa_colour_swatches_id_color',  // Your discovered key
			'pa_color_swatches_id_color',   // Alternative spelling
			'color',                        // Simple key
			'swatch_color',                 // Common swatch key
			'attribute_color',              // Attribute-specific key
			'term_color',                   // Term-specific key
			'_swatch_color',                // Private meta key
			'_color',                       // Private color key
		);

		foreach ( $possible_meta_keys as $meta_key ) {
			$color_value = get_term_meta( $term_id, $meta_key, true );

			if ( ! empty( $color_value ) && $this->is_valid_hex_color( $color_value ) ) {
				return $color_value;
			}
		}

		return '';
	}



	/**
	 * Check if a string is a valid hex color
	 */
	public function is_valid_hex_color( $color ) {
		return preg_match( '/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color );
	}

	/**
	 * Check if an attribute is a color attribute based on its name
	 */
	public function is_color_attribute( $attribute_name ) {
		$color_attribute_names = array(
			'color',
			'colour',
			'colors',
			'colours',
			'pa_color',
			'pa_colour',
			'pa_colors',
			'pa_colours',
		);

		// Apply filters to allow customization
		$color_attribute_names = apply_filters( 'blaze_commerce_color_attribute_names', $color_attribute_names );

		$attribute_name_lower = strtolower( trim( $attribute_name ) );

		return in_array( $attribute_name_lower, $color_attribute_names );
	}
}


