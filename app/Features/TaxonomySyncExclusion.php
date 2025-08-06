<?php

namespace BlazeWooless\Features;

use BlazeWooless\TypesenseClient;

class TaxonomySyncExclusion {
	private static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		// Add meta field to WooCommerce category edit page
		add_action( 'product_cat_edit_form_fields', array( $this, 'add_exclusion_field' ), 95, 1 );
		
		// Save the meta field when category is saved
		add_action( 'edited_product_cat', array( $this, 'save_exclusion_field' ), 10, 1 );
		
		// Filter terms before batch data preparation
		add_filter( 'blazecommerce_taxonomy_sync_terms', array( $this, 'exclude_terms_from_sync' ), 10, 1 );
	}

	/**
	 * Add exclusion checkbox field to WooCommerce category edit page
	 *
	 * @param WP_Term $tag The term object
	 */
	public function add_exclusion_field( $tag ) {
		$exclude_from_sync = get_term_meta( $tag->term_id, 'blaze_exclude_from_typesense_sync', true );
		?>
		<tr class="form-field">
			<th scope="row" valign="top">
				<label for="blaze_exclude_from_typesense_sync">
					<?php esc_html_e( 'Typesense Sync', 'blaze-commerce' ); ?>
				</label>
			</th>
			<td>
				<input type="checkbox" 
					   name="blaze_exclude_from_typesense_sync" 
					   id="blaze_exclude_from_typesense_sync" 
					   value="1" 
					   <?php checked( $exclude_from_sync, '1' ); ?> />
				<label for="blaze_exclude_from_typesense_sync">
					<?php esc_html_e( 'Exclude from Typesense sync', 'blaze-commerce' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Check this box to exclude this category from being synced to Typesense search index.', 'blaze-commerce' ); ?>
				</p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save the exclusion field when category is saved
	 *
	 * @param int $term_id The term ID
	 */
	public function save_exclusion_field( $term_id ) {
		// Verify this is a legitimate save action
		if (
			empty( $_POST['action'] ) ||
			( 'editedtag' !== $_POST['action'] && 'inline-save-tax' !== $_POST['action'] )
		) {
			return;
		}

		// Sanitize and save the checkbox value
		$exclude_from_sync = isset( $_POST['blaze_exclude_from_typesense_sync'] ) ? '1' : '0';
		update_term_meta( $term_id, 'blaze_exclude_from_typesense_sync', $exclude_from_sync );

		// If the category is being excluded, remove it from Typesense
		if ( $exclude_from_sync === '1' ) {
			$this->remove_term_from_typesense( $term_id );
		} else {
			// If the category is being included again, update it in Typesense
			$this->update_term_in_typesense( $term_id );
		}
	}

	/**
	 * Filter terms to exclude those marked for exclusion from sync
	 *
	 * @param array $terms Array of WP_Term objects
	 * @return array Filtered array of terms
	 */
	public function exclude_terms_from_sync( $terms ) {
		if ( empty( $terms ) ) {
			return $terms;
		}

		$filtered_terms = array();
		
		foreach ( $terms as $term ) {
			$exclude_from_sync = get_term_meta( $term->term_id, 'blaze_exclude_from_typesense_sync', true );
			
			// Only include terms that are not marked for exclusion
			if ( $exclude_from_sync !== '1' ) {
				$filtered_terms[] = $term;
			}
		}

		return $filtered_terms;
	}

	/**
	 * Remove a term from Typesense when it's excluded
	 *
	 * @param int $term_id The term ID to remove
	 */
	private function remove_term_from_typesense( $term_id ) {
		try {
			$typesense_client = TypesenseClient::get_instance();

			// Try to delete the document from Typesense
			$typesense_client->taxonomy()[ (string) $term_id ]->delete();

		} catch ( \Exception $e ) {
			// Term might not exist in Typesense, which is fine
			// We can add logging here later if needed
		}
	}

	/**
	 * Update a term in Typesense when it's no longer excluded
	 *
	 * @param int $term_id The term ID to update
	 */
	private function update_term_in_typesense( $term_id ) {
		try {
			$term = get_term( $term_id );

			if ( ! $term || is_wp_error( $term ) ) {
				return;
			}

			// Use the existing taxonomy update method which handles the Typesense sync
			$taxonomy_collection = \BlazeWooless\Collections\Taxonomy::get_instance();
			$document = $taxonomy_collection->generate_typesense_data( $term );
			$taxonomy_collection->upsert( $document );

		} catch ( \Exception $e ) {
			// Error updating term - we can add logging here later if needed
		}
	}
}
