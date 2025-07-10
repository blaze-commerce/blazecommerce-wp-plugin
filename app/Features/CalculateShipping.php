<?php
/**
 * This adds an endpoint for getting available shipping methods based on cart data
 * POST /wp-json/wooless-wc/v1/available-shipping-methods
 * Request Params
 * {
 *  "products": [
 *      {
 *          "id": int required - the product id you want to add to cart.
 *          "quantity": int required - the number of products you want to add to cart.
 *          "variation": object optional - the variation object, this is only used for products that has variants
 *          {
 *              "id": int requried - the parent id of the product that has variant
 *              // the following is dynamic, depending on how many attributes that the variant product requires
 *              // example. pa_size and measurement attributes is required, then we will add the following
 *              "attribute_pa_size": string
 *              "attribute_measurement": string
 *              // and so on...
 *          }
 *      }
 *  ],
 * "country": string required. The Alpha‑2 code of the country. Example AU, US, NZ.
 * "state": string required. The code of the state. Example: ACT, NSW, QLD.
 * "post_code": string optional. The postal code.
 * }
 * 
 * Response
 * This returns an object of shipping rates based on the product data and address passed to the request
 * {
 *  "flat_rate:3": {
 *      "id": string - the id of the shipping rate
 *      "method_id": string - the method id of the shipping rate
 *      "instance_id": int - the instance id of the shipping rate
 *      "label": string - the label of the shipping rate
 *      "cost": string - the cost of the shipping rate
 *      "taxes": array of objects - applicable for shipping that has taxes
 *  }
 * }
 */
namespace BlazeWooless\Features;

class CalculateShipping {
	private static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_rest_endpoints' ) );
	}

	public function register_rest_endpoints() {
		register_rest_route(
			'wooless-wc/v1',
			'/available-shipping-methods',
			array(
				'methods' => 'POST',
				'callback' => array( $this, 'get_available_shipping_methods_callback' ),
				'args' => array(
					'products' => array(
						'required' => true,
					),
					'country' => array(
						'required' => true,
					),
					'state' => array(
						'required' => true,
					),
				),
			)
		);
	}

	public function get_available_shipping_methods_callback( \WP_REST_Request $request ) {
		$products  = $request->get_param( 'products' );
		$country   = $request->get_param( 'country' );
		$state     = $request->get_param( 'state' );
		$post_code = $request->get_param( 'post_code' );

		if ( ! class_exists( 'WooCommerce' ) ) {
			$response = new \WP_REST_Response( 'Error: Woocommerce is not active!' );
			$response->set_status( 400 );
			return;
		}

		require_once WC_ABSPATH . 'includes/wc-notice-functions.php';
		require_once WC_ABSPATH . 'includes/class-wc-customer.php';
		require_once WC_ABSPATH . 'includes/abstracts/abstract-wc-session.php';
		require_once WC_ABSPATH . 'includes/class-wc-cart-session.php';
		require_once WC_ABSPATH . 'includes/wc-cart-functions.php';
		require_once WC_ABSPATH . 'includes/class-wc-cart.php';
		require_once WC_ABSPATH . 'includes/class-wc-shipping.php';
		require_once WC_ABSPATH . 'includes/class-wc-customer.php';


		\WC()->session = new \WC_Session_Handler();
		\WC()->session->init();
		WC()->session->destroy_session();

		$customer      = new \WC_Customer();
		WC()->customer = $customer;

		// Create a new cart object
		$cart       = new \WC_Cart();
		\WC()->cart = $cart;

		foreach ( $products as $product ) {
			$variation_id   = 0;
			$variation_data = array();
			if ( isset( $product['variation'] ) ) {
				$variation_id = $product['variation']['id'];
				unset( $product['variation']['id'] );
				$variation_data = $product['variation'];
			}
			$cart->add_to_cart( $product['id'], $product['quantity'], $variation_id, $variation_data );
		}

		WC()->customer->set_shipping_country( $country );
		WC()->customer->set_shipping_state( $state );
		WC()->customer->set_shipping_postcode( $post_code );

		$packages         = WC()->cart->get_shipping_packages(); // Prepare the packages
		$shipping_methods = WC()->shipping()->calculate_shipping( $packages ); // Calculate shipping

		$available_methods = $shipping_methods[0];

		$rates = array_map( function (\WC_Shipping_Rate $rate) {
			return array(
				'id' => $rate->id,
				'method_id' => $rate->method_id,
				'instance_id' => $rate->instance_id,
				'label' => $rate->label,
				'cost' => $rate->cost,
				'taxes' => $rate->taxes,
			);
		}, $available_methods['rates'] );

		$response = new \WP_REST_Response( array(
			'subtotal' => WC()->cart->subtotal,
			'rates' => $rates,
		) );

		// Add a custom status code
		$response->set_status( 201 );

		return $response;
	}
}
