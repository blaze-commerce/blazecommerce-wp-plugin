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
