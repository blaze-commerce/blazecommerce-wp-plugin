<?php
/*
Plugin Name: Blaze Commerce
Plugin URI: https://www.blazecommerce.io
Description: The official plugin that integrates your site with the Blaze Commerce service.
Version: 1.14.5
Requires Plugins: woocommerce, wp-graphql, wp-graphql-cors, wp-graphql-jwt-authentication, wp-graphql-woocommerce
Author: Blaze Commerce
Author URI: https://www.blazecommerce.io
*/

use BlazeWooless\PostType;

define( 'BLAZE_COMMERCE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BLAZE_COMMERCE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BLAZE_COMMERCE_VERSION', '1.14.5' );

require 'vendor/autoload.php';
require_once plugin_dir_path( __FILE__ ) . 'lib/class-tgm-plugin-activation.php';
require_once plugin_dir_path( __FILE__ ) . 'lib/regional-data-helper.php';
require_once plugin_dir_path( __FILE__ ) . 'lib/setting-helper.php';
require_once plugin_dir_path( __FILE__ ) . 'lib/blaze-wooless-functions.php';
require_once plugin_dir_path( __FILE__ ) . 'lib/blaze-wooless-shortcodes.php';
require_once plugin_dir_path( __FILE__ ) . 'blocks/blocks.php';

// Include test file for development/debugging
// Updated: Enhanced debugging support for version bump testing
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
	require_once plugin_dir_path( __FILE__ ) . 'test/test-country-specific-images.php';
}


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
 * Register the required plugins for the plugin
 */
add_action( 'tgmpa_register', function () {
	$plugins = array(
		array(
			'name' => 'WooCommerce',
			'slug' => 'woocommerce',
			'required' => true,
		),
		array(
			'name' => 'WPGraphQL',
			'slug' => 'wp-graphql',
			'required' => true,
		),

		array(
			'name' => 'WPGraphQL CORS',
			'slug' => 'wp-graphql-cors',
			'required' => true,
			'source' => 'https://github.com/funkhaus/wp-graphql-cors/archive/master.zip',
		),

		array(
			'name' => 'WPGraphQL JWT Authentication',
			'slug' => 'wp-graphql-jwt-authentication',
			'required' => true,
			'source' => 'https://github.com/wp-graphql/wp-graphql-jwt-authentication/archive/master.zip',
		),

		array(
			'name' => 'WPGraphQL WooCommerce',
			'slug' => 'wp-graphql-woocommerce',
			'required' => true,
			'source' => 'https://github.com/wp-graphql/wp-graphql-woocommerce/archive/master.zip',
		),

		array(
			'name' => 'Enable Tailwind CSS Classes in Gutenberg',
			'slug' => 'website-builder',
			'required' => false,
		)

	);

	$config = array(
		'id' => 'blaze-commerce',                 // Unique ID for hashing notices for multiple instances of TGMPA.
		'default_path' => '',                      // Default absolute path to bundled plugins.
		'menu' => 'tgmpa-install-plugins', // Menu slug.
		'parent_slug' => 'plugins.php',            // Parent menu slug.
		'capability' => 'manage_options',    // Capability needed to view plugin install page, should be a capability associated with the parent menu used.
		'has_notices' => true,                    // Show admin notices or not.
		'dismissable' => true,                    // If false, a user cannot dismiss the nag message.
		'dismiss_msg' => '',                      // If 'dismissable' is false, this message will be output at top of nag.
		'is_automatic' => false,                   // Automatically activate plugins after installation or not.
		'message' => '',
		'strings' => array(
			'page_title' => __( 'Install Required Plugins', 'blaze-commerce' ),
			'menu_title' => __( 'Install Plugins', 'blaze-commerce' ),
			'installing' => __( 'Installing Plugin: %s', 'blaze-commerce' ),
			'oops' => __( 'Something went wrong with the plugin API.', 'blaze-commerce' ),
			'notice_can_install_required' => _n_noop(
				'Blaze Commerce requires the following plugin: %1$s.',
				'Blaze Commerce requires the following plugins: %1$s.',
				'blaze-commerce'
			),
			'notice_can_install_recommended' => _n_noop(
				'Blaze Commerce recommends the following plugin: %1$s.',
				'Blaze Commerce recommends the following plugins: %1$s.',
				'blaze-commerce'
			),
			'notice_cannot_install' => _n_noop(
				'Sorry, but you do not have the correct permissions to install the %s plugin.',
				'Sorry, but you do not have the correct permissions to install the %s plugins.',
				'blaze-commerce'
			),
			'notice_ask_to_update' => _n_noop(
				'Blaze Commerce recommends you update the following plugin: %1$s.',
				'Blaze Commerce recommends you update the following plugins: %1$s.',
				'blaze-commerce'
			),
			'notice_ask_to_update_maybe' => _n_noop(
				'There is an update available for: %1$s.',
				'There are updates available for the following plugins: %1$s.',
				'blaze-commerce'
			),
			'notice_can_activate_required' => _n_noop(
				'The following required plugin is currently inactive: %1$s.',
				'The following required plugins are currently inactive: %1$s.',
				'blaze-commerce'
			),
			'notice_can_activate_recommended' => _n_noop(
				'The following recommended plugin is currently inactive: %1$s.',
				'The following recommended plugins are currently inactive: %1$s.',
				'blaze-commerce'
			),
			'install_link' => _n_noop(
				'Begin installing plugin',
				'Begin installing plugins',
				'blaze-commerce'
			),
			'update_link' => _n_noop(
				'Begin updating plugin',
				'Begin updating plugins',
				'blaze-commerce'
			),
		)
	);

	tgmpa( $plugins, $config );
} );

function plugin_activate() {

	PostType::initialize_default_home_page();
}
register_activation_hook( __FILE__, 'plugin_activate' );