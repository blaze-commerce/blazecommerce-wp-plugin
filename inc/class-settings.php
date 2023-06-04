<?php

if ( !class_exists( 'Blaze_Wooless_Settings' ) ) {
    class Blaze_Wooless_Settings {
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
            add_action( 'admin_init', array( $this, 'initialize_wooless_settings' ), 10, 1 );
        }

        public function initialize_wooless_settings()
        {
            Blaze_Wooless_General_Settings::get_instance()->init();
            Blaze_Wooless_Product_Page_Settings::get_instance()->init();
        }        
    }

    Blaze_Wooless_Settings::get_instance();
}
