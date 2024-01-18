<?php


namespace BlazeWooless\Features;

class DraggableContent {
	private static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		add_filter( 'blaze_wooless_product_data_for_typesense', array( $this, 'add_dragable_content' ), 10, 2 );
		add_action( 'before_wooless_draggable_canvas', array( $this, 'content_region_selector' ) );
	}

	public function add_dragable_content( $product_data, $product_id ) {
		return $product_data;
	}

	// public function register_settings($product_page_settings)
	// {
	//     $product_page_settings['wooless_settings_attributes_section'] = array(
	//         'label' => 'Attributes',
	//         'options' => $this->get_attribute_mapping_settings(),
	//     );

	//     return $product_page_settings;
	// }

	public function content_region_selector() {
		$regions      = \BlazeWooless\Settings\RegionalSettings::get_instance()->get_option( 'regions' );
		$countries    = \WC()->countries->get_countries();
		$base_country = \WC()->countries->get_base_country();
		?>
		<input type="hidden" id="available-countries" value='<?php echo json_encode( $regions ) ?>' />
		<input type="hidden" id="base-country" value='<?php echo json_encode( $base_country ) ?>' />
		<select name="region_selector" id="region_selector">
			<?php foreach ( $regions as $country_code ) : ?>
				<option value="<?php echo $country_code ?>">
					<?php echo $countries[ $country_code ] ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}
}
