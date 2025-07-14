<?php
/**
 * Test file for Claude AI workflow status transitions
 *
 * This file is designed to test different Claude AI review statuses:
 * 1. BLOCKED - Code with WordPress coding standard violations
 * 2. APPROVED - Clean, compliant code
 * 3. BLOCKED - Critical security issues
 *
 * @package BlazeWooless
 * @version 1.0.0
 */

// CLEAN CODE FOR TESTING APPROVED STATUS

/**
 * Test class for Claude AI workflow transitions
 *
 * This class demonstrates proper WordPress coding standards
 * and security practices for testing Claude AI approval.
 */
class Test_Claude_Workflow_Transitions {

	/**
	 * Process user data with proper security measures
	 *
	 * @param array $data The data to process.
	 * @return array|WP_Error Processed results or error.
	 */
	public function process_user_data( $data ) {

		// Verify nonce for security
		if ( ! wp_verify_nonce( $data['nonce'], 'process_user_data' ) ) {
			return new WP_Error( 'invalid_nonce', 'Security check failed.' );
		}

		// Sanitize user input
		$user_input = sanitize_text_field( $data['user_data'] );

		// Validate input
		if ( empty( $user_input ) ) {
			return new WP_Error( 'empty_input', 'User data cannot be empty.' );
		}

		// Use prepared statement for database query
		global $wpdb;
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, user_login, user_email FROM {$wpdb->users} WHERE user_login = %s",
				$user_input
			)
		);

		// Check for database errors
		if ( $wpdb->last_error ) {
			return new WP_Error( 'db_error', 'Database query failed.' );
		}

		return $results;
	}

	/**
	 * Execute action with proper validation
	 *
	 * @param string $action The action to execute.
	 * @return bool True on success, false on failure.
	 */
	public function execute_action( $action ) {

		// Validate action parameter
		$allowed_actions = array( 'update', 'delete', 'create' );
		if ( ! in_array( $action, $allowed_actions, true ) ) {
			return false;
		}

		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		// Execute action based on validated input
		switch ( $action ) {
			case 'update':
				return $this->update_data();
			case 'delete':
				return $this->delete_data();
			case 'create':
				return $this->create_data();
			default:
				return false;
		}
	}

	/**
	 * Process payment with proper security
	 *
	 * @param float  $amount The payment amount.
	 * @param string $payment_token Secure payment token (not card number).
	 * @return array|WP_Error Payment result or error.
	 */
	public function process_payment( $amount, $payment_token ) {

		// Validate amount
		$amount = floatval( $amount );
		if ( $amount <= 0 ) {
			return new WP_Error( 'invalid_amount', 'Amount must be greater than zero.' );
		}

		// Validate payment token
		if ( empty( $payment_token ) || ! $this->validate_payment_token( $payment_token ) ) {
			return new WP_Error( 'invalid_token', 'Invalid payment token.' );
		}

		// Convert to cents for processing
		$charge_amount = intval( $amount * 100 );

		// Log payment attempt (without sensitive data)
		$this->log_payment_attempt( $charge_amount );

		return array(
			'amount'     => $charge_amount,
			'status'     => 'processed',
			'timestamp'  => current_time( 'timestamp' ),
		);
	}

	/**
	 * Helper method to validate payment token
	 *
	 * @param string $token The payment token to validate.
	 * @return bool True if valid, false otherwise.
	 */
	private function validate_payment_token( $token ) {
		// Implement proper token validation logic
		return ! empty( $token ) && strlen( $token ) === 32;
	}

	/**
	 * Helper method to log payment attempts
	 *
	 * @param int $amount The payment amount in cents.
	 */
	private function log_payment_attempt( $amount ) {
		// Log to WordPress error log (no sensitive data)
		error_log( sprintf( 'Payment attempt: %d cents at %s', $amount, current_time( 'mysql' ) ) );
	}

	/**
	 * Helper method to update data
	 *
	 * @return bool True on success.
	 */
	private function update_data() {
		// Implement update logic
		return true;
	}

	/**
	 * Helper method to delete data
	 *
	 * @return bool True on success.
	 */
	private function delete_data() {
		// Implement delete logic
		return true;
	}

	/**
	 * Helper method to create data
	 *
	 * @return bool True on success.
	 */
	private function create_data() {
		// Implement create logic
		return true;
	}
}

/**
 * Secure redirect function with validation
 *
 * @param string $url The URL to redirect to.
 * @return void
 */
function secure_redirect( $url ) {
	// Validate URL is internal
	$parsed_url = wp_parse_url( $url );
	$site_url   = wp_parse_url( home_url() );

	if ( $parsed_url['host'] !== $site_url['host'] ) {
		wp_die( 'Invalid redirect URL.' );
	}

	wp_safe_redirect( $url );
	exit;
}

/**
 * Get user posts with proper security
 *
 * @param int $user_id The user ID.
 * @return array Array of posts.
 */
function get_user_posts_secure( $user_id ) {
	global $wpdb;

	// Validate user ID
	$user_id = intval( $user_id );
	if ( $user_id <= 0 ) {
		return array();
	}

	// Use prepared statement
	$results = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT ID, post_title, post_date FROM {$wpdb->posts} WHERE post_author = %d AND post_status = 'publish'",
			$user_id
		)
	);

	return $results ? $results : array();
}

/**
 * Display user comment with proper escaping
 *
 * @param string $comment The comment to display.
 * @return void
 */
