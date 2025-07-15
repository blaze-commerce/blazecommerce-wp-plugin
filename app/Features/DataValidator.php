<?php
/**
 * Data Validator Feature
 *
 * Provides validation and sanitization functions for data throughout the plugin.
 * Helps ensure data integrity before sending to Typesense or processing in WordPress.
 *
 * @package BlazeWooless\Features
 * @since 1.14.6
 */

namespace BlazeWooless\Features;

use BlazeWooless\TypesenseClient;

class DataValidator {
	/**
	 * Singleton instance
	 *
	 * @var DataValidator
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return DataValidator Instance of the class
	 */
	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor
	 * 
	 * Sets up hooks and filters for data validation
	 */
	public function __construct() {
		// Add filters for Typesense data validation
		add_filter( 'blazecommerce/collection/product/typesense_data', array( $this, 'validate_product_data' ), 20, 3 );
		add_filter( 'blazecommerce/collection/taxonomy/typesense_data', array( $this, 'validate_taxonomy_data' ), 20, 3 );
		add_filter( 'blazecommerce/collection/page/typesense_data', array( $this, 'validate_page_data' ), 20, 3 );
		
		// Add filters for API request validation
		add_filter( 'blaze_wooless_api_request_args', array( $this, 'validate_api_request_args' ), 10, 2 );
		
		// Register settings
		add_filter( 'blaze_wooless_general_settings', array( $this, 'register_validation_settings' ), 20, 1 );
	}

	/**
	 * Validate product data before sending to Typesense
	 *
	 * @param array $data Product data
	 * @param int $product_id Product ID
	 * @param object $product Product object
	 * @return array Validated product data
	 */
	public function validate_product_data( $data, $product_id, $product ) {
		// Skip validation if disabled in settings
		if ( ! $this->is_validation_enabled() ) {
			return $data;
		}

		// Validate product name
		if ( isset( $data['name'] ) ) {
			$data['name'] = $this->sanitize_text( $data['name'] );
		}

		// Validate product description
		if ( isset( $data['description'] ) ) {
			$data['description'] = $this->sanitize_html( $data['description'] );
		}

		// Validate product short description
		if ( isset( $data['short_description'] ) ) {
			$data['short_description'] = $this->sanitize_html( $data['short_description'] );
		}

		// Validate prices
		if ( isset( $data['price'] ) ) {
			$data['price'] = $this->validate_numeric( $data['price'] );
		}
		
		if ( isset( $data['regular_price'] ) ) {
			$data['regular_price'] = $this->validate_numeric( $data['regular_price'] );
		}
		
		if ( isset( $data['sale_price'] ) ) {
			$data['sale_price'] = $this->validate_numeric( $data['sale_price'] );
		}

		// Validate URLs
		if ( isset( $data['permalink'] ) ) {
			$data['permalink'] = $this->validate_url( $data['permalink'] );
		}

		// Log validation if debug is enabled
		if ( $this->is_debug_enabled() ) {
			$this->log_validation( 'Product data validated', $product_id );
		}

		return $data;
	}

	/**
	 * Validate taxonomy data before sending to Typesense
	 *
	 * @param array $data Taxonomy data
	 * @param int $term_id Term ID
	 * @param object $term Term object
	 * @return array Validated taxonomy data
	 */
	public function validate_taxonomy_data( $data, $term_id, $term ) {
		// Skip validation if disabled in settings
		if ( ! $this->is_validation_enabled() ) {
			return $data;
		}

		// Validate term name
		if ( isset( $data['name'] ) ) {
			$data['name'] = $this->sanitize_text( $data['name'] );
		}

		// Validate term description
		if ( isset( $data['description'] ) ) {
			$data['description'] = $this->sanitize_html( $data['description'] );
		}

		// Validate URLs
		if ( isset( $data['permalink'] ) ) {
			$data['permalink'] = $this->validate_url( $data['permalink'] );
		}

		// Log validation if debug is enabled
		if ( $this->is_debug_enabled() ) {
			$this->log_validation( 'Taxonomy data validated', $term_id );
		}

		return $data;
	}

	/**
	 * Validate page data before sending to Typesense
	 *
	 * @param array $data Page data
	 * @param int $page_id Page ID
	 * @param object $page Page object
	 * @return array Validated page data
	 */
	public function validate_page_data( $data, $page_id, $page ) {
		// Skip validation if disabled in settings
		if ( ! $this->is_validation_enabled() ) {
			return $data;
		}

		// Validate page title
		if ( isset( $data['title'] ) ) {
			$data['title'] = $this->sanitize_text( $data['title'] );
		}

		// Validate page content
		if ( isset( $data['content'] ) ) {
			$data['content'] = $this->sanitize_html( $data['content'] );
		}

		// Validate URLs
		if ( isset( $data['permalink'] ) ) {
			$data['permalink'] = $this->validate_url( $data['permalink'] );
		}

		// Log validation if debug is enabled
		if ( $this->is_debug_enabled() ) {
			$this->log_validation( 'Page data validated', $page_id );
		}

		return $data;
	}

