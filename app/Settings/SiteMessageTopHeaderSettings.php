<?php

namespace BlazeWooless\Settings;

use BlazeWooless\TypesenseClient;

class SiteMessageTopHeaderSettings extends BaseSettings {
    private static $instance = null;
    public $tab_key = 'site_message_top_header';
    public $page_label = 'Site Message Top Header';

    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self( 'wooless_site_message_top_header_settings_options' );
        }

        return self::$instance;
    }

    public function update_typesense_data($site_message_top_header)
    {
        TypesenseClient::get_instance()->site_info()->upsert([
            'id' => '1002456',
            'name' => 'site_message_top_header',
            'value' => json_encode($site_message_top_header),
            'updated_at' => time(),
        ]);
    }
    
    public function settings_callback( $options )
    {
        $site_message_top_header = array();
        if (isset($_POST['site_message_top_header'])) {
            $site_message_top_header = json_decode( stripslashes($_POST['site_message_top_header']), true );
        }

        if (is_array($site_message_top_header)) {
            update_option('blaze_wooless_site_message_top_header', $site_message_top_header);

            $this->update_typesense_data( $site_message_top_header );
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

        $site_message_top_header = get_option('blaze_wooless_site_message_top_header', '');
        ?>
            <input type="hidden" id="draggable_result" name="site_message_top_header" value='<?php echo json_encode($site_message_top_header) ?>'/>
        <?php
    }

    public function add_site_settings_data()
    {
        $site_message_top_header = get_option('blaze_wooless_site_message_top_header', '');
		if (empty($site_message_top_header)) return;

        $this->update_typesense_data( $site_message_top_header );
    }
}

SiteMessageSettings::get_instance();
