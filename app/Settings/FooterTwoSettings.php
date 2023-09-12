<?php

namespace BlazeCommerce\Settings;

use BlazeCommerce\TypesenseClient;

class FooterTwoSettings extends BaseSettings {
    private static $instance = null;
    public $tab_key = 'footer_2';
    public $page_label = 'Footer 2';

    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self( 'blaze_commerce_footer_2_settings_options' );
        }

        return self::$instance;
    }
    
    public function settings_callback( $options )
    {
        $footer_content_2 = array();
        if (isset($_POST['footer_content_2'])) {
            $footer_content_2 = json_decode( stripslashes($_POST['footer_content_2']), true );
        }

        if (is_array($footer_content_2)) {
            update_option('blaze_commerce_footer_2_content', $footer_content_2);

            TypesenseClient::get_instance()->site_info()->upsert([
                'id' => '1000008',
                'name' => 'footer_content_2',
                'value' => json_encode($footer_content_2),
                'updated_at' => time(),
            ]);
        }
        
        return $options;
    }

    public function settings()
    {
        return array();
    }

    public function section_callback() {
        echo '<p>Select which areas of content you wish to display.</p>';
    }

    public function register_hooks()
    {
        add_action( 'blaze_commerce_render_settings_tab_footer', array( $this, 'default_draggable_data' ), 10 );
        add_action( 'blaze_commerce_after_site_info_sync', array( $this, 'add_footer_two_data' ), 10, 2 );
    }

    public function footer_callback()
    {
        require_once BLAZE_COMMERCE_PLUGIN_DIR . 'views/draggable-content-simple.php';
    }

    public function default_draggable_data()
    {
        if (empty($_GET['tab']) || $this->tab_key !== $_GET['tab']) return;

        $footer_content = get_option('blaze_commerce_footer_2_content', '');
        ?>
            <input type="hidden" id="draggable_result" name="footer_content_2" value='<?php echo json_encode($footer_content) ?>'/>
        <?php
    }

    public function add_footer_two_data()
    {
        $footer_content_2 = get_option('blaze_commerce_footer_2_content', '');
        TypesenseClient::get_instance()->site_info()->upsert([
            'id' => '1000008',
            'name' => 'footer_content_2',
            'value' => json_encode($footer_content_2),
            'updated_at' => time(),
        ]);
    }
}

FooterTwoSettings::get_instance();
