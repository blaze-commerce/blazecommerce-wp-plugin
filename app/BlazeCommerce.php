<?php

namespace BlazeCommerce;

use BlazeCommerce\Collections\Menu;
use BlazeCommerce\Collections\Taxonomy;

class BlazeCommerce
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
        // add_action('wp_update_nav_menu', array( Menu::get_instance(), 'update_typesense_document_on_menu_update' ), 10, 2);
        add_action('edited_term', array( Taxonomy::get_instance(), 'update_typesense_document_on_taxonomy_edit' ), 10, 3);

        TypesenseClient::get_instance();

        $this->register_settings();
        
        $this->register_features();
        
        Ajax::get_instance();
        Woocommerce::get_instance();
    }

    public function register_settings()
    {
        $settings = array(
            '\\BlazeCommerce\\Settings\\GeneralSettings',
            '\\BlazeCommerce\\Settings\\RegionalSettings',
            '\\BlazeCommerce\\Settings\\ProductPageSettings',
            '\\BlazeCommerce\\Settings\\HomePageSettings',
            '\\BlazeCommerce\\Settings\\SiteMessageTopHeaderSettings',
            '\\BlazeCommerce\\Settings\\SiteMessageSettings',
            '\\BlazeCommerce\\Settings\\FooterBeforeSettings',
            '\\BlazeCommerce\\Settings\\FooterOneSettings',
            '\\BlazeCommerce\\Settings\\FooterTwoSettings',
            '\\BlazeCommerce\\Settings\\FooterThreeSettings',
            '\\BlazeCommerce\\Settings\\FooterAfterSettings',
        );

        foreach ( $settings as $setting ) {
            // Instantiating the settings will register an admin_init hook to add the configuration
            // See here BlazeCommerce\Settings\BaseSEttings.php @ line 18
            $setting::get_instance();
        }
    }

    public function register_features()
    {
        $features = array(
            '\\BlazeCommerce\\Features\\AttributeSettings',
            '\\BlazeCommerce\\Features\\CalculateShipping',
            '\\BlazeCommerce\\Features\\DraggableContent',
            '\\BlazeCommerce\\Features\\LoadCartFromSession',
        );

        foreach ( $features as $feature ) {
            $feature::get_instance();
        }
    }

    public function register_extensions()
    {
        $extensions = array(
            '\\BlazeCommerce\\Extensions\\CustomProductTabsForWoocommerce',
            '\\BlazeCommerce\\Extensions\\JudgeMe',
            '\\BlazeCommerce\\Extensions\\ProductAddons',
            '\\BlazeCommerce\\Extensions\\WoocommerceAeliaCurrencySwitcher',
            // '\\BlazeCommerce\\Extensions\\WoocommercePriceBasedOnCountry',
            '\\BlazeCommerce\\Extensions\\YoastSEO',
            '\\BlazeCommerce\\Extensions\\GraphQL',
            '\\BlazeCommerce\\Extensions\\WoocommerceVariationSwatches',
        );

        foreach ( $extensions as $extension ) {
            // Instantiating the extension will run all hooks in it's constructor
            $extension::get_instance();
        }
    }
}

BlazeCommerce::get_instance();
