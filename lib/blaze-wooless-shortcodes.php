<?php

add_shortcode('bc_current_year', 'blaze_wooless_current_year');

function blaze_wooless_current_year() {
	$year = date('Y');
    return $year;
}