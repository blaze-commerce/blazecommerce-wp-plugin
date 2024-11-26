<?php

namespace BlazeWooless\Extensions;

class NiWooCommerceProductVariationsTable {
	private static $instance = null;
	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		if ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'ni-woocommerce-product-variations-table/ni-woocommerce-product-variations-table.php' ) ) {

			add_filter( 'blaze_wooless_product_data_for_typesense', array( $this, 'save_table_to_description' ), 10, 3 );
		}
	}

	public function get_default_columns() {
		$columns           = array();
		$columns["sku"]    = __( "SKU" );
		$columns["custom"] = __( "Custom" );
		$columns["price"]  = __( "Price" );
		return $columns;
	}

	public function generate_html_table( $product ) {
		$columns       = array();
		$this->options = get_option( 'nipv_setting_option' );
		$columns       = isset( $this->options["nipv_setting_option"] ) ? $this->options["nipv_setting_option"] : array();

		$has_color         = false;
		$output            = '';
		$variation_product = '';
		$sale_price        = 0;
		$regular_price     = 0;
		$cart_url          = wc_get_cart_url();
		$custom_headers    = array();
		$show_message      = false;
		if ( get_field( 'custom_headers', $product->get_id() ) ) {
			$custom_headers = get_field( 'custom_headers', $product->get_id() );
		}
		if ( get_field( 'show_message', $product->get_id() ) ) {
			$show_message = get_field( 'show_message', $product->get_id() );
		}



		if ( ! $product->has_child() ) {
			return '';
		} elseif ( empty( $custom_headers ) ) {
			return '';
		}
		$available_variations = $product->get_available_variations();
		$sort                 = array();
		if ( count( $columns ) == 0 ) {
			$columns = $this->get_default_columns();
		}

		if ( is_array( $available_variations ) && ! empty( $available_variations ) ) {
			foreach ( $available_variations as $av ) {
				if ( isset( $av['attributes'] ) && is_array( $av['attributes'] ) && ! empty( $av['attributes'] ) ) {
					$atts = $av['attributes'];
					foreach ( $atts as $att ) {
						$sort[ $att ][] = $av;
						break;
					}
				}
			}
		}

		//ksort($sort);
		$true = array();
		foreach ( $sort as $ss ) {
			foreach ( $ss as $s ) {
				$true[] = $s;
			}
		}

		$available_variations = $true;

		$attributes    = $product->get_variation_attributes();
		$columns_count = 0;
		?>
		<div class="nipv-tablesorter-spwrapper">
			<table cellspacing="0" class="nipv_table" style="width:100%" id="nipv-tablesorter" class="tablesorter">
				<thead>
					<tr>
						<?php foreach ( $columns as $key => $value ) : ?>
							<?php switch ( $key ) {
								case "image":
									$columns_count++;
									?>
									<th data-sorter="false" style="width:5%"><?php echo $value; ?></th>
									<?php
									break;
								case "variation":
									$columns_count++;
									?>
									<th><?php echo $value; ?></th>
									<?php
									break;
								case "sku":
									$columns_count++;
									?>
									<th><?php echo $value; ?></th>
									<?php
									break;
								case "price":
									$columns_count++;
									?>
									<th class="{sorter: 'digit'}"><?php echo $value; ?></th>
									<?php
									break;
								case "stock_status":
									$columns_count++;
									?>
									<th><?php echo $value; ?></th>
									<?php
									break;
								case "stock_quantity":
									$columns_count++;
									?>
									<th class="{sorter: 'digit'}"><?php echo $value; ?></th>
									<?php
									break;
								case "variation_description":
									$columns_count++;
									?>
									<th><?php echo $value; ?></th>
									<?php
									break;
								case "custom":
									if ( ! empty( $custom_headers ) ) :
										foreach ( $custom_headers as $ch ) :
											$columns_count++;
											if ( strtolower( $ch["header"] ) == 'color' ) {
												$has_color = true;
											}
											?>
											<th><?php echo $ch["header"]; ?></th>
											<?php
										endforeach;
									endif;
									break;
							} ?>

						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
					<?php
					$variation_all_attributes_arr = array();
					$displayed_cart_row           = array();
					foreach ( $available_variations as $key => $value ) {
						$fc                = '';
						$product_variation = wc_get_product( $value['variation_id'] );
						$product_id        = $value['variation_id'];
						$product_custom    = array();
						if ( get_field( 'variation_data', $product_id ) ) {
							$product_custom = get_field( 'variation_data', $product_id );
						}

						if ( ! $product_variation->variation_is_visible() )
							continue;

						// get a list of all attributes key/value pairs (aside from color) and see if it matches any existing products
						$all_attributes_arr        = array();
						$attributes_sans_color_arr = array();
						$all_attributes_str        = '';
						$attributes_sans_color_str = '';
						$row_classes               = 'no-color';
						$attribute_color           = '';
						$data_attributes_arr       = array();
						$data_attributes_keys_arr  = array();

						foreach ( $product_variation->get_attributes() as $key => $val ) {
							$val = str_replace( '"', '&quot;', $val );
							if ( $key != 'pa_color' ) :
								$all_attributes_arr[]        = $key . '-' . $val;
								$attributes_sans_color_arr[] = $key . '-' . $val;
								$data_attributes_arr[]       = 'data-attr-' . $key . '="' . $val . '"';
								$data_attributes_keys_arr[]  = $key;
							else :
								$row_classes          = 'has-color';
								$all_attributes_arr[] = $key . '-' . $val;
								$attribute_color      = $val;
							endif;
						}

						$all_attributes_str        = implode( '_', $all_attributes_arr );
						$attributes_sans_color_str = implode( '_', $attributes_sans_color_arr );
						$data_attributes_keys_str  = implode( ',', $data_attributes_keys_arr );
						$data_attributes_str       = implode( ' ', $data_attributes_arr );

						// comment out after testing
						$data_attributes_keys_str .= ',test_data_attribute';

						if ( ! in_array( $attributes_sans_color_str, $variation_all_attributes_arr ) ) :
							$variation_all_attributes_arr[] = $attributes_sans_color_str;
							$row_classes .= ' show-row';
						else :
							$row_classes .= ' hide-row';
						endif;
						?>
						<tr class="variation-row <?php echo $row_classes; ?>" id="<?php echo $all_attributes_str; ?>"
							data-color="<?php echo $attribute_color; ?>"
							data-shared-atts="<?php echo $attributes_sans_color_str; ?>"
							data-attr-keys="<?php echo $data_attributes_keys_str; ?>" <?php echo $data_attributes_str; ?>>
							<?php
							foreach ( $columns as $k => $v ) {
								switch ( $k ) {
									case "image":
										?>
										<td>
											<?php echo $product_variation->get_image(); ?>
										</td>
										<?php
										break;

									case "custom":
										if ( ! empty( $product_custom ) ) :
											foreach ( $product_custom as $pc ) :
												?>
												<td><?php echo $pc["data_entry"]; ?></td>
												<?php
											endforeach;

											if ( $has_color ) :
												//show color variation ?>
												<?php foreach ( $attributes as $attribute_name => $options ) :
													if ( $attribute_name == 'pa_color' ) : ?>
														<td class="color-select">
															<select name="attribute_pa_color" data-attribute_name="attribute_pa_color">
																<?php foreach ( $options as $option ) {
																	if ( $fc == '' ) {
																		$fc = $option;
																	}
																	$option_term = get_term_by( 'slug', $option, 'pa_color' );
																	echo '<option value="' . esc_attr( $option ) . '">' . $option_term->name . '</option>';
																} ?>
															</select>


														</td>
													<?php endif; endforeach; ?>

											<?php endif;
										endif;
										break;
									case "variation":
										?>
										<td>
											<?php $all_variation = ""; ?>
											<?php foreach ( $product_variation->get_attributes() as $key => $val ) {
												$val = str_replace( array( '-', '_' ), ' ', $val );
												if ( strlen( $all_variation ) > 0 ) {

													$all_variation .= " - " . ucwords( $val );
												} else {
													$all_variation .= ucwords( $val );
												}
											} ?>
											<?php echo $all_variation; ?>
										</td>
										<?php
										break;
									case "sku":
										?>
										<td class="vt-sku" data-sku="<?php echo $product_variation->get_sku(); ?>">
											<?php echo $product_variation->get_sku(); ?>
										</td>
										<?php
										break;
									case "price":
										?>
										<td style="text-align:right">
											<?php echo $product_variation->get_price_html(); ?>
										</td>
										<?php
										break;
									case "color":
										?>
										<td style="text-align:right">
											<?php echo 'color'; ?>
										</td>
										<?php
										break;
									case "stock_status":
										?>
										<td>
											<?php echo $product_variation->get_stock_status() ?>
										</td>
										<?php
										break;
									case "stock_quantity":
										?>
										<td style="text-align:right">
											<?php
											echo $product_variation->get_stock_quantity();
											?>
										</td>
										<?php
										break;
									case "variation_description":
										?>
										<td>
											<?php
											echo $product_variation->get_variation_description();
											?>
										</td>
										<?php
										break;
								}
							}

							?>
						</tr>

						<?php if ( ! in_array( $attributes_sans_color_str, $displayed_cart_row ) ) :
							$displayed_cart_row[] = $attributes_sans_color_str; ?>
							<tr class="add-to-cart-row" data-shared-atts="<?php echo $attributes_sans_color_str; ?>">
								<td colspan="<?php echo $columns_count; ?>">
								</td>
							</tr>
						<?php endif; ?>
					<?php } ?>
				</tbody>
			</table>
		</div>
		<?php

	}
	public function get_variation_table( $product ) {
		if ( class_exists( 'Ni_wooCommerce_After_Single_Product_Summary' ) ) {
			ob_start();
			$this->generate_html_table( $product );
			return ob_get_clean();
		}

		return '';
	}


	public function save_table_to_description( $product_data, $product_id, $product ) {

		$tableHtml                   = $this->get_variation_table( $product );
		$product_data['description'] = $tableHtml . $product_data['description'];

		return $product_data;
	}
}
