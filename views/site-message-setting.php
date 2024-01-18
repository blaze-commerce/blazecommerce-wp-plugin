<?php

add_action( 'bwl_setting_menu', 'bwl_site_message', 10 );
add_action( 'admin_init', 'site_message_settings' );
add_action( 'admin_enqueue_scripts', 'my_admin_enqueue_scripts' );

function bwl_site_message( $menu_slug ) {
	add_submenu_page(
		$menu_slug,
		'Site Message',
		'Site Message',
		'manage_options',
		$menu_slug . '-site-message',
		'site_message_page'
	);
}

function site_message_page() {
	?>
	<div class="site-message-wrap">
		<h1>Site Message</h1>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'site_message_settings' );
			do_settings_sections( 'site_message' );
			submit_button();
			?>
		</form>
	</div>
	<?php
}

function site_message_settings() {
	register_setting( 'site_message_settings', 'default_message' );

	$regions = [ 'NZ', 'US' ]; // Add more regions as needed.
	foreach ( $regions as $region ) {
		register_setting( 'site_message_settings', $region . '_message' );
		register_setting( 'site_message_settings', $region . '_enabled' );
	}

	add_settings_section(
		'site_message_section',
		'Geo Config',
		null,
		'site_message'
	);

	add_settings_field(
		'default_message',
		'Default Site Message',
		'default_message_callback',
		'site_message',
		'site_message_section'
	);

	add_settings_field(
		'region_messages',
		'Region Specific Messages',
		'region_messages_callback',
		'site_message',
		'site_message_section'
	);
}

function default_message_callback() {
	$default_message = get_option( 'default_message', '' );
	echo "<input class='def_message' type='text' name='default_message' value='$default_message'>";
}

function region_messages_callback() {
	$regions = [ 'NZ', 'US' ]; // Add more regions as needed.
	foreach ( $regions as $region ) {
		$region_message = get_option( $region . '_message', '' );
		$region_enabled = get_option( $region . '_enabled', '' ) === '1' ? 'checked' : '';
		echo "<div class='region_message'><label><input class='region-checkbox' type='checkbox' name='${region}_enabled' value='1' $region_enabled>$region</label><input class='region-message' id='${region}_message' type='text' name='${region}_message' value='$region_message'></div><br/>";
	}
}

if ( ! class_exists( 'Blaze_Wooless_Site_Message_Compatibility' ) ) {
	class Blaze_Wooless_Site_Message_Compatibility {
		private static $instance = null;

		public static function get_instance() {
			if ( self::$instance === null ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		public function __construct() {
			add_filter( 'blaze_wooless_additional_site_info_message', array( $this, 'add_site_messages' ), 10, 1 );
		}

		public function add_site_messages( $site_messages_settings ) {
			$default_message = get_option( 'default_message', '' );
			if ( $default_message !== '' ) {
				$site_messages_settings['default_message'] = $default_message;
			}

			$regions = [ 'NZ', 'US' ]; // Add more regions as needed
			foreach ( $regions as $region ) {
				$region_message = get_option( $region . '_message', '' );
				if ( $region_message !== '' ) {
					$site_messages_settings[ $region . '_message' ] = $region_message;
				}
			}

			return $site_messages_settings;
		}
	}

	Blaze_Wooless_Site_Message_Compatibility::get_instance();
}
