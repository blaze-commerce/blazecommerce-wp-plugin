<?php
if ( !class_exists( 'Blaze_Wooless_Yoast_SEO_Compatibility' ) ) {
    class Blaze_Wooless_Yoast_SEO_Compatibility {
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
            if ( is_plugin_active( 'wordpress-seo/wp-seo.php' ) ) {
                add_filter( 'blaze_wooless_product_data_for_typesense', array( $this, 'add_seo_to_product_schema' ), 10, 2 );
            }
        }

        public function add_seo_to_product_schema( $product_data, $product_id )
        {
            $product = wc_get_product( $product_id );

            // Generate seo
            $seo_head = '';
            $prev_post = $GLOBALS['post'];
            $GLOBALS['post'] = get_post($product->get_id());

            $wpseo_frontend = WPSEO_Frontend::get_instance();
            $title = $wpseo_frontend->get_content_title();
            $metadesc = $wpseo_frontend->get_meta_description();

            $canonical = WPSEO_Meta::get_value('canonical');
            $canonical = $canonical ? $canonical : get_permalink($product->get_id());

            $seo_head = "<title>$title</title>";
            $seo_head .= "<meta name='description' content='$metadesc' />";
            $seo_head .= "<link rel='canonical' href='$canonical' />";

            $GLOBALS['post'] = $prev_post;
            $product_data['seo'] = $seo_head;

            // Generate full seo head
            $fullHead = '';
            if ( $this->is_wp_graphql_yoast_seo_active() ) {
                $meta = YoastSEO()->meta->for_post($product_id);
                $fullHead = wp_gql_seo_get_full_head($meta);
            }
            $product_data['seoFullHead'] = $fullHead;

            return $product_data;
        }

        public function is_wp_graphql_yoast_seo_active()
        {
            return is_plugin_active( 'wp-graphql-yoast-seo-master/wp-graphql-yoast-seo.php' ) || is_plugin_active( 'add-wpgraphql-seo/wp-graphql-yoast-seo.php' );
        }
    }

    Blaze_Wooless_Yoast_SEO_Compatibility::get_instance();
}
