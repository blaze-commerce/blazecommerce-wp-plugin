<?php

namespace BlazeWooless\Settings;

class ExportImportSettings extends BaseSettings {
	private static $instance = null;
	public $tab_key = 'export_import';
	public $page_label = 'Export/Import';

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self( 'wooless_export_import_settings_options' );
		}

		return self::$instance;
	}

	public function register_hooks() {
		add_action( 'wp_ajax_blaze_export_settings', array( $this, 'handle_export_settings' ) );
		add_action( 'wp_ajax_blaze_import_settings', array( $this, 'handle_import_settings' ) );
		add_action( 'admin_init', array( $this, 'handle_form_export' ) );
	}

	public function settings_callback( $options ) {
		// Handle file upload for import
		if ( isset( $_FILES['import_file'] ) && ! empty( $_FILES['import_file']['tmp_name'] ) ) {
			$this->handle_import_file();
		}

		return $options;
	}

	public function settings() {
		return array();
	}

	public function footer_callback() {
		require_once BLAZE_WOOLESS_PLUGIN_DIR . 'views/export-import-settings.php';
	}

	/**
	 * Get all plugin settings option keys
	 */
	private function get_all_settings_keys() {
		return array(
			// Main settings from BaseSettings subclasses
			'wooless_general_settings_options',
			'wooless_regional_settings_options',
			'wooless_product_filters_settings_options',
			'wooless_settings_product_page_options',
			'wooless_settings_category_page_options',
			'wooless_header_settings_options',
			'wooless_footer_settings_options',
			'wooless_homepage_settings_options',
			'wooless_synonym_settings_options',
			'wooless_synonyms',

			// Additional content and layout settings
			'blaze_wooless_product_filters_content',
			'blaze_wooless_homepage_layout',
			'free_shipping_threshold',

			// Extension and integration settings
			'wooless_custom_jwt_secret_key',
			'judgeme_widget_html_miracle',
			'judgeme_widget_settings',
			'judgeme_shop_token',
			'blaze_commerce_judgeme_product_reviews',
			'blaze_commerce_yotpo_product_reviews',
			'yotpo_settings',
			'nipv_setting_option',
			'wcact_settings',
		);
	}

	/**
	 * Export all settings to JSON
	 */
	public function export_settings() {
		$settings_keys = $this->get_all_settings_keys();
		$export_data = array();

		foreach ( $settings_keys as $key ) {
			$value = get_option( $key );
			if ( $value !== false ) {
				$export_data[ $key ] = $value;
			}
		}

		// Add metadata
		$export_data['_export_metadata'] = array(
			'plugin_version' => BLAZE_WOOLESS_VERSION,
			'export_date' => current_time( 'mysql' ),
			'site_url' => site_url(),
			'wp_version' => get_bloginfo( 'version' ),
		);

		return $export_data;
	}

	/**
	 * Import settings from JSON data
	 */
	public function import_settings( $import_data ) {
		if ( ! is_array( $import_data ) ) {
			return array( 'success' => false, 'message' => 'Invalid import data format.' );
		}

		// Check if this is a valid Blaze Commerce export
		if ( ! isset( $import_data['_export_metadata'] ) ) {
			return array( 'success' => false, 'message' => 'This does not appear to be a valid Blaze Commerce settings export file.' );
		}

		$settings_keys = $this->get_all_settings_keys();
		$imported_count = 0;
		$skipped_count = 0;
		$errors = array();

		foreach ( $settings_keys as $key ) {
			if ( isset( $import_data[ $key ] ) ) {
				// Skip empty values to avoid overwriting existing settings with empty data
				if ( empty( $import_data[ $key ] ) && $import_data[ $key ] !== '0' && $import_data[ $key ] !== 0 ) {
					$skipped_count++;
					continue;
				}

				$result = update_option( $key, $import_data[ $key ] );
				if ( $result !== false ) {
					$imported_count++;
				} else {
					// Check if the option already exists with the same value
					$existing_value = get_option( $key );
					if ( $existing_value === $import_data[ $key ] ) {
						$imported_count++; // Count as successful even if no update was needed
					} else {
						$errors[] = "Failed to import setting: {$key}";
					}
				}
			}
		}

		$message = "Successfully imported {$imported_count} settings.";
		if ( $skipped_count > 0 ) {
			$message .= " Skipped {$skipped_count} empty settings.";
		}

		if ( $imported_count > 0 ) {
			return array(
				'success' => true,
				'message' => $message,
				'errors' => $errors
			);
		} else {
			return array(
				'success' => false,
				'message' => 'No settings were imported.',
				'errors' => $errors
			);
		}
	}

	/**
	 * Handle AJAX export request
	 */
	public function handle_export_settings() {
		// Check nonce and permissions
		if ( ! wp_verify_nonce( $_POST['nonce'], 'blaze_export_import_nonce' ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Security check failed' );
		}

		$export_data = $this->export_settings();
		$json_content = json_encode( $export_data, JSON_PRETTY_PRINT );
		$filename = 'blaze-commerce-settings-' . date( 'Y-m-d-H-i-s' ) . '.json';

		// Clear any previous output
		if ( ob_get_level() ) {
			ob_end_clean();
		}

		// Set proper headers for file download
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $json_content ) );
		header( 'Cache-Control: no-cache, must-revalidate' );
		header( 'Expires: 0' );

		echo $json_content;
		wp_die();
	}

	/**
	 * Handle AJAX import request
	 */
	public function handle_import_settings() {
		// Check nonce and permissions
		if ( ! wp_verify_nonce( $_POST['nonce'], 'blaze_export_import_nonce' ) || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
		}

		// Check if file was uploaded
		if ( ! isset( $_FILES['import_file'] ) || empty( $_FILES['import_file']['tmp_name'] ) ) {
			wp_send_json_error( array( 'message' => 'No file uploaded' ) );
		}

		$file = $_FILES['import_file'];

		// Validate file
		if ( $file['error'] !== UPLOAD_ERR_OK ) {
			wp_send_json_error( array( 'message' => 'File upload failed' ) );
		}

		// Check file type
		$file_info = pathinfo( $file['name'] );
		if ( strtolower( $file_info['extension'] ) !== 'json' ) {
			wp_send_json_error( array( 'message' => 'Please upload a JSON file' ) );
		}

		// Read and decode JSON
		$json_content = file_get_contents( $file['tmp_name'] );
		$import_data = json_decode( $json_content, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			wp_send_json_error( array( 'message' => 'Invalid JSON file format' ) );
		}

		// Import settings
		$result = $this->import_settings( $import_data );

		if ( $result['success'] ) {
			wp_send_json_success( array(
				'message' => $result['message'],
				'errors' => $result['errors']
			) );
		} else {
			wp_send_json_error( array(
				'message' => $result['message'],
				'errors' => $result['errors']
			) );
		}
	}

	/**
	 * Handle form-based export (fallback)
	 */
	public function handle_form_export() {
		if ( isset( $_POST['action'] ) && $_POST['action'] === 'blaze_export_settings_form' ) {
			// Check nonce and permissions
			if ( ! wp_verify_nonce( $_POST['export_form_nonce'], 'blaze_export_settings_form' ) || ! current_user_can( 'manage_options' ) ) {
				wp_die( 'Security check failed' );
			}

			$this->handle_export_settings();
		}
	}

	/**
	 * Handle file upload import
	 */
	private function handle_import_file() {
		// Check permissions first
		if ( ! current_user_can( 'manage_options' ) ) {
			add_settings_error(
				'blaze_import_error',
				'security_error',
				'You do not have permission to import settings.',
				'error'
			);
			return;
		}

		// Check nonce - WordPress settings API uses different nonce
		$nonce_valid = false;

		// Try our custom nonce first
		if ( isset( $_POST['import_nonce'] ) && wp_verify_nonce( $_POST['import_nonce'], 'blaze_import_settings_nonce' ) ) {
			$nonce_valid = true;
		}

		// Try WordPress settings API nonce as fallback
		if ( ! $nonce_valid && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], $this->option_key . '-options' ) ) {
			$nonce_valid = true;
		}

		if ( ! $nonce_valid ) {
			// Debug information (remove in production)
			$debug_info = '';
			if ( WP_DEBUG ) {
				$debug_info = ' Debug: ';
				$debug_info .= isset( $_POST['import_nonce'] ) ? 'Custom nonce present, ' : 'Custom nonce missing, ';
				$debug_info .= isset( $_POST['_wpnonce'] ) ? 'WP nonce present' : 'WP nonce missing';
			}

			add_settings_error(
				'blaze_import_error',
				'security_error',
				'Security check failed. Please try again.' . $debug_info,
				'error'
			);
			return;
		}

		$file = $_FILES['import_file'];

		// Validate file
		if ( $file['error'] !== UPLOAD_ERR_OK ) {
			add_settings_error(
				'blaze_import_error',
				'upload_error',
				'File upload failed.',
				'error'
			);
			return;
		}

		// Check file type
		$file_info = pathinfo( $file['name'] );
		if ( strtolower( $file_info['extension'] ) !== 'json' ) {
			add_settings_error(
				'blaze_import_error',
				'file_type_error',
				'Please upload a JSON file.',
				'error'
			);
			return;
		}

		// Read and decode JSON
		$json_content = file_get_contents( $file['tmp_name'] );
		$import_data = json_decode( $json_content, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			add_settings_error(
				'blaze_import_error',
				'json_error',
				'Invalid JSON file format.',
				'error'
			);
			return;
		}

		// Import settings
		$result = $this->import_settings( $import_data );

		if ( $result['success'] ) {
			add_settings_error(
				'blaze_import_success',
				'import_success',
				$result['message'],
				'updated'
			);

			// Show any errors that occurred during import
			if ( ! empty( $result['errors'] ) ) {
				foreach ( $result['errors'] as $error ) {
					add_settings_error(
						'blaze_import_warning',
						'import_warning',
						$error,
						'notice-warning'
					);
				}
			}
		} else {
			add_settings_error(
				'blaze_import_error',
				'import_error',
				$result['message'],
				'error'
			);
		}
	}
}
