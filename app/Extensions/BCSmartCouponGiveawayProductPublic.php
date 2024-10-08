<?php

namespace BlazeWooless\Extensions;

use \Wt_Smart_Coupon_Giveaway_Product;
use \Wt_Smart_Coupon;
use \Wt_Smart_Coupon_Security_Helper;
use \Wt_Smart_Coupon_Admin;
use \Wt_Smart_Coupon_Common;
use \Wt_Smart_Coupon_Restriction_Public;
use \Wt_Smart_Coupon_Public;
use \Wt_Smart_Coupon_Restriction;
use \Wt_Smart_Coupon_Mulitlanguage;
use \WC_Coupon;

/**
 * Giveaway products public section
 *
 * @link       
 * @since 2.0.1     
 *
 * @package  Wt_Smart_Coupon  
 */
if (!defined('ABSPATH')) {
    exit;
}

if(!class_exists('Wt_Smart_Coupon_Giveaway_Product')) /* common module class not found so return */
{
    return;
}

class BCSmartCouponGiveawayProductPublic extends Wt_Smart_Coupon_Giveaway_Product
{
    public $module_base='giveaway_product';
    public $module_id='';
    public static $module_id_static='';
    private static $instance = null;
    public static $bogo_allowed_options_to_display_products=array('specific_product', 'same_product_in_the_cart');
    public static $bogo_eligible_session_id='wt_sc_bogo_eligible';
    public static $break_add_to_cart_loop_session_id='wt_sc_break_add_to_cart_loop'; /* this is used to break add to cart indefinite looping when cart contents convert as giveaway */
    public static $giveaway_count_adjust=false;
    public static $specific_product_addtocart_hooked=false; /* To check single specific product add to cart is hooked already */
    public static $giveaway_fully_availed_flag='fully_availed'; /* value to indicate giveaway was fully availed. This value is used to hide the giveaway eligible message */
    
    private static $cheapest_giveaway_loop_count = 0;
    private static $cheapest_giveaway_frequency_backup = 0;

    public static $bogo_discounts=array(); /* BOGO coupon type giveaway total discount */
    
    private static $allowed_customer_gets_cheapest_giveaway = array('any_product_from_category', 'any_product_from_store', 'any_product_from_category_in_the_cart'); /* `Customer gets` allowed for cheapest giveaway option. */

    public function __construct()
    {
        $this->module_id=Wt_Smart_Coupon::get_module_id($this->module_base);
        self::$module_id_static=$this->module_id;
    }

    /**
     * Get Instance
     */
    public static function get_instance()
    {
        if(self::$instance==null)
        {
            self::$instance=new BCSmartCouponGiveawayProductPublic();
        }
        return self::$instance;
    }

    /**
     *  Show giveaway available message after applying a coupon. Applicable for any_product_from_category, any_product_from_store, any_product_from_category_in_the_cart
     *  
     *  @since 2.0.4
     *  @since 2.0.7  Added any product from category in the cart option
     */
    public function show_giveaway_eligible_message()
    {
        $cart = WC()->cart;
        
        if(is_null($cart))
        {
            return;
        }

        $coupons = $cart->get_applied_coupons();
        $coupons = (!is_array($coupons) ? array() : $coupons);
        
        $bogo_eligible = self::get_bogo_eligible_session();
        
        /* Alter the message or set as empty to hide the message on current page */
        $bogo_eligible = apply_filters('wt_sc_alter_giveaway_eligible_message', $bogo_eligible);
        $bogo_eligible = (!is_array($bogo_eligible) ? array() : $bogo_eligible);
        
        foreach($bogo_eligible as $coupon_code => $message)
        {
            if(in_array($coupon_code, $coupons))
            {
                if("" !== $message && $message !== self::$giveaway_fully_availed_flag)
                {
                    wc_add_notice($message, 'notice');
                }
            }else
            {
                self::remove_bogo_eligible_session($coupon_code);
            }
        }
    }
    
    
    /**
     * Check the coupon valid. 
     * If multiple giveaway products show option to choose products, otherwise add giveaway to cart
     * 
     *  @since 2.0.4 Added BOGO option compatibility
     *  @since 2.0.7 Added compatibility for any product from category in the cart
     */
    public function add_giveaway_products_with_coupon($valid, $coupon)
    {
        $coupon_code = wc_format_coupon_code($coupon->get_code());
        if(!$valid)
        {
            self::remove_bogo_eligible_session($coupon_code);
            return false;
        }

        $coupon_id  = $coupon->get_id();   
        
        if(self::is_bogo($coupon))
        {
            $bogo_customer_gets = $this->get_coupon_meta_value($coupon_id, '_wt_sc_bogo_customer_gets');
            if('specific_product' === $bogo_customer_gets)
            {
                $this->process_specific_product_giveaway($coupon_id, $coupon_code);

            }elseif('any_product_from_category' === $bogo_customer_gets || 'any_product_from_store' === $bogo_customer_gets || 'any_product_from_category_in_the_cart' === $bogo_customer_gets) /* any product from store or any product from specific category */
            {
                $this->store_giveaway_available_message($coupon_id, $bogo_customer_gets); /* show message */

            }else
            {
                /**
                 *  same_product_in_the_cart
                 */
                $this->set_hook_to_show_giveaway_products();
            }
            
        }else
        {
            $this->process_specific_product_giveaway($coupon_id, $coupon_code);
        }

        return $valid;
    }

    /**
     *  This function will decide whether to show or add to cart get the giveaway items
     *  For BOGO specific products and normal coupons
     *  @since 2.0.4
     *  @param      int         $coupon_id          ID of coupon
     *  @param      string      $coupon_code        Coupon code
     */
    public function process_specific_product_giveaway($coupon_id, $coupon_code)
    {
        $free_products = self::get_giveaway_products($coupon_id); 
        
        if(!empty($free_products))
        {          
            $first_product = wc_get_product($free_products[0]);
            if(sizeof($free_products)== 1 && $this->is_purchasable($first_product) && 'variable'!==$first_product->get_type())
            {
                $giveaway_data=$this->get_product_giveaway_data($free_products[0], $coupon_code);
                if($this->is_full_free_item($first_product, $giveaway_data))
                {
                    $this->set_hook_to_add_giveaway_products($coupon_code); /* add to cart */
                }else
                {
                    $this->set_hook_to_show_giveaway_products();
                }
            }else
            {            
                $this->set_hook_to_show_giveaway_products();
            }
        }
    }
    
    /** 
     *  This function will hook a callback function to show giveaway products in the cart page
     *  @since 2.0.4
     */
    public function set_hook_to_show_giveaway_products()
    { 
        add_action('woocommerce_after_cart_table', array($this, 'display_give_away_products'), 1); 
    }

    /**
     *  Add required scripts/styles for giveaway products
     *  @since 2.0.5
     */
    public function enqueue_scripts()
    { 
        if(function_exists('is_cart') && is_cart())
        {
            wp_enqueue_style('wt-smart-coupon-giveaway', plugin_dir_url( __FILE__ ).'assets/css/main.css', array(), WEBTOFFEE_SMARTCOUPON_VERSION, 'all');
            wp_enqueue_script('wt-smart-coupon-giveaway', plugin_dir_url( __FILE__ ).'assets/js/main.js', array('jquery'), WEBTOFFEE_SMARTCOUPON_VERSION, false);
        }   
    }

    /**
     *  Prepare array of giveaway list from cart items. This will exclude free items 
     */
    public function prepare_cart_items_as_giveaway($qty_price_data)
    {
        $new_coupon_products=array();
        foreach(WC()->cart->get_cart() as $cart_item)
        {
            if(self::is_a_free_item($cart_item))
            {
                continue;
            }
            $item_id = ($cart_item['variation_id']>0 ? $cart_item['variation_id'] : $cart_item['product_id']);
            $new_coupon_products[$item_id]=$qty_price_data;
        }
        return $new_coupon_products;
    }

