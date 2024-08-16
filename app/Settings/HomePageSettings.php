<?php

namespace BlazeWooless\Settings;

use BlazeWooless\TypesenseClient;

class HomePageSettings extends BaseSettings {
	private static $instance = null;
	public $tab_key = 'homepage';
	public $page_label = 'Home Page';

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self( 'wooless_homepage_settings_options' );
		}

		return self::$instance;
	}

	public function settings_callback( $options ) {
		$homepage_layout = array();
		if ( isset( $_POST['homepage_layout'] ) ) {
			$homepage_layout = json_decode( stripslashes( $_POST['homepage_layout'] ), true );
			$homepage_layout = array_map( function ($block) {
				$base_country = \WC()->countries->get_base_country();
				if ( $block['blockId'] === 'gutenbergBlocks' && is_numeric( $block['metaData'][ $base_country ]['id'] ) ) {
					$block['metaData'][ $base_country ]['content'] = get_post_field( 'post_content', $block['metaData'][ $base_country ]['id'] );
				}

				return $block;
			}, $homepage_layout );
		}

		if ( is_array( $homepage_layout ) ) {
			update_option( 'blaze_wooless_homepage_layout', $homepage_layout );

			$home_page_data = [ 
				'id' => '1000003',
				'name' => 'homepage_layout',
				'value' => json_encode( $homepage_layout ),
				'updated_at' => time(),
			];
			TypesenseClient::get_instance()->site_info()->upsert( $home_page_data );

			do_action( 'blaze_wooless_update_page_layout', $home_page_data );
		}

		return $options;
	}

	public function settings() {
		return array();
	}

	public function section_callback() {
		echo '<p>Select which areas of content you wish to display.</p>';
	}

	public function register_hooks() {
		add_action( 'blazecommerce/settings/render_settings_tab_content_footer', array( $this, 'default_draggable_data' ), 10 );
		add_action( 'blaze_wooless_after_site_info_sync', array( $this, 'add_homepage_data' ), 10, 2 );

		add_filter( 'blaze_wooless_additional_site_info', array( $this, 'add_home_page_slug' ), 10, 1 );
	}

	public function add_home_page_slug( $site_infos ) {
		$home_page_id = get_option( 'page_on_front' );
		if ( ! empty( $home_page_id ) ) {
			$site_infos['homepage_slug'] = (string) get_post_field( 'post_name', $home_page_id );
		}
		return $site_infos;
	}

	public function footer_callback() {
		require_once BLAZE_WOOLESS_PLUGIN_DIR . 'views/draggable-content.php';
	}

	public function default_draggable_data() {
		if ( empty( $_GET['tab'] ) || $this->tab_key !== $_GET['tab'] )
			return;

		$homepage_layout = get_option( 'blaze_wooless_homepage_layout', '' );
		?>
		<input type="hidden" id="draggable_result" name="homepage_layout"
			value='<?php echo htmlspecialchars( json_encode( $homepage_layout ), ENT_QUOTES ) ?>' />
		<?php
	}

	public function add_homepage_data() {
		$homepage_layout = get_option( 'blaze_wooless_homepage_layout', '' );
		if ( empty( $homepage_layout ) )
			return;

		TypesenseClient::get_instance()->site_info()->upsert( [ 
			'id' => '1000003',
			'name' => 'homepage_layout',
			'value' => json_encode( $homepage_layout ),
			'updated_at' => time(),
		] );
	}
}

HomePageSettings::get_instance();
