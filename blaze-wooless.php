<?php
/*
Plugin Name: Blaze Commerce
Plugin URI: https://www.blazecommerce.io
Description: The official plugin that integrates your site with the Blaze Commerce service.
Version: 1.5.1
Author: Blaze Commerce
Author URI: https://www.blazecommerce.io
*/

use Http\Message\Authentication\Header;

define( 'BLAZE_WOOLESS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BLAZE_WOOLESS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BLAZE_WOOLESS_VERSION', '1.5.1' );

require 'vendor/autoload.php';
require_once plugin_dir_path( __FILE__ ) . 'lib/regional-data-helper.php';
require_once plugin_dir_path( __FILE__ ) . 'lib/setting-helper.php';
require_once plugin_dir_path( __FILE__ ) . 'lib/blaze-wooless-functions.php';
require_once plugin_dir_path( __FILE__ ) . 'blocks/blocks.php';


// Initialize plugin
function BlazeCommerce() {
	return \BlazeWooless\BlazeWooless::get_instance();
}

BlazeCommerce()->init();


add_action( 'admin_enqueue_scripts', 'enqueue_typesense_product_indexer_scripts' );
add_action( 'admin_menu', 'add_typesense_product_indexer_menu' );

function enqueue_typesense_product_indexer_scripts() {
	wp_enqueue_script( 'jquery' );
}
function typesense_enqueue_google_fonts( $hook ) {
	// Only load the font on your plugin's page
	if ( 'toplevel_page_wooless-settings' !== $hook ) {
		return;
	}

	// Register and enqueue the 'Poppins' Google Font
	wp_register_style( 'google-font-poppins', 'https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,500;1,400&display=swap', array( 'chosen' ), null );
	wp_enqueue_style( 'google-font-poppins' );

	wp_register_style(
		'chosen',
		'//cdnjs.cloudflare.com/ajax/libs/chosen/1.1.0/chosen.min.css',
		array(),
		null,
		'all',
	);
	wp_enqueue_style( 'chosen' );

	wp_enqueue_style( 'blaze-wooless-admin-style', plugins_url( 'assets/css/blaze-wooless.css', __FILE__ ), null, '1.0' );
	wp_enqueue_script( 'blaze-wooless-admin-script', plugins_url( 'assets/js/blaze-wooless.js', __FILE__ ), array( 'jquery', 'jquery-ui-droppable', 'jquery-ui-draggable', 'jquery-ui-sortable' ), '1.0', true );
	// wp_enqueue_script( 'blaze-wooless-admin-script-react', plugins_url( 'dist/main.js', __FILE__ ), array( 'jquery', 'jquery-ui-droppable', 'jquery-ui-draggable', 'jquery-ui-sortable' ), '1.0', true );

	wp_register_script(
		'chosen',
		'//cdnjs.cloudflare.com/ajax/libs/chosen/1.1.0/chosen.jquery.min.js',
		array( 'jquery' ),
		null,
		true,
	);
	wp_enqueue_script( 'chosen' );


	wp_register_style(
		'jquery.modal',
		'https://cdnjs.cloudflare.com/ajax/libs/jquery-modal/0.9.1/jquery.modal.min.css',
		array(),
		null,
		'all',
	);
	wp_enqueue_style( 'jquery.modal' );
	wp_register_script(
		'jquery.modal',
		'https://cdnjs.cloudflare.com/ajax/libs/jquery-modal/0.9.1/jquery.modal.min.js',
		array( 'jquery' ),
		null,
		true,
	);
	wp_enqueue_script( 'jquery.modal' );

	// register jsrender cdn
	wp_register_script(
		'jsrender',
		'https://cdnjs.cloudflare.com/ajax/libs/jsrender/1.0.14/jsrender.min.js',
		array( 'jquery' ),
		null,
		true,
	);

	if ( is_admin() ) :
		// only enqueue the script in wooless settings page and specially in synonym settings, effectively in the synonyms tab
		global $hook_suffix;
		if ( $hook_suffix == 'toplevel_page_wooless-settings' && isset( $_GET['tab'] ) && $_GET['tab'] == 'synonyms' ) :
			wp_enqueue_script( 'jsrender' );
		endif;
	endif;
}

add_action( 'admin_enqueue_scripts', 'typesense_enqueue_google_fonts' );

function typesense_product_indexer_page() {
	echo '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;700&display=swap">';
	require_once plugin_dir_path( __FILE__ ) . 'views/settings.php';
}

function add_typesense_product_indexer_menu() {
	$menu_slug = 'wooless-settings';

	add_menu_page(
		'Blaze Commerce',
		'Blaze Commerce',
		'manage_options',
		$menu_slug,
		'typesense_product_indexer_page',
		'dashicons-admin-generic'
	);

	// Create the submenus using the action
	do_action( 'bwl_setting_menu', $menu_slug );

	// Remove the default 'Wooless' submenu page
	remove_submenu_page( $menu_slug, $menu_slug );

	// Add the "Setting" subpage last so it appears at the end
	add_submenu_page(
		$menu_slug,
		'Setting',
		'Setting',
		'manage_options',
		$menu_slug,
		'typesense_product_indexer_page'
	);
}

/**
 * Fix issue with elementor editor
 * Replace home_url with site_url
 * @param $env
 * @return mixed	
 */
add_filter( 'elementor/editor/localize_settings', function ($env) {

	$site_url = get_site_url();
	$home_url = get_home_url();

	$env['initial_document']['urls'] = array_map( function ($value) use ($site_url, $home_url) {
		return str_replace( $home_url, $site_url, $value );
	}, $env['initial_document']['urls'] );

	$env['home_url'] = $site_url;

	return $env;
}, 9999999 );