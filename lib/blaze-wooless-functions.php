<?php

function blaze_wooless_custom_logout_redirect( $redirect_to, $requested_redirect_to, $user ) {
	return home_url();
}
add_filter( 'logout_redirect', 'blaze_wooless_custom_logout_redirect', 50, 3 );

add_action('wp_footer', 'blaze_commerce_my_account_scripts' );
function blaze_commerce_my_account_scripts() {
	?>
	<script>
		(function($) {
			var logoutUrl = $('.blz-logout-links').attr('href');
			jQuery('.woocommerce-MyAccount-navigation-link--customer-logout > a').attr('href', logoutUrl);
		})(jQuery)
	</script>
	<?php
}
