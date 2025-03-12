<?php
/**
 * WooCommerce Variation Swatches Integration
 *
 * @package BlazeWooless
 * @subpackage Extensions
 */

namespace BlazeWooless\Extensions;

/**
 * Handles integration with WooCommerce Variation Swatches plugin.
 */
class WoocommerceVariationSwatches {
	/**
	 * Singleton instance
	 *
	 * @var self
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return self
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		if ( ! $this->is_active() ) {
			return;
		}

		$this->init_hooks();
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		add_filter( 'blaze_wooless_product_attribute_for_typesense', array( $this, 'add_swatches_data' ), 10, 2 );
		add_filter( 'blaze_commerce_taxonomy_fields', array( $this, 'add_taxonomy_fields' ) );
		add_filter( 'blaze_commerce_taxonomy_data', array( $this, 'add_taxonomy_fields_data' ), 10, 2 );
		add_filter( 'blaze_wooless_product_taxonomy_item', array( $this, 'modify_product_taxonomy_item' ), 10, 2 );
	}

	/**
	 * Check if WooCommerce Variation Swatches plugin is active
	 *
	 * @return bool
	 */
	public function is_active() {
		return function_exists( 'is_plugin_active' ) && 
			is_plugin_active( 'woo-variation-swatches/woo-variation-swatches.php' );
	}

	/**
	 * Get raw attribute data from database
	 *
	 * @param string $taxonomy_slug The taxonomy slug.
	 * @return object|false Attribute data or false if not found
	 */
	public function get_raw_attribute( $taxonomy_slug ) {
		if ( 'pa_' !== substr( $taxonomy_slug, 0, 3 ) ) {
			return false;
		}

		$transient_key = 'wooless_attribute_' . $taxonomy_slug;
		$attribute_taxonomy = get_transient( $transient_key );

		if ( false !== $attribute_taxonomy ) {
			return $attribute_taxonomy;
		}

		$attribute_name = str_replace( 'pa_', '', wc_sanitize_taxonomy_name( $taxonomy_slug ) );
		$attribute_taxonomy = $this->get_attribute_from_db( $attribute_name );

		set_transient( $transient_key, $attribute_taxonomy );

		return $attribute_taxonomy;
	}

