<?php


namespace BlazeWooless\Features;

use BlazeWooless\TypesenseClient;

class ContentBuilder {
	private static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		add_action( 'init', array( $this, 'register_blaze_settings_post_type' ), 0, 1 );
		add_action( 'admin_init', array( $this, 'prevent_access_to_list' ));
		add_action( 'save_post_blaze_settings', array( $this, 'save' ), 10, 2);
	}

	public function register_blaze_settings_post_type() {
		$labels = array(
			'name'                => _x( 'Blaze Commerce Settings', 'Post Type General Name', 'blaze-commerce' ),
			'singular_name'       => _x( 'Blaze Commerce Setting', 'Post Type Singular Name', 'blaze-commerce' ),
			'menu_name'           => __( 'Blaze Commerce Settings', 'blaze-commerce' ),
			'parent_item_colon'   => __( 'Parent Blaze Commerce Setting', 'blaze-commerce' ),
			'all_items'           => __( 'All Blaze Commerce Settings', 'blaze-commerce' ),
			'view_item'           => __( 'View Blaze Commerce Setting', 'blaze-commerce' ),
			'add_new_item'        => __( 'Add New Blaze Commerce Setting', 'blaze-commerce' ),
			'add_new'             => __( 'Add New', 'blaze-commerce' ),
			'edit_item'           => __( 'Edit Blaze Commerce Setting', 'blaze-commerce' ),
			'update_item'         => __( 'Update Blaze Commerce Setting', 'blaze-commerce' ),
			'search_items'        => __( 'Search Blaze Commerce Setting', 'blaze-commerce' ),
			'not_found'           => __( 'Not Found', 'blaze-commerce' ),
			'not_found_in_trash'  => __( 'Not found in Trash', 'blaze-commerce' ),
		);
			
		$args = array(
			'label'               => __( 'blaze commerce settings', 'blaze-commerce' ),
			'description'         => __( 'Blaze Commerce Settings', 'blaze-commerce' ),
			'labels'              => $labels,
			'supports'            => array( 'title', 'editor', 'revisions', 'custom-fields', ),
			'taxonomies'          => array( 'genres' ),
			'hierarchical'        => false,
			'public'              => true,
			'show_ui'             => true,
			'show_in_menu'        => false,
			'show_in_nav_menus'   => false,
			'show_in_admin_bar'   => false,
			'menu_position'       => 5,
			'can_export'          => true,
			'has_archive'         => true,
			'exclude_from_search' => false,
			'publicly_queryable'  => true,
			'capability_type'     => 'post',
			'show_in_rest' => true,
		);
			
		// Registering your Custom Post Type
		register_post_type( 'blaze_settings', $args );
	}

	public function prevent_access_to_list() {
		if ( '/wp-admin/edit.php?post_type=blaze_settings' === $_SERVER['REQUEST_URI'] ) {
			wp_redirect( admin_url('admin.php?page=wooless-settings') );
		}
	}

	public function save( $post_id, $post ) {
		// Check if this is an autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
	
		// Check user permissions
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$post_name = $post->post_name;
		$settings = apply_filters( 'set_blaze_setting_data', array(), $post_id, $post );
		
		if ( isset( $settings[ $post_name ] ) ) {
			TypesenseClient::get_instance()
				->site_info()
				->upsert( $settings[ $post_name ] );
		}
	}
}