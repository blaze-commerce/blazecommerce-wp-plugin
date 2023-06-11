<?php

namespace BlazeWooless;

use BlazeWooless\Collections\Menu;
use BlazeWooless\Collections\Taxonomy;

class BlazeWooless
{
    private static $instance = null;

    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function init()
    {
        // add_action('ts_product_update', array( $this, 'revalidate_frontend_path' ), 10, 1);
        // add_action('next_js_revalidation_event', array( $this, 'do_next_js_revalidation_event' ), 10, 2);
        add_action( 'init', array( $this, 'register_extensions' ) );
        add_action('wp_update_nav_menu', array( Menu::get_instance(), 'update_typesense_document_on_menu_update' ), 10, 2);
        add_action('edited_term', array( Taxonomy::get_instance(), 'update_typesense_document_on_taxonomy_edit' ), 10, 3);

        TypesenseClient::get_instance();

        $this->register_settings();
        
        Ajax::get_instance();
        Woocommerce::get_instance();
    }

    public function register_settings()
    {
        $settings = array(
            '\\BlazeWooless\\Settings\\GeneralSettings',
            '\\BlazeWooless\\Settings\\RegionalSettings',
            '\\BlazeWooless\\Settings\\ProductPageSettings',
        );

        foreach ( $settings as $setting ) {
            // Instantiating the settings will register an admin_init hook to add the configuration
            // See here BlazeWooless\Settings\BaseSEttings.php @ line 18
            $setting::get_instance();
        }
    }

    public function register_extensions()
    {
        $extensions = array(
            '\\BlazeWooless\\Extensions\\CustomProductTabsForWoocommerce',
            '\\BlazeWooless\\Extensions\\JudgeMe',
            '\\BlazeWooless\\Extensions\\ProductAddons',
            '\\BlazeWooless\\Extensions\\WoocommerceAeliaCurrencySwitcher',
            '\\BlazeWooless\\Extensions\\WoocommercePriceBasedOnCountry',
            '\\BlazeWooless\\Extensions\\YoastSEO',
        );

        foreach ( $extensions as $extension ) {
            // Instantiating the extension will run all hooks in it's constructor
            $extension::get_instance();
        }
    }
}

BlazeWooless::get_instance();
