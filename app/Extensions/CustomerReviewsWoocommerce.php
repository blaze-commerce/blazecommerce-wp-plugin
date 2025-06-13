<?php

namespace BlazeWooless\Extensions;

class CustomerReviewsWoocommerce {
	private static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		// Debug: Log extension initialization
		error_log('CustomerReviewsWoocommerce extension initialized');

		if ( $this->is_active() ) {
			error_log('CustomerReviewsWoocommerce extension is active');
			add_filter( 'blaze_wooless_review_setting_options', array( $this, 'register_review_settings' ) );
			add_filter( 'blaze_wooless_additional_site_info', array( $this, 'add_review_config_to_site_info' ), 10, 2 );
			add_action( 'rest_api_init', array( $this, 'register_api_endpoints' ) );
		} else {
			error_log('CustomerReviewsWoocommerce extension is NOT active - plugin not found');
		}
	}

	protected function is_active() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}

		$review_plugins = array(
			'customer-reviews-woocommerce/customer-reviews-woocommerce.php',
			'woocommerce-photo-reviews/woocommerce-photo-reviews.php',
			'woo-photo-reviews/woo-photo-reviews.php',
			'judgeme-product-reviews-woocommerce/judgeme-product-reviews-woocommerce.php',
			'yotpo-social-reviews-for-woocommerce/yotpo-social-reviews-for-woocommerce.php'
		);

		foreach ( $review_plugins as $plugin ) {
			if ( is_plugin_active( $plugin ) ) {
				error_log("Found active review plugin: $plugin");
				return true;
			}
		}

		// Also check if WooCommerce reviews are enabled
		if ( class_exists( 'WooCommerce' ) && get_option( 'woocommerce_enable_reviews', 'yes' ) === 'yes' ) {
			error_log('WooCommerce native reviews are enabled');
			return true;
		}

		error_log('No review plugins found');
		return false;
	}

	public function register_api_endpoints() {
		error_log('Registering customer reviews API endpoints');

		register_rest_route( 'blaze-wooless/v1', '/customer-reviews', array(
			'methods' => 'GET',
			'callback' => array( $this, 'get_customer_reviews' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( 'blaze-wooless/v1', '/customer-reviews/stats', array(
			'methods' => 'GET',
			'callback' => array( $this, 'get_review_stats' ),
			'permission_callback' => '__return_true',
		) );

		// Debug endpoint to check database
		register_rest_route( 'blaze-wooless/v1', '/customer-reviews/debug', array(
			'methods' => 'GET',
			'callback' => array( $this, 'debug_reviews' ),
			'permission_callback' => '__return_true',
		) );
	}

	public function register_review_settings( array $fields ) {
		$fields[] = array(
			'id' => 'customer_reviews_showcase_enabled',
			'label' => 'Enable Customer Reviews Showcase',
			'type' => 'checkbox',
			'description' => 'Enable the customer reviews showcase section',
		);

		$fields[] = array(
			'id' => 'customer_reviews_title',
			'label' => 'Reviews Section Title',
			'type' => 'text',
			'default' => '3,000+ Five Star Reviews',
			'description' => 'Title displayed in the reviews showcase section',
		);

		$fields[] = array(
			'id' => 'customer_reviews_limit',
			'label' => 'Number of Reviews to Display',
			'type' => 'number',
			'default' => 3,
			'description' => 'Maximum number of reviews to show in the showcase',
		);

		return $fields;
	}

	public function add_review_config_to_site_info( array $additional_settings ) {
		$additional_settings['customer_reviews_settings'] = array();

		$product_options = get_option( 'wooless_settings_product_page_options' );

		if ( isset( $product_options['customer_reviews_showcase_enabled'] ) ) {
			$additional_settings['customer_reviews_settings']['enabled'] = $product_options['customer_reviews_showcase_enabled'];
		}

		if ( isset( $product_options['customer_reviews_title'] ) ) {
			$additional_settings['customer_reviews_settings']['title'] = $product_options['customer_reviews_title'];
		}

		if ( isset( $product_options['customer_reviews_limit'] ) ) {
			$additional_settings['customer_reviews_settings']['limit'] = intval( $product_options['customer_reviews_limit'] );
		}

		return $additional_settings;
	}

	public function get_customer_reviews( \WP_REST_Request $request ) {
		try {
			$limit = $request->get_param( 'limit' ) ?: 3;
			$offset = $request->get_param( 'offset' ) ?: 0;

			$reviews = $this->fetch_featured_reviews( $limit, $offset );
			$stats = $this->get_overall_review_stats();

			$response = new \WP_REST_Response( array(
				'reviews' => $reviews,
				'stats' => $stats,
				'total' => count( $reviews ),
			) );

			$response->set_status( 200 );
		} catch ( \Exception $e ) {
			$response = new \WP_REST_Response( array(
				'error' => $e->getMessage()
			) );
			$response->set_status( 400 );
		}

		return $response;
	}

	public function get_review_stats( \WP_REST_Request $request ) {
		try {
			$stats = $this->get_overall_review_stats();

			$response = new \WP_REST_Response( $stats );
			$response->set_status( 200 );
		} catch ( \Exception $e ) {
			$response = new \WP_REST_Response( array(
				'error' => $e->getMessage()
			) );
			$response->set_status( 400 );
		}

		return $response;
	}

	protected function fetch_featured_reviews( $limit = 3, $offset = 0 ) {
		global $wpdb;

		// Get high-rated reviews with images or verified purchases
		$sql = "SELECT c.comment_ID, c.comment_author, c.comment_content, c.comment_date, 
				       cm_rating.meta_value as rating,
				       cm_verified.meta_value as verified,
				       cm_images.meta_value as review_images,
				       p.post_title as product_title
				FROM {$wpdb->comments} c
				LEFT JOIN {$wpdb->commentmeta} cm_rating ON c.comment_ID = cm_rating.comment_id AND cm_rating.meta_key = 'rating'
				LEFT JOIN {$wpdb->commentmeta} cm_verified ON c.comment_ID = cm_verified.comment_id AND cm_verified.meta_key = 'verified'
				LEFT JOIN {$wpdb->commentmeta} cm_images ON c.comment_ID = cm_images.comment_id AND cm_images.meta_key = 'reviews-images'
				LEFT JOIN {$wpdb->posts} p ON c.comment_post_ID = p.ID
				WHERE c.comment_approved = '1' 
				AND c.comment_type = 'review'
				AND cm_rating.meta_value >= 4
				ORDER BY cm_rating.meta_value DESC, c.comment_date DESC
				LIMIT %d OFFSET %d";

		$results = $wpdb->get_results( $wpdb->prepare( $sql, $limit, $offset ) );

		$reviews = array();
		foreach ( $results as $result ) {
			$images = array();
			if ( ! empty( $result->review_images ) ) {
				$image_ids = maybe_unserialize( $result->review_images );
				if ( is_array( $image_ids ) ) {
					$images = array_map( function( $id ) {
						return wp_get_attachment_url( $id );
					}, $image_ids );
				}
			}

			$reviews[] = array(
				'id' => intval( $result->comment_ID ),
				'author' => sanitize_text_field( $result->comment_author ),
				'content' => wp_trim_words( $result->comment_content, 30 ),
				'rating' => intval( $result->rating ),
				'verified' => boolval( $result->verified ),
				'date' => $result->comment_date,
				'product_title' => sanitize_text_field( $result->product_title ),
				'images' => $images,
				'avatar' => get_avatar_url( '', array( 'size' => 56 ) ),
			);
		}

		return $reviews;
	}

	protected function get_overall_review_stats() {
		global $wpdb;

		// Get overall review statistics
		$stats_sql = "SELECT 
						COUNT(*) as total_reviews,
						AVG(CAST(cm.meta_value AS DECIMAL(3,2))) as average_rating,
						SUM(CASE WHEN cm.meta_value = '5' THEN 1 ELSE 0 END) as five_star_count,
						SUM(CASE WHEN cm.meta_value = '4' THEN 1 ELSE 0 END) as four_star_count,
						SUM(CASE WHEN cm.meta_value = '3' THEN 1 ELSE 0 END) as three_star_count,
						SUM(CASE WHEN cm.meta_value = '2' THEN 1 ELSE 0 END) as two_star_count,
						SUM(CASE WHEN cm.meta_value = '1' THEN 1 ELSE 0 END) as one_star_count
					  FROM {$wpdb->comments} c
					  LEFT JOIN {$wpdb->commentmeta} cm ON c.comment_ID = cm.comment_id AND cm.meta_key = 'rating'
					  WHERE c.comment_approved = '1' 
					  AND c.comment_type = 'review'
					  AND cm.meta_value IS NOT NULL";

		$stats = $wpdb->get_row( $stats_sql );

		return array(
			'total_reviews' => intval( $stats->total_reviews ),
			'average_rating' => floatval( $stats->average_rating ),
			'five_star_count' => intval( $stats->five_star_count ),
			'four_star_count' => intval( $stats->four_star_count ),
			'three_star_count' => intval( $stats->three_star_count ),
			'two_star_count' => intval( $stats->two_star_count ),
			'one_star_count' => intval( $stats->one_star_count ),
		);
	}

	public function debug_reviews( \WP_REST_Request $request ) {
		global $wpdb;

		$debug_info = array();

		// Check if WooCommerce is active
		$debug_info['woocommerce_active'] = class_exists( 'WooCommerce' );
		$debug_info['reviews_enabled'] = get_option( 'woocommerce_enable_reviews', 'yes' );

		// Check for review plugins
		$review_plugins = array(
			'customer-reviews-woocommerce/customer-reviews-woocommerce.php',
			'woocommerce-photo-reviews/woocommerce-photo-reviews.php',
			'woo-photo-reviews/woo-photo-reviews.php',
			'judgeme-product-reviews-woocommerce/judgeme-product-reviews-woocommerce.php',
			'yotpo-social-reviews-for-woocommerce/yotpo-social-reviews-for-woocommerce.php'
		);

		$debug_info['active_review_plugins'] = array();
		foreach ( $review_plugins as $plugin ) {
			if ( is_plugin_active( $plugin ) ) {
				$debug_info['active_review_plugins'][] = $plugin;
			}
		}

		// Check database tables
		$debug_info['comments_table_exists'] = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->comments}'") === $wpdb->comments;
		$debug_info['commentmeta_table_exists'] = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->commentmeta}'") === $wpdb->commentmeta;

		// Count total comments
		$debug_info['total_comments'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments}");
		$debug_info['approved_comments'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = '1'");

		// Count reviews (comments with rating)
		$debug_info['total_reviews'] = $wpdb->get_var("
			SELECT COUNT(*)
			FROM {$wpdb->comments} c
			LEFT JOIN {$wpdb->commentmeta} cm ON c.comment_ID = cm.comment_id AND cm.meta_key = 'rating'
			WHERE c.comment_approved = '1' AND cm.meta_value IS NOT NULL
		");

		// Sample reviews
		$sample_reviews = $wpdb->get_results("
			SELECT c.comment_ID, c.comment_author, c.comment_content, c.comment_date, cm.meta_value as rating
			FROM {$wpdb->comments} c
			LEFT JOIN {$wpdb->commentmeta} cm ON c.comment_ID = cm.comment_id AND cm.meta_key = 'rating'
			WHERE c.comment_approved = '1' AND cm.meta_value IS NOT NULL
			ORDER BY c.comment_date DESC
			LIMIT 5
		");

		$debug_info['sample_reviews'] = $sample_reviews;

		// Check for photo review meta
		$photo_reviews_count = $wpdb->get_var("
			SELECT COUNT(*)
			FROM {$wpdb->commentmeta}
			WHERE meta_key = 'reviews-images'
		");
		$debug_info['photo_reviews_count'] = $photo_reviews_count;

		return new \WP_REST_Response( $debug_info );
	}
}
