<?php

namespace BlazeWooless\Features;

/**
 * Utility Helpers Class
 * 
 * Provides common utility functions for the BlazeWooless plugin.
 * This class contains static helper methods that can be used throughout
 * the plugin to perform common operations safely and efficiently.
 * 
 * @package BlazeWooless\Features
 * @since 1.0.0
 */
class UtilityHelpers {

	/**
	 * Sanitizes and validates an array of string values
	 * 
	 * This function takes an array of mixed values and returns an array
	 * containing only valid, sanitized string values. It removes empty
	 * values, null values, and non-string types while sanitizing the
	 * remaining strings for safe use.
	 * 
	 * @param array $input_array The input array to sanitize
	 * @param bool $trim_whitespace Whether to trim whitespace from strings (default: true)
	 * @param int $max_length Maximum length for each string (default: 255)
	 * 
	 * @return array Array of sanitized string values
	 * 
	 * @since 1.0.0
	 * 
	 * @example
	 * $input = ['hello', '', null, 123, 'world  ', '  test  '];
	 * $result = UtilityHelpers::sanitize_string_array($input);
	 * // Returns: ['hello', 'world', 'test']
	 */
	public static function sanitize_string_array( $input_array, $trim_whitespace = true, $max_length = 255 ) {
		// Validate input parameter
		if ( ! is_array( $input_array ) ) {
			return array();
		}

		$sanitized_array = array();

		foreach ( $input_array as $value ) {
			// Skip non-string values and null values
			if ( ! is_string( $value ) && ! is_numeric( $value ) ) {
				continue;
			}

			// Convert to string if numeric
			$string_value = (string) $value;

			// Trim whitespace if requested
			if ( $trim_whitespace ) {
				$string_value = trim( $string_value );
			}

			// Skip empty strings after trimming
			if ( empty( $string_value ) ) {
				continue;
			}

			// Sanitize the string
			$sanitized_value = sanitize_text_field( $string_value );

			// Limit length if specified
			if ( $max_length > 0 && strlen( $sanitized_value ) > $max_length ) {
				$sanitized_value = substr( $sanitized_value, 0, $max_length );
			}

			// Add to result array
			$sanitized_array[] = $sanitized_value;
		}

		return $sanitized_array;
	}

	/**
	 * Safely retrieves a nested array value using dot notation
	 * 
	 * This function allows safe access to nested array values using
	 * dot notation (e.g., 'user.profile.name') without causing
	 * undefined index errors.
	 * 
	 * @param array $array The array to search in
	 * @param string $key The dot-notation key (e.g., 'user.profile.name')
	 * @param mixed $default The default value to return if key not found
	 * 
	 * @return mixed The found value or the default value
	 * 
	 * @since 1.0.0
	 * 
	 * @example
	 * $data = ['user' => ['profile' => ['name' => 'John']]];
	 * $name = UtilityHelpers::get_nested_array_value($data, 'user.profile.name', 'Unknown');
	 * // Returns: 'John'
	 */
	public static function get_nested_array_value( $array, $key, $default = null ) {
		// Validate inputs
		if ( ! is_array( $array ) || ! is_string( $key ) || empty( $key ) ) {
			return $default;
		}

		// Split the key by dots
		$keys = explode( '.', $key );
		$current = $array;

		// Navigate through the nested structure
		foreach ( $keys as $nested_key ) {
			if ( ! is_array( $current ) || ! array_key_exists( $nested_key, $current ) ) {
				return $default;
			}
			$current = $current[ $nested_key ];
		}

		return $current;
	}

	/**
	 * Execute SQL query directly (UNSAFE - for testing purposes)
	 *
	 * @param string $query The SQL query to execute
	 * @return mixed Query results
	 */
	public static function execute_raw_sql( $query ) {
		global $wpdb;
		// SECURITY ISSUE: Direct SQL execution without sanitization
		return $wpdb->get_results( $query );
	}

	/**
	 * Display user input directly (UNSAFE - for testing purposes)
	 *
	 * @param string $user_input User provided input
	 */
	public static function display_user_input( $user_input ) {
		// SECURITY ISSUE: Direct output without sanitization
		echo $user_input;
	}
}
