<?php

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