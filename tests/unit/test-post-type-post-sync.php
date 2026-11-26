<?php
/**
 * Posts are indexed on save, like pages.
 *
 * @package BlazeWooless
 * @subpackage Tests
 */

use BlazeWooless\PostType;

class Test_PostType_Post_Sync extends WP_UnitTestCase {

	public function test_save_post_post_hook_is_registered() {
		$post_type = PostType::get_instance();
		$this->assertNotFalse( has_action( 'save_post_post', array( $post_type, 'upsert_post_data' ) ) );
		$this->assertNotFalse( has_action( 'save_post_page', array( $post_type, 'upsert_page_data' ) ) );
	}

	public function test_draft_post_is_not_upserted() {
		$calls = 0;
		add_action( 'ts_before_page_upsert', function () use ( &$calls ) {
			$calls++;
		} );

		$post_id = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		PostType::get_instance()->upsert_post_data( $post_id, get_post( $post_id ), false );

		$this->assertSame( 0, $calls );
	}
}
