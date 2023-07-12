<?php

namespace BlazeWooless\Settings;

use BlazeWooless\TypesenseClient;

class HomePageSettings extends BaseSettings {
    private static $instance = null;
    public $tab_key = 'homepage';
    public $page_label = 'Home Page';

    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self( 'wooless_homepage_settings_options' );
        }

        return self::$instance;
    }
    
    public function settings_callback( $options )
    {
        $homepage_layout = array();
        if (isset($_POST['homepage_layout'])) {
            $homepage_layout = json_decode( stripslashes($_POST['homepage_layout']), true );
        }

        if (is_array($homepage_layout)) {
            update_option('blaze_wooless_homepage_layout', $homepage_layout);

            TypesenseClient::get_instance()->site_info()->upsert([
                'id' => '1000003',
                'name' => 'homepage_layout',
                'value' => json_encode($homepage_layout),
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
        $hompage_layout = get_option('blaze_wooless_homepage_layout', '');
        ?>
            <input type="hidden" name="homepage_layout" value='<?php echo json_encode($hompage_layout) ?>'/>
        <?php
    }
}

HomePageSettings::get_instance();
