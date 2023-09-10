<?php

function blaze_wooless_custom_logout_redirect($redirect_to, $requested_redirect_to, $user) {
    return home_url();
}
add_filter('logout_redirect', 'blaze_wooless_custom_logout_redirect', 50, 3);
