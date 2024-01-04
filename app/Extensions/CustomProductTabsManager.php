<?php

namespace BlazeWooless\Extensions;

/**
 * Support for Custom Product Tabs Manager by Addify version 1.0.0
 */

class CustomProductTabsManager
{
	private static $instance = null;
	private static $session_header;

	public static function get_instance()
	{
		if (self::$instance === null) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct()
	{
		if (class_exists("\Custom_Product_Tabs_Main")) {
			add_filter('wooless_product_tabs', [$this, 'generate_product_tabs'], 999, 3);
		}

	}


	public function generate_product_tabs($formatted_additional_tabs, $product_id, $product)
	{

		$product_tabs = $this->get_custom_tabs($product);

		if (!empty($product_tabs)) {

			if (isset($product_tabs['description'])) {
				// We are removing desription because this is processed by the frontend separately 
				unset($product_tabs['description']);
			}

			$formatted_additional_tabs = []; // resets or initialize the data to empty array
			foreach ($product_tabs as $key => $product_tab) {
				$content = '';
				if (isset($product_tab['callback'])) {
					ob_start();
					call_user_func($product_tab['callback'], $key, $product_tab);
					$content = ob_get_clean();
				}

				$tab_item = [
					'title' => wp_kses_post(apply_filters('woocommerce_product_' . $key . '_tab_title', $product_tab['title'], $key)),
					'content' => $content,
					'isOpen' => 0,
					'location' => 'side'
				];

				$formatted_additional_tabs[] = apply_filters('wooless_tab_' . $key, $tab_item, $product_tab, $product);
			}
		}

		return $formatted_additional_tabs;
	}

	/**
	 * Check the method name ka_check_rule_for_product from Custom_Product_Tabs_Front class. This function is a copy of it
	 */
	public function check_product_tabs_rule($rule_id, $product_id)
	{
		$cpt_hide_products = (array) json_decode(get_post_meta(intval($rule_id), 'new_for_search_products', true));
		$cpt_hide_categories = (array) json_decode(get_post_meta(intval($rule_id), 'product_tabs_category', true));
		$cpt_all_user_roles = (array) get_post_meta(intval($rule_id), 'enable_all_user_roles', true);

		$applied_on_tags = (array) json_decode(get_post_meta($rule_id, 'search_product_tags', true));
		$ka_specific_user = (array) json_decode(get_post_meta($rule_id, 'ka_specific_user', true));
		$enable_for_all_products = get_post_meta($rule_id, 'ap_checkbox', true);

		if (!is_user_logged_in()) {

			if (!in_array('guest', (array) $cpt_all_user_roles, true)) {
				return false;
			}
		} else {
			$curr_user = wp_get_current_user();
			$curr_user_role = current($curr_user->roles);

			$user_match = false;
			if (in_array((string) $curr_user->ID, $ka_specific_user, true)) {
				$user_match = true;
			}
			if (in_array((string) $curr_user_role, (array) $cpt_all_user_roles, true)) {
				$user_match = true;
			}

			if (!$user_match) {
				return false;
			}
		}
		if ('yes' === $enable_for_all_products) {
			return true;
		}
		if (in_array((string) $product_id, $cpt_hide_products, true)) {

			return true;
		}

		foreach ($cpt_hide_categories as $cat) {

			if (!empty($cat) && has_term($cat, 'product_cat', $product_id)) {

				return true;
			}
		}

		foreach ($applied_on_tags as $cat) {
			if (!empty($cat) && has_term($cat, 'product_tag', $product_id)) {

				return true;
			}
		}

		return false;
	}

	public function get_custom_tabs($product)
	{
		// Source of this code can be found on /wp-content/plugins/custom-product-tabs-manager/class-custom-product-tabs-front.php method name is ka_woo_new_custom_tab
		$sorting_additional_information = get_option('ka_tabs_additional_information_field');
		$enable_additional_information = get_option('ka_tabs_enable_additional_information_field');

		if (isset($sorting_additional_information) && !empty($sorting_additional_information)) {
			$newtab['additional_information']['title'] = esc_html($sorting_additional_information);
		}


		if (empty($enable_additional_information)) {
			unset($newtab['additional_information']);
		}

		$args = array(
			'post_type' => 'product_tab',
			'post_status' => 'publish',
			'numberposts' => -1,
			'order_by' => 'post_date',
			'fields' => 'ids',
			'suppress_filters' => false,
		);
		$allcustomtabs = get_posts($args);
		foreach ($allcustomtabs as $alltabs_id) {
			$custom_tab_tittle = get_post_meta($alltabs_id, 'tabetittle', true);

			if ('checkbox' !== get_post_meta($alltabs_id, 'enablecheckbox', true)) {
				continue;
			}

			if (!$this->check_product_tabs_rule($alltabs_id, $product->get_id())) {
				continue;
			}

			$newtab[$alltabs_id] = array(
				'title' => $custom_tab_tittle,
				'callback' => array($this, 'tab_content'),
				'priority' => 50,
				'ka_custom_tabs_id' => $alltabs_id,
			);
		}

		$sorted_array = array();

		if (!empty(get_option('cpt_sortable'))) {
			foreach (get_option('cpt_sortable') as $key => $value) {
				if (!empty(intval($value))) {
					$tab_post = get_post($value);

					if (!empty($tab_post) && isset($newtab[$value])) {
						$sorted_array[$value] = $newtab[$value];
					}
				} else {

					if (isset($newtab[$value])) {
						$sorted_array[$value] = $newtab[$value];
					}
				}
			}
			foreach ($newtab as $key => $value) {
				if (!isset($sorted_array[$key])) {
					$sorted_array[$key] = $newtab[$key];
				}
			}
		} else {
			$sorted_array = $newtab;
		}

		return $sorted_array;
	}

	public function tab_content($tabid, $tab)
	{
		  // Source of this code can be found on /wp-content/plugins/custom-product-tabs-manager/class-custom-product-tabs-front.php method name is ka_customized_custom_tabs_content
		if (!isset($tab['ka_custom_tabs_id'])) {
            return;
		}

        $new_tab_id = $tab['ka_custom_tabs_id'];

		if (
            'checkbox' === get_post_meta($new_tab_id, 'enablecheckbox', true) &&
            'editor'   === get_post_meta($new_tab_id, 'tabcallback_select', true)
        ) {
            $af_tab_content = get_post_meta($new_tab_id, 'cpt_tab_content', true);
            echo wp_kses_post(apply_filters('the_content', $af_tab_content));
        }
	}

}
