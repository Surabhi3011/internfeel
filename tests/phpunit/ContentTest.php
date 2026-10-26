<?php
/**
 * Content types, meta and anonymous authors (architecture.md 4 and plan.md 5.1).
 *
 * @package InternFeel
 */

use InternFeel\Content\Content;
use InternFeel\Fields\Fields;

/**
 * @covers \InternFeel\Content\Content
 * @covers \InternFeel\Fields\Fields
 */
class ContentTest extends WP_UnitTestCase {

	/**
	 * The test framework unregisters all meta keys after each test, so register ours again.
	 */
	public function set_up(): void {
		parent::set_up();
		if ( ! registered_meta_key_exists( 'post', '_if_paid', 'post' ) ) {
			Fields::register_meta();
		}
	}

	public function test_posts_are_called_experiences(): void {
		$this->assertSame( 'Experiences', get_post_type_object( 'post' )->labels->name );
	}

	public function test_content_types_and_statuses_are_registered(): void {
		$this->assertTrue( taxonomy_exists( Content::TAXONOMY ) );
		$this->assertTrue( is_taxonomy_hierarchical( Content::TAXONOMY ) );
		$this->assertTrue( post_type_exists( Content::LIST_TYPE ) );
		$this->assertNotNull( get_post_status_object( 'rejected' ) );
		$this->assertNotNull( get_post_status_object( 'removed' ) );
	}

	public function test_only_admins_manage_the_master_list(): void {
		$taxonomy = get_taxonomy( Content::TAXONOMY );

		$this->assertSame( 'manage_options', $taxonomy->cap->manage_terms );
		$this->assertSame( 'manage_options', $taxonomy->cap->edit_terms );
	}

	public function test_meta_is_sanitised_to_its_type(): void {
		$post = self::factory()->post->create();

		update_post_meta( $post, '_if_rating_overall', 9 );
		update_post_meta( $post, '_if_type', 'not-a-type' );
		update_post_meta( $post, '_if_start_month', '2024-13' );
		update_post_meta( $post, '_if_story', '{"q1":"<script>x</script>It was great"}' );

		$this->assertSame( '5', get_post_meta( $post, '_if_rating_overall', true ) );
		$this->assertSame( '', get_post_meta( $post, '_if_type', true ) );
		$this->assertSame( '', get_post_meta( $post, '_if_start_month', true ) );
		$this->assertSame( array( 'q1' => 'It was great' ), json_decode( get_post_meta( $post, '_if_story', true ), true ) );
	}

	public function test_private_meta_never_appears_in_the_rest_api(): void {
		$post = self::factory()->post->create();
		update_post_meta( $post, '_if_proof_files', '["proof.pdf"]' );
		update_post_meta( $post, '_if_verification_note', 'Name matches the letter' );

		$meta = rest_do_request( new WP_REST_Request( 'GET', "/wp/v2/posts/{$post}" ) )->get_data()['meta'];

		$this->assertArrayHasKey( '_if_paid', $meta, 'REST meta keys: ' . implode( ', ', array_keys( (array) $meta ) ) );
		foreach ( Fields::private_post_keys() as $key ) {
			$this->assertArrayNotHasKey( $key, $meta );
		}
	}

	public function test_anonymous_authors_are_hidden_from_the_rest_api(): void {
		$author    = self::factory()->user->create( array( 'role' => 'if_student' ) );
		$anonymous = self::factory()->post->create( array( 'post_author' => $author ) );
		$named     = self::factory()->post->create( array( 'post_author' => $author ) );
		update_post_meta( $anonymous, '_if_is_anonymous', true );
		wp_set_current_user( 0 );

		$response = rest_do_request( new WP_REST_Request( 'GET', "/wp/v2/posts/{$anonymous}" ) );
		$this->assertSame( 0, $response->get_data()['author'] );
		$this->assertArrayNotHasKey( 'author', $response->get_links() );

		$this->assertSame( $author, rest_do_request( new WP_REST_Request( 'GET', "/wp/v2/posts/{$named}" ) )->get_data()['author'] );
	}

	public function test_visitors_cannot_list_posts_by_author(): void {
		$author = self::factory()->user->create( array( 'role' => 'if_student' ) );
		$theirs = self::factory()->post->create( array( 'post_author' => $author ) );
		$other  = self::factory()->post->create();
		update_post_meta( $theirs, '_if_is_anonymous', true );
		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'author', array( $author ) );
		$ids = wp_list_pluck( rest_do_request( $request )->get_data(), 'id' );

		$this->assertContains( $other, $ids, 'The author filter should be ignored for visitors.' );
	}
}
