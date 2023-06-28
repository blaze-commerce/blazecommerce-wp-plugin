<?php

namespace BlazeWooless\Extensions;

class WoocommerceVariationSwatches
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
        if($this->is_active()) {
            add_filter('blaze_wooless_product_attribute_for_typesense', array( $this, 'add_swatches_data' ), 10, 2);
        }
    }

    public function is_active()
    {
        return is_plugin_active('woo-variation-swatches/woo-variation-swatches.php');
    }


    public function add_swatches_data($attribute_to_register, $attribute)
    {
        // Set default attribute type to select
        $attribute_to_register['type'] = 'select';
        if($attribute->is_taxonomy()) {
            $taxonomy_id = $attribute->get_id();
            $swatch_attribute = woo_variation_swatches()->get_frontend()->get_attribute_taxonomy_by_id($taxonomy_id);
            if($swatch_attribute->attribute_type) {
                // Set type depending on what is selected for the woocommerce attribute in wp admin
                $attribute_to_register['type'] = $swatch_attribute->attribute_type;
                $attribute_to_register = $this->get_options_value($attribute_to_register, $attribute);
            }

        }

        return $attribute_to_register;
    }

    public function get_options_value($attribute_to_register, $attribute)
    {
        $type = $attribute_to_register['type'];
        foreach($attribute_to_register['options'] as $key =>  $option) {
            $attribute_to_register['options'][$key]['value'] = $this->get_option_value($type, $option['term_id'], $option);
        }
        return $attribute_to_register;
    }

    public function get_option_value($type, $term_id, $option)
    {
        // default value will be the option name 
        $value = $option['name'];
        if(!empty($term_id)) {
            switch ($type) {
                case "color":
                    $value = $this->get_color_hex($term_id);
                    break;
                case "image":
                    //TODO supply correct value
                    $value = '';
                    break;
                case "button":
                    //TODO supply correct value
                    $value = $option['name'];
                    break;
                case "radio":
                    //TODO supply correct value
                    $value = $option['name'];
                    break;
                default:
                    $value = $option['name'];
            }
        }

        return $value;
    }

    public function get_color_hex($term_id)
    {
        $swatch_frontend = woo_variation_swatches()->get_frontend();
        $value = sanitize_hex_color($swatch_frontend->get_product_attribute_color($term_id));

        return $value;
    }

    public function get_image_src()
    {

    }
}
