<?php

namespace BlazeWooless\Extensions\Gutenberg\Blocks;

use BlazeWooless\TypesenseClient;

class Footer {
	private static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		add_action( 'save_post_gp_elements', array( $this, 'upsert_site_footer' ) );
		add_action( 'blaze_wooless_after_site_info_sync', array( $this, 'sync_footer_to_typesense' ) );
	}

	public function sync_footer_to_typesense() {

		$post_id = $this->get_site_footer_id();
		if ( ! empty( $post_id ) ) {

			$site_footer = $this->get_site_footer( $post_id );
			if ( ! empty( $site_footer ) ) {
				TypesenseClient::get_instance()->site_info()->upsert( $site_footer );
			}
		}
	}

	public function upsert_site_footer( $post_id ) {
		// Check if this is an autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check user permissions
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$site_footer_id = $this->get_site_footer_id();
		$block_type     = get_post_meta( $post_id, '_generate_block_type', true );

		if (
			'site-footer' == $block_type &&
			$post_id == $site_footer_id
		) {

			$site_footer = $this->get_site_footer( $site_footer_id );
			if ( ! empty( $site_footer ) ) {
				TypesenseClient::get_instance()->site_info()->upsert( $site_footer );
			}

			return;
		}
	}

	public function get_site_footer_id() {
		global $wpdb;

		$query = "SELECT post_id FROM $wpdb->postmeta
                    WHERE meta_key = '_generate_block_type' AND meta_value = 'site-footer'
                    ORDER BY post_id ASC
                    LIMIT 1";

		$post_id = $wpdb->get_var( $query );

		return $post_id;
	}


	public function get_site_footer( $post_id = null ) {

		if ( empty( $post_id ) ) {
			$post_id = $this->get_site_footer_id();
		}

		if ( $post_id ) {
			return array(
				'id' => (string) $post_id,
				'name' => 'site-footer',
				'value' => get_post_field( 'post_content', $post_id ),
				'updated_at' => time(),
			);
		}

		return null;
	}
}