    /**
     * Callback function for displaying giveaway products in the cart page.
     * @since 1.0.0
     * @since 1.3.5  [Bug fix] Variation product image not displaying on checkout page
     * @since 2.0.4  Added compatibility with BOGO type coupons
     * @since 2.0.5  Auto hiding giveaway products when all eligible products was added to cart
     */
    public function display_give_away_products()
    {
        global $woocommerce;
        $applied_coupons  = $woocommerce->cart->applied_coupons;
        if(empty($applied_coupons))
        {
            return;
        }

        $free_products=array();
        $add_to_cart_all=array();           
        $show_quantity_option=array();           
        foreach($applied_coupons as $coupon_code)
        {
            $coupon_code=wc_format_coupon_code($coupon_code);
            $coupon = new WC_Coupon($coupon_code);
            if(!$coupon)
            {
                continue;
            }

            $coupon_id=$coupon->get_id();
            $add_to_cart_all[$coupon_id]=false;
            $show_quantity_option[$coupon_id]=0;

            $qty_price_data=array(
                'qty'=>$this->get_non_individual_discount_quantity($coupon_id), 
                'price'=>$this->get_coupon_meta_value($coupon_id, '_wt_product_discount_amount'), 
                'price_type'=>$this->get_coupon_meta_value($coupon_id, '_wt_product_discount_type')
            );

            if(self::is_bogo($coupon))
            {
                $bogo_customer_gets = $this->get_coupon_meta_value($coupon_id, '_wt_sc_bogo_customer_gets');
                if(in_array($bogo_customer_gets, self::$bogo_allowed_options_to_display_products))
                {
                    if('specific_product'==$bogo_customer_gets)
                    {
                        $bogo_product_condition=$this->get_coupon_meta_value($coupon_id, '_wt_sc_bogo_product_condition');
                        
                        $bogo_products=$this->get_all_bogo_giveaway_products($coupon_id);

                        $frequency=$this->get_coupon_applicable_count($coupon_id, $coupon_code);
                       
                        /**
                         *  Giveaway max quantity checking
                         *  Note: `$bogo_products` is a reference argument for the below function 
                         */
                        $this->check_giveaway_max_quantity($coupon_code, $coupon_id, $bogo_customer_gets, $bogo_product_condition, $bogo_products, $frequency);

                        $free_products[$coupon_code]=$bogo_products;                    

                        if('and'===$bogo_product_condition)
                        {
                            $add_to_cart_all[$coupon_id]=true; /* no single add to cart button */
                        }

                    }else  //same_product_in_the_cart
                    {
                        $coupon_products = $coupon->get_product_ids();
                        if(!$this->is_product_category_restriction_enabled($coupon_id)) /* No product/category restriction */
                        {
                            if('same_product_in_the_cart'==$bogo_customer_gets) //Show all products in the cart as giveaway items
                            {
                                $balance_qty=$this->prepare_balance_quantity_for_same_product_in_cart($coupon_id, $coupon_code);
                                if($balance_qty>0) /* show products only when balance quantity exists */
                                {
                                    $new_coupon_products=$this->prepare_cart_items_as_giveaway($qty_price_data);
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
                                $balance_qty=$this->prepare_balance_quantity_for_same_product_in_cart($coupon_id, $coupon_code);
                                if($balance_qty>0) /* show products only when balance quantity exists */
                                {
                                    /* this function will prepare product list based product/category restriction */
                                    $coupon_products = $this->prepare_product_list_for_any_product_from_cart($coupon, $qty_price_data);
                                    if(count($coupon_products)>0)
                                    {
                                        $free_products[$coupon_code]=$coupon_products;
                                        $show_quantity_option[$coupon_id]=$balance_qty;                                    
                                    }else
                                    {
                                        //no products in the coupon restriction section, so use entire cart items
                                        $coupon_products=$this->prepare_cart_items_as_giveaway($qty_price_data);
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
                $total_qty=self::get_total_coupon_cart_item_qty($coupon_code); //total cart quantity for the coupon
                $total_qty=(is_array($total_qty) && !empty($total_qty) ? array_sum($total_qty) : 0);

                /* allowed maximum quantity */
                $discount_quantity=$this->get_non_individual_discount_quantity($coupon_id);
                
                if($discount_quantity>$total_qty) /* balance quantity exists. Otherwise it will not show the giveaway products */
                {
                    $free_product_id_arr=self::get_giveaway_products($coupon_id);
                    if(!empty($free_product_id_arr))
                    {
                        $qty_price_arr=array_fill(0, count($free_product_id_arr), $qty_price_data);
                        $new_coupon_products=array_combine($free_product_id_arr, $qty_price_arr);
                        $free_products[$coupon_code]=$new_coupon_products;
                    }
                }
            }
        }

        if(empty($free_products))
        {
            return;  
        }
        include_once plugin_dir_path( __FILE__ ).'views/_cart_giveaway_products.php';
    }

    /**
     * Ajax action function for getting variation id
     * @since 1.0.0
     */
    public function ajax_find_matching_product_variation_id()
    {
        $out=array('status'=>false, 'status_msg'=>__('Invalid request', 'wt-smart-coupons-for-woocommerce-pro'));
        
        if(check_ajax_referer( 'wt_smart_coupons_public', '_wpnonce', false))
        {         
            if(isset($_POST['attributes']) && isset($_POST['product']))
            {
                $product_id = Wt_Smart_Coupon_Security_Helper::sanitize_item($_POST['product'], 'int');
                $attributes = Wt_Smart_Coupon_Security_Helper::sanitize_item($_POST['attributes'], 'text_arr');
                if($product_id!='' && !empty($attributes))
                {
                    $variation_id=$this->find_matching_product_variation_id($product_id, $attributes);
                    $_product = wc_get_product($variation_id);
                    if($this->is_purchasable($_product))
                    {
                        $out=array('variation_id'=>$variation_id, 'status'=>true, 'status_msg'=>__('Success', 'wt-smart-coupons-for-woocommerce-pro'));
                    }else
                    {
                        $out['status_msg']=__('Sorry! this product is not available for giveaway.', 'wt-smart-coupons-for-woocommerce-pro');
                    }
                }    
            }
        }

        echo json_encode($out);
        wp_die();
    }

    /**
     * Function for getting variation id from product and selected attributes
     * @param $prodcut_id Given Product Id.
     * @param $attributes Attribute values ad key value pair.
     * @since 1.0.0
     */
    public function find_matching_product_variation_id($product_id, $attributes)
    {
        return (new \WC_Product_Data_Store_CPT())->find_matching_product_variation(
            new \WC_Product($product_id),
            $attributes
        );
    }

    /**
     * Helper function to get giveaway product discount text
     * @since 1.2.4
     * @since 2.0.4 Added compatibility with BOGO type coupons
     */
    public function get_give_away_discount_text($coupon_code=0, $product_data=array())
    {
        if($coupon_code>0)
        {
            if(is_int($coupon_code))
            {
                $coupon_id = $coupon_code;
            } else {
                $coupon_id  = wc_get_coupon_id_by_code( $coupon_code );
            }
            $wt_product_discount_amount     = get_post_meta( $coupon_id, '_wt_product_discount_amount',true );
            $wt_product_discount_type       = get_post_meta( $coupon_id, '_wt_product_discount_type',true );
        
        }else
        {
            $dummy_qty_price=self::get_dummy_qty_price();
            $product_data=(empty($product_data) ? $dummy_qty_price : $product_data); 
            $wt_product_discount_amount=(isset($product_data['price']) ? $product_data['price'] : $dummy_qty_price['price']);
            $wt_product_discount_type=(isset($product_data['price_type']) ? $product_data['price_type'] : $dummy_qty_price['price_type']);
        }
      
        
        if(''==$wt_product_discount_amount  || ''==$wt_product_discount_type)
        {
            return '100%';
        }
        switch($wt_product_discount_type)
        {
            case 'percent': 
                $discount_text = $wt_product_discount_amount.'%';
                break;
            default:
                $discount_text = Wt_Smart_Coupon_Admin::get_formatted_price( $wt_product_discount_amount );
        }
        return $discount_text;
    }

    /** 
     * This function will hook a callback function to add giveaway products to the cart
     * 
     */
    public function set_hook_to_add_giveaway_products($coupon_code)
    {
        if(self::$specific_product_addtocart_hooked===false)
        {
            self::$specific_product_addtocart_hooked=true;
            
            /* schedule after coupon applied */ 
            add_action('woocommerce_applied_coupon', array($this, 'add_free_product_into_cart'), 10, 1);
        } 
    }

    /**
     *  When the giveaway scenario: 
     *  1. The giveaway condition is specific product 
     *  2. Only single prodcut with 100% discount
     *  3. Apply repeatedly enabled
     *  This method will be called when product quantity is updated
     *  
     *  @since 2.0.4
     *  @param      string      $cart_item_key      Cart item key
     *  @param      int         $quantity
     *  @param      int         $old_quantity
     *  @param      object      $cart
     */
    public function check_to_add_giveaway($cart_item_key, $quantity, $old_quantity, $cart)
    {
        $cart_item_data = isset($cart->cart_contents[$cart_item_key]) ? $cart->cart_contents[$cart_item_key] : null;
        
        if(is_null($cart_item_data))
        {
            return;
        }

        if(self::is_a_free_item($cart_item_data))
        {
            return; /* already a free item so no need to check */
        }
        
        if($old_quantity<$quantity) //quantity increased
        {
            $cart=WC()->cart;
            $coupons=$cart->get_applied_coupons();
            
            foreach($coupons as $coupon_code)
            {
                $coupon_code=wc_format_coupon_code($coupon_code);
                $coupon = new WC_Coupon($coupon_code);
                
                if(!$coupon)
                {
                    continue;
                }
                
                if(self::is_bogo($coupon))
                {
                    $coupon_id=$coupon->get_id();
                    $bogo_customer_gets = $this->get_coupon_meta_value($coupon_id, '_wt_sc_bogo_customer_gets');
                    
                    if('specific_product'===$bogo_customer_gets)
                    {
                        /* recalculate the apply frequency quantity with the newly added quantity */
                        $this->recalculate_apply_frequency_count($coupon);

                        $this->add_free_product_into_cart($coupon_code);
                    }
                }
            }
        }
    }

    /**
     * Get free product added success message
     * @since 1.3.5
     * @since 2.0.4 Code updated
     *              New argument $giveaway_data - Giveaway price, price type, quantity
     */
    public function get_free_product_added_message($product, $coupon_code, $giveaway_data=array())
    {
        $message='';
        
        if(is_int($product))
        {
            $product = wc_get_product($product);
        }
        
        if($product)
        {
            if(empty($giveaway_data))
            {
                $giveaway_data=$this->get_product_giveaway_data($product->get_id(), $coupon_code);
            }
            
            if($this->is_full_free_item($product, $giveaway_data))
            {
                $message=__("Greetings! you've got a free gift!", 'wt-smart-coupons-for-woocommerce-pro');

            }else
            {
                $discount_text = $this->get_give_away_discount_text(0, $giveaway_data);
                $message=sprintf(__("You're in luck! %s A free product is added to your cart at a %s discount.", 'wt-smart-coupons-for-woocommerce-pro'), '<br/>', $discount_text);
            }
        }
        return apply_filters('wt_sc_alter_free_product_added_message', $message, $product, $coupon_code);
    }

    /**
     *  Add Giveaway product into cart ( When product is single )
     *  @since 1.0.0
     *  @since 1.3.4  [Bug fix] Giveaway product is added repeatedly when logged in back to the site.
     *  @since 2.0.4  Code updated
     *                Added compatibility for BOGO coupon types 
     */
    public function add_free_product_into_cart($coupon_code)
    {
        $cart=WC()->cart;
        $coupons=$cart->get_applied_coupons();
        $coupon_code=wc_format_coupon_code($coupon_code);   
        if(!in_array($coupon_code, $coupons))
        {
            return;
        } 
        
        $coupon_id=wc_get_coupon_id_by_code($coupon_code);
        $free_products=self::get_giveaway_products($coupon_id);
        if(!empty($free_products))
        {          
            $first_product = wc_get_product($free_products[0]);
            if(sizeof($free_products)== 1 && $this->is_purchasable($first_product) && 'variable'!==$first_product->get_type()) /* single product with no variations */
            {
                $item_id=$free_products[0];
                $giveaway_data=$this->get_product_giveaway_data($item_id, $coupon_code); 
                if($this->is_full_free_item($first_product, $giveaway_data)) /* add to cart */
                {    
                    /* This function will prepare quantity based on coupon frequency. If apply repeatedly enabled */
                    $giveaway_qty=$this->prepare_quantity_based_on_apply_frequency($coupon_id, $giveaway_data['qty']);

                    //get cart item data
                    $product_cart_item_qty=self::get_product_cart_item_qty($item_id, $coupon_code);
                    
                    if(empty($product_cart_item_qty)) /* product does not exists in the cart */
                    {
                        $this->add_item_to_cart($item_id, $giveaway_qty, $coupon_code);
                        $success_message=$this->get_free_product_added_message($first_product, $coupon_code, $giveaway_data);
                        if($success_message!="")
                        {
                            wc_add_notice($success_message, 'success');
                        }
                    }else
                    {
                        $total_qty_in_cart=array_sum($product_cart_item_qty);
                        if($total_qty_in_cart<$giveaway_qty) //lesser qty in cart. Case when apply repeatedly enabled and customer increased the cart item quantity
                        {                      
                            $this->add_item_to_cart($item_id, ($giveaway_qty-$total_qty_in_cart), $coupon_code);
                        }
                    }
                }else
                {
                    $this->set_hook_to_show_giveaway_products();
                }
            }else
            {
                $this->set_hook_to_show_giveaway_products();
            }
        }
    }

    /** 
     *  This function will store the message for customer to add products to avail the giveaway
     *  The stored message will show via wp_head hook. Applicable for any_product_from_category, any_product_from_store, any_product_from_category_in_the_cart
     *  
     *  @since 2.0.4    
     *  @since 2.0.7 Added compatibility for `Any product from category in the cart`   
     *  @param      int         $coupon_id              ID of coupon
     *  @param      string      $bogo_customer_gets     BOGO customer gets option value
     */
    public function store_giveaway_available_message($coupon_id, $bogo_customer_gets)
    {
        $coupon_code    = wc_get_coupon_code_by_id($coupon_id);
        $message        = '';
        
        $bogo_eligible  = self::get_bogo_eligible_session();      
        $bogo_eligible  = (!isset($bogo_eligible[$coupon_code]) ? '' : $bogo_eligible[$coupon_code]);

        if('any_product_from_category' === $bogo_customer_gets || 'any_product_from_category_in_the_cart' === $bogo_customer_gets)
        {
            $bogo_free_categories = ('any_product_from_category' === $bogo_customer_gets ? $this->get_coupon_meta_value($coupon_id, '_wt_sc_bogo_free_categories') : $this->get_cart_item_categories_for_coupon($coupon_code, $coupon_id)); 

            if(!empty($bogo_free_categories))
            {
                $cat_arr = get_terms(array(
                    'taxonomy'      => 'product_cat',
                    'orderby'       => 'name',
                    'hide_empty'    => false,
                    'include'       => array_keys($bogo_free_categories),
                ));              

                if(is_array($cat_arr) && $bogo_eligible != self::$giveaway_fully_availed_flag)
                {
                    $cat_link_arr=array();
                    
                    foreach($cat_arr as $cat)
                    {
                        $cat_link_arr[] = '<a href="'.esc_attr(get_term_link($cat->term_id)).'" class="wt_sc_giveaway_category_link">'.esc_html($cat->name).'</a>';
                    }
                    
                    $message     =  sprintf(__("Congrats! you've earned a giveaway by applying coupon %s! Add any products from the following category to your cart to redeem the offer.", 'wt-smart-coupons-for-woocommerce-pro'), "<b>{$coupon_code}</b>");
                    $message    .=  '<br />'; 
                    $message    .=  __("Eligible categories: ", 'wt-smart-coupons-for-woocommerce-pro');
                    $message    .=  implode(", ", $cat_link_arr);
                    
                    self::set_bogo_eligible_session($coupon_id, $message);
                }
            }

        }elseif('any_product_from_store' === $bogo_customer_gets) /* when coupon condition is `all products from store` */
        {
            $message=sprintf(__("Congrats! you've earned a giveaway by applying coupon %s! Add any product to your cart to redeem the offer. ", 'wt-smart-coupons-for-woocommerce-pro'), "<b>{$coupon_code}</b>");           
            self::set_bogo_eligible_session($coupon_id, $message);
        }
    }

    /** 
     *  Check customer was added the full eligible quantities of free products.
     *  This function was using to toggle the giveaway available message. Applicable for any_product_from_category, any_product_from_store, any_product_from_category_in_the_cart
     *  @since 2.0.4    
     *  @param      int         $coupon_id              ID of coupon
     *  @param      string      $coupon_code            Coupon code
     *  @param      int         $max_qty_allowed        Maximum giveaway quantity allowed. `Apply frequency` calculation included.
     *  @param      int         $total_qty_in_cart      Total quantity of giveaway in the cart
     */
    public static function set_bogo_fully_availed($coupon_id, $coupon_code, $max_qty_allowed, $total_qty_in_cart)
    { 
        if($max_qty_allowed <= $total_qty_in_cart)
        {
            //all eligible quantities of free products are in the cart. So can remove the info message
            self::remove_bogo_eligible_session($coupon_code);
            self::set_bogo_eligible_session($coupon_id, self::$giveaway_fully_availed_flag);
                    
        }else
        {
            /* this is to clear the existing eligible session. The value assigning hook will be called later */
            self::remove_bogo_eligible_session($coupon_code);
            self::set_bogo_eligible_session($coupon_id, '');
        }
    }

    /**
     *  Remove BOGO eligible session when the corresponding coupon was removed
     *  @since 2.0.4 
     *  @param coupon code
     */
    public static function remove_bogo_eligible_session($coupon_code)
    {
        $bogo_eligible=self::get_bogo_eligible_session();
        $coupon_code=wc_format_coupon_code($coupon_code);
        if(isset($bogo_eligible[$coupon_code]))
        {
            unset($bogo_eligible[$coupon_code]);
            WC()->session->set(self::$bogo_eligible_session_id, $bogo_eligible);
        }
    }

    /**
     *  Get BOGO eligible sessions if exists
     *  @since 2.0.4 
     *  @return     array   Empty array if not exists, otherwise an array with the session info
     */
    public static function get_bogo_eligible_session()
    {
        $bogo_eligible=WC()->session->get(self::$bogo_eligible_session_id);
        return (is_null($bogo_eligible) ? array() : $bogo_eligible);
    }

    /**
     *  Add the coupon code to BOGO eligible session array
     *  @since 2.0.4 
     *  @param int      coupon id
     *  @param string   value for BOGO eligible session. Here BOGO available message, BOGO fully availed info etc
     */
    public static function set_bogo_eligible_session($coupon_id, $data)
    {
        $bogo_eligible=self::get_bogo_eligible_session();
        $coupon_code=wc_format_coupon_code(wc_get_coupon_code_by_id($coupon_id));
        if(!isset($bogo_eligible[$coupon_code]) || (isset($bogo_eligible[$coupon_code]) && $bogo_eligible[$coupon_code]==""))
        {
            $bogo_eligible[$coupon_code]=$data;
            WC()->session->set(self::$bogo_eligible_session_id, $bogo_eligible);
        }
    }

    /**
     *  Error/Validation messages when giveaway products are adding to cart.
     *  @since 2.0.4
     *  @since 2.0.5    Message is added to wc_notice for removing alert error message on ajax response
     *  @param string $reason reason string
     *  @param array $extra_args extra arguments to process the message
     *  @param string $coupon_type coupon type
     */
    public static function set_add_to_cart_messages($reason, $extra_args=array(), $coupon_type=null)
    {
        $out='';
        switch($reason)
        {
            case "product_id_missing":
            case "coupon_id_missing":
            case "product_not_under_giveaway_list":
                $out=__("Oops! It seems like you've made an invalid request. Please try again.", 'wt-smart-coupons-for-woocommerce-pro');
                break;
            case "product_is_not_a_bogo_product":
            case "given_product_is_not_under_the_category":
            case "non_free_item_of_the_given_category_product_not_in_the_cart":
            case "non_free_product_not_found_in_the_cart": //`same_product_in_the_cart`
                $out=__("Oops! It seems like you've moved an invalid product to cart. Please try again.", 'wt-smart-coupons-for-woocommerce-pro');
                break;
            case "product_max_quantity_reached":
                $out=__("You've exceeded the maximum quantity of products to avail the giveaway.", 'wt-smart-coupons-for-woocommerce-pro');
                break;
            case "coupon_max_quantity_reached":
                $out=__("You've exceeded the maximum quantity allowed as a giveaway.", 'wt-smart-coupons-for-woocommerce-pro');
                break;
            case "no_free_product_in_the_cart":
                $out=__("Something went wrong! It seems like there are no products available for this coupon. Please contact our support team.", 'wt-smart-coupons-for-woocommerce-pro');
                break;
            case "already_availed_bogo":
                $out=__("Seems like you have already moved all the giveaway products in the cart.", 'wt-smart-coupons-for-woocommerce-pro');
                break;
            default:
                $out=__("Oops! It seems like you've made an invalid request. Please try again.", 'wt-smart-coupons-for-woocommerce-pro');
        }

        if(isset($extra_args['apply_frequency']) && 'repeat'==$extra_args['apply_frequency'])
        {
            $out.=" ".__("Please add more products to cart to avail more giveaway.", 'wt-smart-coupons-for-woocommerce-pro');
        }

        $msg=apply_filters('wt_sc_alter_giveaway_addtocart_messages', $out, $reason, $extra_args, $coupon_type);

        wc_add_notice($msg, 'error');
        wc_print_notices();
    }

    /**
     *  Ajax action function for adding Giveaway products into cart.
     *  @since 1.0.0
     *  @since 2.0.4 Added compatibility with BOGO type coupons 
     */
    public function add_to_cart($coupon_id, $product_id, $variation_id, $add_to_cart_all, $quantity)
    {
        if(0===$coupon_id)
        {
            return array(
                "status" => "FAILED",
                "message" => "Coupon ID missing."
            );
        }
        if(0===$add_to_cart_all) /* individual add to cart */
        {
            if(0===$product_id)
            {
                return array(
                    "status" => "FAILED",
                    "message" => "Product ID missing. Coupon ID: " . $coupon_id,
                );
            }
        }

        $coupon=new WC_Coupon($coupon_id);
        $coupon_code=wc_format_coupon_code($coupon->get_code());
        if(self::is_bogo($coupon))
        {
            $bogo_customer_gets = $this->get_coupon_meta_value($coupon_id, '_wt_sc_bogo_customer_gets');
            if('specific_product'===$bogo_customer_gets)
            {
                return $this->specific_product_add_to_cart($coupon, $coupon_id, $coupon_code, $bogo_customer_gets, $product_id, $variation_id); 

            }elseif('same_product_in_the_cart'===$bogo_customer_gets)
            {
                return $this->same_product_add_to_cart($coupon, $coupon_id, $coupon_code, $bogo_customer_gets, $product_id, $variation_id, $quantity);             
            
            }

            /** only the above 2 types are allowed in BOGO **/

        }else
        {
            return $this->non_bogo_add_to_cart($coupon, $coupon_id, $coupon_code, $bogo_customer_gets, $product_id, $variation_id);
        }
        
        $notices=wc_get_notices('error');
        if(count($notices)>0)
        {
            $last_error=end($notices);
            if(isset($last_error['notice']))
            {
                wc_clear_notices(); /* to avoid notice printing on page refresh */
                return array(
                    "status" => "FAILED",
                    "message" => "Something went wrong.",
                );
            }
        }else
        {
            return array(
                "status" => "SUCCESS",
                "message" => "Successfully added to cart",
            );
        }
    }


    /**
     *  Ajax sub function
     *  Add to cart for non BOGO coupon types
     *  @since 2.0.4
     */
    private function non_bogo_add_to_cart($coupon, $coupon_id, $coupon_code, $bogo_customer_gets, $product_id, $variation_id)
    {
        $free_product_id_arr=self::get_giveaway_products($coupon_id);
        $item_id=0;
        if(in_array($variation_id, $free_product_id_arr))
        {
            $item_id=$variation_id;

        }elseif(in_array($product_id, $free_product_id_arr))
        {
            $item_id=$product_id;
        }else
        {
            return array(
                "status" => "FAILED",
                "message" => "Product not under giveaway list.",
            );
        }

        //get cart item data
        $total_qty=self::get_total_coupon_cart_item_qty($coupon_code); //total cart quantity for the coupon
        
        /* allowed maximum quantity */
        $discount_quantity=$this->get_non_individual_discount_quantity($coupon_id);

        if(empty($total_qty)) /* product does not exists in the cart */
        {
            /* no free product in the cart */
            $this->add_item_to_cart(($variation_id>0 ? $variation_id : $product_id), $discount_quantity, $coupon_code);
            return array(
                "status" => "SUCCESS",
                "message" => "No free product in the cart",
            );

        }else
        {            
            
            $total_qty=array_sum($total_qty);
            if($discount_quantity>$total_qty) /* balance quantity exists */
            {
                $this->add_item_to_cart(($variation_id>0 ? $variation_id : $product_id), ($discount_quantity - $total_qty), $coupon_code);
                return array(
                    "status" => "SUCCESS",
                    "message" => "Item added to cart.",
                );

            }else
            {
                return array(
                    "status" => "FAILED",
                    "message" => "Coupon max quantity reached.",
                );
            }
        }
    }

    /**
     *  Get giveaway quantity for `any_product_from_store`, `same_product_in_the_cart`, `any_product_from_category_in_the_cart`
     *  Also this function will calculate the quantity based on `apply repeatedly` option
     *  
     *  @since 2.0.4
     *  @since 2.0.7    $frequency added as an optional argument. If frequency given then value will be prepared based on the given frequency
     *  @param  int      Coupon id
     *  @return int      Quantity
     */
    private function get_quantity_for_non_individual_quantity_bogo($coupon_id, $frequency = null)
    {
        /* allowed quantity */
        $item_qty = $this->get_non_individual_discount_quantity($coupon_id);

        /* apply repeatedly quantity preparation */
        return $this->prepare_quantity_based_on_apply_frequency($coupon_id, $item_qty, $frequency);
    }

    /**
     *  Get non individual discount quantity. 
     *  Applicable for Non BOGO and `any_product_from_store`, `same_product_in_the_cart`, `any_product_from_category_in_the_cart` in BOGO
     *  @since 2.0.5
     *  @param  int      Coupon id
     *  @return int      Quantity
     */
    private function get_non_individual_discount_quantity($coupon_id)
    {
        /* allowed quantity */
        $discount_quantity=$this->get_coupon_meta_value($coupon_id, '_wt_product_discount_quantity');
        return (absint($discount_quantity)===0 ? 1 : $discount_quantity);
    }
    
    /**
     *  Ajax sub function
     *  Add to cart products on `same_product_in_the_cart` as coupon option
     *  @since 2.0.4
     */
    private function same_product_add_to_cart($coupon, $coupon_id, $coupon_code, $bogo_customer_gets, $product_id, $variation_id, $quantity=0)
    {
        //check the current product is in the cart as non giveaway product
        if(!self::non_free_product_exists(array('product_id'=>$product_id, 'variation_id'=>$variation_id)))
        {
            return array(
                "status" => "FAILED",
                "message" => "Non free product not found in cart.",
            );
        }

        /* get balance giveaway allowed quantity */
        $balance_qty=$this->prepare_balance_quantity_for_same_product_in_cart($coupon_id, $coupon_code);
        if($balance_qty<=0)
        {

            /* allowed quantity */
            $item_qty=$this->get_quantity_for_non_individual_quantity_bogo($coupon_id);

            return array(
                "status" => "FAILED",
                "message" => "Coupon max quantity reached.",
            );
        }

        /* This function will prepare product list based on product/category restriction */
        $coupon_products = $this->prepare_product_list_for_any_product_from_cart($coupon);
        
        $add_to_cart_qty=($quantity>0 ? min($balance_qty, $quantity) : $balance_qty);
        $add_to_cart_id=($variation_id>0 ? $variation_id : $product_id);
        
        if(!$this->is_product_category_restriction_enabled($coupon_id) || empty($coupon_products)) /* No product/category restriction */ 
        {           
            $this->add_item_to_cart($add_to_cart_id, $add_to_cart_qty, $coupon_code);
        }else
        {                                                
            if(isset($coupon_products[$product_id]) || isset($coupon_products[$variation_id])) /* current product is under coupon product list */
            {
                $this->add_item_to_cart($add_to_cart_id, $add_to_cart_qty, $coupon_code);
            }
        }

        return array(
            "status" => "SUCCESS",
            "message" => "Item added to cart.",
        );
    }

    /**
     *  Ajax sub function
     *  Add to cart product on `specific_product` as coupon option
     *  @since 2.0.4
     */
    private function specific_product_add_to_cart($coupon, $coupon_id, $coupon_code, $bogo_customer_gets, $product_id, $variation_id)
    {
        $coupon_code=wc_format_coupon_code($coupon_code);
        $bogo_product_condition = $this->get_coupon_meta_value($coupon_id, '_wt_sc_bogo_product_condition');
        $bogo_products=$this->get_all_bogo_giveaway_products($coupon_id);
        
        $frequency=$this->get_coupon_applicable_count($coupon_id, $coupon_code);


        if('and'==$bogo_product_condition) //Add all to cart
        {
            /**
             *  Giveaway max quantity checking
             *  Note: `$bogo_products` is a reference argument for the below function 
             */
            $is_giveaway_fully_added=$this->check_giveaway_max_quantity($coupon_code, $coupon_id, $bogo_customer_gets, $bogo_product_condition, $bogo_products, $frequency, array('update_quantity'=>true));

            if(!empty($bogo_products)) /* after checking the existing items, any remaining items to be added */
            {
                $is_giveaway_fully_added=false;
                $product_id_arr=Wt_Smart_Coupon_Security_Helper::sanitize_item($_POST['product_id_arr'], 'absint_arr');
                $variation_id_arr=Wt_Smart_Coupon_Security_Helper::sanitize_item($_POST['variation_id_arr'], 'absint_arr');
                foreach($product_id_arr as $key=>$product_id)
                {
                    $variation_id=(isset($variation_id_arr[$key]) ? $variation_id_arr[$key] : 0);
                    if($variation_id>0)
                    {
                        if(isset($bogo_products[$variation_id]))
                        {
                            $giveaway_qty=$bogo_products[$variation_id]['qty'];

                        }elseif(isset($bogo_products[$product_id]))
                        {
                            $giveaway_qty=$bogo_products[$product_id]['qty'];
                        }
                        $giveaway_qty=$this->prepare_quantity_based_on_apply_frequency($coupon_id, $giveaway_qty);
                        $this->add_item_to_cart($variation_id, $giveaway_qty, $coupon_code);

                    }else
                    {
                        if(isset($bogo_products[$product_id]))
                        {
                            $giveaway_qty=$this->prepare_quantity_based_on_apply_frequency($coupon_id, $bogo_products[$product_id]['qty']);
                            $this->add_item_to_cart($product_id, $giveaway_qty, $coupon_code);
                        }
                    }
                }
            }

            if($is_giveaway_fully_added)
            {
                return array(
                    "status" => "SUCCESS",
                    "message" => "Already availed giveaway product.",
                );
            }

        }else
        {

            /**
             *  Giveaway max quantity checking
             *  Note: `$bogo_products` is a reference argument for the below function 
             */
            $this->check_giveaway_max_quantity($coupon_code, $coupon_id, $bogo_customer_gets, $bogo_product_condition, $bogo_products, $frequency, array('throw_error'=>true));
            $item_data=array();
            $item_id=0;
            if($variation_id>0 && isset($bogo_products[$variation_id]))
            {
                $item_data=$bogo_products[$variation_id];
                $item_id=$variation_id;

            }elseif(isset($bogo_products[$product_id]))
            {
                $item_data=$bogo_products[$product_id];
                $item_id = ($variation_id > 0 ? $variation_id : $product_id);

            }else
            {
                return array(
                    "status" => "FAILED",
                    "message" => "Product is not a giveaway product.",
                );
            }

            /* allowed quantity */
            $item_data['qty']=(absint($item_data['qty'])===0 ? 1 : $item_data['qty']);

            /* prepare item max quantity based on applicable frequency. If apply repeatedly enabled */
            $giveaway_qty=$this->prepare_quantity_based_on_apply_frequency($coupon_id, $item_data['qty']);

            $product_cart_item_qty=self::get_product_cart_item_qty($item_id, $coupon_code); /* get cart item data. Coupon code is given so return single item array. Here the quantity will be total if multiple records exists */
            if(empty($product_cart_item_qty)) /* product not already added so add it. */
            {
                //add to cart with the $giveaway_qty quantity
                $this->add_item_to_cart(($variation_id>0 ? $variation_id : $product_id), $giveaway_qty, $coupon_code);

            }else /* product already in cart. So check its a free item of current coupon */
            {
                $total_qty=array_sum($product_cart_item_qty); //total cart quantity 
                if($giveaway_qty!=$total_qty) /* quantity mismatch so update */
                {
                    //update quantity. And show a quantity updated message
                    $qty_increment= $giveaway_qty-$total_qty;
                    $this->update_existing_free_item_qty($product_cart_item_qty, $qty_increment);
                }else
                {
                    return array(
                        "status" => "FAILED",
                        "message" => "Product max quantity reached.",
                    );
                }
            }

        }

        return array(
            "status" => "SUCCESS",
            "message" => "Item added to cart.",
        );
    }

    /**
     *  Update giveaway item quantity
     *  @since 2.0.4
     */
    private function update_existing_free_item_qty($product_cart_item_qty, $qty_increment)
    {   
        $cart_item_key_arr=array_keys($product_cart_item_qty);                    
        $cart_item_key=$cart_item_key_arr[0]; /* update quantity of the first item */
        $new_quantity=$product_cart_item_qty[$cart_item_key]+$qty_increment;

        $this->update_cart_qty($cart_item_key, $new_quantity);
    }

    /**
     *  Update cart item quantity
     *  @since 2.0.4
     */
    private function update_cart_qty($cart_item_key, $quantity)
    {
        $cart = WC()->cart; 
        $cart->set_quantity($cart_item_key, $quantity);
    }
    
    /**
     *  Giveaway add to cart function
     *  @since 2.0.4
     *  @param int      $item_id        Product/variation id
     *  @param int      $quantity       Quantity
     *  @param string   $coupon_code    Coupon code
     *  @param int      $category       Category ID, On category wise giveaway [Optional]
     */
    private function add_item_to_cart($item_id, $quantity, $coupon_code, $category='')
    {
        $product = wc_get_product($item_id);
        if($product)
        {
            if(!$this->is_purchasable($product))
            {
                return false;
            }
            if('variable'===$product->get_type())
            {
                return false; /* not possible to add variable parent  */  
            }

            if(!$product->has_enough_stock($quantity))
            {
                $quantity = $product->get_stock_quantity();
                if($quantity===0)
                {
                    return false;
                }
            }
            
            $variation_id   = 0;
            $product_id     = $item_id;
            $variation      = array();

            if($product && 'variation'===$product->get_type())
            {
                $variation_id = $product_id;
                $product_id   = $product->get_parent_id();
                $variation    = $product->get_variation_attributes();  
            }

            $cart_item_data = array(
                'free_product'          => 'wt_give_away_product',
                'free_gift_coupon'      => $coupon_code,
                'free_category'         => $category
            );

            $cart_item_data = apply_filters('wt_sc_alter_giveaway_cart_item_data_before_add_to_cart', $cart_item_data, $product_id, $variation_id, $quantity);
            
            return WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $variation, $cart_item_data);
        }

        return false;
    }

    /**
     *  Checks the product purchasable or not.
     *  If varaible product, checks any of the variation is purchasable, and returns the variation id if successfull, otherwise false will return
     *  @since 2.0.4
     *  @param  Wc_Product
     *  @param  variation attributes    optional    only applicable for variable products, If any of the variation was purchasable, the attributes of first purchasable variation will assigned to this variable.
     *  @return boolean/integer
     */
    public function is_purchasable($_product, &$variation_attributes=array())
    {
        if(is_int($_product))
        {
            $_product=wc_get_product($_product);
        }

        if(!$_product)
        {
            return false;
        }

        if($_product->is_type('variable')) /* variation choosing option */
        {
            $variations=$_product->get_available_variations();
            if(empty($variations) && false!==$variations)
            {
                return false;
            }else
            {
                $is_purchasable=false;
                foreach($variations as $variation)
                { 
                    $variation_id=$variation['variation_id'];
                    $variation_product=wc_get_product($variation_id);
                    if($this->is_purchasable($variation_product)) /* any of the product is purchasable */
                    {
                        $variation_attributes=$variation['attributes'];
                        $is_purchasable=true;
                        break;
                    }
                }

                if(!$is_purchasable) /* all variations are not purchasable */
                {
                    return false;
                }else
                {
                    return $variation_id; // ID of first purchasable variation
                }
            }
        }else
        {
            if(!$_product->has_enough_stock(1))
            {
                $quantity = $_product->get_stock_quantity();
                if($quantity===0)
                {
                    return false;
                }
            }
        }

        return $_product->is_purchasable();
    }

    /**
     *  Register for applicable for giveaway checking. This function will trigger after cart item quantity update
     *  @since 2.0.4 
     */
    public function reg_applicable_for_giveaway($cart_item_key, $quantity, $old_quantity, $cart)
    {
        if($old_quantity<$quantity) //quantity increased
        {
            $cart_item_data=$cart->cart_contents[$cart_item_key];
            $product_id=$cart_item_data['product_id'];
            $variation_id=$cart_item_data['variation_id'];
            $variation=$cart_item_data['variation'];
            $increased_quantity=$quantity-$old_quantity;

            /** 
             *  avoid calling this function on add_to_cart
             *  prevent looping 
             */
            if(isset($_REQUEST['update_cart']) && is_null(WC()->session->get(self::$break_add_to_cart_loop_session_id)))
            {
                $this->applicable_for_giveaway($cart_item_key, $product_id, $increased_quantity, $variation_id, $variation, $cart_item_data);
            }
        }
    }

    
    /**
     *  Checks the newly added item is eligible as giveaway product. If yes then convert the item as giveaway
     *  
     *  @since 2.0.4 
     *  @since 2.0.7    Added compatibility for `Any product from category in the cart`
     */
    public function applicable_for_giveaway($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data)
    {
        global $wt_sc_just_added_coupons; /* just added auto coupons */

        if(self::is_a_free_item($cart_item_data))
        {
            return; /* already a free item so no need to check */
        }

        if(WC()->session->get(self::$break_add_to_cart_loop_session_id)==1)
        {
            WC()->session->set(self::$break_add_to_cart_loop_session_id, null);
            return; /* prevent indefinite loop */
        }
       
        $bogo_eligible=self::get_bogo_eligible_session();
        
        if(!empty($bogo_eligible))
        {
            $cart = WC()->cart;
            $bogo_eligible=array_keys($bogo_eligible); //not needed the eligible message 
            $coupons=$cart->get_applied_coupons();
            $applied_eligible=array_intersect($bogo_eligible, $coupons);
            
            if(!empty($applied_eligible))
            {
                $cart_items=WC()->cart->get_cart();
                
                $current_cart_item = $cart_items[$cart_item_key];
                $current_cart_item_qty = $current_cart_item['quantity'];
                $current_cart_item_price = $current_cart_item['line_subtotal']/$current_cart_item_qty;
                $newly_added_qty = $quantity; //this is for a backup, because value of $quantity may change. This is using in eligibile quantity calculation section

                /* Remove the newly added quantity. This is to avoid the apply frequency calculation issue */
                $new_cart_item_qty = $current_cart_item_qty - $quantity;
                $cart->set_quantity($cart_item_key, $new_cart_item_qty);

                $wt_sc_just_added_coupons=!is_array($wt_sc_just_added_coupons) ? array() : array_unique($wt_sc_just_added_coupons);
                $existing_coupons=array_diff($applied_eligible, $wt_sc_just_added_coupons);
                
                if(!empty($existing_coupons))/* already added coupons are there. So give priority */
                {
                    /* $quantity is a reference argument */
                    $this->set_as_giveaway($existing_coupons, $variation_id, $product_id, $quantity);
                }

                if($quantity>0) /* balance quantity exists */
                {
                    $new_coupon_eligibility_qty = 0; //required minimum eligibility qty for newly added coupons(If exists)

                    if(!empty($wt_sc_just_added_coupons)) /* newly added coupons are there */
                    {
                        $do_qty_chk = false;
                        
                        foreach($wt_sc_just_added_coupons as $i => $coupon_code)
                        {
                            if($quantity<=0)
                            {
                                break;
                            }

                            $coupon_code = wc_format_coupon_code($coupon_code);

                            if(0 === wc_get_coupon_id_by_code($coupon_code))
                            {
                                unset($wt_sc_just_added_coupons[$i]); //this will be usefull for next `foreach` for adding giveaway  
                                continue;
                            }

                            $coupon     = new WC_Coupon($coupon_code);
                            $coupon_id  = $coupon->get_id();
                            $bogo_customer_gets = $this->get_coupon_meta_value($coupon_id, '_wt_sc_bogo_customer_gets');
                            
                            if('any_product_from_category' === $bogo_customer_gets)
                            {
                                $bogo_free_categories = $this->get_coupon_meta_value($coupon_id, '_wt_sc_bogo_free_categories');
                                
                                if(!empty($bogo_free_categories))
                                {
                                    $bogo_free_category_ids = array_keys($bogo_free_categories);
                                    $product_cats   = wc_get_product_cat_ids($product_id);
                                    $matching_cats  = array_intersect($product_cats, $bogo_free_category_ids);
                                    
                                    if(!empty($matching_cats))
                                    {
                                        //check contribution for eligibility by the current product
                                        $do_qty_chk = true;

                                    }else
                                    {
                                        unset($wt_sc_just_added_coupons[$i]);
                                        continue;
                                    }

                                }else
                                {
                                    unset($wt_sc_just_added_coupons[$i]);
                                    continue;
                                }

                            }elseif('any_product_from_store' === $bogo_customer_gets || 'any_product_from_category_in_the_cart' === $bogo_customer_gets)
                            {
                                //check contribution for eligibility by the current product
                                $do_qty_chk = true;

                            }else
                            {
                                //no chance but ..., $bogo_eligible list only contains the eligible coupons
                                continue;
                            }

                            if($do_qty_chk)
                            {
                                $eligibility_qty = $this->get_eligibility_contribution($coupon_code, $newly_added_qty, $current_cart_item_price, $product_id, $variation_id);

                                if($eligibility_qty > ($quantity + $new_coupon_eligibility_qty)) /* the remaining (quantity + the quantity already considered for eligibility) is lesser than the current required eligible quantity */
                                {
                                    unset($wt_sc_just_added_coupons[$i]);
                                    continue; //the coupon will be removed.
                                }else
                                {
                                    /**
                                     *  adjust the quantity.
                                     *  sometimes same product may give eligibility for multiple coupons 
                                     */
                                    if(0 === $new_coupon_eligibility_qty)
                                    {
                                        $quantity -= $eligibility_qty;
                                        $new_coupon_eligibility_qty = $eligibility_qty;

                                    }else
                                    {
                                        if($new_coupon_eligibility_qty < $eligibility_qty) /* product gives eligibility for multiple coupons, current coupon needs more quantity than previous coupon */
                                        {
                                            $quantity -= ($eligibility_qty - $new_coupon_eligibility_qty);

                                            $new_coupon_eligibility_qty = $eligibility_qty; //reset the value with new higher value
                                        }
                                    }                                    
                                }
                            }
                        }

                        if($new_coupon_eligibility_qty > 0)
                        {
                            $this->set_as_normal_cartitem($product_id, $variation_id, $new_coupon_eligibility_qty);
                        }
                        
                        if($quantity>0 && !empty($wt_sc_just_added_coupons)) //balance quantity exists
                        {
                            /* $quantity is a reference argument */
                            $this->set_as_giveaway($wt_sc_just_added_coupons, $variation_id, $product_id, $quantity); 
                        }
                    }

                    if($quantity > 0) /* balance quantity exists. Add it as normal cart item */
                    {
                        $this->set_as_normal_cartitem($product_id, $variation_id, $quantity);
                    }
                }
            }
        }
    }

    private function get_eligibility_contribution($coupon_code, $quantity, $cart_item_price, $product_id, $variation_id)
    {
        $eligibility_qty=1;
        
        if(Wt_Smart_Coupon_Common::module_exists('coupon_restriction'))
        {
            $eligibility_qty=Wt_Smart_Coupon_Restriction_Public::get_eligibility_contribution($coupon_code, $quantity, $cart_item_price, $product_id, $variation_id);
        }

        return $eligibility_qty;
    }

    private function set_as_normal_cartitem($product_id, $variation_id, $quantity)
    {
        $product = wc_get_product(($variation_id>0 ? $variation_id : $product_id));
        $variation = array();

        if('variation'===$product->get_type())
        {
            $variation = $product->get_variation_attributes();
        }

        WC()->session->set(self::$break_add_to_cart_loop_session_id, 1); /* to inform not check again for giveaway */
        
        WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $variation, array());
    }

    private function set_as_giveaway($coupons, $variation_id, $product_id, &$quantity)
    {
        $item_id=($variation_id>0 ? $variation_id : $product_id);
        
        foreach($coupons as $coupon_code)
        {
            $coupon_code=wc_format_coupon_code($coupon_code);
            $coupon=new WC_Coupon($coupon_code);
            
            if($coupon)
            {
                //recalculate the eligibility count for the current coupon. Because we removed the newly added quantity
                $this->recalculate_apply_frequency_count($coupon);                 

                $coupon_id=$coupon->get_id();
                $bogo_customer_gets = $this->get_coupon_meta_value($coupon_id, '_wt_sc_bogo_customer_gets');                       
                $cart_items=WC()->cart->get_cart();
                
                if('any_product_from_category' === $bogo_customer_gets)
                {
                    $bogo_free_categories=$this->get_coupon_meta_value($coupon_id, '_wt_sc_bogo_free_categories');
                    if(!empty($bogo_free_categories))
                    {   
                        $bogo_free_category_ids = array_keys($bogo_free_categories);
                        $product_cats = wc_get_product_cat_ids($product_id);
                        $cat_qty_arr = array(); //already in cart quantity
                        $matching_cats = array_values(array_intersect($product_cats, $bogo_free_category_ids));

                        if(empty($matching_cats)) /* current product not belongs to the coupon categories */
                        {
                            continue;
                        }

                        foreach($cart_items as $item_key=>$cart_item)
                        {
                            if(self::is_a_free_item($cart_item, $coupon_code)) /* a free item under the given coupon */
                            {
                                if(isset($cart_item['free_category']) && in_array($cart_item['free_category'], $matching_cats))
                                {
                                    if(isset($cat_qty_arr[$cart_item['free_category']]))
                                    {
                                        $cat_qty_arr[$cart_item['free_category']]+=$cart_item['quantity'];
                                    }else
                                    {
                                        $cat_qty_arr[$cart_item['free_category']]=$cart_item['quantity'];
                                    }
                                }
                            }
                        }
                        
                        $product = wc_get_product($item_id);

                        $matching_cat_data=$this->sort_category_by_profit($matching_cats, $bogo_free_categories, $product, $coupon_id); /* sort the category based on discount, update quantity based on `Apply repeatedly` option */

                        $total_allowed_free_qty_for_cats=array_sum(array_column($matching_cat_data, 'qty')); //maximum free products allowed for the categories. 
                        $total_free_qty_for_cats_in_the_cart=array_sum($cat_qty_arr);
                        $total_qty_for_free=0;
                        if($total_allowed_free_qty_for_cats>$total_free_qty_for_cats_in_the_cart) //balance qty exists in allowed maximum
                        {
                            $balance_discount_qty=$total_allowed_free_qty_for_cats-$total_free_qty_for_cats_in_the_cart;
                            $total_qty_for_free=min($quantity, $balance_discount_qty);
                            $quantity-=$total_qty_for_free; //any balance will added to next coupon, in the next iteration
                        }

                        foreach($matching_cat_data as $matching_cat=>$cat_data)
                        {
                            if($cat_data['qty']==0) //no qty added by admin, so skip
                            {
                                continue;
                            }
                            if($total_qty_for_free<=0)
                            {
                                break;
                            }

                            $total_qty_in_cart=isset($cat_qty_arr[$matching_cat]) ? $cat_qty_arr[$matching_cat] : 0; /* total quantity for the category in the cart */  
                            if($total_qty_in_cart<$cat_data['qty']) /* total quantity in the cart is lesser than the maximum allowed */
                            {
                                $qty_for_current_cat=min($total_qty_for_free, ($cat_data['qty']-$total_qty_in_cart));
                                $this->add_item_to_cart($item_id, $qty_for_current_cat, $coupon_code, $matching_cat);
                                $total_qty_for_free-=$qty_for_current_cat;
                            }
                        }

                        /* check and set is bogo fully availed or not */
                        self::set_bogo_fully_availed($coupon_id, $coupon_code, $total_allowed_free_qty_for_cats, ($total_free_qty_for_cats_in_the_cart+$total_qty_for_free));
                        
                        if($quantity==0) //no more quantity in total added quantity so break the main loop, otherwise continue the loop for other coupon(if exists)
                        {
                            break;
                        }

                    }else
                    {
                        //no category added by admin
                    }

                }elseif('any_product_from_store'===$bogo_customer_gets)
                {
                    $total_qty_used=0;
                    $current_product_free_qty=0;
                    foreach($cart_items as $item_key=>$cart_item) 
                    {
                        if(self::is_a_free_item($cart_item, $coupon_code)) /* a free item under the given coupon */
                        {
                            $total_qty_used+=$cart_item['quantity'];
                            if(self::is_same_prodct($cart_item, $product_id, $variation_id))
                            {
                                $current_product_free_qty+=$cart_item['quantity'];
                                WC()->cart->remove_cart_item($item_key);  
                            }
                        }
                    }

                    /* allowed quantity */
                    $max_item_qty=$this->get_quantity_for_non_individual_quantity_bogo($coupon_id);

                    $qty_for_free=0;
                    if($total_qty_used<$max_item_qty) /* remaining quantity exists */
                    {
                        $balance_discount_qty=$max_item_qty-$total_qty_used;
                        $qty_for_free=min($quantity, $balance_discount_qty);
                        $quantity-=$qty_for_free; //any balance will added to next coupon, in the next iteration
                    }

                    $total_qty_for_free=$current_product_free_qty+$qty_for_free;

                    $this->add_item_to_cart($item_id, $total_qty_for_free, $coupon_code);
                                            
                    /* check and set, is bogo fully availed or not */
                    self::set_bogo_fully_availed($coupon_id, $coupon_code, $max_item_qty, ($total_qty_used+$qty_for_free));

                    if($quantity==0) //no more quantity in total added quantity so break the main loop, otherwise continue the loop for other coupon(if exists)
                    {
                        break;
                    }
                }elseif('any_product_from_category_in_the_cart' === $bogo_customer_gets)
                {
                    $bogo_free_categories = $this->get_cart_item_categories_for_coupon($coupon_code, $coupon_id);
                    $frequency = $this->get_coupon_applicable_count($coupon_id, $coupon_code);
                    $in_cart_frequency = array_sum(array_column($bogo_free_categories, 'frequency'));
                    $remaining_frequency = $frequency - $in_cart_frequency;

                    if(!empty($bogo_free_categories) && 0 < $remaining_frequency)
                    {
                        $bogo_free_category_ids = array_keys($bogo_free_categories);
                        $product_cats = wc_get_product_cat_ids($product_id);
                        $matching_cats = array_values(array_intersect($product_cats, $bogo_free_category_ids));

                        if(empty($matching_cats)) /* current product not belongs to the coupon allowed categories */
                        {
                            continue;
                        }

                        /**
                         *  Prepare the array for sorting.
                         */
                        $matching_cat_data  = array();
                        $required_qty_arr   = array(); //for sorting
                        $is_reminder_exists = false;

                        foreach($matching_cats as $cat_id)
                        {
                            $cat_data = $bogo_free_categories[$cat_id];
                            $matching_cat_data[$cat_id] = $cat_data;
                            $required_qty_arr[] = $cat_data['required'];

                            if(0 > $cat_data['reminder'])
                            {
                                $is_reminder_exists = true;
                            }
                        }

                        /**
                         * Only when reminder exists for any categories, otherwise same priority for all categories
                         */
                        if($is_reminder_exists)
                        {
                            /**
                             *  Sort the array by incomplete frequency filled at first
                             */
                            array_multisort($required_qty_arr, SORT_DESC, $matching_cat_data);

                            /**
                             *  First loop for incompletely filled frequencies
                             *  Fill the remaining quantities
                             */
                            foreach($matching_cat_data as $matching_cat => $cat_data)
                            {
                                //Category with reminder, convert it as giveaway
                                if(0 > $cat_data['reminder'])
                                {
                                    $qty_for_current_cat = min($cat_data['reminder'], $quantity);
                                    $quantity -= $qty_for_current_cat;

                                    $this->add_item_to_cart($item_id, $cat_data['reminder'], $coupon_code, $matching_cat);
                                }
                            }
                        }

                        if(0 < $quantity) //if any quantity remains 
                        { 
                            
                            $single_eligibility_qty = $this->get_non_individual_discount_quantity($coupon_id);
                            $current_qty_frq = ceil($quantity / $single_eligibility_qty); //available frequency in current quantity
                            
                            foreach($matching_cat_data as $matching_cat => $cat_data)
                            {                              
                                if($current_qty_frq <= $remaining_frequency)
                                {
                                    $remaining_frequency = $remaining_frequency - $current_qty_frq;

                                    //convert full quantity as giveaway
                                    $this->add_item_to_cart($item_id, $quantity, $coupon_code, $matching_cat);

                                    $quantity = 0;

                                    break 2; //no quantity so break both loops
                                }else
                                {                               
                                    $new_quantity = ($remaining_frequency * $single_eligibility_qty);

                                    //convert as giveaway
                                    $this->add_item_to_cart($item_id, $new_quantity, $coupon_code, $matching_cat);
                                    
                                    $quantity -= $new_quantity; //reduce the quantity
                                    $remaining_frequency = 0;

                                    break;
                                }
                            }
                        }

                    }else
                    {
                        //may be fully availed
                    }

                    /* check and set, is bogo fully availed or not */
                    $in_cart_qty            = array_sum(array_column($bogo_free_categories, 'qty'));
                    $max_qty_allowed        = $this->get_quantity_for_non_individual_quantity_bogo($coupon_id);

                    self::set_bogo_fully_availed($coupon_id, $coupon_code, $max_qty_allowed, $in_cart_qty);
                }
            }
        }
    }

    /**
     *  Sort the coupon categories based on the discount amount. Category with least discount amount will be the first one.
     *  Update the quantity based on `Apply repeatedly` option
     *  @since 2.0.4
     */
    public function sort_category_by_profit($matching_cats, $bogo_free_categories, $product, $coupon_id)
    {
        $out=array();
        if(count($matching_cats)==1)
        {
            $cat_id=$matching_cats[0];
            $cat_data=$bogo_free_categories[$cat_id];
            
            /* prepare quantity for apply repeatedly */
            $cat_data['qty']=$this->prepare_quantity_based_on_apply_frequency($coupon_id, $cat_data['qty']);

            $out[$matching_cats[0]]=$cat_data;
            return $out;
        }
        $product_price=self::get_product_price($product);
        
        foreach($matching_cats as $cat_id)
        {
            $cat_data=$bogo_free_categories[$cat_id];

            /* prepare quantity for apply repeatedly */
            $cat_data['qty']=$this->prepare_quantity_based_on_apply_frequency($coupon_id, $cat_data['qty']);

            if('percent'==$cat_data['price_type'])
            {
                $discount_amount=((($cat_data['price']*$product_price)/100)*$cat_data['qty']);
            }else
            {
                $discount_amount=$cat_data['price']*$cat_data['qty'];
            }
            $cat_data['discount_amount']=$discount_amount;
            $out[$cat_id]=$cat_data;
        }
        uasort($out, function($a, $b){ return $a['discount_amount'] - $b['discount_amount']; });                     
        return $out;
    }

    /**
     * Action function for displaying description for Giveaway product on cart page
     * @since 1.0.0
     * @since 2.0.4 Added compatibility for BOGO type coupons
     */
    public function display_giveaway_product_description( $cart_item )
    {
        $product_id     = $cart_item['product_id'];
        $variation_id   = $cart_item['variation_id'];

        if(self::is_a_free_item($cart_item))
        {
            $coupon_code    = wc_format_coupon_code($cart_item['free_gift_coupon']);
            $item_id        = ($variation_id>0 ? $variation_id : $product_id);
            $product        = wc_get_product($item_id);

            $giveaway_data=$this->get_product_giveaway_data($item_id, $coupon_code, $cart_item);

            if($this->is_full_free_item($product, $giveaway_data))
            {
                $free_gift_text = __("Congrats! you've got a free gift from us!", 'wt-smart-coupons-for-woocommerce-pro');
            }else
            {
                $discount_text = $this->get_give_away_discount_text(0, $giveaway_data); /* set coupon id as zero(first argument) because we have already fetched data */
                $free_gift_text = sprintf(__("You're in luck! A free product is added to the cart with a %s discount.", 'wt-smart-coupons-for-woocommerce-pro'), $discount_text);
            }

            $info_text=apply_filters('wt_sc_alter_giveaway_cart_lineitem_text', '<p style="color:green;clear:both">'.$free_gift_text.'</p>', $cart_item);
            echo wp_kses_post($info_text);
        }

    }

    /**
     * Update Cart item values
     * @since 1.0.0
     * @since 2.0.4 Added compatibility for BOGO type coupons
     */
    public function update_cart_item_values($cart_item, $product_id = 0, $variation_id = 0, $qty = 1)
    {
        if(self::is_a_free_item($cart_item)) 
        {
            $coupon_code = wc_format_coupon_code($cart_item['free_gift_coupon']);
            $coupon=new WC_Coupon($coupon_code);
            if($coupon)
            {
                $coupon_id=$coupon->get_id();
                if(wc_string_to_bool($this->get_coupon_meta_value($coupon_id, 'wt_apply_discount_before_tax_calculation'))===false)
                {
                    return $cart_item;
                } 

                $item_id = ($cart_item['variation_id']>0 ? $cart_item['variation_id'] : $cart_item['product_id']);
                $product = wc_get_product($item_id);
                $giveaway_data=$this->get_product_giveaway_data($item_id, $coupon_code, $cart_item);

                $discount=self::get_available_discount_for_giveaway_product($product, $giveaway_data); 

                $product_price=self::get_product_price($product);

                $discounted_price = ($product_price - $discount);
                $cart_item['data']->set_price($discounted_price);
                $cart_item['data']->set_regular_price($product_price);
            }
        }

        return $cart_item;
    }

    /**
     *  Update cart item value for applying price before tax calculation.
     *  @since 1.0.0
     */
    public function update_cart_item_in_session( $session_data = array(), $values = array(), $key = '' )
    {
        if(self::is_a_free_item($session_data))
        {
            $coupon_code = wc_format_coupon_code($session_data['free_gift_coupon']);
            $coupon_id =  wc_get_coupon_id_by_code($coupon_code) ;
            if(wc_string_to_bool($this->get_coupon_meta_value($coupon_id, 'wt_apply_discount_before_tax_calculation'))===false)
            {
                return $session_data;
            }
            
            $qty =(isset($session_data['quantity']) ?  $session_data['quantity'] :  1);
            
            $session_data = $this->update_cart_item_values($session_data, $session_data['product_id'], $session_data['variation_id'], $qty);
        }
        return $session_data;
    }

    /**
     *  Function for updating cart item price display ( used when apply giveaway discount before tax calculation).
     *  @since 1.2.4
     */
    public function update_cart_item_price($price, $cart_item)
    {
        return $this->alter_cart_item_price($price, $cart_item, false);
    }

    /**
     * Update Cart item Quantity field non editable
     * @since 1.0.0
     */
    public function update_cart_item_quantity_field($product_quantity = '', $cart_item_key = '', $cart_item = array() )
    {
        if(self::is_a_free_item($cart_item))
        {
            $product_quantity = sprintf( '%s <input type="hidden" name="cart[%s][qty]" value="%s" />', $cart_item['quantity'], $cart_item_key, $cart_item['quantity']);
        }
        return $product_quantity;
    }

    /**
     *  Add Free gift item price details into cart and checkout.
     *  @since 1.0.0
     *  @since 2.0.4 Added compatibility for BOGO type coupons
    */
    public function add_give_away_product_discount()
    {
        $cart_object=WC()->cart;
        if($this->is_cart_contains_free_products('', $cart_object))
        {     
            $cart_items=$cart_object->get_cart();
            foreach($cart_items as $cart_item_key=>$cart_item)
            {
                if(self::is_a_free_item($cart_item))
                {
                    $coupon_code=(isset($cart_item['free_gift_coupon']) ? $cart_item['free_gift_coupon'] : '');
                    if(!empty($coupon_code))
                    {
                        $coupon_code=wc_format_coupon_code($coupon_code);
                        $coupon=new WC_Coupon($coupon_code);
                        if($coupon && !self::is_bogo($coupon))
                        {
                            $coupon_id=$coupon->get_id();
                            if(wc_string_to_bool($this->get_coupon_meta_value($coupon_id, 'wt_apply_discount_before_tax_calculation'))===true) /* currently only applicable for non BOGO */
                            {
                                continue;
                            }

                            $item_id = ($cart_item['variation_id']>0 ? $cart_item['variation_id'] : $cart_item['product_id']);
                            $product = wc_get_product($item_id);
                            $giveaway_data=$this->get_product_giveaway_data($item_id, $coupon_code, $cart_item);

                            $discount=(float) self::get_available_discount_for_giveaway_product($product, $giveaway_data)*$cart_item['quantity'];

                            $label_text=apply_filters('wt_sc_alter_giveaway_cart_summary_label', __('Free gift', 'wt-smart-coupons-for-woocommerce-pro' ), $cart_item);                          
                            
                            $discount_price=Wt_Smart_Coupon_Admin::get_formatted_price((number_format((float) $discount, 2, '.', '')));
                            $discount_price=apply_filters('wt_sc_alter_giveaway_cart_summary_value', '-'.$discount_price, $discount, $cart_item); 
                            ?>
                            <tr class="woocommerce-give_away_product wt_give_away_product">
                                <th><?php echo wp_kses_post($label_text); ?></th>
                                <td><?php echo wp_kses_post($discount_price); ?></td>                      
                            </tr>
                            <?php
                        }
                    }
                } 
            }
        }
    }

    /**
     *  
     *  Exclude the free giveaway products from applying other coupons.
     *  This is applicable when product is 'free giveaway`.
     *  @param bool     $valid   is valid or not
     *  @param WC_Product $product   Product instance
     *  @param WC_Product     $coupon   Coupon data
     *  @param array  $values  Cart item values.
     *  @return bool
     *  @since    2.0.6
     */
    public function exclude_giveaway_from_other_discounts($valid, $product, $coupon, $values)
    {
        if(self::is_a_free_item($values))
        {
            $valid = false;
        }

        return $valid;
    }

    /**
     * Filter function for updating cart item price ( Displaying cart item price in cart and checkout page )
     * @param $price Price html.
     * @param $cart_item Cart item object
     * @since 1.0.0
     */
    public function add_custom_cart_item_total($price, $cart_item)
    {
        return $this->alter_cart_item_price($price, $cart_item);
    }

    private function alter_cart_item_price($price, $cart_item, $is_total=true)
    {
        $out=$price;
        if(self::is_a_free_item($cart_item))
        {
            $coupon_code    = wc_format_coupon_code($cart_item['free_gift_coupon']);
            $coupon_id      = wc_get_coupon_id_by_code($coupon_code);
            $item_id = ($cart_item['variation_id']>0 ? $cart_item['variation_id'] : $cart_item['product_id']);
            $product = wc_get_product($item_id);
            $product_price = self::get_product_price($product);
            $giveaway_data = $this->get_product_giveaway_data($item_id, $coupon_code, $cart_item);
            
            $discount=self::get_available_discount_for_giveaway_product($product, $giveaway_data);
            $sale_price_after_discount = ($product_price - $discount);

            if($is_total)
            {
                $sale_price_after_discount = $sale_price_after_discount * $cart_item['quantity'];
                $product_price=$product_price * $cart_item['quantity'];
                $discount = $discount * $cart_item['quantity'];

                if(!isset(self::$bogo_discounts[$coupon_code]))
                {
                    self::$bogo_discounts[$coupon_code] = 0;
                }

                self::$bogo_discounts[$coupon_code] += $discount; 
            }

            if(wc_string_to_bool($this->get_coupon_meta_value($coupon_id, 'wt_apply_discount_before_tax_calculation'))===true)
            {
                $out = '<span>'.wc_price($product_price-$discount).'</span>';
            }else
            {
                $out = '<span>'.wc_price($product_price).'</span> <br /> <span class="wt_sc_bogo_cart_item_discount">'.__('Discount: ', 'wt-smart-coupons-for-woocommerce-pro').wc_price($discount).'</span>';
            }
            
        }

        return $out; 
    }

    /**
     *  Calculate the Cart Total after reducing the free product price.
     *  @since 1.0.0.
     *  @since 2.0.4 Added compatibility for BOGO type coupons
    */
    public function discounted_calculated_total($cart_object)
    {
        $new_total = $cart_object->get_total('edit');
        if($this->is_cart_contains_free_products('', $cart_object))
        {     
            $cart_items=$cart_object->get_cart();
            foreach($cart_items as $cart_item_key=>$cart_item)
            {
                if(self::is_a_free_item($cart_item))
                {
                    $coupon_code=$cart_item['free_gift_coupon'];
                    if(!empty($coupon_code))
                    {
                        $coupon_code=wc_format_coupon_code($coupon_code);
                        $coupon=new WC_Coupon($coupon_code);
                        if($coupon)
                        {
                            $coupon_id=$coupon->get_id();
                            if(wc_string_to_bool($this->get_coupon_meta_value($coupon_id, 'wt_apply_discount_before_tax_calculation'))===true)
                            {
                                continue;
                            } 
                            
                            $item_id = ($cart_item['variation_id']>0 ? $cart_item['variation_id'] : $cart_item['product_id']);
                            $product = wc_get_product($item_id);
                            $giveaway_data=$this->get_product_giveaway_data($item_id, $coupon_code, $cart_item);

                            $discount=self::get_available_discount_for_giveaway_product($product, $giveaway_data);
                            $new_total = $new_total-($discount*$cart_item['quantity']);
                        }
                    }
                } 
            }
            $new_total=round($new_total, $cart_object->dp);
            $cart_object->set_total($new_total);
        }
    }

    /**
     *  Removes any free products from the cart if their related coupon is not present in the cart
     *  @since 1.3.4
     */
    public function check_any_free_products_without_coupon()
    {
        if(is_admin())
        {
            return; 
        }
        $cart = ((is_object(WC()) && isset(WC()->cart)) ? WC()->cart : null);
        if(is_object( $cart ) && is_callable(array($cart, 'is_empty')) && ! $cart->is_empty()) 
        {
            $coupons=$cart->get_applied_coupons();           
            $cart_items = $cart->get_cart();
            $cart_items =((isset($cart_items) && is_array($cart_items)) ? $cart_items : array());            
            foreach($cart_items as $cart_item_key => $cart_item)
            {                  
                if(self::is_a_free_item($cart_item))
                {
                    if(!in_array($cart_item['free_gift_coupon'], $coupons)) /* coupon not found in the applied coupon list */
                    {
                        $cart->remove_cart_item($cart_item_key); /* remove the free item */
                    }
                }
            }
        }                
    }

    /**
     * Remove giveaway available session. If already added    
     * @since 2.0.2
     */
    public function remove_giveaway_available_session($coupon_code)
    {
        self::remove_bogo_eligible_session($coupon_code); 
    }

    /**
     * Remove Free Product from cart (Hook to When Coupon removed)
     * @since 1.0.0
     * @since 2.0.2     Code updated
     */
    public function remove_free_product_from_cart($coupon_code)
    {
        $cart=WC()->cart;
        $applied_coupons  = $cart->get_applied_coupons(); 
        if(isset($coupon_code) && !empty($coupon_code) && !in_array($coupon_code, $applied_coupons))
        {
            foreach($cart->get_cart() as $cart_item_key => $cart_item )
            {
                if(self::is_a_free_item($cart_item, $coupon_code))
                {
                    $cart->remove_cart_item($cart_item_key);
                }
            }         
        }
    }


    /**
     * Add Free Prodcut details on cart item list.
     * @since 1.0.0
     * @since 2.0.2 Code updated
    */
    public function add_free_product_details_into_order($item, $cart_item_key, $values, $order)
    {
        if(!self::is_a_free_item($values))
        {
            return;
        }        
        $item->add_meta_data('free_product' , $values['free_product']);
        $item->add_meta_data('free_gift_coupon' , $values['free_gift_coupon']);
    }


    /**
     * Display free product discount detail in order details.
     * @since 1.0.0
     */
    public function woocommerce_get_order_item_totals($total_rows, $order, $tax_display)
    {
        $out=array();
        $order_items = $order->get_items();
        foreach($order_items as $order_item_id=>$order_item)
        {
            $giveaway_info=$this->prepare_giveaway_info_for_order($order_item_id, $order_item, $order);
            if($giveaway_info)
            {
                $label_text=apply_filters('wt_sc_alter_order_detail_giveaway_info_label', __('Free gift:', 'wt-smart-coupons-for-woocommerce-pro'), $order_item, $order_item_id, $order);
                $out['free_product_'.$order_item_id]=array(
                    'label'     => $label_text,
                    'value'     => $giveaway_info,
                );
            }
        }

        if(!empty($out))
        {
            $offset = array_search('shipping', array_keys($total_rows));
            $total_rows = array_merge(
                array_slice($total_rows, 0, $offset),
                $out,
                array_slice($total_rows, $offset, null)
            );
        }

        return $total_rows;
    }

    /**
     * Manage Item Meta on order page
     * @since 1.0.0
     */  
    public function unset_free_product_order_item_meta_data($formatted_meta, $item)
    {
        foreach($formatted_meta as $key => $meta)
        {
            if(in_array($meta->key, array('free_product', 'free_gift_coupon', 'free_category')))
            {
                unset($formatted_meta[$key]);
            }            
        }
        return $formatted_meta;
    }


    /**
     *  Get current product cart item quantity
     *  @since 2.0.4
     *  @return array
     */
    public static function get_product_cart_item_qty($item_id, $coupon_code)
    {
        $out=array();
        foreach(WC()->cart->get_cart() as $cart_item_key=>$cart_item)
        {
            if($cart_item['product_id']==$item_id || $cart_item['variation_id']==$item_id) //product found
            {
                if(self::is_a_free_item($cart_item, $coupon_code))
                {
                    $out[$cart_item_key]=$cart_item['quantity'];                    
                }
            }   
        }
        return $out;
    }

    /**
     *  Checks the current cart item is a free item. Or a free item under the given coupon code
     *  @since 2.0.4
     *  @return bool
     */
    public static function is_a_free_item($cart_item, $coupon_code = "")
    {
        $out = isset($cart_item['free_gift_coupon']) && isset($cart_item['free_product']) && 'wt_give_away_product' === $cart_item['free_product'];
        
        if("" !== $coupon_code && $out)
        {
            $out = (wc_format_coupon_code($cart_item['free_gift_coupon']) === wc_format_coupon_code($coupon_code));
        }

        $out = apply_filters('wt_sc_alter_is_free_cart_item', $out, $cart_item, $coupon_code); /* other plugins to confirm their giveaway item */
        return $out;
    }

    /**
     *  Checks the current cart item is the same product/variation
     *  @since 2.0.4
     *  @param  array   $cart_item          Cart item array
     *  @param  int     $product_id         Product ID
     *  @param  int     $variation_id       Variation ID
     *  @return bool
     */
    public static function is_same_prodct($cart_item, $product_id, $variation_id)
    {
        return ($cart_item['product_id']==$product_id && $cart_item['variation_id']==$variation_id);
    }

    /**
     *  Get total quantity of current coupon free products
     *  @return array 
     */
    public static function get_total_coupon_cart_item_qty($coupon_code)
    {
        $out=array();
        foreach(WC()->cart->get_cart() as $cart_item_key=>$cart_item)
        {
            if(self::is_a_free_item($cart_item, $coupon_code))
            {
                $out[$cart_item_key]=$cart_item['quantity'];                    
            }   
        }
        return $out;
    }

    /**
     * Check whether cart contains any Giveaway products from given coupon
     * @since 1.0.0
     * @since 2.0.2 Code updated, added cart object as second argument(optional)
     * @return bool
     */
    public function is_cart_contains_free_products($coupon_code = '', $cart = null)
    {
        $cart = (is_null($cart) ? WC()->cart : $cart);
        $cart_items = $cart->get_cart();
        $wt_give_away_meta = array_column($cart_items, 'free_product');
        
        $out=in_array('wt_give_away_product', $wt_give_away_meta); 
        
        if($coupon_code!="" && $out)
        {
            $wt_give_away_coupon_meta=array_column($cart_items, 'free_gift_coupon');
            $out=in_array($coupon_code, $wt_give_away_coupon_meta);
        }

        return $out;
    }


    /**
     *  Remove/Update quantity of giveaway items when eligibility count was changed. This will be called on `wp_loaded` hook
     *  
     *  @since  2.0.4
     *  @since  2.0.7       Added compatibility for WPML on `Specific product` giveaway.
     *                      Added compatibility for `Any product from category in the cart`.
     */
    public function adjust_giveaway_count_when_eligibility_changed()
    {
        $cart=WC()->cart;
        if(self::$giveaway_count_adjust===true || is_admin() || !Wt_Smart_Coupon_Public::module_exists('coupon_restriction') || is_null(WC()->session) || is_null($cart))
        {
            return;
        }       

        self::$giveaway_count_adjust=true;    
        
        $applied_coupons  = $cart->applied_coupons;
        $applied_coupons = (!is_array($applied_coupons) ? array() : $applied_coupons);
        $cart_items=$cart->get_cart(); 
        foreach($applied_coupons as $coupon_code)
        {
            $coupon_code=wc_format_coupon_code($coupon_code);
            $coupon=new WC_Coupon($coupon_code);
            if(self::is_bogo($coupon))
            {
                $coupon_id=$coupon->get_id();
                
                $bogo_product_condition = $this->get_coupon_meta_value($coupon_id, '_wt_sc_bogo_product_condition');
                
                $frequency=$this->get_coupon_applicable_count($coupon_id, $coupon_code);

                $bogo_customer_gets = $this->get_coupon_meta_value($coupon_id, '_wt_sc_bogo_customer_gets');

                if('specific_product'===$bogo_customer_gets)
                {
                    $bogo_products=$this->get_all_bogo_giveaway_products($coupon_id);
                    $cart_available_qty=array();
                    foreach($cart_items as $item_key=>$cart_item)
                    {
                        if(self::is_a_free_item($cart_item, $coupon_code)) /* a free item under the given coupon */
                        {
                            $item_id = $this->check_giveaway_id_match_on_multi_lang_site($cart_item, $coupon_id, $bogo_products);

                            if($item_id>0)
                            {
                                if(!isset($cart_available_qty[$item_id]))
                                {
                                    $cart_available_qty[$item_id]=array();
                                }
                                $cart_available_qty[$item_id][$item_key]=$cart_item['quantity'];

                            }else
                            {
                                //a non giveaway free product. Remove it
                                WC()->cart->remove_cart_item($item_key);
                            }
                        }
                    }

                    $total_eligibility=$frequency;
                    foreach($cart_available_qty as $item_id=>$available_qty_data)
                    {
                        if($total_eligibility<=0) //no eligibility remaining
                        {   
                            foreach($available_qty_data as $cart_item_key=>$quantity)
                            {
                                //eligibility reached. Remove it
                                WC()->cart->remove_cart_item($cart_item_key);
                            } 
                        }
                        $total_qty_in_cart=array_sum($available_qty_data);
                        
                        if('and' === $bogo_product_condition) /* product condition `and` */
                        {
                            $giveaway_qty=$this->prepare_quantity_based_on_apply_frequency($coupon_id, $bogo_products[$item_id]['qty']);
                            if($giveaway_qty<$total_qty_in_cart)
                            {
                                foreach($available_qty_data as $cart_item_key=>$quantity)
                                {
                                    if(0 >= $giveaway_qty) /* max giveaway quantity reached. So remove it */
                                    {
                                        WC()->cart->remove_cart_item($cart_item_key);
                                        continue;  
                                    }

                                    if($quantity >= $giveaway_qty)
                                    {
                                        $this->update_cart_qty($cart_item_key, $giveaway_qty);
                                        $giveaway_qty=0;
                                    }else
                                    {
                                        $giveaway_qty=$giveaway_qty-$quantity;
                                    }
                                }
                            }     
                            continue; //no need to execute the below codes, Its for product condition `or`
                        }
                        
                        $cr_eligibility=floor($total_qty_in_cart/$bogo_products[$item_id]['qty']);
                        if($cr_eligibility<=$total_eligibility)
                        {
                            $total_eligibility=$total_eligibility-$cr_eligibility;
                        }else /* there are some extra giveaway items */
                        {
                            $max_qty=$total_eligibility*$bogo_products[$item_id]['qty'];
                            foreach($available_qty_data as $cart_item_key=>$quantity)
                            {
                                if(0 >= $max_qty)
                                {
                                    //eligibile max qty reached. Remove it
                                    WC()->cart->remove_cart_item($cart_item_key);
                                    continue;
                                }

                                if($max_qty >= $quantity)
                                {
                                    $max_qty=$max_qty-$quantity;
                                }else
                                {
                                    $this->update_cart_qty($cart_item_key, $max_qty);
                                    $max_qty=0;
                                }
                            }
                        }
                    }
                }elseif('same_product_in_the_cart' === $bogo_customer_gets)
                {
                    /* allowed quantity */
                    $item_qty=$this->get_quantity_for_non_individual_quantity_bogo($coupon_id);

                    $total_qty_in_cart=0;
                    foreach(WC()->cart->get_cart() as $cart_item_key=>$cart_item)
                    {
                        if(self::is_a_free_item($cart_item, $coupon_code)) //this is a free item but not from this coupon, so we need to skip it
                        {
                            if(!$this->non_free_product_exists($cart_item)) //non free product for the current free product not found so the current free product is not valid as giveaway */
                            {
                                WC()->cart->remove_cart_item($cart_item_key);
                            }else
                            {

                                if($total_qty_in_cart<$item_qty) 
                                {
                                    
                                    $balance_qty=$item_qty-$total_qty_in_cart; /* balance quantity allowed for giveaway */

                                    if($balance_qty<$cart_item['quantity']) /* current cart item quantity is greater than allowed. So adjust the quantity */
                                    {
                                        $this->update_cart_qty($cart_item_key, $balance_qty);
                                        $total_qty_in_cart+=$balance_qty;
                                    }else
                                    {
                                        $total_qty_in_cart+=$cart_item['quantity'];
                                    }

                                }else /* max quantity reached. So remove the upcoming items */
                                {
                                    WC()->cart->remove_cart_item($cart_item_key); 
                                }
                            }   
                        }
                    }

                }elseif('any_product_from_store'===$bogo_customer_gets)
                {
                    /* allowed quantity */
                    $max_qty_allowed=$this->get_quantity_for_non_individual_quantity_bogo($coupon_id);                   
                    
                    $max_qty_allowed_backup=$max_qty_allowed; /* this is using for BOGO available message toggling section */
                    $total_qty_in_cart=0;

                    foreach($cart_items as $cart_item_key=>$cart_item)
                    {
                        if(self::is_a_free_item($cart_item, $coupon_code))
                        {
                            if(0 === $max_qty_allowed)
                            {
                                WC()->cart->remove_cart_item($cart_item_key);
                                continue;
                            }

                            if($cart_item['quantity']<=$max_qty_allowed)
                            {
                               $max_qty_allowed-=$cart_item['quantity'];
                               $total_qty_in_cart+=$cart_item['quantity'];
                            }else
                            {
                                $total_qty_in_cart+=$max_qty_allowed;
                                $this->update_cart_qty($cart_item_key, $max_qty_allowed);
                                $max_qty_allowed=0;
                            }
                        }
                    }

                    /* check and set is bogo fully availed or not */
                    self::set_bogo_fully_availed($coupon_id, $coupon_code, $max_qty_allowed_backup, $total_qty_in_cart);

                }elseif('any_product_from_category'===$bogo_customer_gets)
                {
                    $bogo_free_categories=$this->get_coupon_meta_value($coupon_id, '_wt_sc_bogo_free_categories');
                    $bogo_free_category_ids=array_keys($bogo_free_categories);

                    $cat_qty_arr=array();
                    foreach($cart_items as $item_key=>$cart_item)
                    {
                        if(self::is_a_free_item($cart_item, $coupon_code)) /* a free item under the given coupon */
                        {
                            if(isset($cart_item['free_category']))
                            {
                                $product_cats = wc_get_product_cat_ids($cart_item['product_id']);
                                $matching_cats = array_intersect($bogo_free_category_ids, $product_cats); /* $coupon_categories must be the first argument, because its in the order of product direct category then parent category. To maintain the order we have to use $coupon_categories as first argument */
                                if(empty($matching_cats)) /* this item is not belongs to the current coupon */
                                {
                                    WC()->cart->remove_cart_item($item_key);
                                    continue;
                                }

                                if(!isset($cat_qty_arr[$cart_item['free_category']]))
                                {
                                    $cat_qty_arr[$cart_item['free_category']]=array();
                                }
                                $cat_qty_arr[$cart_item['free_category']][$item_key]=$cart_item['quantity'];
                            }else
                            {
                                WC()->cart->remove_cart_item($item_key); //no category information so this item may not belongs to current coupon
                            }
                        }
                    } 
                    
                    $fully_availed = true; /* here giving giveaway for all categories, so check for any of the category's giveaway was pending */
                    
                    foreach($bogo_free_categories as $cat_id => $cat_data)
                    {
                        if(isset($cat_qty_arr[$cat_id]))
                        {
                            /* prepare max quantity for category with apply repeatedly */
                            $max_qty_allowed = $this->prepare_quantity_based_on_apply_frequency($coupon_id, $cat_data['qty']);
                            
                            $total_qty_in_cart = array_sum($cat_qty_arr[$cat_id]); /* total quantity for the category in the cart */                               

                            if($max_qty_allowed < $total_qty_in_cart)
                            {
                                foreach($cat_qty_arr[$cat_id] as $cart_item_key => $qty)
                                {
                                    if(0 >= $max_qty_allowed)
                                    {
                                        WC()->cart->remove_cart_item($cart_item_key);
                                        break;
                                    }

                                    if($qty <= $max_qty_allowed)
                                    {
                                        $max_qty_allowed = $max_qty_allowed-$qty;
                                    }else
                                    {
                                        $this->update_cart_qty($cart_item_key, $max_qty_allowed);
                                        $max_qty_allowed=0;
                                    }
                                }
                            }elseif($max_qty_allowed>$total_qty_in_cart)
                            {
                                $fully_availed=false;
                            }

                        }else
                        {
                           $fully_availed=false; 
                        }
                    }

                    /* preparing dummy values to trigger the below function */
                    $dummy_max_qty_allowed=2;
                    $dummy_total_qty_in_cart=1;
                    if($fully_availed)
                    {
                        $dummy_total_qty_in_cart=2;
                    }

                    /* check and set is bogo fully availed or not */
                    self::set_bogo_fully_availed($coupon_id, $coupon_code, $dummy_max_qty_allowed, $dummy_total_qty_in_cart);

                }elseif('any_product_from_category_in_the_cart'===$bogo_customer_gets)
                {
                    
                    $bogo_free_categories   = $this->get_cart_item_categories_for_coupon($coupon_code, $coupon_id);
                    $in_cart_qty            = array_sum(array_column($bogo_free_categories, 'qty'));
                    $max_qty_allowed        = $this->get_quantity_for_non_individual_quantity_bogo($coupon_id);

                    /**
                     * Giveaway quantity is greater than allowed. 
                     * So remove the extra items.
                     * The category list is a sorted list. Category with reminder will come first
                     */
                    if($max_qty_allowed < $in_cart_qty) 
                    {
                        $to_remove_qty = $in_cart_qty - $max_qty_allowed;
                        $single_eligibility_qty = $this->get_non_individual_discount_quantity($coupon_id);
                        $in_cart_reminder = array_sum(array_column($bogo_free_categories, 'reminder'));

                        if($in_cart_reminder > 0) //Do this only when reminder exists. Otherwise directly remove the items. This is only for to improve user experience.
                        {
                            /**
                             *  This multiple loop code is only for improving user experience.
                             */
                            foreach($bogo_free_categories as $cat_id => $cat_data)
                            {
                                $cr_cat_reminder = $cat_data['reminder'];

                                if($cr_cat_reminder > 0) //this loop is only for items with reminder
                                {
                                    foreach($cart_items as $cart_item_key => $cart_item)
                                    { 
                                        if(self::is_a_free_item($cart_item, $coupon_code) && isset($cart_item['free_category']) && $cat_id === $cart_item['free_category'])
                                        {
                                            $cr_avl_qty = min($cart_item['quantity'], $cr_cat_reminder);
                                            $cr_cat_reminder -= $cr_avl_qty;

                                            /* remove extra quantity */
                                            $this->remove_extra_giveaway_for_category_in_cart($to_remove_qty, $cart_item, $cart_item_key, $cr_avl_qty);

                                            if(0 === $to_remove_qty)
                                            {
                                                break 2; //no more quantity to remove. So break both loops
                                            }

                                            if(0 === $cr_cat_reminder)
                                            {
                                                break;
                                            }
                                        }
                                    }
                                }

                                if(0 > $to_remove_qty) //again some quantity exists after above removal code
                                {
                                    foreach($cart_items as $cart_item_key => $cart_item)
                                    {
                                        if(self::is_a_free_item($cart_item, $coupon_code))
                                        {
                                            /* remove extra quantity */
                                            $this->remove_extra_giveaway_for_category_in_cart($to_remove_qty, $cart_item, $cart_item_key);

                                            if(0 === $to_remove_qty)
                                            {
                                                break; //no more quantity to remove
                                            }
                                        }
                                    }
                                }  
                            }
                        }else
                        {
                            foreach($cart_items as $cart_item_key => $cart_item)
                            {
                                if(self::is_a_free_item($cart_item, $coupon_code))
                                {
                                    /* remove extra quantity */
                                    $this->remove_extra_giveaway_for_category_in_cart($to_remove_qty, $cart_item, $cart_item_key);

                                    if(0 === $to_remove_qty)
                                    {
                                        break; //no more quantity to remove
                                    }
                                }
                            }
                        }

                        $in_cart_qty = $max_qty_allowed; //all extra quantity is removed by above code.
                    }

                    /* check and set is bogo fully availed or not */
                    self::set_bogo_fully_availed($coupon_id, $coupon_code, $max_qty_allowed, $in_cart_qty);

                }            
            }
        }    
    }

    /**
     *  Alter coupon block title text.
     *  @since  2.0.4
     *  @param      array     $coupon_data    Coupon data
     *  @param      object    $coupon         WC_Coupon object
     *  @return     array     $coupon_data
     */
    public function alter_coupon_title_text($coupon_data, $coupon)
    {
        if(self::is_bogo($coupon))
        {
            $coupon_data['coupon_amount'] = '';
            $coupon_data['coupon_type'] = apply_filters( 'wt_sc_alter_coupon_title_text', __('Free products', 'wt-smart-coupons-for-woocommerce-pro'), $coupon);
        }
        return $coupon_data;
    }

    /**
     *  Checks non free product of current cart item exists. Using in `same_product_in_the_cart` option
     *  @since  2.0.4
     *  @param      array     $cart_item_to_check    Cart item 
     *  @return     bool  
     */
    private function non_free_product_exists($cart_item_to_check)
    {
        $is_exists=false;
        foreach(WC()->cart->get_cart() as $cart_item_key=>$cart_item)
        {
            if(!self::is_a_free_item($cart_item)) //not a free item
            {
                if(self::is_same_prodct($cart_item_to_check, $cart_item['product_id'], $cart_item['variation_id']))
                {
                    $is_exists=true;
                    break;
                }
            }
        } 

        return $is_exists;
    }

    /**
     *  Is product/category restriction enabled
     *  @since  2.0.4
     *  @param      int      $coupon_id    Coupon ID 
     *  @return     bool  
     */
    private function is_product_category_restriction_enabled($coupon_id)
    {
        $wt_enable_product_category_restriction='yes';
        if(Wt_Smart_Coupon_Common::module_exists('coupon_restriction'))
        {
            $wt_enable_product_category_restriction =Wt_Smart_Coupon_Restriction::get_coupon_meta_value($coupon_id, '_wt_enable_product_category_restriction');
        }

        return wc_string_to_bool($wt_enable_product_category_restriction);
    }

    /**
     *  Is apply frequency enabled and prepare the quantity based on applicable frequency
     *  @since  2.0.4
     *  @since  2.0.5   Frequency taking functionality moved to another new function named `get_coupon_applicable_count`
     *  @since 2.0.7    $frequency added as an optional argument. If frequency given then value will be prepared based on the given frequency
     *  @param      int      $coupon_id    Coupon ID 
     *  @param      int      $quantity     Quantity 
     *  @return     int      $quantity     Quantity 
     */
    private function prepare_quantity_based_on_apply_frequency($coupon_id, $quantity, $frequency = null)
    {
        $wt_sc_bogo_apply_frequency = $this->get_coupon_meta_value($coupon_id, '_wt_sc_bogo_apply_frequency');
        
        if('repeat' === $wt_sc_bogo_apply_frequency)
        {
            $coupon_code    = wc_get_coupon_code_by_id($coupon_id);
            $frequency      = (is_null($frequency) ? $this->get_coupon_applicable_count($coupon_id, $coupon_code) : $frequency);     
            $quantity       = ($quantity * $frequency);
        }

        return $quantity;
    }

    /**
     *  This method will take coupon applicable count from session created by coupon restriction module
     *  @since 2.0.5
     */ 
    private function get_coupon_applicable_count($coupon_id, $coupon_code)
    {
        $frequency=1;
        if(Wt_Smart_Coupon_Public::module_exists('coupon_restriction'))
        {
            $bogo_applicable_count=Wt_Smart_Coupon_Restriction_Public::get_bogo_applicable_count_session();
            $coupon_code=wc_format_coupon_code($coupon_code);
            $frequency=absint(isset($bogo_applicable_count[$coupon_code]) ? $bogo_applicable_count[$coupon_code] : 1);
            $frequency=($frequency<1 ? 1 : $frequency);

            $wt_sc_bogo_apply_frequency = $this->get_coupon_meta_value($coupon_id, '_wt_sc_bogo_apply_frequency');
            $frequency=('once'==$wt_sc_bogo_apply_frequency ? 1 : $frequency);
        }

        return $frequency;
    }

    /**
     *  Recalculate apply frequency count.
     *  @since  2.0.4
     *  @param  object      $coupon    WC_Coupon object 
     */
    private function recalculate_apply_frequency_count($coupon)
    {
        $coupon_id=$coupon->get_id();
        $wt_sc_bogo_apply_frequency = $this->get_coupon_meta_value($coupon_id, '_wt_sc_bogo_apply_frequency');
        if('repeat'==$wt_sc_bogo_apply_frequency)
        {
            if(Wt_Smart_Coupon_Public::module_exists('coupon_restriction'))
            {
                $coupon_restriction_obj=Wt_Smart_Coupon_Restriction_Public::get_instance();
                try {
                    $coupon_restriction_obj->wt_woocommerce_coupon_is_valid(true, $coupon);
                }catch(Exception $e)
                {
                   wc_add_notice($e->getMessage(), 'error'); 
                }
            }
        }
    }

    /**
     *  This function will prepare product list based product/category restriction. Applicable for `same_product_in_the_cart`
     *  @since 2.0.5
     *  @param $coupon object WC_coupon object
     *  @param $qty_price_data array Price/Quantity data for giveaway (optional)
     */
    private function prepare_product_list_for_any_product_from_cart($coupon, $qty_price_data=array())
    {
        $coupon_products = $coupon->get_product_ids();
        $coupon_products = (!is_array($coupon_products) ? array() : $coupon_products);

        $coupon_categories = $coupon->get_product_categories();
        
        $new_coupon_products = array();

        foreach(WC()->cart->get_cart() as $cart_item)
        {
            $found = false;
            
            if($cart_item['variation_id']>0)
            {
                if(in_array($cart_item['variation_id'], $coupon_products) || in_array($cart_item['product_id'], $coupon_products))
                {
                    $new_coupon_products[$cart_item['variation_id']] = $qty_price_data;
                    $found = true;
                }
            }else
            {
                if(in_array($cart_item['product_id'], $coupon_products))
                {
                    $new_coupon_products[$cart_item['product_id']] = $qty_price_data;
                    $found = true;
                }
            }

            if(!$found) /* if the cart item not include in the product restriction */
            {
                $product_cats = wc_get_product_cat_ids($cart_item['product_id']);
                $matching_cats=array_intersect($coupon_categories, $product_cats); /* $coupon_categories must be the first argument, because its in the order of product direct category then parent category. To maintain the order we have to use $coupon_categories as first argument */
                if(!empty($matching_cats)) /* this product is under the given categories */
                {
                    $item_id=($cart_item['variation_id']>0 ? $cart_item['variation_id'] : $cart_item['product_id']);
                    $new_coupon_products[$item_id]=$qty_price_data;                                                                                    
                }          
            }
        }

        return $new_coupon_products;
    }

    /**
     *  Calculating balance giveaway quantity based on the giveaway products exists in the cart. (`same_product_in_the_cart`)
     *  @since  2.0.5
     *  @param  $coupon_id      int     ID of coupon
     *  @param  $coupon_code    string  Coupon code
     *  @return $balance_qty    int     Balance giveaway quantity to be added to cart
     */
    private function prepare_balance_quantity_for_same_product_in_cart($coupon_id, $coupon_code)
    {
        /* allowed quantity */
        $item_qty=$this->get_quantity_for_non_individual_quantity_bogo($coupon_id);

        //get cart item data
        $total_qty=self::get_total_coupon_cart_item_qty($coupon_code);

        $total_qty=!empty($total_qty) ? array_sum($total_qty) : 0; //existing free products in the cart

        return max(($item_qty-$total_qty), 0); //avoid negative values
    }

    /**
     *  Trigger WC is_valid coupon check. This is required for showing giveaway available message
     *  @since 2.0.5
     */
    public function trigger_coupon_is_valid()
    {
        $cart = (is_object(WC()) && isset(WC()->cart)) ? WC()->cart : null;
        if(is_object($cart) && is_callable(array($cart, 'is_empty')) && !$cart->is_empty())
        {
            foreach($cart->get_applied_coupons() as $coupon_code)
            {
                $coupon=new WC_Coupon($coupon_code);
                $coupon->is_valid();
            }
        }
    }

    /**
     *  @since 2.0.5
     *  This function is used to check the giveaway max quantity based on the available giveaway quantity in cart and apply repeatedly option
     *  Applicable for `specific_product` condition
     *  
     *  @param  $coupon_code                string              coupon code
     *  @param  $coupon_id                  int                 coupon id
     *  @param  $bogo_customer_gets         string              customer gets option in giveaway (using when $throw_error argument is true)
     *  @param  $bogo_product_condition     string              Any(or)/All(and) products. 
     *  @param  $bogo_products              array               Array of giveaway products under the current coupon (reference argument)
     *  @param  $frequency                  int                 Applicable frequency based on apply repeatedly option   
     *  @param  $options                    array               Other optional arguments
     *                                                          $throw_error    boolean     Throw error message when max quantity reached. Othewise return an empty array($bogo_products) [Optional]. Default: false (Do not throw error) - Applicable for `or` product condition
     *                                                          $update_qty     boolean     Update existing giveaway product quantiy if mismatch found. Default: false (Do not update quantity) - Applicable for `and` product condition
     *  
     *  @return                             void/boolean        `void` when $bogo_product_condition is `or` and $throw_error is true when max quantity reached
     *                                                          `boolean` when $bogo_product_condition is `and` and $update_quantity is true
     */
    public function check_giveaway_max_quantity($coupon_code, $coupon_id, $bogo_customer_gets, $bogo_product_condition, &$bogo_products, $frequency, $options=array())
    {
        $cart_items=WC()->cart->get_cart();
        if('and'==$bogo_product_condition)
        {
            $update_qty=isset($options['update_quantity']) ? (bool) $options['update_quantity'] : false;

            $is_giveaway_fully_added=true; // only applicable when $update_qty is true

            foreach(WC()->cart->get_cart() as $cart_item_key=>$cart_item) /* this loop is for to check the existing free items in the cart */
            {
                $item_id=0;
                if($cart_item['variation_id']>0 && isset($bogo_products[$cart_item['variation_id']]))
                {
                    $item_id=$cart_item['variation_id'];

                }elseif(isset($bogo_products[$cart_item['product_id']]))
                {
                    $item_id=$cart_item['product_id'];
                }

                if($item_id>0 && self::is_a_free_item($cart_item, $coupon_code)) /* this product is in the bogo list. Check it is a free item */
                {
                    $bogo_item_data=$bogo_products[$item_id];
                    $bogo_item_data['qty']=(absint($bogo_item_data['qty'])===0 ? 1 : $bogo_item_data['qty']);
                    
                    $giveaway_qty=$this->prepare_quantity_based_on_apply_frequency($coupon_id, $bogo_item_data['qty']);
                    if($giveaway_qty!=$cart_item['quantity']) 
                    {
                        if($update_qty)
                        {
                            //quantity mismatch so update
                            $this->update_cart_qty($cart_item_key, $giveaway_qty);
                            $is_giveaway_fully_added=false;
                        }
                    }
                    
                    if($update_qty)
                    {
                        unset($bogo_products[$item_id]); /* remove already added product from bogo list */
                    }else
                    {
                        if($giveaway_qty==$cart_item['quantity']) 
                        {
                            unset($bogo_products[$item_id]); /* remove fully added product from bogo list */
                        }
                    }
                    
                }
            }

            if($update_qty)
            {
                return $is_giveaway_fully_added;
            }

        }else
        {
            
            $throw_error=isset($options['throw_error']) ? (bool) $options['throw_error'] : false;

            $cart_available_qty=array();
            foreach($cart_items as $item_key=>$cart_item)
            {
                if(self::is_a_free_item($cart_item, $coupon_code)) /* a free item under the given coupon */
                {
                    $item_id=0;
                    if($cart_item['variation_id']>0 && isset($bogo_products[$cart_item['variation_id']]))
                    {
                        $item_id=$cart_item['variation_id'];
                    }elseif(isset($bogo_products[$cart_item['product_id']]))
                    {
                        $item_id=$cart_item['product_id'];
                    }

                    if($item_id>0)
                    {
                        if(!isset($cart_available_qty[$item_id]))
                        {
                            $cart_available_qty[$item_id]=array();
                        }
                        $cart_available_qty[$item_id][$item_key]=$cart_item['quantity'];
                        
                    }else
                    {
                        //a non giveaway free product. Remove it
                        WC()->cart->remove_cart_item($item_key);
                    }
                }
            }

            $total_eligibility=$frequency;
            foreach($cart_available_qty as $item_id=>$available_qty_data)
            {
                $total_qty_in_cart=array_sum($available_qty_data);
                $cr_eligibility=floor($total_qty_in_cart/$bogo_products[$item_id]['qty']);
                if($cr_eligibility>=$total_eligibility)
                {
                    if($throw_error)
                    {
                        self::set_add_to_cart_messages(
                            "already_availed_bogo", 
                            array(
                                'coupon_id'=>$coupon_id, 
                                'customer_gets'=>$bogo_customer_gets,
                                'apply_frequency'=>$this->get_coupon_meta_value($coupon_id, '_wt_sc_bogo_apply_frequency'),
                            ), 
                            self::$bogo_coupon_type_name);

                        wp_die();
                    }else
                    {
                        $bogo_products=array();
                    }
                }else
                {
                    $total_eligibility=$total_eligibility-$cr_eligibility;
                }
            }
        }
    }


    public function alter_coupon_discount_amount_html($discount_amount_html, $coupon)
    {
        if(self::is_bogo($coupon))
        {
            $coupon_code = wc_format_coupon_code($coupon->get_code());
            $discount = (isset(self::$bogo_discounts[$coupon_code]) ? self::$bogo_discounts[$coupon_code] : 0);
            $discount_amount_html = wc_price($discount);
        }

        return $discount_amount_html;
    }


    /**
     *  When the giveaway scenario: 
     *  1. The giveaway condition is specific product 
     *  2. Only single prodcut with 100% discount
     *  3. Apply repeatedly enabled
     *  Update giveaway quantity when new cart item added
     * 
     *  @since 2.0.7
     * 
     */
    public function check_and_add_giveaway_on_add_to_cart($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data)
    {
        $this->check_to_add_giveaway($cart_item_key, $quantity, 0, WC()->cart);
    }


    /**
     *  Convert cheapest cart item as giveaway.
     * 
     *  @since 2.0.7
     */
    public function convert_cheapest_as_giveaway()
    {
        if(is_admin())
        {
            return;
        }

        $applied_coupon_codes = WC()->cart->get_applied_coupons();

        if(empty($applied_coupon_codes))
        {
            return; //no coupons applied
        }  

        foreach($applied_coupon_codes as $applied_coupon_code) //find cheapest giveaway enabled coupons
        {
            $coupon = new WC_Coupon($applied_coupon_code);
            $coupon_id = $coupon->get_id();
            $bogo_customer_gets = $this->get_coupon_meta_value($coupon_id, '_wt_sc_bogo_customer_gets');

            if($this->is_cheapest_giveaway_enabled_coupon($coupon))
            {
                if('any_product_from_category' === $bogo_customer_gets)
                {
                    $this->apply_cheapest_giveaway_for_any_product_from_category($coupon, $coupon_id, $applied_coupon_code);

                }elseif('any_product_from_store' === $bogo_customer_gets)
                {
                    $this->apply_cheapest_giveaway_for_any_product_from_store($coupon, $coupon_id, $applied_coupon_code);

                }elseif('any_product_from_category_in_the_cart' === $bogo_customer_gets)
                {
                    //coming soon
                }
            }
        }

    }


    /**
     *  Enable Individual use` option for cheapest giveaway enabled coupons
     *   
     *  @since  2.0.7 
     *  @param  bool            $is_enabled     `Individual use` enabled or not
     *  @param  WC_Coupon       $coupon         WC_Coupon object
     *  @return bool            `Individual use` enabled or not
     */
    public function set_cheapest_giveaway_coupon_to_individual_use($is_enabled, $coupon)
    {
        if($this->is_cheapest_giveaway_enabled_coupon($coupon))
        {
           $is_enabled = true; //always an individual use coupon 
        }    

        return $is_enabled;
    }


    /**
     *  Force remove coupons that can be used along with individual use coupons
     *  
     *  @since 2.0.7
     *  @param $allowed_coupons     array       Array of coupon codes that can be used along with individual use coupons 
     *  @param $the_coupon          WC_Coupon   WC_Coupon object
     *  @param $applied_coupons     array       Array of applied coupon codes
     */
    public function force_remove_individual_use_allowed_coupons($allowed_coupons, $the_coupon, $applied_coupons)
    {
        foreach($applied_coupons as $applied_coupon_code) //find any coupon with cheapest giveaway option enabled.
        {
            $coupon = new WC_Coupon($applied_coupon_code);

            if($this->is_cheapest_giveaway_enabled_coupon($coupon))
            {
                $allowed_coupons = array(); //empty the array
                break;
            }
        }

        return $allowed_coupons;
    }


    /**
     *  Do not allow other coupons along with `cheapest as giveaway` enabled coupons
     * 
     *  @since 2.0.7
     *  @param bool         $allow_coupon                   Is allow the newly applied coupon
     *  @param WC_Coupon    $coupon                         WC_Coupon object for newly applied coupon
     *  @param WC_Coupon    $individual_enabled_coupon      WC_Coupon object for individual enabled coupon
     *  @return bool        Is allow or not the current coupon
     */
    public function reject_other_coupon_along_with_cheapest_giveaway_coupon($allow_coupon, $coupon, $individual_enabled_coupon)
    {
        return ($this->is_cheapest_giveaway_enabled_coupon($individual_enabled_coupon) ? false : $allow_coupon);
    }

    
    /**
     *  Checks the current coupon was `Cheapest giveaway` option enabled.
     *   
     *  @since  2.0.7 
     *  @param  WC_Coupon    $coupon     WC_Coupon object
     *  @return bool        Is `Cheapest giveaway` enabled or not
     */
    private function is_cheapest_giveaway_enabled_coupon($coupon)
    {
        $coupon_id = $coupon->get_id();
        $bogo_customer_gets = $this->get_coupon_meta_value($coupon_id, '_wt_sc_bogo_customer_gets');

        return (self::is_bogo($coupon) 
            && wc_string_to_bool(self::get_coupon_meta_value($coupon_id, '_wt_sc_cheapest_item_as_giveaway'))
            && in_array($bogo_customer_gets, self::$allowed_customer_gets_cheapest_giveaway)
        );
    }

    
    /**
     *  Convert cheapest item as giveaway if the coupon giveaway option is `any_product_from_category`
     * 
     *  @since 2.0.7
     *  @param $coupon          WC_Coupon   WC_Coupon object
     *  @param $coupon_id       int         Coupon id
     *  @param $coupon_code     string      Coupon code  
     */
    private function apply_cheapest_giveaway_for_any_product_from_category($coupon, $coupon_id, $coupon_code)
    {
        $bogo_free_categories   = $this->get_coupon_meta_value($coupon_id, '_wt_sc_bogo_free_categories');
        $bogo_free_category_ids = array_keys($bogo_free_categories);
               
        $already_converted_as_giveaway = array(); //cart item keys of giveaway items
        $price_of_eligibility_item_with_lowest_price = $this->get_price_of_eligibility_item_having_lowest_price($coupon, $coupon_id, $coupon_code);
        $frequency = $this->get_coupon_applicable_count($coupon_id, $coupon_code);

        foreach($bogo_free_category_ids as $category_id)
        {         
            category_loop_start: //we have to re-start from here in some cases

            $temp_arr   = array(); //temp cart items array
            
            //for sorting purpose
            $price_arr  = array();
            $coupon_arr = array();

            $cart_items = WC()->cart->get_cart(); //take a freshlist everytime.

            /**
             *  Prepare cart item list under the current category
             */
            foreach($cart_items as $cart_item_key => $cart_item)
            {
                if(in_array($cart_item_key, $already_converted_as_giveaway)) //skip the items that are already converted as giveaway for previous category
                {
                    continue;
                }

                $product_cats = wc_get_product_cat_ids($cart_item['product_id']);
                
                if(in_array($category_id, $product_cats)) /* this item is belongs to the current coupon category */
                {
                    $item_id = ($cart_item['variation_id'] > 0 ? $cart_item['variation_id'] : $cart_item['product_id']);
                    $product = wc_get_product($item_id);
                    $product_price = self::get_product_price($product);

                    $cart_item['wt_price']      = $product_price;
                    $temp_arr[$cart_item_key]   = $cart_item;
                    $price_arr[]                = $product_price;
                    $coupon_arr[]               = (isset($cart_item['free_gift_coupon']) ? $cart_item['free_gift_coupon'] : '');       
                    
                }
            }  

            /**
             *  Check and convert as giveaway or normal items
             */
            if(!empty($temp_arr)) //items present in the current category
            {
                //sort the item by price descending first then reverse the array.
                array_multisort($price_arr, SORT_DESC, SORT_REGULAR, $temp_arr, $coupon_arr);              
                $temp_arr = array_reverse($temp_arr);

                //this is used to run multiple iteration when required
                $old_giveaway_count = 0;
                $new_giveaway_count = 0;

                $cat_data = $bogo_free_categories[$category_id];
                $eligibility_qty = $this->prepare_quantity_based_on_apply_frequency($coupon_id, $cat_data['qty'], $frequency);
                $eligibility_qty_back   = $eligibility_qty; //value backup
 
                //loop through the sorted cart items
                foreach($temp_arr as $cart_item_key => $cart_item)
                {
                    $item_id = ($cart_item['variation_id'] > 0 ? $cart_item['variation_id'] : $cart_item['product_id']);
                    $product_price = $cart_item['wt_price'];

                    /**
                     *  Do not add giveaway that has price higher than the lowest priced eligible item. 
                     */
                    if(!is_null($price_of_eligibility_item_with_lowest_price) && $price_of_eligibility_item_with_lowest_price < $product_price)
                    {
                        if(self::is_a_free_item($cart_item))
                        {
                            //convert as normal item
                            $this->convert_giveaway_cartitem_as_normal_cartitem($cart_item_key);
                            $old_giveaway_count += $cart_item['quantity'];
                        }

                        continue; //no need to do further checks
                    }

                    /**
                     *  Check the item is available for giveaway or already giveaway
                     */
                    if(self::is_a_free_item($cart_item)) //already a free item
                    {
                        $old_giveaway_count += $cart_item['quantity'];

                        if(0 === $eligibility_qty) //check eligibility is remaining
                        {
                            //convert as normal item
                            $this->convert_giveaway_cartitem_as_normal_cartitem($cart_item_key);

                        }else
                        {
                            //deduct eligibility
                            $to_deduct = $cart_item['quantity'];

                            //this is to skip this item in the next category loop
                            $already_converted_as_giveaway[] = $cart_item_key; 

                            if($cart_item['quantity'] > $eligibility_qty)
                            {
                                $to_deduct = $eligibility_qty;

                                //the specified quantity will convert as normal item
                                $this->convert_giveaway_cartitem_as_normal_cartitem($cart_item_key, ($cart_item['quantity'] - $eligibility_qty));
                            }

                            $new_giveaway_count += $to_deduct;
                            $eligibility_qty -= $to_deduct;
                        }

                    }else
                    {
                        $qty_available_for_giveaway = 0;
                        $cart_item_quantity = $cart_item['quantity'];

                        //loop through the eligibility qty
                        for($i = 0; $i < min($eligibility_qty, $cart_item_quantity); $i++)
                        {
                            $cart_items = WC()->cart->get_cart(); //need to take the cart list again to get the refreshed list.
                            
                            $cart_item = $cart_items[$cart_item_key];
                            $new_qty = $cart_item['quantity'] - 1;

                            $this->update_cart_qty($cart_item_key, $new_qty);

                            if(!$coupon->is_valid() || $frequency > $this->get_coupon_applicable_count($coupon_id, $coupon_code)) //coupon eligibility gone, or eligibility count reduced.
                            {                      
                                if(0 === $new_qty)
                                {
                                    $this->set_as_normal_cartitem($cart_item['product_id'], $cart_item['variation_id'], 1);

                                }else
                                {
                                   $this->update_cart_qty($cart_item_key, ($new_qty + 1)); 
                                }

                                break;  //break the loop
                            }else
                            {
                                $qty_available_for_giveaway++;
                            }

                        }

                        if($qty_available_for_giveaway > 0) //we got some quantity to convert as giveaway
                        {
                            $new_cart_item_key = $this->add_item_to_cart($item_id, $qty_available_for_giveaway, $coupon_code, $category_id); //add the quantity as giveaway
                            
                            if(false !== $new_cart_item_key)
                            {
                                $eligibility_qty = $eligibility_qty - $qty_available_for_giveaway; //deduct the currently converted quantity
                                $new_giveaway_count += $qty_available_for_giveaway;

                                //this is to skip this item in the next category loop
                                $already_converted_as_giveaway[]= $new_cart_item_key;
                            }
                        }
                    }
                }

                if(1 > self::$cheapest_giveaway_loop_count && $old_giveaway_count > $new_giveaway_count) //we have to recheck the items.
                {
                    self::$cheapest_giveaway_loop_count = 1; //to prevent idefinite loop

                    //repeat the check, because new giveaway count is lesser than old giveaway count
                    goto category_loop_start;
                }

                if(1 === self::$cheapest_giveaway_loop_count)
                {
                    self::$cheapest_giveaway_loop_count = 0; //reset the loop counter for next category. Otherwise second iteration check for next category will fail 
                }
            }         
        }
    }

    
    /**
     *  This is for applying cheapest giveaway for `any_product_from_category`. 
     *  Unlike `any_product_from_store`, here we are preparing list of cart items based on the categories so the eligibility items may or may not in the list. 
     *  So we have to take the lowest priced eligibility item from the whole cart items instead of the prepared list.
     *      
     *  @since 2.0.7
     *  @param $coupon          WC_Coupon   Coupon object
     *  @param $coupon_id       int         Id of coupon
     *  @param $coupon_code     string      Coupon code
     *  @return null|float      Price of lowest priced eligibility cart item, If not found return null
     */
    private function get_price_of_eligibility_item_having_lowest_price($coupon, $coupon_id, $coupon_code)
    {
        $cart_items = WC()->cart->get_cart();
        $price_arr = array(); //for sorting purpose
        $coupon_arr = array(); //for sorting purpose

        foreach($cart_items as $cart_item_key => $cart_item)
        {
            $item_id = ($cart_item['variation_id']>0 ? $cart_item['variation_id'] : $cart_item['product_id']);
            $product = wc_get_product($item_id);
            $product_price = self::get_product_price($product);

            $cart_items[$cart_item_key]['wt_price'] = $product_price;
            
            $price_arr[]  = $product_price;
            $coupon_arr[] = (isset($cart_item['free_gift_coupon']) ? $cart_item['free_gift_coupon'] : '');
        }

        array_multisort($price_arr, SORT_DESC, SORT_REGULAR, $cart_items, $coupon_arr);
        $cart_items = array_reverse($cart_items);

        $price_of_eligibility_item_with_lowest_price = null;
        $frequency = $this->get_coupon_applicable_count($coupon_id, $coupon_code); //if it was a second iteration then take old frequency backup otherwise fresh one.
        
        //loop through the sorted cart items
        foreach($cart_items as $cart_item_key => $cart_item)
        {
            if(self::is_a_free_item($cart_item)) //no need to check free items for eligibility
            {
                continue;
            }

            $this->update_cart_qty($cart_item_key, 0); //remove the item first

            if(!$coupon->is_valid() || $frequency > $this->get_coupon_applicable_count($coupon_id, $coupon_code)) //coupon eligibility gone, or eligibility count reduced.
            {
                //this item is required for the coupon to be valid or for eligibility count
                $item_id = ($cart_item['variation_id'] > 0 ? $cart_item['variation_id'] : $cart_item['product_id']);
                $product = wc_get_product($item_id);
                $price_of_eligibility_item_with_lowest_price = self::get_product_price($product);

                $this->set_as_normal_cartitem($cart_item['product_id'], $cart_item['variation_id'], $cart_item['quantity']);

                break; //break the loop, further check not required, we only need the cheapest item.

            }else
            {
                $this->set_as_normal_cartitem($cart_item['product_id'], $cart_item['variation_id'], $cart_item['quantity']);
            }
        }

        return $price_of_eligibility_item_with_lowest_price;
    }

    
    /**
     *  Convert cheapest item as giveaway if the coupon giveaway option is `any_product_from_store`
     * 
     *  @since 2.0.7
     *  @param $coupon          WC_Coupon   WC_Coupon object
     *  @param $coupon_id       int         Coupon id
     *  @param $coupon_code     string      Coupon code  
     */
    private function apply_cheapest_giveaway_for_any_product_from_store($coupon, $coupon_id, $coupon_code)
    {     
        /**
         *  Sort the cart items ascending by price.
         *  First we are sorting the array in descending order then reversing the array. Because, in case of multiple cheap price items and that items include giveaway then we need giveaway items in first positions, this will avoid switching giveaway items in each refresh
         */
        $cart_items = WC()->cart->get_cart();
        $price_arr = array(); //for sorting purpose
        $coupon_arr = array(); //for sorting purpose
        
        //this is used to run multiple iteration when required
        $old_giveaway_count = 0;
        $new_giveaway_count = 0;

        foreach($cart_items as $cart_item_key => $cart_item) 
        {
            $item_id = ($cart_item['variation_id']>0 ? $cart_item['variation_id'] : $cart_item['product_id']);
            $product = wc_get_product($item_id);
            $product_price = self::get_product_price($product);

            $cart_items[$cart_item_key]['wt_price'] = $product_price;
            
            $price_arr[]  = $product_price;
            $coupon_arr[] = (isset($cart_item['free_gift_coupon']) ? $cart_item['free_gift_coupon'] : '');
        }

        array_multisort($price_arr, SORT_DESC, SORT_REGULAR, $cart_items, $coupon_arr);
        $cart_items = array_reverse($cart_items);
   

        /**
         *  Check the cart items and convert as giveaway
         * 
         */
        $frequency              = (1 === self::$cheapest_giveaway_loop_count ? self::$cheapest_giveaway_frequency_backup : $this->get_coupon_applicable_count($coupon_id, $coupon_code)); //if it was a second iteration then take old frequency backup otherwise fresh one.
        $eligibility_qty        = $this->get_quantity_for_non_individual_quantity_bogo($coupon_id, $frequency); //Prepare based on the given $frequency. This is for giving compatibility when multiple iteration exists
        $eligibility_qty_back   = $eligibility_qty; //value backup

        $price_of_eligibility_item_with_lowest_price = null; //this is usefull when multiple cheapest item with same price exists 

        //loop through the sorted cart items
        foreach($cart_items as $cart_item_key => $cart_item)
        {
            $item_id = ($cart_item['variation_id'] > 0 ? $cart_item['variation_id'] : $cart_item['product_id']);
            $product_price = $cart_item['wt_price'];

            /**
             *  Do not add giveaway that has price higher than the lowset priced eligible item. 
             */
            if(!is_null($price_of_eligibility_item_with_lowest_price) && $price_of_eligibility_item_with_lowest_price < $product_price)
            {
                if(self::is_a_free_item($cart_item))
                {
                    //convert as normal item
                    $this->convert_giveaway_cartitem_as_normal_cartitem($cart_item_key);
                    $old_giveaway_count += $cart_item['quantity'];
                }

                continue; //no need to do further checks
            }
            

            /**
             *  Check the item is available for giveaway or already giveaway
             */
            if(self::is_a_free_item($cart_item)) //already a free item
            {
                $old_giveaway_count += $cart_item['quantity'];

                if(0 === $eligibility_qty) //check eligibility is remaining
                {
                    //convert as normal item
                    $this->convert_giveaway_cartitem_as_normal_cartitem($cart_item_key);

                }else
                {
                    //deduct eligibility
                    $to_deduct = $cart_item['quantity'];

                    if($cart_item['quantity'] > $eligibility_qty)
                    {
                        $to_deduct = $eligibility_qty;

                        //the specified quantity will convert as normal item
                        $this->convert_giveaway_cartitem_as_normal_cartitem($cart_item_key, ($cart_item['quantity'] - $eligibility_qty));

                    }

                    $new_giveaway_count += $to_deduct;
                    $eligibility_qty -= $to_deduct;
                }

            }else
            {
                $qty_available_for_giveaway = 0;

                //loop through the eligibility qty
                for($i = 0; $i < min($eligibility_qty, $cart_item['quantity']); $i++)
                {
                    $cart_items = WC()->cart->get_cart(); //need to take the cart list again to get the refreshed list.
                    
                    $cart_item = $cart_items[$cart_item_key];
                    $new_qty = $cart_item['quantity'] - 1;

                    $this->update_cart_qty($cart_item_key, $new_qty);

                    if(!$coupon->is_valid() || $frequency > $this->get_coupon_applicable_count($coupon_id, $coupon_code)) //coupon eligibility gone, or eligibility count reduced.
                    {                      
                        if(0 === $new_qty)
                        {
                            $this->set_as_normal_cartitem($cart_item['product_id'], $cart_item['variation_id'], 1);

                        }else{
                           $this->update_cart_qty($cart_item_key, ($new_qty + 1)); 
                        }

                        $price_of_eligibility_item_with_lowest_price = $product_price; // take the price of eligiblity item with lowset price. Not giving giveaways with price greater than this price.
                        break;  //break the loop
                    }else
                    {
                        $qty_available_for_giveaway++;
                    }

                }

                if($qty_available_for_giveaway > 0) //we got some quantity to convert as giveaway
                {
                    $this->add_item_to_cart($item_id, $qty_available_for_giveaway, $coupon_code); //add the quantity as giveaway
                    
                    $eligibility_qty = $eligibility_qty - $qty_available_for_giveaway; //deduct the currently converted quantity
                    $new_giveaway_count += $qty_available_for_giveaway;
                }
            }
        }

        if(1 > self::$cheapest_giveaway_loop_count && $old_giveaway_count > $new_giveaway_count) //we have to recheck the items.
        {
            self::$cheapest_giveaway_loop_count = 1; //to prevent idefinite loop
            self::$cheapest_giveaway_frequency_backup = $frequency; //assumes the frequency will also change. So we are storing the existing value for next iteration
            
            return $this->apply_cheapest_giveaway_for_any_product_from_store($coupon, $coupon_id, $coupon_code);
        }

        /* check and set, is bogo fully availed or not */
        self::set_bogo_fully_availed($coupon_id, $coupon_code, $eligibility_qty_back, ($eligibility_qty_back - $eligibility_qty));

        if($eligibility_qty_back > ($eligibility_qty_back - $eligibility_qty))
        {
            $this->store_giveaway_available_message($coupon_id, 'any_product_from_store'); /* show message */
        }
    }


    /**
     *  Convert giveaway item as normal cart item.
     *  
     *  @since 2.0.7
     *  @param  $cart_item_key  string  Cart item key
     *  @param  $quantity  int  Quantity to be converted as giveaway. Optional argument. If no quantity given then the whole cart item quantity is converetd as normal cart item 
     */
    private function convert_giveaway_cartitem_as_normal_cartitem($cart_item_key, $quantity = null)
    {
        $cart_item = WC()->cart->cart_contents[$cart_item_key];
        
        if(is_null($quantity)) //quantity not specified so move all quantity as normal product
        {
            WC()->cart->remove_cart_item($cart_item_key);
            $quantity = $cart_item['quantity'];
        }else
        {
            $this->update_cart_qty($cart_item_key, ($cart_item['quantity'] - $quantity)); 
        }
        
        $this->set_as_normal_cartitem($cart_item['product_id'], $cart_item['variation_id'], $quantity);

    }

    
    /**
     *  Check cart `giveaway product id` and coupon `giveaway product id` to confirm the current giveaway item belongs to the coupon.
     *  When a multi language plugin(WPML) is active then the function will compare ids of all languages with giveaway product ids to get a match
     * 
     *  @since 2.0.7
     *  @param $cart_item       array       Cart item array
     *  @param $coupon_id       int         Id of coupon
     *  @param $bogo_products   array       Associative array of giveaway products and its data
     *  @return $item_id        int         If any match found then the matched ID will return otherwise 0
     */
    private function check_giveaway_id_match_on_multi_lang_site($cart_item, $coupon_id, $bogo_products = null)
    {
        $bogo_products = is_null($bogo_products) ? self::get_all_bogo_giveaway_products($coupon_id) : $bogo_products;
        $item_id = 0;
        
        if(0 < $cart_item['variation_id'] && isset($bogo_products[$cart_item['variation_id']]))
        {
            $item_id = $cart_item['variation_id'];

        }elseif(isset($bogo_products[$cart_item['product_id']]))
        {
            $item_id = $cart_item['product_id'];
        }

        /**
         *  For multi language compatibility
         */
        if(0 === $item_id)
        {
            $multi_lang_obj = Wt_Smart_Coupon_Mulitlanguage::get_instance();

            if($multi_lang_obj->is_multilanguage_plugin_active())
            {
                $bogo_product_ids = array_keys($bogo_products); //product ids

                if(0 < $cart_item['variation_id']) //variable product
                {
                    /**
                     *  Take ids of all languages
                     */
                    $all_lang_ids = $multi_lang_obj->get_all_translations($cart_item['variation_id'], 'post_product');

                    if(!empty($all_lang_ids) && !empty($matching_ids = array_intersect($all_lang_ids, $bogo_product_ids)))
                    {
                        $item_id = (int) reset($matching_ids); //take first item
                    }
                }

                if(0 === $item_id)
                {  
                    /**
                     *  Take ids of all languages
                     */
                    $all_lang_ids = $multi_lang_obj->get_all_translations($cart_item['product_id'], 'post_product');

                    if(!empty($all_lang_ids) && !empty($matching_ids = array_intersect($all_lang_ids, $bogo_product_ids)))
                    {
                        $item_id = (int) reset($matching_ids); //take first item
                    }
                }
            }
        }

        return $item_id;
    }


    /**
     *  Get all giveaway product ids for cart operations.
     * 
     *  @since 2.0.7
     *  @param $post_id     int     Id of coupon
     *  @return $free_products     int[]     Array of giveaway product ids. Product ids will be updated to current language product ids if multi language plugin(WPML) is active
     */
    public static function get_giveaway_products($post_id)
    {
        $free_products = parent::get_giveaway_products($post_id);
        $free_products_original = $free_products; //assumes main language product id

        $multi_lang_obj = Wt_Smart_Coupon_Mulitlanguage::get_instance();

        if($multi_lang_obj->is_multilanguage_plugin_active())
        {
            $out = array();

            foreach($free_products as $product_id)
            {
                /**
                 *  Take id of product in the current language.
                 * 
                 *  @param  $product_id         int     Id of product
                 *  @param  post type           string  Post type
                 *  @param  Return original     bool    Return original if no translation found in the current language. Default: false
                 * 
                 */
                $out[] = apply_filters('wpml_object_id', $product_id, 'product', TRUE);
            }
            
            $free_products = $out;
        }

        /**
         *  Alter BOGO product ids for cart (Only applicable for frontend functionalities)
         * 
         *  @param  $free_products              int[]       Array of giveaway product ids. Product ids of this array was converted to current language ids if any multi lang plugin(WPML) exists.
         *  @param  $post_id                    int         Id of coupon
         *  @param  $free_products_original     int[]       Array of giveaway product ids. Here the product ids are the ids configured by admin from backend.
         * 
         */
        return apply_filters('wt_sc_alter_bogo_giveaway_product_ids_for_cart', $free_products, $post_id, $free_products_original);
    }


    /**
     *  Get all giveaway products and its data for cart operations.
     * 
     *  @since 2.0.7
     *  @param $post_id     int     Id of coupon
     *  @return $bogo_products     array     Associative array of giveaway products and its data. Product ids will be updated to current language product ids if multi language plugin(WPML) is active
     */
    public static function get_all_bogo_giveaway_products($post_id)
    {
        $bogo_products = parent::get_all_bogo_giveaway_products($post_id);
        $bogo_products_original = $bogo_products; //assumes main language product id

        $multi_lang_obj = Wt_Smart_Coupon_Mulitlanguage::get_instance();

        if($multi_lang_obj->is_multilanguage_plugin_active())
        {
            $out = array();

            foreach($bogo_products as $product_id => $product_data)
            {
                /**
                 *  Take id of product in the current language.
                 * 
                 *  @param  $product_id         int     Id of product
                 *  @param  post type           string  Post type
                 *  @param  Return original     bool    Return original if no translation found in the current language. Default: false
                 * 
                 */
                $product_id = apply_filters('wpml_object_id', $product_id, 'product', TRUE);

                $out[$product_id] = $product_data;
            }
            
            $bogo_products = $out;
        }

        /**
         *  Alter BOGO products data for cart (Only applicable for frontend functionalities)
         * 
         *  @param  $bogo_products              array       An associative array of giveaway products and its giveaway data. Product ids of this array was converted to current language ids if any multi lang plugin(WPML) exists.
         *  @param  $post_id                    int         Id of coupon
         *  @param  $bogo_products_original     array       An associative array of giveaway products and its giveaway data. Here the product ids are the ids configured by admin from backend.
         * 
         */
        return apply_filters('wt_sc_alter_bogo_giveaway_products_for_cart', $bogo_products, $post_id, $bogo_products_original);
    }

    
    /**
     *  Get giveaway categories for BOGO coupon
     *  This is applicable for any_product_from_category_in_the_cart
     *  
     *  Return array structure: array(
     *      category_id => array(
     *          'qty'       => (int) Current cart giveaway quantity of this category,
     *          'reminder'  => (int) Reminder quantity with single eligibility quantity,
     *          'frequency' => (int) Total frequency completed by this category. Here we are using `ceil` function because we need full number in the case when incomplete frequency exists,
     *          'required'  => (int) Required quantity to fill the existing/next frequency. If any incomplete frequency exists then `required` will be (`single eligibility quantity` - `reminder`). Otherwise it will be `single eligibility quantity`,
     *      )
     *  )
     *  
     * 
     *  @since 2.0.7
     *  @param $coupon_code   string     Code of coupon
     *  @return array               Coupon category id array
     */
    public function get_cart_item_categories_for_coupon($coupon_code, $coupon_id)
    {
        $out  = array();
        $cart = WC()->cart;

        if(is_null($cart))
        {
            return $out;
        }

        $single_eligibility_qty = $this->get_non_individual_discount_quantity($coupon_id);
        $frequency = $this->get_coupon_applicable_count($coupon_id, $coupon_code);

        /* allowed quantity by frequency */
        $item_qty = $this->get_quantity_for_non_individual_quantity_bogo($coupon_id);
        $cart_items = $cart->get_cart();
        $cat_qty_arr = array(); //already in cart quantity
        $required_qty_arr = array(); //for sorting

        foreach($cart_items as $cart_item_key => $cart_item)
        {
            if(self::is_a_free_item($cart_item, $coupon_code)) //free item of current coupon
            {
                if(isset($cat_qty_arr[$cart_item['free_category']]))
                {
                    $cat_qty_arr[$cart_item['free_category']] += $cart_item['quantity'];
                }else
                {
                    $cat_qty_arr[$cart_item['free_category']]  = $cart_item['quantity'];
                }

            }else
            {
                $product_cats = wc_get_product_cat_ids($cart_item['product_id']);

                if(is_array($product_cats) && !empty($product_cats))
                {
                    foreach($product_cats as $cat_id)
                    {
                        if(!isset($cat_qty_arr[$cat_id]))
                        {
                            $cat_qty_arr[$cat_id] = 0; //zero quantity, because normal cart item.
                        }else
                        {
                            //if already exists, nothing to do because its not a free item so no quantity increment required.
                        }
                    }
                }
            }
        }

        foreach($cat_qty_arr as $category_id => $cat_qty)
        {
            $cat_qty_arr[$category_id] = array(
                'qty'       => $cat_qty,
                'reminder'  => ($cat_qty % $single_eligibility_qty),
                'frequency' => ceil($cat_qty / $single_eligibility_qty),
            );

            $cat_qty_arr[$category_id]['required'] = $single_eligibility_qty - $cat_qty_arr[$category_id]['reminder'];
            $required_qty_arr[] = $cat_qty_arr[$category_id]['required']; //for sorting purpose
        }

        $cart_giveaway_frequency_full_filled = array_sum(array_column($cat_qty_arr, 'frequency'));
        $cart_giveaway_total_reminder = array_sum(array_column($cat_qty_arr, 'reminder'));

        
        /**
         *  Frequency is fullfilled and some category have incomplete quantity. Then we have to remove categories other than incomplete giveaways
         */
        if($cart_giveaway_frequency_full_filled === $frequency)
        {
            if(0 < $cart_giveaway_total_reminder)
            {
                foreach($cat_qty_arr as $category_id => $cat_qty_data)
                {
                    if(0 === $cat_qty_data['reminder']) //remove completed items
                    {
                       unset($cat_qty_arr[$category_id]); 
                    }
                }
            }else
            {
                //fully availed
                $cat_qty_arr = array();
            }

        }elseif($cart_giveaway_frequency_full_filled > $frequency) //more giveaway is added. So sort by reminder items first.
        {
            /**
             *  Sort the array by incomplete frequency filled at first
             */
            array_multisort($required_qty_arr, SORT_DESC, $cat_qty_arr);
        }

        return $cat_qty_arr;
    }


    /**
     *  Remove the extra giveaway item in the cart.
     *  Applicable for `any_product_from_category_in_the_cart`
     * 
     *  @since 2.0.7
     *  @param $to_remove_qty       int         How many quantity to be removed (Reference argument)
     *  @param $cart_item           array       Cart item
     *  @param $cart_item_key       string      Cart key
     *  @param $cart_item_qty       int         The quantity available for removal, Optional, If not specified then remove all the cart item quantity
     */
    private function remove_extra_giveaway_for_category_in_cart(&$to_remove_qty, $cart_item, $cart_item_key, $cart_item_qty = null)
    {

        $cart_item_qty  = (is_null($cart_item_qty) ? $cart_item['quantity'] : $cart_item_qty); //the quantity available for removal
        $cr_remove_qty  = min($to_remove_qty, $cart_item_qty); 
        $new_qty        = $cart_item['quantity'] - $cr_remove_qty; //new quantity for the cart item
        
        $to_remove_qty -= $cr_remove_qty; //adjust the remove quantity variable

        if(0 < $new_qty)
        {
            $this->update_cart_qty($cart_item_key, $new_qty);  
        }else
        {
            WC()->cart->remove_cart_item($cart_item_key); //no balance quantity so remove the item
        }
    }

}

BCSmartCouponGiveawayProductPublic::get_instance();