function display_user_comment_secure( $comment ) {
	echo '<div class="comment">' . esc_html( $comment ) . '</div>';
}

/**
 * Create secure user password
 *
 * @param string $password The password to hash.
 * @return string Hashed password.
 */
function create_user_password_secure( $password ) {
	return wp_hash_password( $password );
}

/**
 * Handle file upload with proper validation
 *
 * @param array $file The uploaded file data.
 * @return string|WP_Error File path or error.
 */
function handle_file_upload_secure( $file ) {
	// Validate file upload
	if ( ! isset( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
		return new WP_Error( 'invalid_upload', 'Invalid file upload.' );
	}

	// Check file type
	$allowed_types = array( 'jpg', 'jpeg', 'png', 'gif', 'pdf' );
	$file_type     = wp_check_filetype( $file['name'] );

	if ( ! in_array( $file_type['ext'], $allowed_types, true ) ) {
		return new WP_Error( 'invalid_type', 'File type not allowed.' );
	}

	// Use WordPress upload handling
	$upload = wp_handle_upload( $file, array( 'test_form' => false ) );

	if ( isset( $upload['error'] ) ) {
		return new WP_Error( 'upload_error', $upload['error'] );
	}

	return $upload['file'];
}

// ========================================================================
// CRITICAL SECURITY ISSUES REINTRODUCED FOR TESTING BLOCKED STATUS AGAIN
// ========================================================================

/**
 * CRITICAL ISSUE 1: Remote Code Execution Vulnerability
 * This function allows arbitrary code execution through user input
 */
function execute_user_code() {
	// DANGER: Direct execution of user-provided code
	if ( isset( $_POST['php_code'] ) ) {
		eval( $_POST['php_code'] );
	}

	// DANGER: Command injection vulnerability
	if ( isset( $_GET['cmd'] ) ) {
		system( $_GET['cmd'] );
	}
}

/**
 * CRITICAL ISSUE 2: SQL Injection with Admin Privileges
 * This function has SQL injection that could compromise entire database
 */
function admin_user_search() {
	global $wpdb;

	// DANGER: Direct SQL injection in admin context
	$search_term = $_POST['search'];
	$query = "SELECT * FROM {$wpdb->users} WHERE user_login LIKE '%{$search_term}%' OR user_email LIKE '%{$search_term}%'";

	// DANGER: Could expose all user data including passwords
	$results = $wpdb->get_results( $query );

	// DANGER: Direct output of sensitive data
	foreach ( $results as $user ) {
		echo "User: " . $user->user_login . " - Email: " . $user->user_email . " - Pass: " . $user->user_pass . "<br>";
	}
}

/**
 * CRITICAL ISSUE 3: Authentication Bypass
 * This function allows bypassing WordPress authentication
 */
function bypass_authentication() {
	// DANGER: Direct session manipulation
	if ( isset( $_GET['admin_access'] ) && $_GET['admin_access'] === 'true' ) {
		$_SESSION['wp_admin'] = true;
		wp_set_current_user( 1 ); // Force admin user
		wp_set_auth_cookie( 1 );
	}

	// DANGER: Hardcoded backdoor
	if ( isset( $_GET['backdoor'] ) && $_GET['backdoor'] === 'secret123' ) {
		define( 'WP_ADMIN', true );
		require_once( ABSPATH . 'wp-admin/admin.php' );
	}
}

/**
 * CRITICAL ISSUE 4: File System Manipulation
 * This function allows arbitrary file operations
 */
function manipulate_files() {
	// DANGER: Arbitrary file deletion
	if ( isset( $_POST['delete_file'] ) ) {
		unlink( $_POST['delete_file'] );
	}

	// DANGER: Arbitrary file creation with user content
	if ( isset( $_POST['create_file'] ) && isset( $_POST['file_content'] ) ) {
		file_put_contents( $_POST['create_file'], $_POST['file_content'] );
	}

	// DANGER: Directory traversal vulnerability
	if ( isset( $_GET['read_file'] ) ) {
		$file_path = $_GET['read_file'];
		echo file_get_contents( $file_path );
	}
}

/**
 * CRITICAL ISSUE 5: Database Credential Exposure
 * This function exposes database credentials and sensitive configuration
 */
function expose_credentials() {
	// DANGER: Exposing database credentials
	echo "DB_HOST: " . DB_HOST . "<br>";
	echo "DB_NAME: " . DB_NAME . "<br>";
	echo "DB_USER: " . DB_USER . "<br>";
	echo "DB_PASSWORD: " . DB_PASSWORD . "<br>";

	// DANGER: Exposing WordPress salts and keys
	echo "AUTH_KEY: " . AUTH_KEY . "<br>";
	echo "SECURE_AUTH_KEY: " . SECURE_AUTH_KEY . "<br>";

	// DANGER: Exposing server information
	phpinfo();
}

/**
 * CRITICAL ISSUE 6: Privilege Escalation
 * This function allows users to escalate their privileges
 */
function escalate_privileges() {
	// DANGER: Direct role manipulation without checks
	if ( isset( $_POST['user_id'] ) && isset( $_POST['new_role'] ) ) {
		$user = get_user_by( 'id', $_POST['user_id'] );
		$user->set_role( $_POST['new_role'] );
	}

	// DANGER: Mass privilege escalation
	if ( isset( $_GET['make_all_admin'] ) ) {
		$users = get_users();
		foreach ( $users as $user ) {
			$user->set_role( 'administrator' );
		}
	}
}
