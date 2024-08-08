<?php
namespace BlazeWooless\Extensions;

use BlazeWooless\TypesenseClient;

class BusinessReviewsBundle {
	private static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		if ( $this->is_active() ) {
			add_filter( 'blaze_wooless_review_setting_options', array( $this, 'register_review_settings' ) );
			add_filter( 'blaze_wooless_additional_site_info', array( $this, 'add_review_config_to_site_info' ), 10, 2 );

			add_action( 'rest_api_init', array( $this, 'register_api_endpoint' ) );
			add_action( 'blaze_wooless_save_product_page_settings', array( $this, 'save_settings' ) );
		}
	}

	protected function is_active() {
		return is_plugin_active( 'business-reviews-bundle/brb.php' );
	}

	public function register_api_endpoint() {
		register_rest_route( 'blaze-wooless/v1', '/brb', array(
			'methods' => 'GET',
			'callback' => array( $this, 'get_reviews' ),
		) );
	}

	public function register_review_settings( array $fields ) {

		$options = [ 
			'select' => 'Select Collection',
		];

		$query = new \WP_Query( [ 
			'post_type' => 'brb_collection',
			'posts_per_page' => -1,
			'post_status' => 'publish',
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'no_found_rows' => true,
		] );

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$options[get_the_ID()] = get_the_title();
			}
		}
		;

		$fields[] = array(
			'id' => 'brb_review_collection',
			'label' => 'Business Reviews Bundle - Select Collection',
			'type' => 'select',
			'args' => array(
				'options' => $options
			),
		);

		return $fields;
	}

	public function add_review_config_to_site_info( array $additional_settings ) {
		$additional_settings['business_reviews_bundle_settings'] = array();

		$product_options = get_option( 'wooless_settings_product_page_options' );

		if ( isset( $product_options['brb_review_collection'] ) ) {
			$additional_settings['business_reviews_bundle_settings']['collection'] = $product_options['brb_review_collection'];
		}

		return $additional_settings;
	}

	protected function render_review_data( $collection_id ) {

		$helper = new \WP_Business_Reviews_Bundle\Includes\Helper();
		$core = new \WP_Business_Reviews_Bundle\Includes\Core\Core( $helper );

		$view_helper = new \WP_Business_Reviews_Bundle\Includes\View\View_Helper();
		$view_reviews = new \WP_Business_Reviews_Bundle\Includes\View\View_Reviews( $view_helper );

		$view1 = new \WP_Business_Reviews_Bundle\Includes\View\View1( $view_reviews, $view_helper );
		$view2 = new \WP_Business_Reviews_Bundle\Includes\View\View2( $view_reviews, $view_helper );
		$view = new \WP_Business_Reviews_Bundle\Includes\View\View( $view_reviews, $view_helper, $view1, $view2 );
		$collection_deserializer = new \WP_Business_Reviews_Bundle\Includes\Collection_Deserializer( new \WP_Query() );
		$builder = new \WP_Business_Reviews_Bundle\Includes\Builder_Page( $collection_deserializer, $core, $view );

		$collection = $collection_deserializer->get_collection( $collection_id );

		$is_clone = false;
		$collection_id = '';
		$collection_post_title = '';
		$collection_content = '';
		$collection_inited = false;
		$businesses = null;
		$reviews = null;

		if ( $collection != null ) {
			if ( ! $is_clone ) {
				$collection_id = $collection->ID;
				$collection_post_title = $collection->post_title;
			}
			$collection_content = trim( $collection->post_content );

			$review_data = $core->get_reviews( $collection );
			if ( $review_data !== false ) {
				$businesses = $review_data['businesses'];
				$reviews = $review_data['reviews'];
				$options = $review_data['options'];
				$errors = $review_data['errors'];
				if ( isset( $businesses ) && count( $businesses ) || isset( $reviews ) && count( $reviews ) ) {
					$collection_inited = true;
				}
			}
		}

		$auth_code = get_option( 'brb_auth_code' );
		$auth_code_test = get_option( 'brb_auth_code_test' );
		$auth_code = isset( $auth_code_test ) && strlen( $auth_code_test ) > 0 ? $auth_code_test : $auth_code;



		$render = htmlspecialchars_decode( $view->render( $collection_id, $businesses, $reviews, $options ) );
		$dom = new \DOMDocument;

		libxml_use_internal_errors( true );

		$dom->loadHTML( $render );
		libxml_clear_errors();

		$xpath = new \DOMXPath( $dom );

		$statusNodes = $xpath->query( "//div[contains(@class, 'rpi-scale')]" );
		$status = ( $statusNodes && $statusNodes->length > 0 ) ? $statusNodes->item( 0 )->textContent : '';

		// Query for the href value
		$hrefNodes = $xpath->query( "//div[contains(@class, 'rpi-review_us rpi-clickable')]/a" );
		$href = ( $hrefNodes && $hrefNodes->length > 0 && $hrefNodes->item( 0 ) instanceof \DOMElement ) ? $hrefNodes->item( 0 )->getAttribute( 'href' ) : '';

		$popupNode = $xpath->query( "//div[contains(@class, 'rpi-clickable') and contains(@onclick, '_rplg_popup')]" )->item( 0 );
		$popupLink = '';
		if ( $popupNode ) {
			preg_match( "/_rplg_popup\('([^']+)'/", $popupNode->getAttribute( 'onclick' ), $matches );
			$popupLink = $matches[1] ?? '';
		}

		$gradeNodes = $xpath->query( "//div[contains(@class, 'rpi-header')]//div[contains(@class, 'rpi-grade')]" );
		$grade = ( $gradeNodes && $gradeNodes->length > 0 ) ? $gradeNodes->item( 0 )->textContent : 0;

		// Get review data from each rpi-card within rpi-content
		$cards = $xpath->query( "//div[contains(@class, 'rpi-content')]//div[contains(@class, 'rpi-card')]" );
		$reviews = [];

		foreach ( $cards as $card ) {
			$imgNode = $xpath->query( ".//div[contains(@class, 'rpi-img')]/img", $card )->item( 0 );
			$nameNode = $xpath->query( ".//div[contains(@class, 'rpi-name')]/a", $card )->item( 0 );
			$starsNode = $xpath->query( ".//div[contains(@class, 'rpi-stars')]", $card )->item( 0 );
			$textNode = $xpath->query( ".//div[contains(@class, 'rpi-text')]", $card )->item( 0 );

			$imgSrc = $imgNode ? $imgNode->getAttribute( 'src' ) : '';
			$reviewerName = $nameNode ? $nameNode->textContent : '';
			$rating = $starsNode ? explode( ',', $starsNode->getAttribute( 'data-info' ) )[0] : '';
			$reviewText = $textNode ? $textNode->textContent : '';

			// Check if the review is already in the array to avoid duplicates
			$isDuplicate = false;
			foreach ( $reviews as $review ) {
				if ( $review['name'] === $reviewerName && $review['review'] === $reviewText ) {
					$isDuplicate = true;
					break;
				}
			}

			if ( ! $isDuplicate ) {
				$reviews[] = [ 
					'image' => $imgSrc,
					'name' => $reviewerName,
					'rating' => $rating,
					'review' => $reviewText
				];
			}
		}

		return [ 
			'status' => $status,
			'grade' => $grade,
			'href' => $href,
			'popupLink' => $popupLink,
			'reviews' => $reviews
		];
	}

	public function get_reviews( \WP_REST_Request $request ) {
		try {

			if ( ! class_exists( 'WP_Business_Reviews_Bundle\Includes\Collection_Deserializer' ) ||
				! class_exists( 'WP_Business_Reviews_Bundle\Includes\Core\Core' ) ||
				! class_exists( 'WP_Business_Reviews_Bundle\Includes\Helper' ) ||
				! class_exists( 'WP_Business_Reviews_Bundle\Includes\View\View1' ) ||
				! class_exists( 'WP_Business_Reviews_Bundle\Includes\View\View2' ) ||
				! class_exists( 'WP_Business_Reviews_Bundle\Includes\View\View' ) ||
				! class_exists( 'WP_Business_Reviews_Bundle\Includes\View\View_Helper' ) ||
				! class_exists( 'WP_Business_Reviews_Bundle\Includes\View\View_Reviews' ) ||
				! class_exists( 'WP_Business_Reviews_Bundle\Includes\Builder_Page' ) )
				throw new \Exception( 'Business Reviews Bundle is not installed' );

			$data = [];
			$product_options = get_option( 'wooless_settings_product_page_options' );

			if ( ! isset( $product_options['brb_review_collection'] ) )
				throw new \Exception( 'Collection not set' );

			$collection_id = absint( $product_options['brb_review_collection'] );

			$data = $this->render_review_data( $collection_id );

			$response = new \WP_REST_Response( $data );

			// Add a custom status code
			$response->set_status( 201 );
		} catch (\Exception $e) {
			$response = new \WP_REST_Response( array(
				'error' => $e->getMessage()
			) );
			$response->set_status( 400 );
		}

		return $response;
	}

	public function save_settings() {
		// $product_options = get_option( 'wooless_settings_product_page_options' );

		// if ( ! isset( $product_options['brb_review_collection'] ) )
		// 	return;

		// TypesenseClient::get_instance()->site_info()->upsert( [ 
		// 	'id' => '1001023',
		// 	'name' => 'business_reviews_bundle_settings',
		// 	'value' => json_encode( [ 'collection' => $product_options['brb_review_collection'] ] ),
		// 	'updated_at' => time(),
		// ] );
	}
}