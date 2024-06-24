<?php

namespace BlazeWooless\Extensions;

class WoocommercePhotoReviews {
	private static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		if ( is_plugin_active( 'woocommerce-photo-reviews/woocommerce-photo-reviews.php' ) && is_plugin_active( 'woo-photo-reviews/woo-photo-reviews.php' ) ) {
			add_filter( 'blaze_wooless_product_data_for_typesense', array( $this, 'reviews_summary' ), 10, 2 );
			add_filter( 'blaze_wooless_product_data_for_typesense', array( $this, 'product_reviews' ), 10, 2 );
		}
	}

	public function reviews_summary( $product_data, $product_id ) {
		if ( ! empty( $product_data ) && $product_id ) {
			$product = wc_get_product( $product_id );
			$review_count = $product->get_review_count();
			$average_rating = $product->get_average_rating();
			for($i=5; $i>0; $i--) {
				$rating_count['rating_' . $i] = $product->get_rating_count($i);
			}

			unset($product);

			$product_data['metaData']['wooProductReviews']['stats'] = array(
				'average_rating' => floatval($average_rating),
				'count_reviews' => intval($review_count),
				'stars_count' => array(
					'rating_5' => intval($rating_count['rating_5']),
					'rating_4' => intval($rating_count['rating_4']),
					'rating_3' => intval($rating_count['rating_3']),
					'rating_2' => intval($rating_count['rating_2']),
					'rating_1' => intval($rating_count['rating_1']),
				),
			);

			unset($average_rating, $review_count, $rating_count);
		}

		return $product_data;
	}

	public function product_reviews( $product_data, $product_id ) {
		if ( ! empty( $product_data ) && $product_id ) {
			$args = array (
					'post_type' => 'product', 
					'post_id' => $product_id,
					'status' => 'approve',
					'post_status' => 'publish',
			);
			$comments = get_comments($args);
			$comments_array = array();

			foreach ($comments as $comment) {
				if( ! empty( $comment )) {
					$rating = get_comment_meta($comment->comment_ID, 'rating', true);
					$vote_up_count = get_comment_meta($comment->comment_ID, 'wcpr_vote_up_count', true);
					$vote_down_count = get_comment_meta($comment->comment_ID, 'wcpr_vote_down_count', true);
					$comments_array[] = array(
						'comment_ID' => intval($comment->comment_ID),
						'author' => strval($comment->comment_author),
						'content' => strval($comment->comment_content),
						'date' => strval($comment->comment_date),
						'rating' => intval($rating),
						'vote_up_count' => intval($vote_up_count),
						'vote_down_count' => intval($vote_down_count),
					);

					unset($comment, $rating, $vote_up_count, $vote_down_count);
				}
			}

			unset($comments);

			$product_data['metaData']['wooProductReviews']['reviews'] = $comments_array;

			unset($comments_array, $average_rating, $review_count, $rating_count);
		}

		return $product_data;
	}
}