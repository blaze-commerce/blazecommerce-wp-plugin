<?php


namespace BlazeWooless\Features;

class DraggableContent
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
        add_filter( 'blaze_wooless_product_data_for_typesense', array( $this, 'add_dragable_content' ), 10, 2 );
        add_action( 'blaze_wooless_render_settings_tab_footer', array( $this, 'footer' ) );
    }

    public function add_dragable_content( $product_data, $product_id )
    {
        return $product_data;
    }

    // public function register_settings($product_page_settings)
    // {
    //     $product_page_settings['wooless_settings_attributes_section'] = array(
    //         'label' => 'Attributes',
    //         'options' => $this->get_attribute_mapping_settings(),
    //     );

    //     return $product_page_settings;
    // }

    public function footer( $active_tab )
    {
        if ( $active_tab !== 'product' ) return;

        require_once BLAZE_WOOLESS_PLUGIN_DIR . 'views/draggable-content.php';
    }
}
