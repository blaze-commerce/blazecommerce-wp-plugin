<?php

namespace BlazeWooless\Features;

use BlazeWooless\TypesenseClient;

class CategoryBanner {
	private static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		add_action( 'product_cat_edit_form_fields', array( $this, 'register_category_fields' ), 90, 1 );
		add_action( 'edited_product_cat', array( $this, 'save_custom_category_options' ), 10, 1 );

		add_filter( 'blaze_commerce_taxonomy_meta_data', array( $this, 'register_taxonomy_meta_datas_to_typesense' ), 10, 2 );
	}

	public function register_category_fields( $tag ) {
		$blaze_commerce_category_subtitle = get_term_meta( $tag->term_id, 'blaze_commerce_category_subtitle', true );
		?>

		<tr class="form-field">
			<th colspan="2">
				<h3>
					<?php esc_html_e( 'Blaze Commerce', 'blaze-commerce' ); ?>
				</h3>
			</th>
		</tr>

		<tr class="form-field">
			<th scope="row" valign="top"><label for="blaze_commerce_category_subtitle">Subtitle</label></th>
			<td>
				<input type="text" name="blaze_commerce_category_subtitle" id="blaze_commerce_category_subtitle"
					value="<?php echo esc_attr( $blaze_commerce_category_subtitle ); ?>" />
				<p class="description">When subtitle is set, it will be displayed after the title on the category pages on the
					front-end.</p>
			</td>
		</tr>
		<?php
	}

	public function save_custom_category_options( $term_id ) {
		if (
			empty( $_POST['action'] ) ||
			( 'editedtag' !== $_POST['action'] && 'inline-save-tax' !== $_POST['action'] )
		) {
			return;
		}

		if ( isset( $_POST['blaze_commerce_category_subtitle'] ) ) {
			update_term_meta( $term_id, 'blaze_commerce_category_subtitle', $_POST['blaze_commerce_category_subtitle'] );
		}

		TypesenseClient::get_instance()->taxonomy()->update( [ 
			'id' => (string) $term_id,
			'metaData' => apply_filters( 'blaze_commerce_taxonomy_meta_data', array(), $term_id ),
			'updatedAt' => time(),
		] );
	}

	public function register_taxonomy_meta_datas_to_typesense( $meta_data, $term_id ) {
		$subtitle = get_term_meta( $term_id, 'blaze_commerce_category_subtitle', true );
		if ( $subtitle ) {
			$meta_data[] = array( 'name' => 'subtitle', 'value' => $subtitle );
		}

		return $meta_data;
	}
}