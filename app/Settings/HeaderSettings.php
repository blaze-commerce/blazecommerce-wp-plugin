<?php

namespace BlazeWooless\Settings;

use BlazeWooless\TypesenseClient;

class HeaderSettings extends BaseSettings {
	private static $instance = null;
	public $tab_key = 'header';
	public $page_label = 'Header';
	public $setting_page_name = 'blaze-settings-header';

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self( 'wooless_header_settings_options' );
		}

		return self::$instance;
	}

	public function settings_callback( $options ) {
		return $options;
	}

	public function settings() {
		return array();
	}

	public function get_post() {
		$args      = array(
			'post_type' => 'blaze_settings',
			'name' => $this->setting_page_name,
		);
		$the_query = new \WP_Query( $args );

		if ( ! $the_query->have_posts() ) {
			return null;
		}

		wp_reset_postdata();
		wp_reset_query();

		return $the_query->posts[0];
	}

	public function maybe_save_settings() {
		$post = $this->get_post();

		if ( $post ) {
			return $post->ID;
		}

		$content        = '<!-- wp:generateblocks/container {"uniqueId":"33f81160","backgroundColor":"#090e1a","isDynamic":true,"blockVersion":4,"display":"flex","flexDirection":"row","alignItems":"center","justifyContent":"center"} -->
<!-- wp:generateblocks/container {"uniqueId":"8747b327","isDynamic":true,"blockVersion":4,"display":"flex","flexDirection":"row","alignItems":"center","justifyContent":"space-between","className":"container"} -->
<!-- wp:site-logo {"shouldSyncIcon":true} /-->

<!-- wp:generateblocks/container {"uniqueId":"e2af78b8","isDynamic":true,"blockVersion":4,"display":"flex","flexDirection":"row","alignItems":"center","justifyContent":"flex-end","columnGap":"8px"} -->
<!-- wp:paragraph {"style":{"color":{"text":"#ffffff"},"elements":{"link":{"color":{"text":"#ffffff"}}}}} -->
<p class="has-text-color has-link-color" style="color:#ffffff"><a href="/shop">Shop</a></p>
<!-- /wp:paragraph -->

<!-- wp:generateblocks/container {"uniqueId":"04c0d214","isDynamic":true,"blockVersion":4,"metadata":{"name":"Search"}} /-->

<!-- wp:generateblocks/container {"uniqueId":"aa90172c","isDynamic":true,"blockVersion":4,"blockLabel":"MiniCartIcon","htmlAttributes":[{"attribute":"data-color","value":"#F7F7F7"}]} -->
<!-- wp:generateblocks/image {"uniqueId":"56f0986b","mediaId":216617,"relNoFollow":true,"sizeSlug":"full","anchor":"minicart","blockVersion":2} -->
<figure class="gb-block-image gb-block-image-56f0986b"><a href="/?cart=1" rel="nofollow"><img class="gb-image gb-image-56f0986b" id="minicart" src="https://cart.ezywiper-bc-v1.blz.onl/wp-content/uploads/2024/07/icon-cart.png" alt="" title="icon-cart"/></a></figure>
<!-- /wp:generateblocks/image -->
<!-- /wp:generateblocks/container -->
<!-- /wp:generateblocks/container -->
<!-- /wp:generateblocks/container -->
<!-- /wp:generateblocks/container -->';
		$default_header = array(
			'post_title' => 'Header',
			'post_type' => 'blaze_settings',
			'post_name' => $this->setting_page_name,
			'post_category' => array( 0 ),
			'post_content' => $content,
			'post_status' => 'publish',
		);
		return wp_insert_post( $default_header );
	}

	public function footer_callback() {

		$post_id   = $this->maybe_save_settings();
		$edit_link = get_edit_post_link( $post_id, '&' );
		wp_redirect( $edit_link );
	}

	public function section_callback() {
		echo '<p>Select which areas of content you wish to display.</p>';
	}

	public function register_hooks() {
		add_filter( 'set_blaze_setting_data', array( $this, 'set_blaze_setting_data' ), 10, 2 );
		// remove_action( 'generate_header', 'generate_construct_header' );
		add_action( 'generate_header', array( $this, 'render_wp_header' ), 10 );
		add_filter( 'blazecommerce/settings', array( $this, 'add_header_settings_to_documents' ), 10, 1 );
	}

	public function set_blaze_setting_data( $blaze_settings, $post_id ) {
		$blaze_settings[ $this->setting_page_name ] = array(
			'id' => (string) $post_id,
			'name' => 'site-header',
			'value' => get_post_field( 'post_content', $post_id ),
			'updated_at' => time(),
		);
		return $blaze_settings;
	}

	public function render_wp_header() {

		global $post;

		$temp_post = $post;

		$post = $this->get_post();

		setup_postdata( $post );

		$post_content = $post->post_content;

		$content = apply_filters( 'the_content', $post_content );

		$post = $temp_post;

		wp_reset_postdata();

		echo $content;
	}

	public function add_header_settings_to_documents( $documents ) {
		$post = $this->get_post();
		if ( $post ) {
			$documents[] = array(
				'id' => (string) $post->ID,
				'name' => 'site-header',
				'value' => get_post_field( 'post_content', $post->ID ),
				'updated_at' => time(),
			);
		}

		return $documents;
	}
}

HeaderSettings::get_instance();
