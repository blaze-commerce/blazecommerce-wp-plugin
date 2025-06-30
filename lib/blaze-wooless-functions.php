<?php

function blaze_wooless_custom_logout_redirect( $redirect_to, $requested_redirect_to, $user ) {
	return home_url();
}
add_filter( 'logout_redirect', 'blaze_wooless_custom_logout_redirect', 50, 3 );

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

function blaze_woolese_array_camel_case_keys( $array ) {
	$newArray = array();
	foreach ( $array as $key => $value ) {
		$camelCaseKey = lcfirst( str_replace( ' ', '', ucwords( str_replace( '_', ' ', $key ) ) ) );
		if ( is_array( $value ) ) {
			$newArray[ $camelCaseKey ] = blaze_woolese_array_camel_case_keys( $value );
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
	$klaviyo_api_key = "W7A7kP";
	if( ! is_klaviyo_connected() ) {
		if( $klaviyo_api_key ) {
			$src_url = 'https://static.klaviyo.com/onsite/js/klaviyo.js?company_id=' . $klaviyo_api_key;
			?>
			<script id="klaviyo-staging-script" src="<?php echo $src_url; ?>" async="true"></script>
			<?php
		}
	}
}

function is_klaviyo_connected() {
	$klaviyo_api_key = "W7A7kP";
	if ( ! empty( $klaviyo_api_key ) ) {
		$url = 'https://a.klaviyo.com/api/v1/metrics?api_key=' . $klaviyo_api_key;
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		$response = curl_exec($ch);
		$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		if ( $httpcode == 200 ) {
			return true;
		}
	}
	return false;
}

function is_string_in_current_url( $string ) {
	// Validate required $_SERVER variables exist
	if ( ! isset( $_SERVER['HTTP_HOST'] ) || ! isset( $_SERVER['REQUEST_URI'] ) ) {
		return false;
	}

	// Sanitize and construct URL
	$host = sanitize_text_field( $_SERVER['HTTP_HOST'] );
	$request_uri = sanitize_text_field( $_SERVER['REQUEST_URI'] );
	$current_url = "https://" . $host . $request_uri;

	// Check if string exists in URL
	return strpos( $current_url, $string ) !== false;
}