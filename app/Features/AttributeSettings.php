<?php

namespace BlazeWooless\Features;

use BlazeWooless\TypesenseClient;

class AttributeSettings {
	private static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		add_filter( 'blaze_wooless_product_data_for_typesense', array( $this, 'add_available_product_attribute' ), 10, 2 );
		add_filter( 'blaze_wooless_product_page_settings', array( $this, 'register_settings' ) );

		add_filter( 'blazecommerce/settings/product_page', array( $this, 'add_settings' ), 10, 2 );
	}

	public static function get_all_attributes() {
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

		// Create a new WP_Query instance
		$query = new \WP_Query( $args );

		$site_product_attributes = array();

		if ( $query->have_posts() ) {
			// Loop through the variable products
			while ( $query->have_posts() ) {
				$query->the_post();

				// Get the product object
				global $product;

				$attributes = $product->get_attributes();

				foreach ( $attributes as $key => $attribute ) {
					$attribute_to_register = array(
						'name' => $key
					);
					if ( $attr = $attribute->get_taxonomy_object() ) {
						$attribute_to_register['label'] = $attr->attribute_label;
					} else {
						$attribute_to_register['label'] = $attribute->get_name();
					}

					$site_product_attributes[ $key ] = $attribute_to_register;
				}
			}
		}

		wp_reset_postdata();

		return $site_product_attributes;
	}

	public function add_available_product_attribute( $product_data, $product_id ) {
		$product                    = wc_get_product( $product_id );
		$attributes                 = $product->get_attributes();
		$product_data['attributes'] = $attributes;

		if ( $product->is_type( 'variable' ) ) {

			$generated_attributes = array();

			foreach ( $attributes as $key => $attribute ) {
				if ( ! $attribute->get_variation() ) {
					continue;
				}
				$attribute_to_register = array(
					'slug' => $key,
					'name' => 'attribute_' . $key,
					'options' => $attribute->get_options(),
				);

				if ( $attribute->is_taxonomy() ) {
					$options = array_map( function ($term) {
						return [ 
							'label' => $term->name,
							'slug' => $term->slug,
							'name' => $term->slug,
							'term_id' => $term->term_id,
							'value' => $term->name,
						];
					}, $attribute->get_terms() );
				} else {
					$options = array_map( function ($option) {
						return [ 
							'label' => $option,
							'slug' => $option,
							'name' => $option,
							'term_id' => 0,
							'value' => $option
						];
					}, $attribute->get_options() );
				}

				$attribute_to_register['options'] = $options;

				if ( $attr = $attribute->get_taxonomy_object() ) {
					$attribute_to_register['label'] = $attr->attribute_label;
				} else {
					$attribute_to_register['label'] = $attribute->get_name();
				}

				$generated_attributes[] = apply_filters( 'blaze_wooless_product_attribute_for_typesense', $attribute_to_register, $attribute );
			}
			$product_data['defaultAttributes'] = $product->get_default_attributes();
			$product_data['attributes']        = $generated_attributes;
		}

		if ( $product->is_type( 'variation' ) ) {
			$generated_attributes = array();
			foreach ( $attributes as $key => $attribute ) {
				// Extract the actual attribute value instead of storing the whole object
				if ( is_object( $attribute ) && method_exists( $attribute, 'get_slug' ) ) {
					// For WC_Product_Attribute objects, get the slug
					$generated_attributes[ 'attribute_' . $key ] = $attribute->get_slug();
				} elseif ( is_array( $attribute ) && isset( $attribute['slug'] ) ) {
					// For array attributes, get the slug
					$generated_attributes[ 'attribute_' . $key ] = $attribute['slug'];
				} elseif ( is_array( $attribute ) && isset( $attribute['name'] ) ) {
					// Fallback to name if slug not available
					$generated_attributes[ 'attribute_' . $key ] = $attribute['name'];
				} else {
					// For simple string values, use as-is
					$generated_attributes[ 'attribute_' . $key ] = $attribute;
				}
			}
			$product_data['attributes'] = $generated_attributes;
		}

		return $product_data;
	}

	public function register_settings( $product_page_settings ) {
		$product_page_settings['wooless_settings_attributes_section'] = array(
			'label' => 'Attributes',
			'options' => $this->get_attribute_mapping_settings(),
		);

		return $product_page_settings;
	}

	public function get_attribute_mapping_settings() {
		return array_map( function ($attribute) {
			return array(
				'id' => 'attribute_' . $attribute['name'],
				'label' => $attribute['label'],
				'type' => 'select',
				'args' => array(
					'options' => array(
						'select' => 'Select',
						'boxed' => 'Boxed',
						'swatch' => 'Swatch',
						'image' => 'Image',
					),
				),
			);
		}, AttributeSettings::get_all_attributes() );
	}

	public function add_settings( $documents, $options ) {
		if ( ! is_array( $options ) ) {
			$options = array();
		}
		$attributes  = array_filter( $options, function ($option, $key) {
			return str_starts_with( $key, 'attribute_' );
		}, ARRAY_FILTER_USE_BOTH );
		$documents[] = array(
			'id' => '1000023',
			'name' => 'attribute_display_type',
			'value' => json_encode( $attributes ),
			'updated_at' => time(),
		);

		return $documents;
	}
}
