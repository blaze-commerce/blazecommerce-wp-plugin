<?php

namespace BlazeWooless\Extensions;

class PageMetaFields {
	private static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		// Register meta fields for pages
		add_action( 'init', array( $this, 'register_meta_fields' ) );

		// Add meta box for both classic and Gutenberg editor
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );

		// Hook into post save
		add_action( 'save_post', array( $this, 'save_page_meta' ), 10, 2 );

		// Enqueue admin scripts for Select2
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		// Hook into Typesense data
		add_filter( 'blazecommerce/collection/page/typesense_fields', array( $this, 'set_page_fields' ), 10, 1 );
		add_filter( 'blazecommerce/collection/page/typesense_data', array( $this, 'set_page_data' ), 10, 2 );
	}

	/**
	 * Register meta fields for pages
	 */
	public function register_meta_fields() {
		// Debug: Log that we're registering meta fields
		error_log( 'Blaze PageMetaFields: Registering meta fields' );

		// Page region - maps to Aelia currency regions
		register_post_meta( 'page', 'blaze_page_region', array(
			'show_in_rest' => true,
			'single' => true,
			'type' => 'string',
			'default' => '',
			'sanitize_callback' => array( $this, 'sanitize_page_region' ),
			'auth_callback' => function() {
				return current_user_can( 'edit_posts' );
			}
		) );

		// Related page - stores page ID, pushes slug to Typesense
		register_post_meta( 'page', 'blaze_related_page', array(
			'show_in_rest' => true,
			'single' => true,
			'type' => 'integer',
			'default' => 0,
			'sanitize_callback' => 'absint',
			'auth_callback' => function() {
				return current_user_can( 'edit_posts' );
			}
		) );

		// Debug: Log that meta fields registration is complete
		error_log( 'Blaze PageMetaFields: Meta fields registration complete' );
	}

	/**
	 * Enqueue admin scripts and styles
	 */
	public function enqueue_admin_scripts( $hook ) {
		// Only load on page edit screens
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ) ) ) {
			return;
		}

		// Only load for pages
		global $post;
		if ( ! $post || $post->post_type !== 'page' ) {
			return;
		}

		// Enqueue Select2 (WordPress includes it by default)
		wp_enqueue_script( 'select2' );
		wp_enqueue_style( 'select2' );

		// Enqueue our custom script
		wp_enqueue_script(
			'blaze-page-meta-select2',
			BLAZE_WOOLESS_PLUGIN_URL . 'assets/js/page-meta-select2.js',
			array( 'jquery', 'select2' ),
			BLAZE_WOOLESS_VERSION,
			true
		);

		// Enqueue custom CSS for Select2 styling
		wp_enqueue_style(
			'blaze-page-meta-select2-css',
			BLAZE_WOOLESS_PLUGIN_URL . 'assets/css/page-meta-select2.css',
			array( 'select2' ),
			BLAZE_WOOLESS_VERSION
		);
	}

	/**
	 * Add meta boxes
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'blaze-page-meta-fields',
			'Blaze Commerce Settings',
			array( $this, 'render_meta_box' ),
			'page',
			'side',
			'default'
		);
	}

	/**
	 * Render meta box content
	 */
	public function render_meta_box( $post ) {
		// Add nonce for security
		wp_nonce_field( 'blaze_page_meta_nonce', 'blaze_page_meta_nonce' );

		// Get current values
		$page_region = get_post_meta( $post->ID, 'blaze_page_region', true );
		$related_page = get_post_meta( $post->ID, 'blaze_related_page', true );

		// Get available regions from Aelia extension
		$available_regions = $this->get_available_regions();

		// Get all published pages for related page selector
		$available_pages = $this->get_available_pages( $post->ID );

		?>
		<table class="form-table">
			<tr>
				<td>
					<label for="blaze_page_region"><strong>Page Region</strong></label>
					<select id="blaze_page_region" name="blaze_page_region" class="blaze-select2" style="width: 100%;" data-placeholder="Select a region...">
						<option value="">Select a region...</option>
						<?php foreach ( $available_regions as $region_code => $region_data ) : ?>
							<option value="<?php echo esc_attr( $region_code ); ?>" <?php selected( $page_region, $region_code ); ?>>
								<?php echo esc_html( $region_data['label'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description">Select the region this page should be displayed in. Regions are mapped from Aelia Currency Switcher settings.</p>
				</td>
			</tr>
			<tr>
				<td>
					<label for="blaze_related_page"><strong>Related Page</strong></label>
					<select id="blaze_related_page" name="blaze_related_page" class="blaze-select2" style="width: 100%;" data-placeholder="Select a related page...">
						<option value="">Select a related page...</option>
						<?php foreach ( $available_pages as $page_id => $page_title ) : ?>
							<option value="<?php echo esc_attr( $page_id ); ?>" <?php selected( $related_page, $page_id ); ?>>
								<?php echo esc_html( $page_title ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description">Select a related page. The permalink will be included in search data.</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save page meta data when post is saved
	 */
	public function save_page_meta( $post_id, $post ) {
		// Check if this is an autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check if this is a page
		if ( $post->post_type !== 'page' ) {
			return;
		}

		// Check user permissions
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Verify nonce
		if ( ! isset( $_POST['blaze_page_meta_nonce'] ) || ! wp_verify_nonce( $_POST['blaze_page_meta_nonce'], 'blaze_page_meta_nonce' ) ) {
			return;
		}

		// Save page region
		if ( isset( $_POST['blaze_page_region'] ) ) {
			update_post_meta( $post_id, 'blaze_page_region', $this->sanitize_page_region( $_POST['blaze_page_region'] ) );
		}

		// Save related page
		if ( isset( $_POST['blaze_related_page'] ) ) {
			update_post_meta( $post_id, 'blaze_related_page', absint( $_POST['blaze_related_page'] ) );
		}
	}

	/**
	 * Get available regions from Aelia Currency Switcher
	 */
	public function get_available_regions() {
		$regions = array();

		// Check if Aelia Currency Switcher is active
		if ( ! function_exists( 'is_plugin_active' ) || ! is_plugin_active( 'woocommerce-aelia-currencyswitcher/woocommerce-aelia-currencyswitcher.php' ) ) {
			return $regions;
		}

		// Get Aelia currency switcher options
		$aelia_options = get_option( 'wc_aelia_currency_switcher', false );
		if ( ! $aelia_options || ! isset( $aelia_options['currency_countries_mappings'] ) ) {
			return $regions;
		}

		$currency_mappings = $aelia_options['currency_countries_mappings'];

		// Build regions array - use simple currency labels for now
		// (WooCommerce country names can be added later if needed)
		foreach ( $currency_mappings as $currency => $mapping ) {
			if ( isset( $mapping['countries'] ) && ! empty( $mapping['countries'] ) ) {
				$country = $mapping['countries'][0];
				$regions[ $country ] = array(
					'label' => $country . ' (' . $currency . ')',
					'currency' => $currency,
					'country' => $country,
					'countries' => $mapping['countries']
				);
			}
		}

		return $regions;
	}

	/**
	 * Get available pages for related page selector
	 */
	public function get_available_pages( $current_page_id = 0 ) {
		$pages = array();

		// Get all published pages
		$query_args = array(
			'post_type' => 'page',
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'orderby' => 'title',
			'order' => 'ASC',
			'exclude' => array( $current_page_id ), // Exclude current page to prevent self-reference
		);

		$page_query = new \WP_Query( $query_args );

		if ( $page_query->have_posts() ) {
			while ( $page_query->have_posts() ) {
				$page_query->the_post();
				$pages[ get_the_ID() ] = get_the_title();
			}
			wp_reset_postdata();
		}

		return $pages;
	}

	/**
	 * Sanitize page region field
	 */
	public function sanitize_page_region( $value ) {
		$available_regions = $this->get_available_regions();

		if ( empty( $value ) || isset( $available_regions[ $value ] ) ) {
			return $value;
		}

		return '';
	}

	/**
	 * Add meta fields to Typesense page fields
	 */
	public function set_page_fields( $fields ) {
		$fields[] = array( 'name' => 'metaData.blazePageRegion', 'type' => 'string', 'optional' => true, 'facet' => true );
		$fields[] = array( 'name' => 'metaData.blazeRelatedPageSlug', 'type' => 'string', 'optional' => true );

		return $fields;
	}

	/**
	 * Add meta data to Typesense page data
	 */
	public function set_page_data( $document, $page ) {
		if ( ! isset( $document['metaData'] ) ) {
			$document['metaData'] = array();
		}

		$document['metaData']['blazePageRegion'] = get_post_meta( $page->ID, 'blaze_page_region', true );

		// Get related page slug
		$related_page_id = get_post_meta( $page->ID, 'blaze_related_page', true );
		$related_page_slug = '';

		if ( $related_page_id ) {
			$related_page = get_post( $related_page_id );
			if ( $related_page && $related_page->post_status === 'publish' ) {
				$related_page_slug = $related_page->post_name;
			}
		}

		$document['metaData']['blazeRelatedPageSlug'] = $related_page_slug;

		return $document;
	}
}
