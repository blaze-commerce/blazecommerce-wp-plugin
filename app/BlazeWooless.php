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
        add_action( 'init', array( $this, 'register_extensions' ) );
        add_action('edited_term', array( Taxonomy::get_instance(), 'update_typesense_document_on_taxonomy_edit' ), 10, 3);

        add_filter('blaze_wooless_generate_breadcrumbs', array( Taxonomy::get_instance(), 'generate_breadcrumbs' ), 10, 2);

        TypesenseClient::get_instance();

        $this->register_settings();
        
        $this->register_features();
        
        Ajax::get_instance();
        Woocommerce::get_instance();
    }

    public function register_settings()
    {
        $settings = array(
            '\\BlazeWooless\\Settings\\GeneralSettings',
            '\\BlazeWooless\\Settings\\RegionalSettings',
            '\\BlazeWooless\\Settings\\ProductFilterSettings',
            '\\BlazeWooless\\Settings\\ProductPageSettings',
            '\\BlazeWooless\\Settings\\HomePageSettings',
            '\\BlazeWooless\\Settings\\SiteMessageTopHeaderSettings',
            '\\BlazeWooless\\Settings\\SiteMessageSettings',
            '\\BlazeWooless\\Settings\\FooterBeforeSettings',
            '\\BlazeWooless\\Settings\\FooterOneSettings',
            '\\BlazeWooless\\Settings\\FooterTwoSettings',
            '\\BlazeWooless\\Settings\\FooterThreeSettings',
            '\\BlazeWooless\\Settings\\FooterAfterSettings',
        );

        foreach ( $settings as $setting ) {
            // Instantiating the settings will register an admin_init hook to add the configuration
            // See here BlazeWooless\Settings\BaseSEttings.php @ line 18
            $setting::get_instance();
        }
    }

    public function register_features()
    {
        $features = array(
            '\\BlazeWooless\\Features\\AttributeSettings',
            '\\BlazeWooless\\Features\\CalculateShipping',
            '\\BlazeWooless\\Features\\DraggableContent',
            '\\BlazeWooless\\Features\\LoadCartFromSession',
            '\\BlazeWooless\\Features\\Authentication',
			'\\BlazeWooless\\Features\\EditCartCheckout',
            '\\BlazeWooless\\Features\\CategoryBanner',
        );

        foreach ( $features as $feature ) {
            $feature::get_instance();
        }
    }

    public function register_extensions()
    {
        $extensions = array(
            '\\BlazeWooless\\Extensions\\CustomProductTabsForWoocommerce',
            '\\BlazeWooless\\Extensions\\JudgeMe',
            '\\BlazeWooless\\Extensions\\Yotpo',
            '\\BlazeWooless\\Extensions\\YithWishList',
            '\\BlazeWooless\\Extensions\\ProductAddons',
            '\\BlazeWooless\\Extensions\\WoocommerceAeliaCurrencySwitcher',
            '\\BlazeWooless\\Extensions\\WoocommerceAfterpay',
            '\\BlazeWooless\\Extensions\\WoocommerceGiftCards',
            // '\\BlazeWooless\\Extensions\\WoocommercePriceBasedOnCountry',
            '\\BlazeWooless\\Extensions\\YoastSEO',
            '\\BlazeWooless\\Extensions\\GraphQL',
            '\\BlazeWooless\\Extensions\\WoocommerceVariationSwatches',
        );

        foreach ( $extensions as $extension ) {
            // Instantiating the extension will run all hooks in it's constructor
            $extension::get_instance();
        }
    }
}

BlazeWooless::get_instance();
