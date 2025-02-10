<?php

namespace BlazeWooless\Features;

use BlazeWooless\TypesenseClient;

class TemplateBuilder {
	private static $instance = null;
	private $post_type = "wp_template";

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		add_action( 'blaze_wooless_after_site_info_sync', array( $this, 'sync_templates' ), 10 );
	}

	public function sync_templates() {
		global $post;

		$query = new \WP_Query( [ 
			'post_type' => $this->post_type,
			'posts_per_page' => -1,
		] );

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();


				TypesenseClient::get_instance()
					->site_info()
					->upsert( array(
						'id' => (string) get_the_ID(),
						'name' => 'site-' . $post->post_name,
						'value' => $post->post_content,
						'updated_at' => time(),
					) );
			}
		}

		$query = new \WP_Query( [ 
			'post_type' => 'wp_template_part',
			'posts_per_page' => -1,
		] );

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();


				TypesenseClient::get_instance()
					->site_info()
					->upsert( array(
						'id' => (string) get_the_ID(),
						'name' => 'site-template-' . $post->post_name,
						'value' => $post->post_content,
						'updated_at' => time(),
					) );
			}
		}
	}

}