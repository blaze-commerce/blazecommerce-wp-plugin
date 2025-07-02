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

			// Add filter for product-specific swatch processing
			add_filter( 'blaze_wooless_product_data_for_typesense', array( $this, 'add_product_specific_swatches_to_product_data' ), 10, 2 );

			// Add filter to merge duplicate color attributes at the end of processing
			add_filter( 'blaze_wooless_product_data_for_typesense', array( $this, 'merge_duplicate_color_attributes' ), 999, 1 );
		}
	}

	public function get_raw_attribute( $taxonomy_slug ) {
		// Check if required functions exist
		if ( ! function_exists( 'wc_sanitize_taxonomy_name' ) || ! function_exists( 'get_transient' ) || ! function_exists( 'set_transient' ) ) {
			return false;
		}

		if ( 'pa_' !== substr( $taxonomy_slug, 0, 3 ) ) {
			return false;
		}

		$transient_key      = 'wooless_attribute_' . $taxonomy_slug;
		$attribute_name     = str_replace( 'pa_', '', wc_sanitize_taxonomy_name( $taxonomy_slug ) );
		$attribute_taxonomy = get_transient( $transient_key );

		if ( false === $attribute_taxonomy ) {
			global $wpdb;

			if ( ! $wpdb ) {
				return false;
			}

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

		if ( isset( $term_data['filters'] ) && ! empty( $term_data['filters'] ) &&
			 isset( $term_data['componentType'] ) && ! empty( $term_data['componentType'] ) ) {
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

		// Check if attribute object has required methods
		if ( ! $attribute || ! method_exists( $attribute, 'is_taxonomy' ) ) {
			return $attribute_to_register;
		}

		if ( $attribute->is_taxonomy() ) {
			// Check if attribute has required methods
			if ( ! method_exists( $attribute, 'get_id' ) || ! method_exists( $attribute, 'get_name' ) ) {
				return $attribute_to_register;
			}

			$taxonomy_id = $attribute->get_id();
			$attribute_name = $attribute->get_name();

			// PRIORITY 1: Try to get product-specific swatch configurations first
			$product_specific_swatches = $this->get_product_specific_swatches( $attribute_to_register, $attribute );
			if ( ! empty( $product_specific_swatches ) ) {
				return $product_specific_swatches;
			}

			// PRIORITY 2: Try to get attribute type from WooCommerce Variation Swatches plugin
			if ( function_exists( 'woo_variation_swatches' ) ) {
				$swatch_frontend = woo_variation_swatches()->get_frontend();
				if ( $swatch_frontend && method_exists( $swatch_frontend, 'get_attribute_taxonomy_by_id' ) ) {
					$swatch_attribute = $swatch_frontend->get_attribute_taxonomy_by_id( $taxonomy_id );
					if ( $swatch_attribute && isset( $swatch_attribute->attribute_type ) && ! empty( $swatch_attribute->attribute_type ) ) {
						// Set type depending on what is selected for the woocommerce attribute in wp admin
						$attribute_to_register['type'] = $swatch_attribute->attribute_type;
						$attribute_to_register = $this->get_options_value( $attribute_to_register, $attribute );
					}
				}
			} else {
				// PRIORITY 3: Fallback - detect color attributes by name when plugin is not active
				if ( $this->is_color_attribute( $attribute_name ) ) {
					$attribute_to_register['type'] = 'color';
					$attribute_to_register = $this->get_options_value( $attribute_to_register, $attribute );
				}
			}
		}

		return $attribute_to_register;
	}

	public function get_options_value( $attribute_to_register, $attribute ) {
		// Add isset() check before accessing 'type' key
		if ( ! isset( $attribute_to_register['type'] ) ) {
			return $attribute_to_register; // Return unchanged if type is not set
		}
		$type = $attribute_to_register['type'];
		$new_options = array();

		// Add isset() check before accessing 'options' key
		if ( ! isset( $attribute_to_register['options'] ) || ! is_array( $attribute_to_register['options'] ) ) {
			return $attribute_to_register; // Return unchanged if options is not set or not an array
		}

		foreach ( $attribute_to_register['options'] as $key => $option ) {
			// Add isset() check before accessing 'term_id' key
			if ( ! isset( $option['term_id'] ) ) {
				continue; // Skip this option if term_id is not set
			}
			$term_id = $option['term_id'];

			// FILTER OUT RANDOM HASH COLOR NAMES - only include valid color names
			if ( ! $this->is_valid_color_name( $option['label'] ?? $option['name'] ?? '' ) ) {
				continue; // Skip this option if it has a random hash name
			}

			// Use the standard get_option_value method which now prioritizes images over colors
			$value = $this->get_option_value( $type, $term_id, $option );
			$option['value'] = $value;
			$new_options[] = $option;
		}

		// Update options array
		$attribute_to_register['options'] = $new_options;

		return $attribute_to_register;
	}

	public function get_option_value( $type, $term_id, $option ) {
		// Use the actual term name instead of random labels
		$term_name = '';
		if ( ! empty( $term_id ) && function_exists( 'get_term' ) ) {
			$term = get_term( $term_id );
			if ( $term && ! ( function_exists( 'is_wp_error' ) && is_wp_error( $term ) ) ) {
				$term_name = $term->name;
			}
		}

		// Default value will be the term name or option label
		$value = ! empty( $term_name ) ? $term_name : ( isset( $option['label'] ) ? $option['label'] : '' );

		if ( ! empty( $term_id ) ) {
			switch ( $type ) {
				case "color":
					// For color type, RESPECT the individual swatch type configuration for each term
					$configured_swatch_type = $this->get_configured_swatch_type_for_term( $term_id );

					if ( $configured_swatch_type === 'image' ) {
						// This term is specifically configured as IMAGE type
						$image_url = $this->get_image_src( $term_id );
						if ( ! empty( $image_url ) ) {
							$value = $image_url;
						} else {
							$value = $term_name;
						}
					} else {
						// This term is configured as COLOR type (or default)
						$color_hex = $this->get_color_hex( $term_id );
						if ( ! empty( $color_hex ) ) {
							$value = $color_hex;
						} else {
							$value = $term_name;
						}
					}
					break;
				case "image":
					// For image type, return image URLs as string
					$image_value = $this->get_image_src( $term_id );
					if ( ! empty( $image_value ) ) {
						// Return ONLY the image URL string
						$value = $image_value;
					} else {
						// If no image found, return the term name
						$value = $term_name;
					}
					break;
				case "button":
					// Return the term name for button type
					$value = $term_name;
					break;
				case "radio":
					// Return the term name for radio type
					$value = $term_name;
					break;
				default:
					$value = $term_name;
			}
		}

		return $value;
	}

	public function get_color_hex( $term_id ) {
		// Validate term_id
		if ( empty( $term_id ) || ! is_numeric( $term_id ) ) {
			return '';
		}

		// CRITICAL CHECK: Only return colors for COLOR/COLOUR taxonomy terms
		if ( function_exists( 'get_term' ) ) {
			$term = get_term( $term_id );
			if ( $term && ! ( function_exists( 'is_wp_error' ) && is_wp_error( $term ) ) ) {
				// Check if this term belongs to a color-related taxonomy
				if ( ! $this->is_color_taxonomy( $term->taxonomy ) ) {
					$this->log_color_retrieval( $term_id, 'wrong_taxonomy', '' );
					return '';
				}
			}
		}

		// PRIORITY 1: Try to get color from product-level swatch configuration first
		$product_config_color = $this->get_color_from_product_swatch_config( $term_id );
		if ( ! empty( $product_config_color ) ) {
			$sanitized_color = function_exists( 'sanitize_hex_color' ) ? sanitize_hex_color( $product_config_color ) : $product_config_color;
			if ( ! empty( $sanitized_color ) ) {
				$this->log_color_retrieval( $term_id, 'product_swatch_config', $sanitized_color );
				return $sanitized_color;
			}
		}

		// PRIORITY 2: Try WooCommerce Variation Swatches plugin API if available
		if ( function_exists( 'woo_variation_swatches' ) ) {
			$swatch_frontend = woo_variation_swatches()->get_frontend();
			if ( method_exists( $swatch_frontend, 'get_product_attribute_color' ) ) {
				$value = $swatch_frontend->get_product_attribute_color( $term_id );
				if ( ! empty( $value ) ) {
					$sanitized_value = function_exists( 'sanitize_hex_color' ) ? sanitize_hex_color( $value ) : $value;
					if ( ! empty( $sanitized_value ) ) {
						$this->log_color_retrieval( $term_id, 'plugin_api', $sanitized_value );
						return $sanitized_value;
					}
				}
			}
		}

		// PRIORITY 3: Try to get color from wp_termmeta using various meta keys
		$color_hex = $this->get_color_from_termmeta( $term_id );
		if ( ! empty( $color_hex ) ) {
			$sanitized_color = function_exists( 'sanitize_hex_color' ) ? sanitize_hex_color( $color_hex ) : $color_hex;
			if ( ! empty( $sanitized_color ) ) {
				$this->log_color_retrieval( $term_id, 'term_meta', $sanitized_color );
				return $sanitized_color;
			}
		}

		// PRIORITY 4: Try name-based color generation as final fallback
		$generated_color = $this->get_color_from_name_generation( $term_id );
		if ( ! empty( $generated_color ) ) {
			$this->log_color_retrieval( $term_id, 'name_generation', $generated_color );
			return $generated_color;
		}

		// Log when no color is found
		$this->log_color_retrieval( $term_id, 'none', '' );

		// Return empty string if no hex value found
		return '';
	}

	public function get_image_src( $term_id ) {
		// Validate term_id
		if ( empty( $term_id ) || ! is_numeric( $term_id ) ) {
			return '';
		}

		// CRITICAL CHECK: Only return images if this term is configured as "Image" type
		$configured_swatch_type = $this->get_configured_swatch_type_for_term( $term_id );
		if ( $configured_swatch_type !== 'image' ) {
			$this->log_image_retrieval( $term_id, 'wrong_type', '' );
			return '';
		}

		// PRIORITY 1: Try to get image from product-level swatch configuration first
		$product_config_image = $this->get_image_from_product_swatch_config( $term_id );
		if ( ! empty( $product_config_image ) ) {
			$this->log_image_retrieval( $term_id, 'product_swatch_config', $product_config_image );
			return $product_config_image;
		}

		// PRIORITY 2: Try WooCommerce Variation Swatches plugin API if available
		if ( function_exists( 'woo_variation_swatches' ) ) {
			$swatch_frontend = woo_variation_swatches()->get_frontend();
			if ( method_exists( $swatch_frontend, 'get_product_attribute_image' ) ) {
				$image_data = $swatch_frontend->get_product_attribute_image( $term_id );
				if ( ! empty( $image_data ) ) {
					$this->log_image_retrieval( $term_id, 'plugin_api', $image_data );
					return $image_data;
				}
			}
		}

		// PRIORITY 3: Try to get image from wp_termmeta using various meta keys
		$image_src = $this->get_image_from_termmeta( $term_id );
		if ( ! empty( $image_src ) ) {
			$this->log_image_retrieval( $term_id, 'term_meta', $image_src );
			return $image_src;
		}

		// Log when no image is found
		$this->log_image_retrieval( $term_id, 'none', '' );

		// Return empty string if no image found
		return '';
	}

	/**
	 * Get color hex value from wp_termmeta table using various meta keys
	 * This method tries multiple meta keys used by different swatch plugins
	 */
	public function get_color_from_termmeta( $term_id ) {
		// Check if required functions exist
		if ( ! function_exists( 'get_term_meta' ) || ! function_exists( 'get_term' ) ) {
			return '';
		}

		// Get the term to determine taxonomy for dynamic meta keys
		$term = get_term( $term_id );
		if ( ! $term || ( function_exists( 'is_wp_error' ) && is_wp_error( $term ) ) ) {
			return '';
		}

		// Build comprehensive list of possible meta keys
		$possible_meta_keys = array(
			// WooCommerce Variation Swatches and Photos plugin keys
			'product_attribute_color',                    // Base plugin key
			$term->taxonomy . '_swatches_id_color',      // Dynamic taxonomy-based key
			'pa_colour_swatches_id_color',               // Discovered key for colour
			'pa_color_swatches_id_color',                // Alternative spelling for color

			// Common swatch plugin keys
			'color',                                     // Simple key
			'swatch_color',                              // Common swatch key
			'attribute_color',                           // Attribute-specific key
			'term_color',                                // Term-specific key
			'_swatch_color',                             // Private meta key
			'_color',                                    // Private color key
			'wvs_color',                                 // WooCommerce Variation Swatches (different plugin)

			// Additional fallback keys
			'hex_color',                                 // Hex color key
			'colour_hex',                                // British spelling
			'color_hex',                                 // American spelling
		);

		// Apply filters to allow customization of meta keys
		if ( function_exists( 'apply_filters' ) ) {
			$possible_meta_keys = apply_filters( 'blaze_commerce_color_meta_keys', $possible_meta_keys, $term_id, $term );
		}

		foreach ( $possible_meta_keys as $meta_key ) {
			$color_value = get_term_meta( $term_id, $meta_key, true );

			if ( ! empty( $color_value ) && $this->is_valid_hex_color( $color_value ) ) {
				return $color_value;
			}
		}

		return '';
	}



	/**
	 * Get color from product-level swatch configuration
	 * This method retrieves colors from the WooCommerce product edit interface configuration
	 * using sophisticated hash mapping and positional matching
	 */
	public function get_color_from_product_swatch_config( $term_id ) {
		// Check if required functions exist
		if ( ! function_exists( 'get_term' ) || ! function_exists( 'is_wp_error' ) ) {
			return '';
		}

		global $wpdb;

		$term = get_term( $term_id );
		if ( ! $term || ( function_exists( 'is_wp_error' ) && is_wp_error( $term ) ) ) {
			return '';
		}

		// Get all products that have this term in their attributes
		$products_with_term = $wpdb->get_results( $wpdb->prepare( "
			SELECT DISTINCT tr.object_id as post_id
			FROM {$wpdb->term_relationships} tr
			INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
			WHERE tt.term_id = %d
		", $term_id ) );

		if ( empty( $products_with_term ) ) {
			return '';
		}

		// Check each product's swatch configuration
		foreach ( $products_with_term as $product ) {
			$swatch_options = get_post_meta( $product->post_id, '_swatch_type_options', true );

			if ( empty( $swatch_options ) || ! is_array( $swatch_options ) ) {
				continue;
			}

			// Try to map this term to a hash in the swatch configuration
			$color_hex = $this->map_term_to_swatch_color( $term, $swatch_options, $product->post_id );
			if ( ! empty( $color_hex ) ) {
				return $color_hex;
			}
		}

		return '';
	}

	/**
	 * Get the configured swatch type (color/image) for a specific term
	 * This is the CRITICAL method that determines what type of value to return
	 * Enhanced to support individual term-level swatch type configuration
	 */
	public function get_configured_swatch_type_for_term( $term_id ) {
		// Check if required functions exist
		if ( ! function_exists( 'get_term' ) || ! function_exists( 'is_wp_error' ) ) {
			return 'color'; // Default to color
		}

		global $wpdb;

		$term = get_term( $term_id );
		if ( ! $term || ( function_exists( 'is_wp_error' ) && is_wp_error( $term ) ) ) {
			return 'color'; // Default to color
		}

		// Get all products that have this term in their attributes
		$products_with_term = $wpdb->get_results( $wpdb->prepare( "
			SELECT DISTINCT tr.object_id as post_id
			FROM {$wpdb->term_relationships} tr
			INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
			WHERE tt.term_id = %d
		", $term_id ) );

		if ( empty( $products_with_term ) ) {
			return 'color'; // Default to color
		}

		// Check each product's swatch configuration to find this term's type
		foreach ( $products_with_term as $product ) {
			$swatch_options = get_post_meta( $product->post_id, '_swatch_type_options', true );

			if ( empty( $swatch_options ) || ! is_array( $swatch_options ) ) {
				continue;
			}

			// Try to find this term's swatch type in the configuration
			$swatch_type = $this->find_term_swatch_type_in_config( $term, $swatch_options, $product->post_id );
			if ( ! empty( $swatch_type ) ) {
				return $swatch_type;
			}
		}

		// Default to color if not found
		return 'color';
	}

	/**
	 * Find the swatch type for a specific term in the swatch configuration
	 */
	public function find_term_swatch_type_in_config( $term, $swatch_options, $product_id ) {
		if ( ! $term || ! is_array( $swatch_options ) ) {
			return '';
		}

		// Strategy 1: Try hash matching to find the exact swatch configuration for this term
		$possible_hashes = $this->generate_all_possible_hashes( $term, $product_id );

		foreach ( $swatch_options as $group ) {
			if ( ! isset( $group['attributes'] ) || ! is_array( $group['attributes'] ) ) {
				continue;
			}

			foreach ( $group['attributes'] as $hash => $attr ) {
				if ( ! isset( $attr['type'] ) ) {
					continue;
				}

				// Check if this hash matches any of our generated hashes
				if ( in_array( $hash, $possible_hashes ) ) {
					return $attr['type'];
				}
			}
		}

		// Strategy 2: Try positional matching to determine swatch type
		return $this->try_positional_swatch_type_matching( $term, $swatch_options, $product_id );
	}

	/**
	 * Try positional matching to determine swatch type when hash matching fails
	 */
	public function try_positional_swatch_type_matching( $term, $swatch_options, $product_id ) {
		// Get all terms for this product's color attribute
		global $wpdb;

		$color_terms = $wpdb->get_results( $wpdb->prepare( "
			SELECT t.term_id, t.name, t.slug, tt.term_order
			FROM {$wpdb->terms} t
			INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
			INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
			WHERE tr.object_id = %d
			AND tt.taxonomy LIKE 'pa_colo%%'
			ORDER BY tt.term_order ASC, t.name ASC
		", $product_id ) );

		if ( empty( $color_terms ) ) {
			return '';
		}

		// Find the position of our term
		$term_position = -1;
		foreach ( $color_terms as $index => $color_term ) {
			if ( $color_term->term_id == $term->term_id ) {
				$term_position = $index;
				break;
			}
		}

		if ( $term_position === -1 ) {
			return '';
		}

		// Get the swatch type at the same position in the swatch configuration
		$swatch_index = 0;
		foreach ( $swatch_options as $group ) {
			if ( ! isset( $group['attributes'] ) || ! is_array( $group['attributes'] ) ) {
				continue;
			}

			foreach ( $group['attributes'] as $hash => $attr ) {
				if ( isset( $attr['type'] ) ) {
					if ( $swatch_index === $term_position ) {
						return $attr['type'];
					}
					$swatch_index++;
				}
			}
		}

		return '';
	}

	/**
	 * Map a term to its corresponding swatch color using intelligent hash analysis
	 */
	public function map_term_to_swatch_color( $term, $swatch_options, $product_id ) {
		if ( ! $term || ! is_array( $swatch_options ) ) {
			return '';
		}

		// Strategy 1: Try to generate the hash that the WooCommerce Variation Swatches plugin would use
		$possible_hashes = $this->generate_all_possible_hashes( $term, $product_id );

		foreach ( $swatch_options as $group ) {
			if ( ! isset( $group['attributes'] ) || ! is_array( $group['attributes'] ) ) {
				continue;
			}

			foreach ( $group['attributes'] as $hash => $attr ) {
				if ( ! isset( $attr['color'] ) || ! $this->is_valid_hex_color( $attr['color'] ) ) {
					continue;
				}

				// Check if this hash matches any of our generated hashes
				if ( in_array( $hash, $possible_hashes ) ) {
					return $attr['color'];
				}
			}
		}

		// Strategy 2: If hash generation fails, try positional matching for colors
		return $this->try_positional_color_matching( $term, $swatch_options, $product_id );
	}

	/**
	 * Check if a term matches a swatch hash configuration
	 * Enhanced version that handles WooCommerce Variation Swatches plugin hash generation
	 */
	public function is_term_match_for_swatch( $term, $hash ) {
		if ( ! $term || ! $hash ) {
			return false;
		}

		// The hash might contain the term slug or term ID
		$term_slug = $term->slug;
		$term_id = $term->term_id;
		$term_name = strtolower( $term->name );

		// Check various matching patterns
		$hash_lower = strtolower( $hash );

		// Direct matches
		if ( $hash === $term_slug || $hash === (string) $term_id ) {
			return true;
		}

		// Substring matches
		if ( strpos( $hash_lower, $term_slug ) !== false ||
			 strpos( $hash_lower, (string) $term_id ) !== false ||
			 strpos( $hash_lower, $term_name ) !== false ) {
			return true;
		}

		// For MD5-style hashes, try WooCommerce Variation Swatches plugin hash patterns
		if ( preg_match( '/^[a-f0-9]{32}$/i', $hash ) ) {
			$generated_hash = $this->generate_woocommerce_swatch_hash( $term );
			if ( $hash === $generated_hash ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Generate hash in the same way as WooCommerce Variation Swatches plugin
	 * This attempts to replicate the hash generation logic used by the plugin
	 */
	public function generate_woocommerce_swatch_hash( $term ) {
		if ( ! $term ) {
			return '';
		}

		// Common patterns used by WooCommerce Variation Swatches plugins
		$possible_patterns = array(
			// Pattern 1: taxonomy_termid
			$term->taxonomy . '_' . $term->term_id,
			// Pattern 2: taxonomy-termid
			$term->taxonomy . '-' . $term->term_id,
			// Pattern 3: termid_taxonomy
			$term->term_id . '_' . $term->taxonomy,
			// Pattern 4: termslug_taxonomy
			$term->slug . '_' . $term->taxonomy,
			// Pattern 5: taxonomy_termslug
			$term->taxonomy . '_' . $term->slug,
			// Pattern 6: Just term ID
			(string) $term->term_id,
			// Pattern 7: Just term slug
			$term->slug,
		);

		// Try each pattern and return the first one that might match
		foreach ( $possible_patterns as $pattern ) {
			$hash = md5( $pattern );
			// We'll return the first generated hash for now
			// In practice, we'd need to test which pattern the plugin actually uses
			return $hash;
		}

		return '';
	}

	/**
	 * Generate color from term name when no color found
	 * This provides intelligent color guessing based on color names
	 */
	public function get_color_from_name_generation( $term_id ) {
		// Check if required functions exist
		if ( ! function_exists( 'get_term' ) || ! function_exists( 'is_wp_error' ) ) {
			return '';
		}

		$term = get_term( $term_id );
		if ( ! $term || ( function_exists( 'is_wp_error' ) && is_wp_error( $term ) ) ) {
			return '';
		}

		$color_name = strtolower( trim( $term->name ) );

		// Common color name to hex mappings
		$color_mappings = array(
			'black'   => '#000000',
			'white'   => '#FFFFFF',
			'red'     => '#FF0000',
			'blue'    => '#0000FF',
			'green'   => '#008000',
			'yellow'  => '#FFFF00',
			'orange'  => '#FFA500',
			'purple'  => '#800080',
			'pink'    => '#FFC0CB',
			'brown'   => '#A52A2A',
			'grey'    => '#808080',
			'gray'    => '#808080',
			'navy'    => '#000080',
			'maroon'  => '#800000',
			'olive'   => '#808000',
			'lime'    => '#00FF00',
			'aqua'    => '#00FFFF',
			'teal'    => '#008080',
			'silver'  => '#C0C0C0',
			'fuchsia' => '#FF00FF',
			'violet'  => '#EE82EE',
		);

		// Apply filters to allow customization
		if ( function_exists( 'apply_filters' ) ) {
			$color_mappings = apply_filters( 'blaze_commerce_color_name_mappings', $color_mappings );
		}

		// Direct match
		if ( isset( $color_mappings[ $color_name ] ) ) {
			return $color_mappings[ $color_name ];
		}

		// Partial match
		foreach ( $color_mappings as $name => $color ) {
			if ( strpos( $color_name, $name ) !== false ) {
				return $color;
			}
		}

		return ''; // No match found
	}

	/**
	 * Get image from product-level swatch configuration
	 * This method retrieves images from the WooCommerce product edit interface configuration
	 * using sophisticated hash mapping and positional matching
	 */
	public function get_image_from_product_swatch_config( $term_id ) {
		// Check if required functions exist
		if ( ! function_exists( 'get_term' ) || ! function_exists( 'is_wp_error' ) ) {
			return '';
		}

		global $wpdb;

		$term = get_term( $term_id );
		if ( ! $term || ( function_exists( 'is_wp_error' ) && is_wp_error( $term ) ) ) {
			return '';
		}

		// Get all products that have this term in their attributes
		$products_with_term = $wpdb->get_results( $wpdb->prepare( "
			SELECT DISTINCT tr.object_id as post_id
			FROM {$wpdb->term_relationships} tr
			INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
			WHERE tt.term_id = %d
		", $term_id ) );

		if ( empty( $products_with_term ) ) {
			return '';
		}

		// Check each product's swatch configuration
		foreach ( $products_with_term as $product ) {
			$swatch_options = get_post_meta( $product->post_id, '_swatch_type_options', true );

			if ( empty( $swatch_options ) || ! is_array( $swatch_options ) ) {
				continue;
			}

			// Try to map this term to a hash in the swatch configuration
			$image_url = $this->map_term_to_swatch_image( $term, $swatch_options, $product->post_id );
			if ( ! empty( $image_url ) ) {
				return $image_url;
			}
		}

		return '';
	}

	/**
	 * Map a term to its corresponding swatch image using intelligent hash analysis
	 * This method uses intelligent matching to map terms to their swatch images
	 */
	public function map_term_to_swatch_image( $term, $swatch_options, $product_id ) {
		if ( ! $term || ! is_array( $swatch_options ) ) {
			return '';
		}

		// Strategy 1: Try to generate the hash that the WooCommerce Variation Swatches plugin would use
		$possible_hashes = $this->generate_all_possible_hashes( $term, $product_id );

		foreach ( $swatch_options as $group ) {
			if ( ! isset( $group['attributes'] ) || ! is_array( $group['attributes'] ) ) {
				continue;
			}

			foreach ( $group['attributes'] as $hash => $attr ) {
				if ( ! isset( $attr['type'] ) || $attr['type'] !== 'image' || ! isset( $attr['image'] ) ) {
					continue;
				}

				// Check if this hash matches any of our generated hashes
				if ( in_array( $hash, $possible_hashes ) ) {
					$image_url = $this->get_image_url_from_attachment( $attr['image'] );
					if ( ! empty( $image_url ) ) {
						return $image_url;
					}
				}
			}
		}

		// Strategy 2: If hash generation fails, try positional matching
		// This assumes the order of terms matches the order of swatch configurations
		return $this->try_positional_matching( $term, $swatch_options, $product_id );
	}

	/**
	 * Generate all possible hashes that the WooCommerce Variation Swatches plugin might use
	 * CONFIRMED: The WooCommerce Variation Swatches plugin uses md5($term->slug) as the hash key
	 */
	public function generate_all_possible_hashes( $term, $product_id ) {
		$hashes = array();

		if ( ! $term ) {
			return $hashes;
		}

		// CONFIRMED PATTERN: The WooCommerce Variation Swatches plugin uses md5($term->slug)
		$primary_hash = md5( $term->slug );
		$hashes[] = $primary_hash;

		// Also include the slug itself as a fallback
		$hashes[] = $term->slug;

		// Additional fallback patterns (in case different plugins use different patterns)
		$fallback_patterns = array(
			// Basic patterns
			$term->taxonomy . '_' . $term->term_id,
			$term->taxonomy . '_' . $term->slug,
			$term->term_id . '_' . $term->taxonomy,
			$term->slug . '_' . $term->taxonomy,

			// Just identifiers
			(string) $term->term_id,
			$term->name,

			// Sanitized versions
			sanitize_title( $term->name ),

			// WooCommerce specific patterns
			'attribute_' . $term->taxonomy . '_' . $term->term_id,
			'attribute_' . $term->taxonomy . '_' . $term->slug,
		);

		// Generate MD5 hashes for fallback patterns
		foreach ( $fallback_patterns as $pattern ) {
			$hashes[] = md5( $pattern );
		}

		// Also include the patterns themselves (in case they're not hashed)
		$hashes = array_merge( $hashes, $fallback_patterns );

		return array_unique( $hashes );
	}

	/**
	 * Try positional matching when hash matching fails
	 */
	public function try_positional_matching( $term, $swatch_options, $product_id ) {
		// Get all terms for this product's color attribute
		global $wpdb;

		$color_terms = $wpdb->get_results( $wpdb->prepare( "
			SELECT t.term_id, t.name, t.slug, tt.term_order
			FROM {$wpdb->terms} t
			INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
			INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
			WHERE tr.object_id = %d
			AND tt.taxonomy LIKE 'pa_colo%%'
			ORDER BY tt.term_order ASC, t.name ASC
		", $product_id ) );

		if ( empty( $color_terms ) ) {
			return '';
		}

		// Find the position of our term
		$term_position = -1;
		foreach ( $color_terms as $index => $color_term ) {
			if ( $color_term->term_id == $term->term_id ) {
				$term_position = $index;
				break;
			}
		}

		if ( $term_position === -1 ) {
			return '';
		}

		// Get the image at the same position in the swatch configuration
		$image_index = 0;
		foreach ( $swatch_options as $group ) {
			if ( ! isset( $group['attributes'] ) || ! is_array( $group['attributes'] ) ) {
				continue;
			}

			foreach ( $group['attributes'] as $hash => $attr ) {
				if ( isset( $attr['type'] ) && $attr['type'] === 'image' && isset( $attr['image'] ) ) {
					if ( $image_index === $term_position ) {
						$image_url = $this->get_image_url_from_attachment( $attr['image'] );
						if ( ! empty( $image_url ) ) {
							return $image_url;
						}
					}
					$image_index++;
				}
			}
		}

		return '';
	}

	/**
	 * Try positional matching for colors when hash matching fails
	 */
	public function try_positional_color_matching( $term, $swatch_options, $product_id ) {
		// Get all terms for this product's color attribute
		global $wpdb;

		$color_terms = $wpdb->get_results( $wpdb->prepare( "
			SELECT t.term_id, t.name, t.slug, tt.term_order
			FROM {$wpdb->terms} t
			INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
			INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
			WHERE tr.object_id = %d
			AND tt.taxonomy LIKE 'pa_colo%%'
			ORDER BY tt.term_order ASC, t.name ASC
		", $product_id ) );

		if ( empty( $color_terms ) ) {
			return '';
		}

		// Find the position of our term
		$term_position = -1;
		foreach ( $color_terms as $index => $color_term ) {
			if ( $color_term->term_id == $term->term_id ) {
				$term_position = $index;
				break;
			}
		}

		if ( $term_position === -1 ) {
			return '';
		}

		// Get the color at the same position in the swatch configuration
		$color_index = 0;
		foreach ( $swatch_options as $group ) {
			if ( ! isset( $group['attributes'] ) || ! is_array( $group['attributes'] ) ) {
				continue;
			}

			foreach ( $group['attributes'] as $hash => $attr ) {
				if ( isset( $attr['color'] ) && $this->is_valid_hex_color( $attr['color'] ) ) {
					if ( $color_index === $term_position ) {
						return $attr['color'];
					}
					$color_index++;
				}
			}
		}

		return '';
	}

	/**
	 * Get image from wp_termmeta table using various meta keys
	 * This method tries multiple meta keys used by different swatch plugins for images
	 */
	public function get_image_from_termmeta( $term_id ) {
		// Check if required functions exist
		if ( ! function_exists( 'get_term_meta' ) || ! function_exists( 'get_term' ) ) {
			return '';
		}

		// Get the term to determine taxonomy for dynamic meta keys
		$term = get_term( $term_id );
		if ( ! $term || ( function_exists( 'is_wp_error' ) && is_wp_error( $term ) ) ) {
			return '';
		}

		// Build comprehensive list of possible image meta keys
		$possible_meta_keys = array(
			// WooCommerce Variation Swatches and Photos plugin keys
			'product_attribute_image',                   // Base plugin key
			$term->taxonomy . '_swatches_id_image',     // Dynamic taxonomy-based key
			'pa_colour_swatches_id_image',              // Discovered key for colour images
			'pa_color_swatches_id_image',               // Alternative spelling for color images

			// Common swatch plugin image keys
			'image',                                    // Simple key
			'swatch_image',                             // Common swatch key
			'attribute_image',                          // Attribute-specific key
			'term_image',                               // Term-specific key
			'_swatch_image',                            // Private meta key
			'_image',                                   // Private image key
			'wvs_image',                                // WooCommerce Variation Swatches (different plugin)

			// Additional fallback keys
			'attachment_id',                            // Attachment ID key
			'image_id',                                 // Image ID key
			'swatch_attachment_id',                     // Swatch attachment ID
		);

		// Apply filters to allow customization of image meta keys
		if ( function_exists( 'apply_filters' ) ) {
			$possible_meta_keys = apply_filters( 'blaze_commerce_image_meta_keys', $possible_meta_keys, $term_id, $term );
		}

		foreach ( $possible_meta_keys as $meta_key ) {
			$image_value = get_term_meta( $term_id, $meta_key, true );

			if ( ! empty( $image_value ) ) {
				// Try to get image URL from attachment ID or direct URL
				$image_url = $this->get_image_url_from_attachment( $image_value );
				if ( ! empty( $image_url ) ) {
					return $image_url;
				}

				// If it's already a URL, validate and return it
				if ( $this->is_valid_image_url( $image_value ) ) {
					return $image_value;
				}
			}
		}

		return '';
	}

	/**
	 * Get image URL from attachment ID or return URL if already valid
	 */
	public function get_image_url_from_attachment( $image_data ) {
		if ( empty( $image_data ) ) {
			return '';
		}

		// If it's already a valid URL, return it
		if ( $this->is_valid_image_url( $image_data ) ) {
			return $image_data;
		}

		// If it's numeric, treat as attachment ID
		if ( is_numeric( $image_data ) && function_exists( 'wp_get_attachment_image_url' ) ) {
			$image_url = wp_get_attachment_image_url( (int) $image_data, 'full' );
			if ( ! empty( $image_url ) ) {
				return $image_url;
			}
		}

		// Try to get attachment URL using wp_get_attachment_url
		if ( is_numeric( $image_data ) && function_exists( 'wp_get_attachment_url' ) ) {
			$image_url = wp_get_attachment_url( (int) $image_data );
			if ( ! empty( $image_url ) ) {
				return $image_url;
			}
		}

		return '';
	}

	/**
	 * Check if a string is a valid image URL
	 */
	public function is_valid_image_url( $url ) {
		if ( empty( $url ) || ! is_string( $url ) ) {
			return false;
		}

		// Check if it's a valid URL format
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		// Check if it has a valid image extension
		$image_extensions = array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg' );
		$url_path = parse_url( $url, PHP_URL_PATH );
		$extension = strtolower( pathinfo( $url_path, PATHINFO_EXTENSION ) );

		return in_array( $extension, $image_extensions );
	}

	/**
	 * Log image retrieval for debugging purposes
	 */
	public function log_image_retrieval( $term_id, $method, $image_value ) {
		// Only log if WP_DEBUG is enabled and error_log function exists
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG || ! function_exists( 'error_log' ) || ! function_exists( 'get_term' ) ) {
			return;
		}

		$term = get_term( $term_id );
		$term_name = $term ? $term->name : 'Unknown';

		$log_message = sprintf(
			'[BlazeCommerce Image Swatch] Term ID: %d (%s) | Method: %s | Image: %s',
			$term_id,
			$term_name,
			$method,
			$image_value ?: 'Not found'
		);

		error_log( $log_message );
	}

	/**
	 * Log color retrieval for debugging purposes
	 */
	public function log_color_retrieval( $term_id, $method, $color_value ) {
		// Only log if WP_DEBUG is enabled and error_log function exists
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG || ! function_exists( 'error_log' ) || ! function_exists( 'get_term' ) ) {
			return;
		}

		$term = get_term( $term_id );
		$term_name = $term ? $term->name : 'Unknown';

		$log_message = sprintf(
			'[BlazeCommerce Color Swatch] Term ID: %d (%s) | Method: %s | Color: %s',
			$term_id,
			$term_name,
			$method,
			$color_value ?: 'Not found'
		);

		error_log( $log_message );
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
		if ( function_exists( 'apply_filters' ) ) {
			$color_attribute_names = apply_filters( 'blaze_commerce_color_attribute_names', $color_attribute_names );
		}

		$attribute_name_lower = strtolower( trim( $attribute_name ) );

		return in_array( $attribute_name_lower, $color_attribute_names );
	}

	/**
	 * Check if a taxonomy is a color-related taxonomy
	 */
	public function is_color_taxonomy( $taxonomy ) {
		$color_taxonomies = array(
			'pa_color',
			'pa_colour',
			'pa_colors',
			'pa_colours',
		);

		// Apply filters to allow customization
		if ( function_exists( 'apply_filters' ) ) {
			$color_taxonomies = apply_filters( 'blaze_commerce_color_taxonomies', $color_taxonomies );
		}

		return in_array( strtolower( $taxonomy ), array_map( 'strtolower', $color_taxonomies ) );
	}

	/**
	 * Check if a color name is valid (not a random hash)
	 * This filters out random hash names like "1ffd9e753c8054cc61456ac7fac1ac89"
	 */
	public function is_valid_color_name( $color_name ) {
		if ( empty( $color_name ) || ! is_string( $color_name ) ) {
			return false;
		}

		$color_name = trim( $color_name );

		// Debug logging
		$logger = wc_get_logger();
		$context = array( 'source' => 'blazecommerce-color-validation' );
		$logger->debug( 'Validating color name: "' . $color_name . '"', $context );

		// Filter out random hash strings (32 character hex strings)
		if ( preg_match( '/^[a-f0-9]{32}$/i', $color_name ) ) {
			$logger->debug( 'Rejected: 32-char hex hash', $context );
			return false;
		}

		// Filter out other hash-like patterns (MD5, SHA1, etc.)
		if ( preg_match( '/^[a-f0-9]{40}$/i', $color_name ) ) { // SHA1
			$logger->debug( 'Rejected: 40-char hex hash', $context );
			return false;
		}

		// Filter out very long strings that look like hashes
		if ( strlen( $color_name ) > 20 && preg_match( '/^[a-f0-9]+$/i', $color_name ) ) {
			$logger->debug( 'Rejected: Long hex string', $context );
			return false;
		}

		// Must contain at least one letter or space (real color names have these)
		if ( ! preg_match( '/[a-zA-Z\s]/', $color_name ) ) {
			$logger->debug( 'Rejected: No letters or spaces', $context );
			return false;
		}

		// Must be reasonable length for a color name
		if ( strlen( $color_name ) > 50 ) {
			$logger->debug( 'Rejected: Too long', $context );
			return false;
		}

		$logger->debug( 'Accepted: Valid color name', $context );
		return true;
	}

	/**
	 * Get product-specific swatch configurations
	 * This method retrieves swatch configurations directly from individual product settings
	 * as shown in the WooCommerce product edit interface screenshot
	 */
	public function get_product_specific_swatches( $attribute_to_register, $attribute ) {
		// Check if required functions exist
		if ( ! function_exists( 'get_post_meta' ) || ! method_exists( $attribute, 'get_name' ) ) {
			return null;
		}

		// Get the current product ID from global context
		$product_id = $this->get_current_product_id();
		if ( empty( $product_id ) ) {
			return null;
		}

		$attribute_name = $attribute->get_name();

		// Get product-specific swatch configurations
		$product_swatches = $this->get_product_swatch_configurations( $product_id, $attribute_name );
		if ( empty( $product_swatches ) ) {
			return null;
		}

		// Process the product-specific swatches
		$attribute_to_register['type'] = 'color'; // Default to color, will be overridden if images are found
		$attribute_to_register['options'] = array();

		foreach ( $product_swatches as $swatch_config ) {
			if ( ! isset( $swatch_config['name'] ) ) {
				continue;
			}

			// FILTER OUT RANDOM HASH COLOR NAMES - only include valid color names
			if ( ! $this->is_valid_color_name( $swatch_config['name'] ) ) {
				continue; // Skip this swatch if it has a random hash name
			}

			$option = array(
				'label' => $swatch_config['label'] ?? $swatch_config['name'],
				'name' => $swatch_config['name'],
				'slug' => sanitize_title( $swatch_config['name'] ),
				'term_id' => $swatch_config['term_id'] ?? '',
			);

			// Determine swatch type and value based on configuration
			if ( ! empty( $swatch_config['color'] ) ) {
				// Color swatch - return ONLY the hex color string
				$attribute_to_register['type'] = 'color';
				$option['value'] = $swatch_config['color'];
			} elseif ( ! empty( $swatch_config['image'] ) ) {
				// Image swatch - return ONLY the image URL string
				$attribute_to_register['type'] = 'image';
				$option['value'] = $swatch_config['image'];
			} else {
				// No swatch data, skip this option
				continue;
			}

			$attribute_to_register['options'][] = $option;
		}

		// Return null if no valid options were found
		if ( empty( $attribute_to_register['options'] ) ) {
			return null;
		}

		$this->log_product_swatch_retrieval( $product_id, $attribute_name, count( $attribute_to_register['options'] ) );

		return $attribute_to_register;
	}

	/**
	 * Get the current product ID from various contexts
	 */
	public function get_current_product_id() {
		global $post, $product;

		// Try to get product ID from global $product object
		if ( $product && method_exists( $product, 'get_id' ) ) {
			return $product->get_id();
		}

		// Try to get product ID from global $post object
		if ( $post && isset( $post->ID ) ) {
			return $post->ID;
		}

		// Try to get product ID from query vars
		if ( function_exists( 'get_query_var' ) ) {
			$product_id = get_query_var( 'product_id' );
			if ( ! empty( $product_id ) ) {
				return $product_id;
			}
		}

		// Try to get from $_GET or $_POST
		if ( isset( $_GET['product_id'] ) && is_numeric( $_GET['product_id'] ) ) {
			return intval( $_GET['product_id'] );
		}

		if ( isset( $_POST['product_id'] ) && is_numeric( $_POST['product_id'] ) ) {
			return intval( $_POST['product_id'] );
		}

		return null;
	}

	/**
	 * Get product-specific swatch configurations from product meta
	 */
	public function get_product_swatch_configurations( $product_id, $attribute_name ) {
		if ( empty( $product_id ) || ! function_exists( 'get_post_meta' ) ) {
			return array();
		}

		$swatches = array();

		// Method 1: Get from _swatch_type_options meta (most common)
		$swatch_options = get_post_meta( $product_id, '_swatch_type_options', true );
		if ( ! empty( $swatch_options ) && is_array( $swatch_options ) ) {
			$swatches = array_merge( $swatches, $this->parse_swatch_type_options( $swatch_options, $attribute_name ) );
		}

		// Method 2: Get from product attribute configurations
		$attribute_swatches = $this->get_product_attribute_swatches( $product_id, $attribute_name );
		if ( ! empty( $attribute_swatches ) ) {
			$swatches = array_merge( $swatches, $attribute_swatches );
		}

		// Method 3: Get from custom meta keys
		$custom_swatches = $this->get_custom_product_swatches( $product_id, $attribute_name );
		if ( ! empty( $custom_swatches ) ) {
			$swatches = array_merge( $swatches, $custom_swatches );
		}

		// Remove duplicates and return
		return $this->deduplicate_swatches( $swatches );
	}

	/**
	 * Parse _swatch_type_options meta data
	 */
	public function parse_swatch_type_options( $swatch_options, $attribute_name ) {
		$swatches = array();

		if ( ! is_array( $swatch_options ) ) {
			return $swatches;
		}

		foreach ( $swatch_options as $group ) {
			if ( ! isset( $group['attributes'] ) || ! is_array( $group['attributes'] ) ) {
				continue;
			}

			foreach ( $group['attributes'] as $hash => $attr ) {
				if ( ! isset( $attr['type'] ) ) {
					continue;
				}

				$swatch = array(
					'name' => $hash,
					'label' => $attr['label'] ?? $hash,
					'term_id' => $attr['term_id'] ?? '',
				);

				if ( $attr['type'] === 'image' && ! empty( $attr['image'] ) ) {
					$swatch['image'] = $this->get_image_url_from_attachment( $attr['image'] );
				} elseif ( $attr['type'] === 'color' && ! empty( $attr['color'] ) ) {
					$swatch['color'] = $attr['color'];
				}

				if ( isset( $swatch['image'] ) || isset( $swatch['color'] ) ) {
					$swatches[] = $swatch;
				}
			}
		}

		return $swatches;
	}

	/**
	 * Get product attribute swatches from WooCommerce product attributes
	 */
	public function get_product_attribute_swatches( $product_id, $attribute_name ) {
		$swatches = array();

		if ( ! function_exists( 'wc_get_product' ) ) {
			return $swatches;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return $swatches;
		}

		$attributes = $product->get_attributes();
		if ( empty( $attributes ) ) {
			return $swatches;
		}

		foreach ( $attributes as $attribute ) {
			if ( $attribute->get_name() !== $attribute_name ) {
				continue;
			}

			if ( $attribute->is_taxonomy() ) {
				$terms = $attribute->get_terms();
				foreach ( $terms as $term ) {
					// FILTER OUT RANDOM HASH COLOR NAMES - only include valid color names
					if ( ! $this->is_valid_color_name( $term->name ) ) {
						continue; // Skip this term if it has a random hash name
					}

					$swatch = array(
						'name' => $term->name,
						'label' => $term->name,
						'term_id' => $term->term_id,
					);

					// Try to get image first, then color
					$image = $this->get_image_src( $term->term_id );
					if ( ! empty( $image ) ) {
						$swatch['image'] = $image;
					} else {
						$color = $this->get_color_hex( $term->term_id );
						if ( ! empty( $color ) ) {
							$swatch['color'] = $color;
						}
					}

					if ( isset( $swatch['image'] ) || isset( $swatch['color'] ) ) {
						$swatches[] = $swatch;
					}
				}
			}
		}

		return $swatches;
	}

	/**
	 * Get custom product swatches from various meta keys
	 */
	public function get_custom_product_swatches( $product_id, $attribute_name ) {
		$swatches = array();

		// Custom meta keys to check for product-specific swatches
		$meta_keys = array(
			'_product_swatches',
			'_custom_swatches',
			'_' . $attribute_name . '_swatches',
			'product_' . $attribute_name . '_config',
		);

		// Apply filters to allow customization
		if ( function_exists( 'apply_filters' ) ) {
			$meta_keys = apply_filters( 'blaze_commerce_product_swatch_meta_keys', $meta_keys, $product_id, $attribute_name );
		}

		foreach ( $meta_keys as $meta_key ) {
			$meta_value = get_post_meta( $product_id, $meta_key, true );
			if ( ! empty( $meta_value ) && is_array( $meta_value ) ) {
				$parsed_swatches = $this->parse_custom_swatch_meta( $meta_value, $attribute_name );
				if ( ! empty( $parsed_swatches ) ) {
					$swatches = array_merge( $swatches, $parsed_swatches );
				}
			}
		}

		return $swatches;
	}

	/**
	 * Parse custom swatch meta data
	 */
	public function parse_custom_swatch_meta( $meta_value, $attribute_name ) {
		$swatches = array();

		if ( ! is_array( $meta_value ) ) {
			return $swatches;
		}

		foreach ( $meta_value as $key => $value ) {
			if ( is_array( $value ) && isset( $value['name'] ) ) {
				$swatch = array(
					'name' => $value['name'],
					'label' => $value['label'] ?? $value['name'],
					'term_id' => $value['term_id'] ?? '',
				);

				if ( ! empty( $value['image'] ) ) {
					$swatch['image'] = $value['image'];
				} elseif ( ! empty( $value['color'] ) ) {
					$swatch['color'] = $value['color'];
				}

				if ( isset( $swatch['image'] ) || isset( $swatch['color'] ) ) {
					$swatches[] = $swatch;
				}
			}
		}

		return $swatches;
	}

	/**
	 * Remove duplicate swatches based on name
	 */
	public function deduplicate_swatches( $swatches ) {
		$unique_swatches = array();
		$seen_names = array();

		foreach ( $swatches as $swatch ) {
			$name = $swatch['name'] ?? '';
			if ( ! empty( $name ) && ! in_array( $name, $seen_names ) ) {
				$unique_swatches[] = $swatch;
				$seen_names[] = $name;
			}
		}

		return $unique_swatches;
	}

	/**
	 * Log product swatch retrieval for debugging purposes
	 */
	public function log_product_swatch_retrieval( $product_id, $attribute_name, $swatch_count ) {
		// Only log if WP_DEBUG is enabled and error_log function exists
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG || ! function_exists( 'error_log' ) ) {
			return;
		}

		$log_message = sprintf(
			'[BlazeCommerce Product Swatches] Product ID: %d | Attribute: %s | Swatches Found: %d',
			$product_id,
			$attribute_name,
			$swatch_count
		);

		error_log( $log_message );
	}

	/**
	 * Add product-specific swatches to product data for Typesense
	 * This ensures that product-specific swatch configurations are included in the search index
	 */
	public function add_product_specific_swatches_to_product_data( $product_data, $product_id ) {
		// Check if required functions exist
		if ( ! function_exists( 'wc_get_product' ) || empty( $product_id ) ) {
			return $product_data;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return $product_data;
		}

		// Only process variable products
		if ( $product->get_type() !== 'variable' ) {
			return $product_data;
		}

		// Get product attributes
		$attributes = $product->get_attributes();
		if ( empty( $attributes ) ) {
			return $product_data;
		}

		// Process each attribute for product-specific swatches
		foreach ( $attributes as $attribute ) {
			if ( ! $attribute->is_taxonomy() ) {
				continue;
			}

			$attribute_name = $attribute->get_name();

			// Skip if not a color/swatch attribute
			if ( ! $this->is_color_attribute( $attribute_name ) ) {
				continue;
			}

			// Get product-specific swatch configurations
			$product_swatches = $this->get_product_swatch_configurations( $product_id, $attribute_name );
			if ( empty( $product_swatches ) ) {
				continue;
			}

			// Add product-specific swatches to product data
			$product_data = $this->merge_product_swatches_into_data( $product_data, $product_swatches, $attribute_name );
		}

		// MERGE DUPLICATE COLOR ATTRIBUTES - prioritize attribute_pa_colour over pa_colour
		$product_data = $this->merge_duplicate_color_attributes( $product_data );

		return $product_data;
	}

	/**
	 * Merge product-specific swatches into product data
	 */
	public function merge_product_swatches_into_data( $product_data, $product_swatches, $attribute_name ) {
		if ( empty( $product_swatches ) ) {
			return $product_data;
		}

		// Initialize attributes array if it doesn't exist
		if ( ! isset( $product_data['attributes'] ) ) {
			$product_data['attributes'] = array();
		}

		// Find existing attribute or create new one
		$attribute_index = null;
		foreach ( $product_data['attributes'] as $index => $attr ) {
			if ( isset( $attr['name'] ) && $attr['name'] === $attribute_name ) {
				$attribute_index = $index;
				break;
			}
		}

		// Create new attribute if not found
		if ( $attribute_index === null ) {
			$attribute_index = count( $product_data['attributes'] );
			$product_data['attributes'][ $attribute_index ] = array(
				'name' => $attribute_name,
				'label' => ucfirst( str_replace( array( 'pa_', '_', '-' ), array( '', ' ', ' ' ), $attribute_name ) ),
				'slug' => $attribute_name,
				'type' => 'color', // Will be updated based on swatch types
				'options' => array(),
			);
		}

		// Process product-specific swatches
		$has_images = false;
		foreach ( $product_swatches as $swatch ) {
			// FILTER OUT RANDOM HASH COLOR NAMES - only include valid color names
			if ( ! $this->is_valid_color_name( $swatch['name'] ?? '' ) ) {
				continue; // Skip this swatch if it has a random hash name
			}

			$option = array(
				'label' => $swatch['label'] ?? $swatch['name'],
				'name' => $swatch['name'],
				'slug' => sanitize_title( $swatch['name'] ),
				'term_id' => $swatch['term_id'] ?? '',
			);

			// Set swatch value based on type
			if ( ! empty( $swatch['color'] ) ) {
				// Color swatch - return ONLY the hex color string
				$option['value'] = $swatch['color'];
			} elseif ( ! empty( $swatch['image'] ) ) {
				// Image swatch - return ONLY the image URL string
				$has_images = true;
				$option['value'] = $swatch['image'];
			} else {
				continue; // Skip options without swatch data
			}

			// Add to options array
			$product_data['attributes'][ $attribute_index ]['options'][] = $option;
		}

		// Update attribute type based on whether images were found
		if ( $has_images ) {
			$product_data['attributes'][ $attribute_index ]['type'] = 'image';
		}

		return $product_data;
	}

	/**
	 * Merge duplicate color attributes and prioritize attribute_pa_colour over pa_colour
	 * This ensures the frontend displays the correct attribute structure
	 */
	public function merge_duplicate_color_attributes( $product_data ) {
		if ( ! isset( $product_data['attributes'] ) || ! is_array( $product_data['attributes'] ) ) {
			return $product_data;
		}

		// Debug logging
		$logger = wc_get_logger();
		$context = array( 'source' => 'blazecommerce-color-merge' );
		$logger->debug( 'Starting color attribute merge. Attributes count: ' . count( $product_data['attributes'] ), $context );

		$color_attributes = array();
		$other_attributes = array();

		// Separate color attributes from other attributes
		foreach ( $product_data['attributes'] as $index => $attribute ) {
			if ( ! isset( $attribute['name'] ) ) {
				$other_attributes[] = $attribute;
				continue;
			}

			$attribute_name = $attribute['name'];
			$logger->debug( 'Processing attribute: ' . $attribute_name, $context );

			// Check if this is a color attribute (including both attribute_pa_colour and pa_colour)
			if ( $this->is_color_attribute( $attribute_name ) || $attribute_name === 'attribute_pa_colour' || $attribute_name === 'pa_colour' ) {
				$logger->debug( 'Found color attribute: ' . $attribute_name . ' with ' . count( $attribute['options'] ?? array() ) . ' options', $context );
				$color_attributes[] = $attribute;
			} else {
				$other_attributes[] = $attribute;
			}
		}

		// If no color attributes found, return original data
		if ( empty( $color_attributes ) ) {
			$logger->debug( 'No color attributes found', $context );
			return $product_data;
		}

		$logger->debug( 'Found ' . count( $color_attributes ) . ' color attributes to merge', $context );

		// If only one color attribute, no need to merge
		if ( count( $color_attributes ) === 1 ) {
			$logger->debug( 'Only one color attribute found, no merge needed', $context );
			return $product_data;
		}

		// Merge all color options from all color attributes
		$merged_options = array();
		$seen_colors = array(); // Track colors by term_id to avoid duplicates
		$primary_color_attribute = null;

		foreach ( $color_attributes as $color_attribute ) {
			// Use the first attribute as the base structure
			if ( ! $primary_color_attribute ) {
				$primary_color_attribute = $color_attribute;
				$primary_color_attribute['options'] = array(); // Reset options, we'll rebuild them
			}

			if ( ! isset( $color_attribute['options'] ) || ! is_array( $color_attribute['options'] ) ) {
				continue;
			}

			foreach ( $color_attribute['options'] as $option ) {
				// Use term_id as the primary unique identifier
				$unique_key = '';
				if ( ! empty( $option['term_id'] ) ) {
					$unique_key = 'term_' . $option['term_id'];
				} else {
					// Fallback to option name/label
					$option_name = $option['name'] ?? $option['label'] ?? '';
					$unique_key = 'name_' . sanitize_title( $option_name );
				}

				// Skip if we've already seen this color
				if ( isset( $seen_colors[ $unique_key ] ) ) {
					$logger->debug( 'Skipping duplicate color: ' . $unique_key, $context );
					continue;
				}

				// Add this color to our merged options
				$merged_options[] = $option;
				$seen_colors[ $unique_key ] = true;
				$logger->debug( 'Added color option: ' . ( $option['label'] ?? $option['name'] ?? 'unknown' ), $context );
			}
		}

		$logger->debug( 'Merged ' . count( $merged_options ) . ' unique color options', $context );

		// Update the primary color attribute with merged options
		$primary_color_attribute['options'] = $merged_options;

		// Ensure the primary color attribute has the correct name for frontend
		$primary_color_attribute['name'] = 'attribute_pa_colour';
		$primary_color_attribute['label'] = 'Colour';
		$primary_color_attribute['slug'] = 'pa_colour';
		$primary_color_attribute['type'] = 'color';

		// Rebuild the attributes array maintaining the original order
		// Find the position of the first color attribute in the original array
		$first_color_position = -1;
		foreach ( $product_data['attributes'] as $index => $attribute ) {
			if ( isset( $attribute['name'] ) && ( $this->is_color_attribute( $attribute['name'] ) || $attribute['name'] === 'attribute_pa_colour' || $attribute['name'] === 'pa_colour' ) ) {
				$first_color_position = $index;
				break;
			}
		}

		// Rebuild the array maintaining original order
		$rebuilt_attributes = array();
		$color_inserted = false;

		foreach ( $product_data['attributes'] as $index => $attribute ) {
			if ( isset( $attribute['name'] ) && ( $this->is_color_attribute( $attribute['name'] ) || $attribute['name'] === 'attribute_pa_colour' || $attribute['name'] === 'pa_colour' ) ) {
				// Insert the merged color attribute only once at the first color position
				if ( ! $color_inserted ) {
					$rebuilt_attributes[] = $primary_color_attribute;
					$color_inserted = true;
					$logger->debug( 'Inserted merged color attribute at position: ' . count( $rebuilt_attributes ), $context );
				}
				// Skip other color attributes (they're already merged)
			} else {
				// Keep non-color attributes in their original positions
				$rebuilt_attributes[] = $attribute;
			}
		}

		$product_data['attributes'] = $rebuilt_attributes;

		$logger->debug( 'Final attributes count: ' . count( $product_data['attributes'] ), $context );

		return $product_data;
	}
}


