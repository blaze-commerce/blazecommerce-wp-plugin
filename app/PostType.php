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



	public static function initiliaze_default_home_page() {
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
					$page_collection->upsert( $document );
				} catch (\Exception $e) {

				}
			}

		}
		return;
	}
}
