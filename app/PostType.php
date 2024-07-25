<?php

namespace BlazeWooless;

use BlazeWooless\Collections\Page;

class PostType {
	private static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		add_action( 'save_post_page', array( $this, 'upsert_page_data' ), 10, 3 );
	}

	public function upsert_page_data( $post_id, $post, $update ) {
		// bail out if this is an autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check user permissions
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$page_collection = Page::get_instance();

		$document = $page_collection->get_data( $post );

		// Index the page/post data in Typesense
		try {
			$page_collection->upsert( $document );
		} catch (\Exception $e) {

		}

		return;
	}
}
