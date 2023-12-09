<?php

function blaze_wooless_custom_logout_redirect($redirect_to, $requested_redirect_to, $user) {
    return home_url();
}
add_filter('logout_redirect', 'blaze_wooless_custom_logout_redirect', 50, 3);

function blaze_commerce_change_my_account_endpoint_urls( $url, $endpoint, $value, $permalink ) {
    $logout_link = home_url() . '?action=logout';

    switch($endpoint) {
        case 'customer-logout':
            $url = $logout_link;
            break;
    }

    return $url;
}
add_filter('woocommerce_get_endpoint_url', 'blaze_commerce_change_my_account_endpoint_urls', 10, 4);

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
