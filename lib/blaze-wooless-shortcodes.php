<?php

add_shortcode('bc_current_date', 'blaze_wooless_current_date');

function blaze_wooless_current_date( $atts ) {
	$formatted_date = date( $atts['format'] );
    return $formatted_date;
}