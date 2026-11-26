<?php

namespace BlazeWooless;

use BlazeWooless\Collections\Page;

class PostType {
	private static $instance = null;

	/**
	 * Posts upserted in this request, post ID => hash of the document that was sent.
	 * A second save of the same, unchanged post in one request does not write again.
	 *
	 * @var array<int, string>
	 */
	private static $upserted_posts = array();

	/**
	 * Posts removed from the index in this request, post ID => true.
	 *
	 * @var array<int, bool>
	 */
	private static $deleted_posts = array();

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		add_action( 'save_post_page', array( $this, 'upsert_page_data' ), 10, 3 );

		// Blog posts live in the same Typesense collection as pages (Page::get_syncable_post_types()).
		// wp_after_insert_post (WP 5.6+) fires after the block editor has saved the categories, the
		// featured image and the meta, so the document holds the values the editor just saved.
		add_action( 'wp_after_insert_post', array( $this, 'upsert_post_data' ), 10, 4 );
		// Unpublish, private, scheduled-again and trash all leave the 'publish' status.
		add_action( 'transition_post_status', array( $this, 'remove_unpublished_post' ), 10, 3 );
		// Permanent delete, including `wp post delete --force`, which skips the trash.
		add_action( 'before_delete_post', array( $this, 'delete_post_data' ), 10, 1 );
	}



	public static function initialize_default_home_page() {
		global $wpdb;

		$post_name = 'blazecommerce-homepage';

		// Query to check if default home page exist
		$query = $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_name = %s LIMIT 1",
			$post_name
		);

		$post_id = $wpdb->get_var( $query );

		if ( ! $post_id ) {
			$content = '<!-- wp:generateblocks/container {"uniqueId":"0a636893","backgroundColor":"#090E19","isDynamic":true,"blockVersion":4,"display":"flex","flexDirection":"column","alignItems":"center","justifyContent":"center","rowGap":"1em","spacing":{"paddingTop":"5em","paddingLeft":"5em","paddingRight":"5em","paddingBottom":"5em"}} -->
<!-- wp:image {"width":"303px","height":"118px","scale":"cover","sizeSlug":"large","linkDestination":"none","align":"center"} -->
<figure class="wp-block-image aligncenter size-large is-resized"><img src="https://blazecommerce.io/wp-content/uploads/2023/10/Frame-2566.png" alt="" style="object-fit:cover;width:303px;height:118px"/></figure>
<!-- /wp:image -->

<!-- wp:heading {"textAlign":"center","level":1,"style":{"color":{"text":"#cecfd1"},"elements":{"link":{"color":{"text":"#cecfd1"}}},"typography":{"fontSize":"42px"}}} -->
<h1 class="wp-block-heading has-text-align-center has-text-color has-link-color" style="color:#cecfd1;font-size:42px">Building online store with exceptional user experience and blazing speed</h1>
<!-- /wp:heading -->

<!-- wp:generateblocks/button {"uniqueId":"7295a4df","hasUrl":true,"blockVersion":4,"display":"inline-flex","spacing":{"paddingTop":"15px","paddingRight":"20px","paddingBottom":"15px","paddingLeft":"20px"},"borders":{"borderTopWidth":"","borderTopStyle":"","borderRightWidth":"","borderRightStyle":"","borderTopLeftRadius":"2px","borderTopRightRadius":"2px","borderBottomLeftRadius":"2px","borderBottomRightRadius":"2px"},"backgroundColor":"#0366d6","backgroundColorHover":"#0366d6","textColor":"#ffffff","textColorHover":"#ffffff","className":"text-white"} -->
<a class="gb-button gb-button-7295a4df gb-button-text text-white" href="https://blazecommerce.io/early-access/">Learn More</a>
<!-- /wp:generateblocks/button -->
<!-- /wp:generateblocks/container -->';

			$default_homepage = array(
				'post_title' => 'BlazeCommerce Home',
				'post_type' => 'page',
				'post_status' => 'publish',
				'post_name' => $post_name,
				'post_category' => array( 0 ),
				'post_content' => $content,
			);

			$post_id = wp_insert_post( $default_homepage );
			if ( $post_id ) {
				update_option( 'show_on_front', 'page' );
				update_option( 'page_on_front', $post_id );
			}
		}
	}

	public function upsert_page_data( $post_id, $post, $update ) {

		$enable_system = boolval( bw_get_general_settings( 'enable_system' ) );

		if ( ! $enable_system ) {
			return;
		}

		// bail out if this is an autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check user permissions
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// If no api key and host has been entered then user can't connect to typesense then we don't attempt to save the data to typesense
		if ( ! TypesenseClient::get_instance()->can_connect() ) {
			return;
		}

		$page_collection = Page::get_instance();
		if ( ! empty( $page_collection ) ) {
			$document = $page_collection->get_data( $post );
			if ( ! empty( $document ) ) {
				// Index the page/post data in Typesense
				try {
					do_action( 'ts_before_page_upsert', $post );
					$page_collection->upsert( $document );
					do_action( 'ts_page_upsert', $post );
				} catch (\Exception $e) {

				}
			}

		}
		return;
	}

	/**
	 * Index a blog post into Typesense once WordPress has finished saving it.
	 *
	 * Hooked to wp_after_insert_post. Only published posts are indexed, matching
	 * `wp bc-sync page_and_post --all` (post_status = publish). Revisions and autosaves have their
	 * own post type, so the 'post' type check skips them. Pages keep their save_post_page path.
	 *
	 * @param int           $post_id     Post ID.
	 * @param \WP_Post      $post        Post object as saved.
	 * @param bool          $update      Whether this is an existing post being updated.
	 * @param \WP_Post|null $post_before Post object before the update, null for new posts.
	 * @return void
	 */
	public function upsert_post_data( $post_id, $post, $update, $post_before = null ) {
		if ( ! $post instanceof \WP_Post || 'post' !== $post->post_type || 'publish' !== $post->post_status ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! $this->can_sync_posts() || ! $this->can_index_post( $post->ID ) ) {
			return;
		}

		$page_collection = Page::get_instance();
		$document        = $page_collection->get_data( $post );
		if ( empty( $document ) ) {
			return;
		}

		$hash = md5( (string) wp_json_encode( $document ) );
		if ( isset( self::$upserted_posts[ $post->ID ] ) && self::$upserted_posts[ $post->ID ] === $hash ) {
			return;
		}

		try {
			do_action( 'ts_before_page_upsert', $post );
			$page_collection->upsert( $document );
			self::$upserted_posts[ $post->ID ] = $hash;
			unset( self::$deleted_posts[ $post->ID ] );
			do_action( 'ts_page_upsert', $post );
		} catch ( \Throwable $e ) {
			$this->log( 'TS Post Upsert Exception (post ' . $post->ID . '): ' . $e->getMessage() );
		}
	}

	/**
	 * Remove a blog post from the index when it leaves the 'publish' status.
	 *
	 * Covers publish -> draft, pending, private, future and trash. No capability check: core has
	 * already authorised the status change, and removing non-public content must never be skipped.
	 *
	 * @param string   $new_status New post status.
	 * @param string   $old_status Previous post status.
	 * @param \WP_Post $post       Post object.
	 * @return void
	 */
	public function remove_unpublished_post( $new_status, $old_status, $post ) {
		if ( ! $post instanceof \WP_Post || 'post' !== $post->post_type ) {
			return;
		}

		if ( 'publish' !== $old_status || 'publish' === $new_status ) {
			return;
		}

		if ( ! $this->can_sync_posts() ) {
			return;
		}

		$this->delete_post_document( $post->ID );
	}

	/**
	 * Remove a blog post from the index before it is permanently deleted.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function delete_post_data( $post_id ) {
		if ( 'post' !== get_post_type( $post_id ) ) {
			return;
		}

		if ( ! $this->can_sync_posts() ) {
			return;
		}

		$this->delete_post_document( (int) $post_id );
	}

	/**
	 * Delete one post document from the active page collection, once per request.
	 *
	 * A missing document (already removed, or never indexed) throws and is logged at debug level.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function delete_post_document( $post_id ) {
		if ( isset( self::$deleted_posts[ $post_id ] ) ) {
			return;
		}

		self::$deleted_posts[ $post_id ] = true;
		unset( self::$upserted_posts[ $post_id ] );

		try {
			do_action( 'ts_before_page_delete', $post_id );
			Page::get_instance()->delete( $post_id );
			do_action( 'ts_page_delete', $post_id );
		} catch ( \Throwable $e ) {
			$this->log( 'TS Post Delete Exception (post ' . $post_id . '): ' . $e->getMessage() );
		}
	}

	/**
	 * Whether post sync is on: system enabled, 'post' still syncable, Typesense configured.
	 *
	 * @return bool
	 */
	private function can_sync_posts() {
		if ( ! boolval( bw_get_general_settings( 'enable_system' ) ) ) {
			return false;
		}

		if ( ! in_array( 'post', Page::get_instance()->get_syncable_post_types(), true ) ) {
			return false;
		}

		return TypesenseClient::get_instance()->can_connect();
	}

	/**
	 * Whether the current request may index this post.
	 *
	 * Scheduled posts are published by WP-Cron (publish_future_post) with no logged-in user, and
	 * `wp post create/update` runs as user 0 unless --user is passed. Both are trusted contexts, so
	 * they skip the edit_post check that editor saves go through.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	private function can_index_post( $post_id ) {
		if ( wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return true;
		}

		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Debug log through the WooCommerce logger when it is available.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	private function log( $message ) {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->debug( $message, array( 'source' => 'wooless-post-sync' ) );
		}
	}
}
