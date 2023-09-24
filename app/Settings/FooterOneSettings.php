<?php

namespace BlazeWooless\Settings;

use BlazeWooless\TypesenseClient;

class FooterOneSettings extends BaseSettings {
    private static $instance = null;
    public $tab_key = 'footer_1';
    public $page_label = 'Footer 1';

    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self( 'wooless_footer_1_settings_options' );
        }

        return self::$instance;
    }
    
    public function settings_callback( $options )
    {
        $footer_content_1 = array();
        if (isset($_POST['footer_content_1'])) {
            $footer_content_1 = json_decode( stripslashes($_POST['footer_content_1']), true );
        }

        if (is_array($footer_content_1)) {
            update_option('blaze_wooless_footer_1_content', $footer_content_1);

            TypesenseClient::get_instance()->site_info()->upsert([
                'id' => '1000006',
                'name' => 'footer_content_1',
                'value' => json_encode($footer_content_1),
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
        add_action( 'blaze_wooless_render_settings_tab_footer', array( $this, 'default_draggable_data' ), 10 );
        add_action( 'blaze_wooless_after_site_info_sync', array( $this, 'add_footer_one_data' ), 10, 2 );
    }

    public function footer_callback()
    {
        require_once BLAZE_WOOLESS_PLUGIN_DIR . 'views/draggable-content-simple.php';
    }

    public function default_draggable_data()
    {
        if (empty($_GET['tab']) || $this->tab_key !== $_GET['tab']) return;

        $footer_content = get_option('blaze_wooless_footer_1_content', '');
        ?>
            <input type="hidden" id="draggable_result" name="footer_content_1" value='<?php echo json_encode($footer_content) ?>'/>
        <?php
    }

    public function add_footer_one_data()
    {
        $footer_content_1 = get_option('blaze_wooless_footer_1_content', '');
		if (empty($footer_content_1)) return;

        TypesenseClient::get_instance()->site_info()->upsert([
            'id' => '1000006',
            'name' => 'footer_content_1',
            'value' => json_encode($footer_content_1),
            'updated_at' => time(),
        ]);
    }
}

FooterOneSettings::get_instance();
