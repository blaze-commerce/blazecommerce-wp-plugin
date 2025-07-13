<?php

function blaze_wooless_custom_logout_redirect( $redirect_to, $requested_redirect_to, $user ) {
	return home_url();
}
add_filter( 'logout_redirect', 'blaze_wooless_custom_logout_redirect', 50, 3 );

/**
 * Get the appropriate URL for plugin integrations
 * Uses site_url for backend/API contexts, home_url for frontend contexts
 *
 * @param string $path Optional path to append
 * @param string $scheme Optional scheme (http, https, etc.)
 * @return string The appropriate URL
 */
function blaze_wooless_get_integration_url( $path = '', $scheme = null ) {
	// Improved error handling: validate input parameters
	if ( ! is_string( $path ) ) {
		$path = '';
	}

	// Check if we should use site_url for this context
	if ( blaze_wooless_should_use_site_url_context() ) {
		return site_url( $path, $scheme );
	}

	return home_url( $path, $scheme );
}

/**
 * Check if current context should use site_url instead of home_url
 *
 * @return bool True if should use site_url, false otherwise
 */
function blaze_wooless_should_use_site_url_context() {
	// Always use site_url in admin area
	if ( is_admin() ) {
		return true;
	}

	// Use site_url for REST API requests
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return true;
	}

	// Use site_url for WooCommerce API requests
	if ( isset( $_GET['wc-api'] ) || isset( $_GET['rest_route'] ) ) {
		return true;
	}

	// Check if request URI contains API patterns
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( $_SERVER['REQUEST_URI'] ) : '';
	$api_patterns = array( '/wp-json/', '/wc-api/', '/woocommerce-api/' );

	foreach ( $api_patterns as $pattern ) {
		if ( strpos( $request_uri, $pattern ) !== false ) {
			return true;
		}
	}

	return false;
}

/**
 * Force a URL to use the site domain instead of home domain
 * Useful for plugin integrations that need to point to the WordPress backend
 *
 * @param string $url The URL to convert
 * @return string The URL with site domain
 */
function blaze_wooless_force_site_domain( $url ) {
	$site_url = get_option( 'siteurl' );
	$home_url = get_option( 'home' );

	// If URLs are the same, no conversion needed
	if ( $site_url === $home_url ) {
		return $url;
	}

	$site_domain = parse_url( $site_url, PHP_URL_HOST );
	$home_domain = parse_url( $home_url, PHP_URL_HOST );

	if ( $site_domain && $home_domain && $site_domain !== $home_domain ) {
		$url = str_replace( $home_domain, $site_domain, $url );
	}

	return $url;
}

add_action( 'wp_footer', 'blaze_commerce_my_account_scripts' );
function blaze_commerce_my_account_scripts() {
	?>
	<script>
		(function ($) {
			var logoutUrl = $('.blz-logout-links').attr('href');
			jQuery('.woocommerce-MyAccount-navigation-link--customer-logout > a').attr('href', logoutUrl);
		})(jQuery)
	</script>
	<?php
}

function blaze_wooless_array_camel_case_keys( $array ) {
	$newArray = array();
	foreach ( $array as $key => $value ) {
		$camelCaseKey = lcfirst( str_replace( ' ', '', ucwords( str_replace( '_', ' ', $key ) ) ) );
		if ( is_array( $value ) ) {
			$newArray[ $camelCaseKey ] = blaze_wooless_array_camel_case_keys( $value );
		} else {
			$newArray[ $camelCaseKey ] = $value;
		}
	}
	return $newArray;
}
if ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'klaviyo/klaviyo.php' ) && is_string_in_current_url('.blz.onl') ) {
	add_action('wp_footer', 'klaviyo_script');
}

function klaviyo_script() {
	$klaviyo_api_key = bw_get_klaviyo_api_key();
	if( ! is_klaviyo_connected() ) {
		if( ! empty( $klaviyo_api_key ) ) {
			$src_url = 'https://static.klaviyo.com/onsite/js/klaviyo.js?company_id=' . esc_attr( $klaviyo_api_key );
			?>
			<script id="klaviyo-staging-script" src="<?php echo esc_url( $src_url ); ?>" async="true"></script>
			<?php
		}
	}
}

function is_klaviyo_connected() {
	$klaviyo_api_key = bw_get_klaviyo_api_key();
	if ( empty( $klaviyo_api_key ) ) {
		return false;
	}

	$url = 'https://a.klaviyo.com/api/v1/metrics?api_key=' . urlencode( $klaviyo_api_key );
	$response = wp_remote_get( $url, array(
		'timeout' => 10,
		'sslverify' => true,
		'user-agent' => 'BlazeCommerce-WP-Plugin/1.0'
	) );

	if ( is_wp_error( $response ) ) {
		error_log( 'Klaviyo API connection error: ' . $response->get_error_message() );
		return false;
	}

	$response_code = wp_remote_retrieve_response_code( $response );
	return $response_code === 200;
}

function is_string_in_current_url( $string ) {
	$http_host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( $_SERVER['HTTP_HOST'] ) : '';
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( $_SERVER['REQUEST_URI'] ) : '';
	$current_url = esc_url_raw( "https://" . $http_host . $request_uri );
	
	if ( strpos($current_url, $string) !== false ) {
		return true;
	}

	return false;
}

/**
 * Test function for automated version bump workflow verification
 *
 * This function is added to test the automated version bumping system.
 * It provides a simple utility for version testing purposes.
 *
 * @since 1.8.1
 * @return string Test message for version bump verification
 */
function blaze_wooless_test_version_bump_feature() {
	return 'Version bump test feature - automated workflow verification';
}