	/**
	 * Get attribute data from database
	 *
	 * @param string $attribute_name The attribute name.
	 * @return object|null
	 */
	private function get_attribute_from_db( $attribute_name ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_name = %s",
				esc_sql( $attribute_name )
			)
		);
	}

	/**
	 * Add taxonomy fields for Typesense
	 *
	 * @param array $fields Existing fields.
	 * @return array Modified fields
	 */
	public function add_taxonomy_fields( $fields ) {
		$additional_fields = array(
			array(
				'name'     => 'componentType',
				'type'     => 'string',
				'facet'    => true,
				'optional' => true,
			),
			array(
				'name'     => 'componentValue',
				'type'     => 'string',
				'facet'    => true,
				'optional' => true,
			),
		);

		return array_merge_recursive( $fields, $additional_fields );
	}

	/**
	 * Modify taxonomy item data
	 *
	 * @param array    $term_data Term data.
	 * @param WP_Term $term Term object.
	 * @return array Modified term data
	 */
	public function modify_product_taxonomy_item( $term_data, $term ) {
		$term_data = $this->add_taxonomy_fields_data( $term_data, $term );

		if ( ! empty( $term_data['filters'] ) && ! empty( $term_data['componentType'] ) ) {
			$term_data['filters'] = implode( '|', array(
				$term_data['filters'],
				$term_data['componentType'],
				$term_data['componentValue'],
			) );
		}

		return $term_data;
	}

	/**
	 * Add taxonomy fields data
	 *
	 * @param array    $document Term document.
	 * @param WP_Term $term Term object.
	 * @return array Modified document
	 */
	public function add_taxonomy_fields_data( $document, $term ) {
		$attribute_taxonomy = $this->get_raw_attribute( $term->taxonomy );

		if ( ! empty( $attribute_taxonomy->attribute_type ) ) {
			$document['componentType'] = $attribute_taxonomy->attribute_type;
			$document['componentValue'] = $this->get_option_value(
				$attribute_taxonomy->attribute_type,
				$term->term_id,
				(array) $term
			);
		}

		return $document;
	}

	/**
	 * Add swatches data to attribute
	 *
	 * @param array           $attribute_to_register Attribute data.
	 * @param WC_Product_Attribute $attribute WC product attribute.
	 * @return array Modified attribute data
	 */
	public function add_swatches_data( $attribute_to_register, $attribute ) {
		$attribute_to_register['type'] = 'select'; // Default type

		if ( ! $attribute->is_taxonomy() ) {
			return $attribute_to_register;
		}

		$swatch_attribute = $this->get_swatch_attribute( $attribute->get_id() );

		if ( ! empty( $swatch_attribute->attribute_type ) ) {
			$attribute_to_register['type'] = $swatch_attribute->attribute_type;
			$attribute_to_register = $this->get_options_value( $attribute_to_register, $attribute );
		}

		return $attribute_to_register;
	}

	/**
	 * Get swatch attribute data
	 *
	 * @param int $taxonomy_id Taxonomy ID.
	 * @return object Swatch attribute data
	 */
	private function get_swatch_attribute( $taxonomy_id ) {
		return woo_variation_swatches()->get_frontend()->get_attribute_taxonomy_by_id( $taxonomy_id );
	}

	/**
	 * Get options value for attribute
	 *
	 * @param array           $attribute_to_register Attribute data.
	 * @param WC_Product_Attribute $attribute WC product attribute.
	 * @return array Modified attribute data
	 */
	public function get_options_value( $attribute_to_register, $attribute ) {
		$type = $attribute_to_register['type'];

		foreach ( $attribute_to_register['options'] as $key => $option ) {
			$attribute_to_register['options'][ $key ]['value'] = $this->get_option_value(
				$type,
				$option['term_id'],
				$option
			);
		}

		return $attribute_to_register;
	}

	/**
	 * Get option value based on type
	 *
	 * @param string $type Attribute type.
	 * @param int    $term_id Term ID.
	 * @param array  $option Option data.
	 * @return string Option value
	 */
	public function get_option_value( $type, $term_id, $option ) {
		if ( empty( $term_id ) ) {
			return $this->get_default_option_value( $option );
		}

		return $this->get_typed_option_value( $type, $term_id, $option );
	}

	/**
	 * Get default option value
	 *
	 * @param array $option Option data.
	 * @return string Default value
	 */
	private function get_default_option_value( $option ) {
		return isset( $option['label'] ) ? $option['label'] : '';
	}

	/**
	 * Get option value based on attribute type
	 *
	 * @param string $type Attribute type.
	 * @param int    $term_id Term ID.
	 * @param array  $option Option data.
	 * @return string Option value
	 */
	private function get_typed_option_value( $type, $term_id, $option ) {
		switch ( $type ) {
			case 'color':
				return $this->get_color_hex( $term_id );

			case 'image':
			case 'button':
			case 'radio':
				// @todo Implement correct value retrieval for these types
				return $option['label'];

			default:
				return $this->get_default_option_value( $option );
		}
	}

	/**
	 * Get color hex value
	 *
	 * @param int $term_id Term ID.
	 * @return string Color hex value
	 */
	public function get_color_hex( $term_id ) {
		$color = get_term_meta( $term_id, 'pa_colour_swatches_id_color', true );

		if ( empty( $color ) ) {
			$color = $this->get_color_from_swatches( $term_id );
		}

		return ! empty( $color ) ? sanitize_hex_color( $color ) : '';
	}

	/**
	 * Get color from WooCommerce Variation Swatches
	 *
	 * @param int $term_id Term ID.
	 * @return string|null Color value
	 */
	private function get_color_from_swatches( $term_id ) {
		$swatch_frontend = woo_variation_swatches()->get_frontend();

		if ( ! $swatch_frontend || ! method_exists( $swatch_frontend, 'get_product_attribute_color' ) ) {
			return null;
		}

		return $swatch_frontend->get_product_attribute_color( $term_id );
	}

	public function get_image_src( $term ) {

	}
}


