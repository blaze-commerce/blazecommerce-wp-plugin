<?php

namespace BlazeWooless\Settings;

use BlazeWooless\TypesenseClient;

class SiteMessageSettings extends BaseSettings {
    private static $instance = null;
    public $tab_key = 'sitemessage';
    public $page_label = 'Site Message';

    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self( 'wooless_sitemessage_settings_options' );
        }

        return self::$instance;
    }
    
    public function settings_callback( $options )
    {
        $site_message = array();
        if (isset($_POST['site_message'])) {
            $site_message = json_decode( stripslashes($_POST['site_message']), true );
        }

        if (is_array($site_message)) {
            update_option('blaze_wooless_site_message', $site_message);

            TypesenseClient::get_instance()->site_info()->upsert([
                'id' => '1000004',
                'name' => 'site_message',
                'value' => json_encode($site_message),
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
        add_action( 'blaze_wooless_after_site_info_sync', array( $this, 'add_site_settings_data' ), 10, 2 );
    }

    public function footer_callback()
    {
        require_once BLAZE_WOOLESS_PLUGIN_DIR . 'views/draggable-content-simple.php';
    }

    public function default_draggable_data()
    {
        if (empty($_GET['tab']) || $this->tab_key !== $_GET['tab']) return;

        $site_message = get_option('blaze_wooless_site_message', '');
        ?>
            <input type="hidden" id="draggable_result" name="site_message" value='<?php echo json_encode($site_message) ?>'/>
        <?php
    }

    public function add_site_settings_data()
    {
        $site_message = get_option('blaze_wooless_site_message', '');
        TypesenseClient::get_instance()->site_info()->upsert([
            'id' => '1000004',
            'name' => 'site_message',
            'value' => json_encode($site_message),
            'updated_at' => time(),
        ]);
    }
}

SiteMessageSettings::get_instance();
