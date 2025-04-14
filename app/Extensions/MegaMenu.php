<?php

namespace BlazeWooless\Extensions;

class MegaMenu {
	private static $instance = null;
	protected $widget_manager;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		if ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'megamenu/megamenu.php' ) ) {
			$this->widget_manager = new \Mega_Menu_Widget_Manager();
			add_filter( 'blaze_wooless_menu_items', array( $this, 'modify_menu_items' ), 10, 2 );
			add_filter( 'blaze_wooless_should_generate_menu_item_data', array( $this, 'should_generate_menu_item_data' ), 10, 2 );
			add_filter( 'blaze_wooless_menu_item_data', array( $this, 'menu_item_data' ), 10, 2 );

			add_filter( 'blazecommerce/collection/menu/menu_item_data', array( $this, 'modify_menu_item_data' ), 10, 2 );
		}
	}

	public function modify_menu_items( $menu_items, $menu ) {
		$mega_menu_items = apply_filters( 'wp_nav_menu_objects', $menu_items, array( 'walker' => new \Mega_Menu_Walker() ) );

		return $mega_menu_items;
	}

	public function is_mega_menu_sub_items( $item ) {
		return in_array( $item->type, array( 'mega_row', 'mega_column', 'widget' ) ) || ( 1 === $item->depth && 'grid' === $item->parent_submenu_type );
	}

	public function should_generate_menu_item_data( $should_generate, $item ) {
		if ( 0 === $item->depth && $item->megamenu_settings && 'grid' === $item->megamenu_settings['type'] && ( $item->megamenu_settings['grid'] && count( $item->megamenu_settings['grid'] ) > 0 ) ) {
			return false;
		}

		if ( $this->is_mega_menu_sub_items( $item ) ) {
			return false;
		}

		return $should_generate;
	}

	public function get_widget_content( $widget_id ) {
		return trim( $this->widget_manager->show_widget( $widget_id ) );
	}

	public function menu_item_data( $menu_item_data, $item ) {
		if ( $this->is_mega_menu_sub_items( $item ) ) {
			return $menu_item_data;
		}

		$megamenu_settings = $item->megamenu_settings;

		$children = array();

		if ( $megamenu_settings && 'grid' === $megamenu_settings['type'] ) {
			$children = array_map( function ($grid) {
				$grid['type'] = 'megamenu';

				if ( count( $grid['columns'] ) > 0 ) {
					$grid['columns'] = array_map( function ($column) {
						if ( isset( $column['items'] ) && count( $column['items'] ) > 0 ) {
							$column['items'] = array_map( function ($item) {
								if ( 'item' === $item['type'] ) {
									$menu_item_object = wp_setup_nav_menu_item( get_post( $item['id'] ) );

									$item['title'] = $menu_item_object->title;
									$item['url'] = $menu_item_object->url;

									$thumbnail_id   = get_woocommerce_term_meta( $menu_item_object->object_id, 'thumbnail_id', true );
									$image          = wp_get_attachment_url( $thumbnail_id );
									$image_fallback = apply_filters( 'blaze_wooless_menu_item_data_fallback_image', false );
									$item['image'] = $image ? $image : $image_fallback;
									$item['id'] = $menu_item_object->ID;
								} else if ( 'widget' === $item['type'] ) {
									$item['content'] = $this->get_widget_content( $item['id'] );
								}

								return $item;
							}, $column['items'] );
						}
						return $column;
					}, $grid['columns'] );
				}

				return $grid;
			}, $megamenu_settings['grid'] );
		}

		$menu_item_data[ $item->ID ] = array(
			'title' => $item->title,
			'url' => $item->url,
			'children' => $children,
		);

		return $menu_item_data;
	}

	public function modify_menu_item_data( $menu_item_data, $menu_item ) {

		$menu_item_data['megamenuSettings'] = array(
			'type' => $menu_item->megamenu_settings['type'],
			'icon' => $menu_item->megamenu_settings['icon'],
			'hideText' => $menu_item->megamenu_settings['hide_text'],
		);

		$custom_icon_media_id = $menu_item->megamenu_settings['custom_icon']['id'];
		if ( ! empty( $menu_item->megamenu_settings['custom_icon']['id'] ) ) {
			$menu_item_data['megamenuSettings']['customIcon']['id']  = $custom_icon_media_id;
			$image_src                                               = wp_get_attachment_image_src( $custom_icon_media_id, 'full' );
			$menu_item_data['megamenuSettings']['customIcon']['src'] = $image_src[0];
		}

		return $menu_item_data;
	}
}