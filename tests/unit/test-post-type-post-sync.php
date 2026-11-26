<?php
/**
 * Blog posts are indexed after save and removed when they stop being public.
 *
 * Typesense is never called: Page::get_instance() is swapped for a mock whose upsert() and
 * delete() only record their arguments.
 *
 * @package BlazeWooless
 * @subpackage Tests
 */

use BlazeWooless\Collections\Page;
use BlazeWooless\PostType;
use BlazeWooless\TypesenseClient;

class Test_PostType_Post_Sync extends WP_UnitTestCase {

	/** @var array<int, array> Documents passed to upsert(). */
	private $upserts = array();

	/** @var array<int, string> IDs passed to delete(). */
	private $deletes = array();

	public function setUp(): void {
		parent::setUp();

		$this->upserts = array();
		$this->deletes = array();

		update_option(
			'wooless_general_settings_options',
			array(
				'enable_system'      => 1,
				'typesense_api_key'  => 'test-key',
				'typesense_host'     => 'typesense.invalid',
				'store_id'           => '1',
			)
		);

		$this->set_static( TypesenseClient::class, 'instance', new TypesenseClient( bw_get_general_settings() ) );

		$page = $this->getMockBuilder( Page::class )
			->disableOriginalConstructor()
			->onlyMethods( array( 'get_data', 'upsert', 'delete' ) )
			->getMock();
		$page->method( 'get_data' )->willReturnCallback(
			function ( $post ) {
				return array(
					'id'         => (string) $post->ID,
					'name'       => $post->post_title,
					'type'       => $post->post_type,
					'categories' => wp_get_post_categories( $post->ID ),
				);
			}
		);
		$page->method( 'upsert' )->willReturnCallback(
			function ( $document ) {
				$this->upserts[] = $document;
				return $document;
			}
		);
		$page->method( 'delete' )->willReturnCallback(
			function ( $id ) {
				$this->deletes[] = (string) $id;
				return array( 'id' => (string) $id );
			}
		);
		$this->set_static( Page::class, 'instance', $page );

		$this->set_static( PostType::class, 'upserted_posts', array() );
		$this->set_static( PostType::class, 'deleted_posts', array() );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	public function tearDown(): void {
		$this->set_static( Page::class, 'instance', null );
		$this->set_static( TypesenseClient::class, 'instance', null );
		remove_all_filters( 'wp_doing_cron' );
		remove_all_filters( 'blazecommerce/collection/syncable_post_types' );
		parent::tearDown();
	}

	private function set_static( $class, $property, $value ) {
		$reflection = new ReflectionProperty( $class, $property );
		if ( PHP_VERSION_ID < 80100 ) {
			$reflection->setAccessible( true );
		}
		$reflection->setValue( null, $value );
	}

	private function upserted_ids() {
		return array_map(
			function ( $document ) {
				return $document['id'];
			},
			array_values(
				array_filter(
					$this->upserts,
					function ( $document ) {
						return 'post' === $document['type'];
					}
				)
			)
		);
	}

	private function reset_request_state() {
		$this->set_static( PostType::class, 'upserted_posts', array() );
		$this->set_static( PostType::class, 'deleted_posts', array() );
	}

	public function test_hooks_are_registered() {
		$post_type = PostType::get_instance();
		$this->assertSame( 10, has_action( 'wp_after_insert_post', array( $post_type, 'upsert_post_data' ) ) );
		$this->assertSame( 10, has_action( 'transition_post_status', array( $post_type, 'remove_unpublished_post' ) ) );
		$this->assertSame( 10, has_action( 'before_delete_post', array( $post_type, 'delete_post_data' ) ) );
		$this->assertSame( 10, has_action( 'save_post_page', array( $post_type, 'upsert_page_data' ) ) );
		$this->assertFalse( has_action( 'save_post_post', array( $post_type, 'upsert_post_data' ) ) );
	}

	public function test_published_post_is_upserted_once() {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$this->assertSame( array( (string) $post_id ), $this->upserted_ids() );
		$this->assertSame( array(), $this->deletes );
	}

	public function test_block_editor_rest_save_indexes_the_saved_categories() {
		// Booting the REST server registers wooless-wc/v1/available-shipping-methods, which has no
		// permission_callback, so core raises a _doing_it_wrong notice for it.
		$this->setExpectedIncorrectUsage( 'register_rest_route' );

		$category_id = self::factory()->category->create( array( 'name' => 'News' ) );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$request->set_body_params(
			array(
				'title'      => 'Published from the block editor',
				'status'     => 'publish',
				'categories' => array( $category_id ),
			)
		);
		$response = rest_do_request( $request );
		$this->assertSame( 201, $response->get_status() );

		$post_id   = (string) $response->get_data()['id'];
		$documents = array_values(
			array_filter(
				$this->upserts,
				function ( $document ) use ( $post_id ) {
					return $post_id === $document['id'];
				}
			)
		);

		// One write, made after core saved the terms (save_post_post would have seen Uncategorized).
		$this->assertCount( 1, $documents );
		$this->assertSame( array( $category_id ), $documents[0]['categories'] );
	}

	public function test_draft_post_is_not_upserted() {
		self::factory()->post->create( array( 'post_status' => 'draft' ) );

		$this->assertSame( array(), $this->upserted_ids() );
	}

	public function test_same_document_is_not_upserted_twice_in_one_request() {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$post    = get_post( $post_id );

		PostType::get_instance()->upsert_post_data( $post_id, $post, true, $post );
		$this->assertCount( 1, $this->upserted_ids() );

		wp_update_post( array( 'ID' => $post_id, 'post_title' => 'Changed title' ) );
		$this->assertCount( 2, $this->upserted_ids() );
		$this->assertSame( 'Changed title', end( $this->upserts )['name'] );
	}

	public function test_scheduled_post_published_by_cron_without_a_user_is_upserted() {
		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'future',
				'post_date'   => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ),
			)
		);
		$this->assertSame( array(), $this->upserted_ids() );

		wp_set_current_user( 0 );
		add_filter( 'wp_doing_cron', '__return_true' );
		wp_publish_post( $post_id );

		$this->assertSame( 'publish', get_post_status( $post_id ) );
		$this->assertSame( array( (string) $post_id ), $this->upserted_ids() );
	}

	public function test_logged_out_save_outside_cron_and_cli_is_not_upserted() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$this->markTestSkipped( 'WP_CLI is defined in this run.' );
		}

		wp_set_current_user( 0 );
		self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$this->assertSame( array(), $this->upserted_ids() );
	}

	public function test_unpublished_post_is_removed() {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ) );

		$this->assertSame( array( (string) $post_id ), $this->deletes );
	}

	public function test_private_post_is_removed() {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		wp_update_post( array( 'ID' => $post_id, 'post_status' => 'private' ) );

		$this->assertSame( array( (string) $post_id ), $this->deletes );
	}

	public function test_trashed_post_is_removed() {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		wp_trash_post( $post_id );

		$this->assertSame( array( (string) $post_id ), $this->deletes );
	}

	public function test_force_deleted_post_is_removed() {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		wp_delete_post( $post_id, true );

		$this->assertSame( array( (string) $post_id ), $this->deletes );
	}

	public function test_republished_post_is_upserted_again_after_removal() {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ) );
		wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );

		$this->assertSame( array( (string) $post_id ), $this->deletes );
		$this->assertCount( 2, $this->upserted_ids() );
	}

	public function test_draft_to_pending_does_not_delete() {
		$post_id = self::factory()->post->create( array( 'post_status' => 'draft' ) );

		wp_update_post( array( 'ID' => $post_id, 'post_status' => 'pending' ) );

		$this->assertSame( array(), $this->deletes );
	}

	public function test_revisions_and_autosaves_are_skipped() {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$this->reset_request_state();
		$this->upserts = array();

		wp_update_post( array( 'ID' => $post_id, 'post_content' => 'Second version' ) );
		$this->upserts = array();

		$revision_id = wp_save_post_revision( $post_id );
		$autosave_id = _wp_put_post_revision(
			array(
				'ID'           => $post_id,
				'post_title'   => 'Autosaved title',
				'post_content' => 'Autosaved content',
			),
			true
		);

		$this->assertSame( array(), $this->upserted_ids() );
		$this->assertSame( array(), $this->deletes );

		// Direct calls with revision objects are rejected by the post type guard too.
		foreach ( array_filter( array( $revision_id, $autosave_id ) ) as $id ) {
			$revision = get_post( $id );
			PostType::get_instance()->upsert_post_data( $id, $revision, false, null );
		}
		$this->assertSame( array(), $this->upserted_ids() );
	}

	public function test_nothing_happens_when_post_is_not_syncable() {
		add_filter(
			'blazecommerce/collection/syncable_post_types',
			function () {
				return array( 'page' );
			}
		);

		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		wp_trash_post( $post_id );

		$this->assertSame( array(), $this->upserted_ids() );
		$this->assertSame( array(), $this->deletes );
	}

	public function test_nothing_happens_when_system_is_disabled() {
		update_option( 'wooless_general_settings_options', array( 'enable_system' => 0 ) );

		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		wp_trash_post( $post_id );

		$this->assertSame( array(), $this->upserted_ids() );
		$this->assertSame( array(), $this->deletes );
	}

	public function test_pages_keep_their_existing_behaviour() {
		$page_id = self::factory()->post->create( array( 'post_type' => 'page', 'post_status' => 'publish' ) );

		$page_upserts = array_values(
			array_filter(
				$this->upserts,
				function ( $document ) {
					return 'page' === $document['type'];
				}
			)
		);
		$this->assertCount( 1, $page_upserts );
		$this->assertSame( (string) $page_id, $page_upserts[0]['id'] );

		wp_trash_post( $page_id );
		wp_delete_post( $page_id, true );
		$this->assertSame( array(), $this->deletes );
	}
}
