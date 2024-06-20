<?php

namespace BlazeWooless\Extensions\Gutenberg\Blocks;

use BlazeWooless\TypesenseClient;

class Product {
	private static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		add_action( 'save_post_gp_elements', array( $this, 'upsert_site_product' ) );
		add_action( 'blaze_wooless_after_site_info_sync', array( $this, 'sync_product_to_typesense' ) );
	}

	public function sync_product_to_typesense() {

		$post_id = $this->get_site_product_id();
		if ( ! empty( $post_id ) ) {

			$site_product = $this->get_site_product( $post_id );

			if ( ! empty( $site_product ) ) {
				TypesenseClient::get_instance()->site_info()->upsert( $site_product );
			}
		} else {
			do_action(
				"inspect", array(
					"typesense_site_product",
					array(
						'site_product' => "No site product found",
					)
				) );
		}
	}

	public function upsert_site_product( $post_id ) {
		// Check if this is an autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check user permissions
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$site_product_id = $this->get_site_product_id();

		if (
			$post_id === $site_product_id
		) {

			$site_product = $this->get_site_product( $site_product_id );
			if ( ! empty( $site_product ) ) {
				TypesenseClient::get_instance()->site_info()->upsert( $site_product );
			}

			return;
		}
	}

	public function get_site_product_id() {

		global $wpdb;

		$query = "SELECT ID FROM $wpdb->posts
                    WHERE post_type = 'gp_elements' AND post_status = 'publish' AND post_title = 'Product'
                    ORDER BY ID ASC
                    LIMIT 1";

		$post_id = $wpdb->get_var( $query );

		return $post_id;
	}


	public function get_site_product( $post_id = null ) {

		if ( empty( $post_id ) ) {
			$post_id = $this->get_site_product_id();
		}

		if ( $post_id ) {
			return array(
				'id' => (string) $post_id,
				'name' => 'site-product',
				'value' => get_post_field( 'post_content', $post_id ),
				'updated_at' => time(),
			);
		}

		return null;
	}
}