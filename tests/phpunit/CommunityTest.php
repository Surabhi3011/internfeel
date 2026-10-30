<?php
/**
 * Comments (edit once, notifications), reports and listicles (plan.md 5.1 and 5.2, architecture.md 5.8).
 *
 * @package InternFeel
 */

use InternFeel\Auth\Roles;
use InternFeel\Comments\Comments;
use InternFeel\Fields\Fields;
use InternFeel\Lists\Lists;
use InternFeel\Notify\Notify;
use InternFeel\Reports\Reports;
use InternFeel\Schema;
use InternFeel\Search\Search;

/**
 * @covers \InternFeel\Comments\Comments
 * @covers \InternFeel\Reports\Reports
 * @covers \InternFeel\Lists\Lists
 */
class CommunityTest extends WP_UnitTestCase {

	private int $author;
	private int $reader;
	private int $post;

	public function set_up(): void {
		parent::set_up();
		if ( ! registered_meta_key_exists( 'post', '_if_paid', 'post' ) ) {
			Fields::register_meta();
		}
		update_option( Schema::OPTION, 0 );
		Schema::upgrade();
		$this->author = self::factory()->user->create( array( 'role' => Roles::STUDENT ) );
		$this->reader = self::factory()->user->create( array( 'role' => Roles::STUDENT ) );
		$this->post   = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_author' => $this->author,
				'post_title'  => 'A lab internship',
			)
		);
	}

	private function comment( int $user_id, string $text = 'Helpful, thanks.' ): int {
		return self::factory()->comment->create(
			array(
				'comment_post_ID'  => $this->post,
				'user_id'          => $user_id,
				'comment_content'  => $text,
				'comment_approved' => 1,
			)
		);
	}

	private function edit( int $id, string $text ): WP_REST_Response {
		$request = new WP_REST_Request( 'PATCH', "/internfeel/v1/comments/{$id}" );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'content' => $text ) ) );

		return rest_do_request( $request );
	}

	public function test_a_new_comment_notifies_the_author_once_but_not_their_own(): void {
		$id = $this->comment( $this->reader );
		$this->comment( $this->author, 'Thanks for reading!' );

		$this->assertSame( array( 'new_comment' ), array_column( Notify::all( $this->author ), 'type' ) );

		// Holding and approving it again does not notify twice.
		wp_set_comment_status( $id, 'hold' );
		wp_set_comment_status( $id, 'approve' );
		$this->assertCount( 1, Notify::all( $this->author ) );
	}

	public function test_a_comment_can_be_edited_once_by_its_author_only(): void {
		$id = $this->comment( $this->reader );

		wp_set_current_user( $this->author );
		$this->assertSame( 404, $this->edit( $id, 'Hijacked' )->get_status() );

		wp_set_current_user( $this->reader );
		$this->assertSame( 200, $this->edit( $id, 'Helpful, thanks. Updated.' )->get_status() );
		$this->assertSame( 'Helpful, thanks. Updated.', get_comment( $id )->comment_content );
		$this->assertTrue( Comments::edited( $id ) );
		$this->assertNotSame( '', get_comment_meta( $id, '_if_edited_at', true ) );

		$this->assertSame( 409, $this->edit( $id, 'Second edit' )->get_status() );
	}

	public function test_readers_report_once_and_three_reports_hide_an_experience(): void {
		$this->assertTrue( Reports::report( $this->reader, 'experience', $this->post, 'spam' ) );
		$this->assertWPError( Reports::report( $this->reader, 'experience', $this->post, 'spam' ), 'Once per reader.' );
		$this->assertWPError( Reports::report( $this->author, 'experience', $this->post, 'spam' ), 'Not your own.' );
		$this->assertWPError( Reports::report( $this->reader, 'experience', $this->post + 999, 'spam' ) );

		$filters = Search::filters( array() );
		$this->assertContains( $this->post, array_column( Search::run( $filters )['cards'], 'id' ) );

		foreach ( array( 1, 2 ) as $n ) {
			$other = self::factory()->user->create( array( 'role' => Roles::STUDENT ) );
			Reports::report( $other, 'experience', $this->post, 'false' );
		}

		$this->assertSame( 3, Reports::count( 'experience', $this->post ) );
		$this->assertTrue( Reports::is_flagged( 'experience', $this->post ) );
		$this->assertNotContains( $this->post, array_column( Search::run( $filters )['cards'], 'id' ), 'Hidden from listings.' );

		Reports::dismiss( 'experience', $this->post, 1 );
		$this->assertFalse( Reports::is_flagged( 'experience', $this->post ) );
		$this->assertContains( $this->post, array_column( Search::run( $filters )['cards'], 'id' ) );
	}

	public function test_three_reports_hold_a_comment_and_removal_trashes_it(): void {
		$id = $this->comment( $this->author );
		foreach ( array( 1, 2, 3 ) as $n ) {
			Reports::report( self::factory()->user->create(), 'comment', $id, 'abuse' );
		}

		$this->assertSame( '0', get_comment( $id )->comment_approved );

		Reports::remove( 'comment', $id, 1, 'Abusive' );
		$this->assertSame( 'trash', get_comment( $id )->comment_approved );
	}

	public function test_removing_a_reported_experience_moves_it_to_removed(): void {
		Reports::remove( 'experience', $this->post, 1, 'Copied text' );

		$this->assertSame( 'removed', get_post_status( $this->post ) );
		$this->assertSame( 'Copied text', get_post_meta( $this->post, '_if_moderation_note', true ) );
	}

	public function test_the_report_route_needs_a_signed_in_reader(): void {
		$request = new WP_REST_Request( 'POST', '/internfeel/v1/report' );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'type'   => 'experience',
					'id'     => $this->post,
					'reason' => 'spam',
				)
			)
		);

		$this->assertSame( 401, rest_do_request( $request )->get_status() );

		wp_set_current_user( $this->reader );
		$this->assertSame( 201, rest_do_request( $request )->get_status() );
	}

	public function test_listicles_keep_order_and_show_only_published_experiences(): void {
		$second = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$draft  = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		$page   = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$list   = self::factory()->post->create( array( 'post_type' => 'if_list' ) );

		$ids = Lists::parse( implode( "\n", array( (string) $second, get_permalink( $this->post ), (string) $draft, (string) $page, (string) $second ) ) );
		$this->assertSame( array( $second, $this->post, $draft ), $ids, 'In order, experiences only, no repeats.' );

		update_post_meta( $list, Lists::ITEMS, $ids );
		$this->assertSame( array( $second, $this->post ), wp_list_pluck( Lists::posts( $list ), 'ID' ) );
	}
}
