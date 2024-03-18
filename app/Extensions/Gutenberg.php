<?php

namespace BlazeWooless\Extensions;

class Gutenberg {
	private static $instance = null;

	protected $short_code_blocks = array(
		'core/shortcode',
		'core/paragraph'
	);

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		add_filter( 'blaze_wooless_page_data_for_typesense', array( $this, 'generate_gutenberg_content' ), 10, 2 );
	}

	public function generate_gutenberg_content( $page_typesense_data, \WP_Post $page ) {
		$post_content = $page->post_content;
		if ( true === has_blocks( $post_content ) ) {
			$blocks = parse_blocks( $post_content );

			ob_start();

			$this->render_block_from_gutenberg( $blocks );
			$rendered_content = ob_get_contents();
			ob_end_clean();

			$page_typesense_data['content'] = $rendered_content;
		}

		return $page_typesense_data;
	}

	public function render_block_from_gutenberg( $blocks ) {
		foreach ( $blocks as $block ) {	
			if ( 'core/columns' === $block['blockName'] ) {
				echo '<div class="content-area columns">';
				$this->render_block_from_gutenberg($block['innerBlocks']);
				echo '</div>';
			} else if ( 'core/column' === $block['blockName'] ) {
				echo '<div class="column">';
				$this->render_block_from_gutenberg($block['innerBlocks']);
				echo '</div>';
			} else if ( in_array( $block['blockName'], $this->short_code_blocks ) ) {
				$paragraph_content = $block['innerHTML'];
				$html = do_shortcode($paragraph_content);
				echo '<div class="content-area">' . $html . '</div>';
			} else {
				ob_start();
	
				echo render_block( $block );
	
				$rendered_content = ob_get_contents();
				ob_end_clean();
	
				echo '<div class="content-area">' . $rendered_content . '</div>';
			}
		}
	}
}