	/**
	 * Validate API request arguments
	 *
	 * @param array $args Request arguments
	 * @param string $endpoint API endpoint
	 * @return array Validated request arguments
	 */
	public function validate_api_request_args( $args, $endpoint ) {
		// Skip validation if disabled in settings
		if ( ! $this->is_validation_enabled() ) {
			return $args;
		}

		// Validate common request parameters
		if ( isset( $args['query'] ) ) {
			$args['query'] = $this->sanitize_text( $args['query'] );
		}

		if ( isset( $args['per_page'] ) ) {
			$args['per_page'] = $this->validate_integer( $args['per_page'], 1, 100 );
		}

		if ( isset( $args['page'] ) ) {
			$args['page'] = $this->validate_integer( $args['page'], 1 );
		}

		// Log validation if debug is enabled
		if ( $this->is_debug_enabled() ) {
			$this->log_validation( 'API request args validated', $endpoint );
		}

		return $args;
	}

	/**
	 * Register validation settings
	 *
	 * @param array $fields Settings fields
	 * @return array Updated settings fields
	 */
	public function register_validation_settings( $fields ) {
		$fields['wooless_general_settings_section']['options'][] = array(
			'id' => 'enable_data_validation',
			'label' => 'Enable Data Validation',
			'type' => 'checkbox',
			'args' => array(
				'description' => 'Enable data validation and sanitization before sending to Typesense. Improves data integrity and security.',
				'default' => '1'
			),
		);

		$fields['wooless_general_settings_section']['options'][] = array(
			'id' => 'enable_validation_debug',
			'label' => 'Enable Validation Debug Logging',
			'type' => 'checkbox',
			'args' => array(
				'description' => 'Log data validation operations for debugging purposes.',
				'default' => '0'
			),
		);

		return $fields;
	}

	/**
	 * Check if validation is enabled in settings
	 *
	 * @return bool Whether validation is enabled
	 */
	public function is_validation_enabled() {
		$settings = bw_get_general_settings();
		return isset( $settings['enable_data_validation'] ) && $settings['enable_data_validation'] === '1';
	}

	/**
	 * Check if validation debug is enabled in settings
	 *
	 * @return bool Whether validation debug is enabled
	 */
	public function is_debug_enabled() {
		$settings = bw_get_general_settings();
		return isset( $settings['enable_validation_debug'] ) && $settings['enable_validation_debug'] === '1';
	}

	/**
	 * Log validation operation
	 *
	 * @param string $message Log message
	 * @param mixed $context Additional context
	 */
	public function log_validation( $message, $context = null ) {
		$logger = wc_get_logger();
		$log_context = array( 'source' => 'wooless-data-validation' );
		$logger->debug( $message . ': ' . print_r( $context, true ), $log_context );
	}

	/**
	 * Sanitize text
	 *
	 * @param string $text Text to sanitize
	 * @return string Sanitized text
	 */
	public function sanitize_text( $text ) {
		return sanitize_text_field( $text );
	}

	/**
	 * Sanitize HTML content
	 *
	 * @param string $html HTML content to sanitize
	 * @return string Sanitized HTML
	 */
	public function sanitize_html( $html ) {
		return wp_kses_post( $html );
	}

	/**
	 * Validate numeric value
	 *
	 * @param mixed $value Value to validate
	 * @param float $min Minimum allowed value
	 * @param float $max Maximum allowed value
	 * @return float|int Validated numeric value
	 */
	public function validate_numeric( $value, $min = 0, $max = null ) {
		$value = floatval( $value );
		
		if ( $value < $min ) {
			$value = $min;
		}
		
		if ( $max !== null && $value > $max ) {
			$value = $max;
		}
		
		return $value;
	}

	/**
	 * Validate integer value
	 *
	 * @param mixed $value Value to validate
	 * @param int $min Minimum allowed value
	 * @param int $max Maximum allowed value
	 * @return int Validated integer value
	 */
	public function validate_integer( $value, $min = 0, $max = null ) {
		$value = intval( $value );
		
		if ( $value < $min ) {
			$value = $min;
		}
		
		if ( $max !== null && $value > $max ) {
			$value = $max;
		}
		
		return $value;
	}

	/**
	 * Validate URL
	 *
	 * @param string $url URL to validate
	 * @return string Validated URL
	 */
	public function validate_url( $url ) {
		return esc_url_raw( $url );
	}
}
