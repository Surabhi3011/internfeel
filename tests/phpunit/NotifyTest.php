<?php
/**
 * Dashboard notifications (plan.md 5.1: "Each event creates exactly one notification; opening the
 * list marks them read").
 *
 * @package InternFeel
 */

use InternFeel\Notify\Notify;

/**
 * @covers \InternFeel\Notify\Notify
 */
class NotifyTest extends WP_UnitTestCase {

	private int $student;

	public function set_up(): void {
		parent::set_up();
		$this->student = self::factory()->user->create( array( 'role' => 'if_student' ) );
	}

	public function test_an_event_creates_one_unread_notification(): void {
		$this->assertTrue( Notify::add( $this->student, 'submission_approved', 'Your experience is live.', home_url( '/x/' ), 'approved:1' ) );
		$this->assertFalse( Notify::add( $this->student, 'submission_approved', 'Your experience is live.', home_url( '/x/' ), 'approved:1' ) );

		$this->assertCount( 1, Notify::all( $this->student ) );
		$this->assertSame( 1, Notify::unread_count( $this->student ) );
	}

	public function test_unknown_types_and_users_are_ignored(): void {
		$this->assertFalse( Notify::add( $this->student, 'not_a_type', 'x' ) );
		$this->assertFalse( Notify::add( 0, 'new_comment', 'x' ) );
		$this->assertSame( array(), Notify::all( $this->student ) );
	}

	public function test_messages_with_quotes_survive_storage(): void {
		Notify::add( $this->student, 'new_comment', 'Priya said "great tips" on your post' );

		$this->assertSame( 'Priya said "great tips" on your post', Notify::all( $this->student )[0]['message'] );
	}

	public function test_links_off_the_site_are_dropped(): void {
		Notify::add( $this->student, 'new_comment', 'x', 'https://elsewhere.example.com/phish' );

		$this->assertSame( '', Notify::all( $this->student )[0]['link'] );
	}

	public function test_only_the_latest_hundred_are_kept_newest_first(): void {
		for ( $i = 1; $i <= Notify::MAX + 5; $i++ ) {
			Notify::add( $this->student, 'new_comment', "Comment {$i}" );
		}

		$items = Notify::all( $this->student );
		$this->assertCount( Notify::MAX, $items );
		$this->assertSame( 'Comment ' . ( Notify::MAX + 5 ), $items[0]['message'] );
	}

	public function test_mark_read_marks_given_ids_or_everything(): void {
		Notify::add( $this->student, 'new_comment', 'one' );
		Notify::add( $this->student, 'new_comment', 'two' );
		$newest = Notify::all( $this->student )[0]['id'];

		Notify::mark_read( $this->student, array( $newest ) );
		$this->assertSame( 1, Notify::unread_count( $this->student ) );

		Notify::mark_read( $this->student );
		$this->assertSame( 0, Notify::unread_count( $this->student ) );
	}

	public function test_rest_routes_need_a_signed_in_user(): void {
		wp_set_current_user( 0 );

		$response = rest_do_request( new WP_REST_Request( 'GET', '/internfeel/v1/me/notifications' ) );
		$this->assertSame( 401, $response->get_status() );
	}

	public function test_rest_list_then_read(): void {
		Notify::add( $this->student, 'invite_used', 'Your invite was used.', '', 'invite:1' );
		wp_set_current_user( $this->student );

		$list = rest_do_request( new WP_REST_Request( 'GET', '/internfeel/v1/me/notifications' ) )->get_data();
		$this->assertSame( 1, $list['unread'] );
		$this->assertArrayNotHasKey( 'key', $list['items'][0] );

		$read = rest_do_request( new WP_REST_Request( 'POST', '/internfeel/v1/me/notifications/read' ) )->get_data();
		$this->assertSame( 0, $read['unread'] );
	}
}
