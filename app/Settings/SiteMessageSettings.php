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
            self::$instance = new self( 'wooless_homepage_settings_options' );
        }

        return self::$instance;
    }
    
    public function settings_callback( $options )
    {
        $site_message = array();
        if (isset($_POST['homepage_layout'])) {
            $site_message = json_decode( stripslashes($_POST['site_message']), true );
        }

        if (is_array($site_message)) {
            update_option('blaze_wooless_site_message', $site_message);

            TypesenseClient::get_instance()->site_info()->upsert([
                'id' => '1000003',
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
        add_action( 'before_draggable_layout_editor_end', array( $this, 'default_draggable_data' ), 10 );
    }

    public function footer_callback()
    {
        require_once BLAZE_WOOLESS_PLUGIN_DIR . 'views/draggable-content.php';
    }

    public function default_draggable_data()
    {
        $hompage_layout = get_option('blaze_wooless_site_message', '');
        ?>
            <input type="hidden" name="site_message" value='<?php echo json_encode($hompage_layout) ?>'/>
        <?php
    }
}

SiteMessageSettings::get_instance();
