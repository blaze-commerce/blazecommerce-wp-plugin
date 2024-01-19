<?php


namespace BlazeWooless\Features;

class EditCartCheckout
{
	private static $instance = null;

	public static function get_instance()
	{
		if (self::$instance === null) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct()
	{
		add_action( 'woocommerce_review_order_before_cart_contents', 'ts_review_order_before_cart_contents', 10 );
	}

	public function ts_review_order_before_cart_contents(){
		echo '<h2>woocommerce_review_order_before_cart_contents</h2>';
	}
}
