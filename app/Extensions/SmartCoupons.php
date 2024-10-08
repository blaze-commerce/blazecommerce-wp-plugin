<?php

namespace BlazeWooless\Extensions;

use \Wt_Smart_Coupon_Restriction_Public;

class SmartCoupons {
	private static $instance = null;
	/**
	 * Wt_Smart_Coupon_Giveaway_Product_Public::class
	 *
	 * @var BCSmartCouponGiveawayProductPublic
	 */
	protected $smart_coupons;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		if ( is_plugin_active( 'wt-smart-coupon-pro/wt-smart-coupon-pro.php' ) ) {
			add_action( 'graphql_register_types', array( $this, 'register_free_product_types' ) );
		}
	}

	public function register_free_product_types() {
		$this->register_types();
		$this->register_fields();
		
		$this->register_mutations();
	}

	public function get_non_individual_discount_quantity($coupon_id) {
		/* allowed quantity */
		$discount_quantity=BCSmartCouponGiveawayProductPublic::get_instance()->get_coupon_meta_value($coupon_id, '_wt_product_discount_quantity');
		return (absint($discount_quantity)===0 ? 1 : $discount_quantity);
	}

	/**
	 *  This method will take coupon applicable count from session created by coupon restriction module
	 *  @since 2.0.5
	 */ 
	public function get_coupon_applicable_count($coupon_id, $coupon_code)
	{
		$frequency=1;
		if(\Wt_Smart_Coupon_Public::module_exists('coupon_restriction'))
		{
			$bogo_applicable_count=Wt_Smart_Coupon_Restriction_Public::get_bogo_applicable_count_session();
			$coupon_code=wc_format_coupon_code($coupon_code);
			$frequency=absint(isset($bogo_applicable_count[$coupon_code]) ? $bogo_applicable_count[$coupon_code] : 1);
			$frequency=($frequency<1 ? 1 : $frequency);

			$wt_sc_bogo_apply_frequency = BCSmartCouponGiveawayProductPublic::get_instance()->get_coupon_meta_value($coupon_id, '_wt_sc_bogo_apply_frequency');
			$frequency=('once'==$wt_sc_bogo_apply_frequency ? 1 : $frequency);
		}

		return $frequency;
	}

	public function register_types()
	{
		register_graphql_object_type('FreeProductItemVariants', [
			'description' => __('Custom product object', 'your-text-domain'),
			'fields' => [
				'productId' => [
					'type' => 'Int',
					'description' => __('The ID of the product', 'your-text-domain'),
				],
				'options' => [
					'type' => 'String',
					'description' => __('Options', 'your-text-domain'),
				],
				'attributeName' => [
					'type' => 'String',
					'description' => __('Attribute name', 'your-text-domain'),
				],
			],
		]);

		register_graphql_object_type('FreeProductItems', [
			'description' => __('Custom product object', 'your-text-domain'),
			'fields' => [
				'id' => [
					'type' => 'Int',
					'description' => __('The ID of the product', 'your-text-domain'),
				],
				'productId' => [
					'type' => 'Int',
					'description' => __('The ID of the product', 'your-text-domain'),
				],
				'name' => [
					'type' => 'String',
					'description' => __('The name of the product', 'your-text-domain'),
				],
				'isPurchasable' => [
					'type' => 'String',
					'description' => __('The price of the product', 'your-text-domain'),
				],
				'image' => [
					'type' => 'String',
					'description' => __('The image of the product', 'your-text-domain'),
				],
				'productDiscountHtml' => [
					'type' => 'String',
					'description' => __('The discount html of the product', 'your-text-domain'),
				],
				'permalink' => [
					'type' => 'String',
					'description' => __('The permalink of the product', 'your-text-domain'),
				],
				'isVariation' => [
					'type' => 'Boolean',
					'description' => __('Is the product a variation?', 'your-text-domain'),
				],
				'variants' => [
					'type' => ['list_of' => 'FreeProductItemVariants'],
					'description' => __('Is the product a variation?', 'your-text-domain'),
				],
				'variationId' => [
					'type' => 'Int',
					'description' => __('The variation ID', 'your-text-domain'),
				],
				'variationAttributes' => [
					'type' => 'String',
					'description' => __('Variation attributes', 'your-text-domain'),
				],
				'quantityMax' => [
					'type' => 'Int',
					'description' => __('Quantity max', 'your-text-domain'),
				],
			],
		]);

		register_graphql_object_type('SmartCoupons', [
			'description' => __('Custom product object', 'your-text-domain'),
			'fields' => [
				'id' => [
					'type' => 'Int',
					'description' => __('The ID of the coupon', 'your-text-domain'),
				],
				'couponId' => [
					'type' => 'String',
					'description' => __('The ID of the coupon', 'your-text-domain'),
				],
				'freeProducts' => [
					'type' => ['list_of' => 'FreeProductItems'],
					'description' => __('Free product items', 'your-text-domain'),
				],
				'buttonLabel' => [
					'type' => 'String',
					'description' => __('The button label', 'your-text-domain'),
				],
			],
		]);
	}

	public function register_fields() {
		register_graphql_field( 'Cart', 'smartCoupons', [ 
			'type' => ['list_of' => 'SmartCoupons'],
			'description' => __( 'Smart coupons', 'wp-graphql-woocommerce' ),
			'resolve' => static function ($cart) {
				$applied_coupons  = $cart->applied_coupons;
				if(empty($applied_coupons))
				{
					return;
				}
				$free_products = array();
				$add_to_cart_all=array();           
				$show_quantity_option=array();

				foreach($applied_coupons as $coupon_code)
				{
					$coupon_code=wc_format_coupon_code($coupon_code);
					$coupon = new \WC_Coupon($coupon_code);
					if(!$coupon)
					{
						continue;
					}

					$coupon_id=$coupon->get_id();
					$add_to_cart_all[$coupon_id]=false;
					$show_quantity_option[$coupon_id]=0;

					$qty_price_data=array(
						'qty'=> SmartCoupons::get_instance()->get_non_individual_discount_quantity($coupon_id), 
						'price'=> BCSmartCouponGiveawayProductPublic::get_instance()->get_coupon_meta_value($coupon_id, '_wt_product_discount_amount'), 
						'price_type'=>BCSmartCouponGiveawayProductPublic::get_instance()->get_coupon_meta_value($coupon_id, '_wt_product_discount_type')
					);

					if(BCSmartCouponGiveawayProductPublic::is_bogo($coupon))
					{
						$bogo_customer_gets = BCSmartCouponGiveawayProductPublic::get_instance()->get_coupon_meta_value($coupon_id, '_wt_sc_bogo_customer_gets');
						if(in_array($bogo_customer_gets, BCSmartCouponGiveawayProductPublic::$bogo_allowed_options_to_display_products))
						{
							if('specific_product'==$bogo_customer_gets)
							{
								$bogo_product_condition=BCSmartCouponGiveawayProductPublic::get_instance()->get_coupon_meta_value($coupon_id, '_wt_sc_bogo_product_condition');
								
								$bogo_products=BCSmartCouponGiveawayProductPublic::get_instance()->get_all_bogo_giveaway_products($coupon_id);

								$frequency=SmartCoupons::get_instance()->get_coupon_applicable_count($coupon_id, $coupon_code);
							
								/**
								 *  Giveaway max quantity checking
								 *  Note: `$bogo_products` is a reference argument for the below function 
								 */
								BCSmartCouponGiveawayProductPublic::get_instance()->check_giveaway_max_quantity($coupon_code, $coupon_id, $bogo_customer_gets, $bogo_product_condition, $bogo_products, $frequency);

								$free_products[$coupon_code]=$bogo_products;                    

								if('and'===$bogo_product_condition)
								{
									$add_to_cart_all[$coupon_id]=true; /* no single add to cart button */
								}

							}else  //same_product_in_the_cart
							{
								$coupon_products = $coupon->get_product_ids();
								if(!BCSmartCouponGiveawayProductPublic::get_instance()->is_product_category_restriction_enabled($coupon_id)) /* No product/category restriction */
								{
									if('same_product_in_the_cart'==$bogo_customer_gets) //Show all products in the cart as giveaway items
									{
										$balance_qty=BCSmartCouponGiveawayProductPublic::get_instance()->prepare_balance_quantity_for_same_product_in_cart($coupon_id, $coupon_code);
										if($balance_qty>0) /* show products only when balance quantity exists */
										{
											$new_coupon_products=BCSmartCouponGiveawayProductPublic::get_instance()->prepare_cart_items_as_giveaway($qty_price_data);
											if(!empty($new_coupon_products))
											{
												$free_products[$coupon_code]=$new_coupon_products;
												$show_quantity_option[$coupon_id]=$balance_qty;
											}
										}

									}

								}else
								{
									if('same_product_in_the_cart'==$bogo_customer_gets)
									{
										$balance_qty=BCSmartCouponGiveawayProductPublic::get_instance()->prepare_balance_quantity_for_same_product_in_cart($coupon_id, $coupon_code);
										if($balance_qty>0) /* show products only when balance quantity exists */
										{
											/* this function will prepare product list based product/category restriction */
											$coupon_products = BCSmartCouponGiveawayProductPublic::get_instance()->prepare_product_list_for_any_product_from_cart($coupon, $qty_price_data);
											if(count($coupon_products)>0)
											{
												$free_products[$coupon_code]=$coupon_products;
												$show_quantity_option[$coupon_id]=$balance_qty;                                    
											}else
											{
												//no products in the coupon restriction section, so use entire cart items
												$coupon_products=BCSmartCouponGiveawayProductPublic::get_instance()->prepare_cart_items_as_giveaway($qty_price_data);
												if(!empty($coupon_products))
												{
													$free_products[$coupon_code]=$coupon_products;
													$show_quantity_option[$coupon_id]=$balance_qty;
												}
											}
										}
										
									}
								}
							}
						}  
					}else
					{

						//get cart item data
						$total_qty=BCSmartCouponGiveawayProductPublic::get_total_coupon_cart_item_qty($coupon_code); //total cart quantity for the coupon
						$total_qty=(is_array($total_qty) && !empty($total_qty) ? array_sum($total_qty) : 0);

						/* allowed maximum quantity */
						$discount_quantity=BCSmartCouponGiveawayProductPublic::get_instance()->get_non_individual_discount_quantity($coupon_id);
						
						if($discount_quantity>$total_qty) /* balance quantity exists. Otherwise it will not show the giveaway products */
						{
							$free_product_id_arr=BCSmartCouponGiveawayProductPublic::get_giveaway_products($coupon_id);
							if(!empty($free_product_id_arr))
							{
								$qty_price_arr=array_fill(0, count($free_product_id_arr), $qty_price_data);
								$new_coupon_products=array_combine($free_product_id_arr, $qty_price_arr);
								$free_products[$coupon_code]=$new_coupon_products;
							}
						}
					}
				}

				$smart_coupons = array();

				foreach($free_products as $coupon_code=>$free_product_items) {
					if(empty($free_product_items))
					{
						continue;
					}

					$coupon_id=wc_get_coupon_id_by_code($coupon_code);
					$smart_coupons[$coupon_id] = array(
						'id' => $coupon_id,
						'couponId' => $coupon_id,
					);

					$single_add_to_cart=true;
					if(isset($add_to_cart_all[$coupon_id]) && $add_to_cart_all[$coupon_id]===true)
					{
						$single_add_to_cart=false;
					}

					$total_purchasable=0;

					foreach($free_product_items as $product_id=>$product_data)
					{
						$smart_coupon_free_product = array();
						$_product = wc_get_product($product_id);           
						if($_product->get_stock_quantity() &&   $_product->get_stock_quantity()<1)
						{
							continue;
						}

						$smart_coupon_free_product['id'] = $product_id;
						$smart_coupon_free_product['productId'] = $product_id;
						$smart_coupon_free_product['name'] = $_product->get_name();

						/* product image */
						$image = wp_get_attachment_image_src( $_product->get_image_id(), 'woocommerce_thumbnail');            
						if(!$image)
						{                
							$parent_product = wc_get_product( $_product->get_parent_id() );
							if($parent_product)
							{
								$image = wp_get_attachment_image_src($parent_product->get_image_id(), 'woocommerce_thumbnail');
							}
						}

						if(!$image) /* image not available so use placeholder image */
						{
							$dimensions = wc_get_image_size('woocommerce_thumbnail');                             
							$image = array(wc_placeholder_img_src('woocommerce_thumbnail'), $dimensions['width'], $dimensions['height'], false);
						}
						$variation_attributes=array(); /* this applicable only for variable products */
						$is_purchasable=BCSmartCouponGiveawayProductPublic::get_instance()->is_purchasable($_product, $variation_attributes);
						
						$smart_coupon_free_product['isPurchasable'] = $is_purchasable;
						
						if($is_purchasable)
						{
							$total_purchasable++;   
						}

						if($image && is_array($image) && isset($image[0])) {
							$smart_coupon_free_product['image'] = $image[0];
						} else {
							$smart_coupon_free_product['image'] = null;
						}

						if(!$_product->is_type('variable')) {
							$smart_coupon_free_product['productDiscountHtml'] = wp_kses_post($_product->get_price_html());
						} else {
							$smart_coupon_free_product['productDiscountHtml'] = wp_kses_post(BCSmartCouponGiveawayProductPublic::get_instance()->get_give_away_discount_text(0, $product_data));
						}

						$smart_coupon_free_product['permalink'] = esc_attr(get_post_permalink($product_id));
						$smart_coupon_free_product['isVariation'] = $_product->is_type('variable');
						
						if($_product->is_type('variable')) /* variation choosing option */
						{
							if($is_purchasable)
							{
								$smart_coupon_free_product['variants'] = array();
									foreach($_product->get_variation_attributes() as $attribute_name => $options)
									{
										$smart_coupon_free_product['variants'][] = array(
											'productId' => $product_id,
											'options' => json_encode($options),
											'attributeName' => $attribute_name,
										);
									}
								$selected_variation_id = $is_purchasable;
								$smart_coupon_free_product['variationId'] = $selected_variation_id;
								$smart_coupon_free_product['variationAttributes'] = esc_attr(json_encode($variation_attributes));
							}
						}

						if($single_add_to_cart && $is_purchasable) 
						{ 
							/* show quantity choosing option */
							if(isset($show_quantity_option[$coupon_id]) && $show_quantity_option[$coupon_id]>0)
							{
								$smart_coupon_free_product['quantityMax'] = esc_attr($show_quantity_option[$coupon_id]);
							}

							if($_product->is_type('variation')) /* this is using in the case of `same_product_in_the_cart` */
							{
								$variation_id   = $product_id;
								$product_id     = $_product->get_parent_id();
								$smart_coupon_free_product['variationId'] = $variation_id;
								$smart_coupon_free_product['productId'] = $product_id;
							}
						}

						$smart_coupons[$coupon_id]['freeProducts'][] = $smart_coupon_free_product;
					}
					
				}

				return $smart_coupons;
			},
		] );
	}

	public function register_mutations()
	{
		register_graphql_mutation(
			'smartCouponAddGiveaway',
			array(
				'inputFields' => array(
					'productId' => array(
						'type' => array( 'non_null' => 'String' ),
						'description' => __( 'The product ID' ),
					),
					'variationId' => array(
						'type' => array( 'non_null' => 'String' ),
						'description' => __( 'The product\'s variation ID' ),
					),
					'attributes' => array(
						'type' => array( 'non_null' => 'String' ),
						'description' => __( 'The product\'s variation ID' ),
					),
					'couponId' => array(
						'type' => array( 'non_null' => 'String' ),
						'description' => __( 'The product\'s variation ID' ),
					),
					'quantity' => array(
						'type' => array( 'non_null' => 'String' ),
						'description' => __( 'The product\'s variation ID' ),
					),
				),
				'outputFields' => array(
					'status' => array(
						'type' => 'String',
						'description' => 'Test output status',
						'resolve' => function ($payload) {
							return $payload['status'];
						},
					),
					'message' => array(
						'type' => 'String',
						'description' => 'Test output status',
						'resolve' => function ($payload) {
							return $payload['message'];
						},
					),
				),
				'mutateAndGetPayload' => function ($input) {
					$coupon_id = (isset($input['couponId']) ?  absint($input['couponId']) : 0);
					$product_id = (isset($input['productId']) ?  absint($input['productId']) : 0);
					$variation_id = (isset($input['variationId']) ?  absint($input['variationId']) : 0);
					$add_to_cart_all = 0;
					// $quantity = (isset($input['quantity']) ?  absint($input['quantity']) : 0);
					$quantity = 1;

					$response = BCSmartCouponGiveawayProductPublic::get_instance()->add_to_cart($coupon_id, $product_id, $variation_id, $add_to_cart_all, $quantity);
					
					
					return $response;
				},
			)
		);
	}
}