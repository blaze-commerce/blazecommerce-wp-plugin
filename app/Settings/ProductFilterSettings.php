<?php

namespace BlazeWooless\Settings;

use BlazeWooless\TypesenseClient;

class ProductFilterSettings extends BaseSettings {
	private static $instance = null;
	public $tab_key = 'product_filters';
	public $page_label = 'Product Filters';

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self( 'wooless_product_filters_settings_options' );
		}

		return self::$instance;
	}

	public function settings_callback( $options ) {
		$product_filters_content = array();
		if ( isset( $_POST['product_filters_content'] ) ) {
			$product_filters_content = json_decode( stripslashes( $_POST['product_filters_content'] ), true );
		}

		if ( is_array( $product_filters_content ) ) {
			update_option( 'blaze_wooless_product_filters_content', $product_filters_content );

			TypesenseClient::get_instance()->site_info()->upsert( [ 
				'id' => '1000010',
				'name' => 'product_filters_content',
				'value' => json_encode( $product_filters_content ),
				'updated_at' => time(),
			] );
		}

		return $options;
	}

	public function settings() {
		return array();
	}

	public function section_callback() {
		echo '<p>Select which areas of content you wish to display.</p>';
	}

	public function register_hooks() {
		add_action( 'blazecommerce/settings/render_settings_tab_content_footer', array( $this, 'default_draggable_data' ), 10 );
		add_filter( 'blazecommerce/settings', array( $this, 'add_product_filters_settings_to_documents' ), 10, 1 );
	}

	public function footer_callback() {
		require_once BLAZE_COMMERCE_PLUGIN_DIR . 'views/draggable-content-product-filters.php';
	}

	public function default_draggable_data() {
		if ( empty( $_GET['tab'] ) || $this->tab_key !== $_GET['tab'] )
			return;

		$product_filters_content = get_option( 'blaze_wooless_product_filters_content', '' );
		?>
		<input type="hidden" id="draggable_result" name="product_filters_content"
			value='<?php echo json_encode( $product_filters_content ) ?>' />
		<?php
	}

	public function add_product_filters_settings_to_documents( $documents ) {
		$product_filters_content = get_option( 'blaze_wooless_product_filters_content', '' );

		$documents[] = array(
			'id' => '1000010',
			'name' => 'product_filters_content',
			'value' => json_encode( $product_filters_content ),
			'updated_at' => time(),
		);

		return $documents;
	}
}

ProductFilterSettings::get_instance();
