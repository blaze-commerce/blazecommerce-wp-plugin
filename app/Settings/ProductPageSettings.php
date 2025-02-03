<?php

namespace BlazeWooless\Settings;

use BlazeWooless\Features\AttributeSettings;
use BlazeWooless\TypesenseClient;

class ProductPageSettings extends BaseSettings {
	private static $instance = null;
	public $tab_key = 'product';
	public $page_label = 'Product Page';

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self( 'wooless_settings_product_page_options' );
		}

		return self::$instance;
	}

	public function settings_callback( $options ) {

		try {
			$this->update_fields( $options );
		} catch (\Throwable $th) {

		}

		$homepage_layout = json_decode( stripslashes( $_POST['homepage_layout'] ), true );

		if ( is_array( $homepage_layout ) ) {
			update_option( 'blaze_wooless_homepage_layout', $homepage_layout );
		}

		if ( isset( $options['description_after_content'] ) ) {
			$options['description_after_content'] = $options['description_after_content'];
		}

		return $options;
	}

	public function settings() {
		$icons                 = array(
			'select' => 'Select',
			'CiDeliveryTruck' => 'CiDeliveryTruck',
			'CiViewList' => 'CiViewList',
			'FiTruck' => 'FiTruck',
			'FiLock' => 'FiLock',
			'FiPackage' => 'FiPackage',
			'CiCircleInfo' => 'CiCircleInfo',
			'GoShieldCheck' => 'GoShieldCheck',
			'BsExclamationOctagon' => 'BsExclamationOctagon',
		);
		$product_page_settings = [ 
			'wooless_settings_product_page_section' => array(
				'label' => 'Product Page',
				'options' => array(
					// Information 1
					array(
						'id' => 'information_1_title',
						'label' => 'Information 1 Title',
						'type' => 'text',
						'args' => array( 'description' => 'Set the title for information 1.', ),
					),
					array(
						'id' => 'information_1_content',
						'label' => 'Information 1 Content',
						'type' => 'html',
						'args' => array(
							'description' => 'Set the content for information 1.',
						),
					),
					array(
						'id' => 'information_1_link',
						'label' => 'Information 1 Link',
						'type' => 'html',
						'args' => array(
							'description' => 'Set the link for information 1. Keep empty to use the dialog sidebar',
						),
					),
					array(
						'id' => 'information_1_icon',
						'label' => 'Information 1 Icon',
						'type' => 'select',
						'args' => array(
							'options' => $icons,
							'description' => 'Set the icon for information 1',
						),
					),

					// Information 2
					array(
						'id' => 'information_2_title',
						'label' => 'Information 2 Title',
						'type' => 'text',
						'args' => array( 'description' => 'Set the title for information 2.', ),
					),
					array(
						'id' => 'information_2_content',
						'label' => 'Information 2 Content',
						'type' => 'html',
						'args' => array( 'description' => 'Set the returns policy content.' ),
					),
					array(
						'id' => 'information_2_link',
						'label' => 'Information 2 Link',
						'type' => 'html',
						'args' => array(
							'description' => 'Set the link for information 2. Keep empty to use the dialog sidebar',
						),
					),
					array(
						'id' => 'information_2_icon',
						'label' => 'Information 2 Icon',
						'type' => 'select',
						'args' => array(
							'options' => $icons,
							'description' => 'Set the icon for information 2',
						),
					),

					// Information 3
					array(
						'id' => 'information_3_title',
						'label' => 'Information 3 Title',
						'type' => 'text',
						'args' => array( 'description' => 'Set the title for information 3.', ),
					),
					array(
						'id' => 'information_3_content',
						'label' => 'Information 3 Content',
						'type' => 'html',
						'args' => array( 'description' => 'Set the returns policy content.' ),
					),
					array(
						'id' => 'information_3_link',
						'label' => 'Information 3 Link',
						'type' => 'html',
						'args' => array(
							'description' => 'Set the link for information 3. Keep empty to use the dialog sidebar',
						),
					),
					array(
						'id' => 'information_3_icon',
						'label' => 'Information 3 Icon',
						'type' => 'select',
						'args' => array(
							'options' => $icons,
							'description' => 'Set the icon for information 3',
						),
					),
					array(
						'id' => 'description_after_content',
						'label' => 'Description After Content',
						'type' => 'html',
						'args' => array( 'description' => 'This will be displayed after the description on all products.' ),
					),
				)
			),
		];

		return apply_filters( 'blaze_wooless_product_page_settings', $product_page_settings );
	}

	public function section_callback() {
		echo '<p>Select which areas of content you wish to display.</p>';
	}

	public function update_fields( $options ) {
		$site_info = TypesenseClient::get_instance()->site_info();

		$site_info->upsert(
			array(
				'id' => '10089551',
				'name' => 'product_page_information_1',
				'value' => json_encode( array(
					'title' => isset( $options['information_1_title'] ) ? $options['information_1_title'] : '',
					'icon' => isset( $options['information_1_icon'] ) ? $options['information_1_icon'] : '',
					'content' => isset( $options['information_1_content'] ) ? $options['information_1_content'] : '',
					'link' => isset( $options['information_1_link'] ) ? $options['information_1_link'] : '',
				) ),
				'updated_at' => time(),
			)
		);

		$site_info->upsert(
			array(
				'id' => '10089552',
				'name' => 'product_page_information_2',
				'value' => json_encode( array(
					'title' => isset( $options['information_2_title'] ) ? $options['information_2_title'] : '',
					'icon' => isset( $options['information_2_icon'] ) ? $options['information_2_icon'] : '',
					'content' => isset( $options['information_2_content'] ) ? $options['information_2_content'] : '',
					'link' => isset( $options['information_2_link'] ) ? $options['information_2_link'] : '',
				) ),
				'updated_at' => time(),
			)
		);

		$site_info->upsert(
			array(
				'id' => '10089553',
				'name' => 'product_page_information_3',
				'value' => json_encode( array(
					'title' => isset( $options['information_3_title'] ) ? $options['information_3_title'] : '',
					'icon' => isset( $options['information_3_icon'] ) ? $options['information_3_icon'] : '',
					'content' => isset( $options['information_3_content'] ) ? $options['information_3_content'] : '',
					'link' => isset( $options['information_3_link'] ) ? $options['information_3_link'] : '',
				) ),
				'updated_at' => time(),
			)
		);

		$site_info->upsert(
			array(
				'id' => '10089554',
				'name' => 'description_after_content',
				'value' => isset( $options['description_after_content'] ) ? $options['description_after_content'] : '',$options['description_after_content']
				'updated_at' => time(),
			)
		);

		$site_info->upsert(
			array(
				'id' => '1000001',
				'name' => 'privacy_policy_content',
				'value' => $options['privacy_policy'],
				'updated_at' => time(),
			)
		);

		$free_shipping_threshold = get_option( 'free_shipping_threshold', '' );
		if ( ! empty( $free_shipping_threshold ) ) {
			$site_info->upsert(
				array(
					'id' => '1000482',
					'name' => 'free_shipping_threshold',
					'value' => json_encode( $free_shipping_threshold ),
					'updated_at' => time(),
				)
			);
		}

		do_action( 'blaze_wooless_save_product_page_settings', $options );
	}

	public function register_hooks() {
		add_action( 'blaze_wooless_after_site_info_sync', array( $this, 'sync_additional_data' ), 10 );
	}

	public function sync_additional_data() {
		$options = $this->get_option();
		$this->update_fields( $options );

		if ( isset( $_POST['free_shipping_threshold'] ) ) {
			update_option( 'free_shipping_threshold', $_POST['free_shipping_threshold'] );
			TypesenseClient::get_instance()->site_info()->upsert( [ 
				'id' => '1000482',
				'name' => 'free_shipping_threshold',
				'value' => json_encode( $_POST['free_shipping_threshold'] ),
				'updated_at' => time(),
			] );
		}
	}

	public function footer_callback() {
		if ( is_plugin_active( 'woocommerce-aelia-currencyswitcher/woocommerce-aelia-currencyswitcher.php' ) ) {
			$available_currencies = \Aelia\WC\CurrencySwitcher\WC_Aelia_Reporting_Manager::get_currencies_from_sales();
		} else {
			$base_currency        = get_woocommerce_currency();
			$available_currencies = [ 
				$base_currency => ''
			];
		}

		$free_shipping_threshold = get_option( 'free_shipping_threshold', [] );
		?>
		<h2>Free Shipping Threshold</h2>
		<table class="form-table" role="presentation">
			<tbody>
				<?php foreach ( $available_currencies as $currency => $value ) : ?>
					<tr>
						<th>
							<?php echo $currency ?>
						</th>
						<td>
							<input type="text" id="free_shipping_threshold_<?php echo $currency ?>"
								name="free_shipping_threshold[<?php echo $currency ?>]"
								value="<?php echo $free_shipping_threshold[ $currency ] ?? ''; ?>">
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}

ProductPageSettings::get_instance();
