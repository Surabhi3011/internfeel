<?php
/**
 * Review queue decisions and the moderation log (architecture.md 4.5, 5.1 and 5.8).
 *
 * @package InternFeel
 */

use InternFeel\Auth\Roles;
use InternFeel\Content\Content;
use InternFeel\Fields\Fields;
use InternFeel\Moderation\Decisions;
use InternFeel\Moderation\Log;
use InternFeel\Moderation\Queue;
use InternFeel\Notify\Notify;
use InternFeel\Schema;
use InternFeel\Submit\Submit;

/**
 * @covers \InternFeel\Moderation\Decisions
 * @covers \InternFeel\Moderation\Log
 * @covers \InternFeel\Moderation\Moderation
 * @covers \InternFeel\Moderation\Queue
 */
class ModerationTest extends WP_UnitTestCase {

	private int $admin;
	private int $student;

	public function set_up(): void {
		parent::set_up();
		if ( ! registered_meta_key_exists( 'post', '_if_paid', 'post' ) ) {
			Fields::register_meta();
		}
		// The test framework drops tables and roles between tests, so build the schema again.
		update_option( Schema::OPTION, 0 );
		Schema::upgrade();

		$this->admin   = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->student = self::factory()->user->create( array( 'role' => Roles::STUDENT ) );
		wp_set_current_user( $this->admin );
	}

	private function submission( string $status = 'pending' ): int {
		$id = self::factory()->post->create(
			array(
				'post_status' => $status,
				'post_author' => $this->student,
				'post_title'  => 'Summer Research Fellowship at Northwind Labs',
			)
		);
		update_post_meta( $id, '_if_verification_status', 'not_submitted' );

		return $id;
	}

	private function decide( string $action, int $id, array $body = array() ): WP_REST_Response {
		$request = new WP_REST_Request( 'POST', "/internfeel/v1/moderation/{$action}" );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'id' => $id ) + $body ) );

		return rest_do_request( $request );
	}

	private function types( int $user_id ): array {
		return array_column( Notify::all( $user_id ), 'type' );
	}

	public function test_approving_publishes_logs_and_notifies_the_student_once(): void {
		$id = $this->submission();

		$this->assertSame( 200, $this->decide( 'approve', $id )->get_status() );
		$this->assertSame( 'publish', get_post_status( $id ) );
		$this->assertSame( array( 'submission_approved' ), $this->types( $this->student ) );

		$log = Log::for_target( 'experience', $id );
		$this->assertCount( 1, $log );
		$this->assertSame( 'approve', $log[0]['action'] );
		$this->assertSame( $this->admin, $log[0]['actor_id'] );

		// A second approval is refused, so the student is not notified twice.
		$this->assertSame( 409, $this->decide( 'approve', $id )->get_status() );
		$this->assertCount( 1, Notify::all( $this->student ) );
	}

	public function test_approving_tells_the_referrer(): void {
		$referrer = self::factory()->user->create( array( 'role' => Roles::STUDENT ) );
		$id       = $this->submission();
		update_post_meta( $id, '_if_referrer_user_id', $referrer );

		$this->decide( 'approve', $id );

		$this->assertSame( array( 'invite_published' ), $this->types( $referrer ) );
	}

	public function test_requesting_changes_needs_a_note_and_returns_the_draft(): void {
		$id = $this->submission();

		$this->assertSame( 400, $this->decide( 'request_changes', $id )->get_status() );
		$this->assertSame( 'pending', get_post_status( $id ) );

		$this->assertSame( 200, $this->decide( 'request_changes', $id, array( 'note' => 'Add your dates.' ) )->get_status() );
		$this->assertSame( 'draft', get_post_status( $id ) );
		$this->assertSame( 'Add your dates.', get_post_meta( $id, '_if_moderation_note', true ) );
		$this->assertSame( array( 'changes_requested' ), $this->types( $this->student ) );
		$this->assertStringContainsString( 'Add your dates.', Notify::all( $this->student )[0]['message'] );

		// Resubmitting clears the note and records the time for the 24-hour highlight.
		Submit::submit( $id );
		$this->assertSame( 'pending', get_post_status( $id ) );
		$this->assertSame( '', get_post_meta( $id, '_if_moderation_note', true ) );
		$this->assertNotSame( '', get_post_meta( $id, '_if_submitted_at', true ) );
	}

	public function test_rejecting_keeps_the_post_out_of_public_view(): void {
		$id = $this->submission();

		$this->decide( 'reject', $id, array( 'note' => 'Not an internship.' ) );

		$this->assertSame( 'rejected', get_post_status( $id ) );
		$this->assertSame( array( 'submission_rejected' ), $this->types( $this->student ) );
		$this->assertFalse( get_post_status_object( 'rejected' )->public );
	}

	public function test_imported_experiences_cannot_be_sent_back(): void {
		$id = $this->submission();
		update_post_meta( $id, '_if_legacy_wp_id', 1234 );

		$this->assertSame( 409, $this->decide( 'request_changes', $id, array( 'note' => 'x' ) )->get_status() );
		$this->assertSame( 'pending', get_post_status( $id ) );
	}

	public function test_approving_an_imported_experience_keeps_its_slug_and_date(): void {
		$id = self::factory()->post->create(
			array(
				'post_status'   => 'pending',
				'post_name'     => 'my-old-internship',
				'post_date'     => '2016-07-01 10:00:00',
				'post_date_gmt' => '2016-07-01 04:30:00',
			)
		);
		update_post_meta( $id, '_if_legacy_wp_id', 99 );
		global $wpdb;
		$wpdb->update( $wpdb->posts, array( 'post_name' => 'my-old-internship' ), array( 'ID' => $id ) );
		clean_post_cache( $id );

		$this->decide( 'approve', $id );
		$post = get_post( $id );

		$this->assertSame( 'publish', $post->post_status );
		$this->assertSame( 'my-old-internship', $post->post_name );
		$this->assertSame( '2016-07-01 10:00:00', $post->post_date );
	}

	public function test_proof_decisions(): void {
		$id = $this->submission();
		$this->assertSame( 409, $this->decide( 'verify', $id )->get_status(), 'No proof uploaded yet.' );

		update_post_meta( $id, '_if_verification_status', 'pending' );
		$this->assertSame( 400, $this->decide( 'reject_proof', $id )->get_status(), 'A reason is required.' );
		$this->decide( 'reject_proof', $id, array( 'note' => 'The name does not match.' ) );
		$this->assertSame( 'rejected', get_post_meta( $id, '_if_verification_status', true ) );
		$this->assertSame( 'The name does not match.', get_post_meta( $id, '_if_verification_note', true ) );

		update_post_meta( $id, '_if_verification_status', 'pending' );
		$this->decide( 'verify', $id );
		$this->assertSame( 'verified', get_post_meta( $id, '_if_verification_status', true ) );
		$this->assertSame( (string) $this->admin, get_post_meta( $id, '_if_verified_by', true ) );
		$this->assertSame( '', get_post_meta( $id, '_if_verification_note', true ) );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', get_post_meta( $id, '_if_verified_at', true ) );
		$this->assertSame( array( 'proof_verified', 'proof_rejected' ), $this->types( $this->student ) );
	}

	public function test_published_and_verified_counts_once_whichever_comes_second(): void {
		$fired = 0;
		add_action(
			'if_experience_verified',
			static function () use ( &$fired ): void {
				++$fired;
			}
		);

		$id = $this->submission();
		update_post_meta( $id, '_if_verification_status', 'pending' );
		$this->decide( 'verify', $id );
		$this->assertSame( 0, $fired, 'Not published yet.' );

		$this->decide( 'approve', $id );
		$this->assertSame( 1, $fired );
		$this->assertSame( '1', get_user_meta( $this->student, '_if_verified_count', true ) );

		Decisions::maybe_count_verified( $id );
		$this->assertSame( 1, $fired, 'Counted once.' );
	}

	public function test_a_suggestion_becomes_a_master_list_entry(): void {
		$id = $this->submission();
		update_post_meta( $id, '_if_organisation_suggestion', 'Brand New Startup' );

		$this->assertSame( 200, $this->decide( 'add_suggestion', $id, array( 'field' => 'organisation', 'name' => 'Brand New Startup Pvt Ltd' ) )->get_status() );

		$term = get_term_by( 'name', 'Brand New Startup Pvt Ltd', Content::TAXONOMY );
		$this->assertInstanceOf( WP_Term::class, $term );
		$this->assertSame( 'organisation', get_term_meta( $term->term_id, '_if_kind', true ) );
		$this->assertSame( (string) $term->term_id, get_post_meta( $id, '_if_organisation_term', true ) );
		$this->assertSame( '', get_post_meta( $id, '_if_organisation_suggestion', true ) );
		$this->assertTrue( has_term( $term->term_id, Content::TAXONOMY, $id ) );

		// A programme needs its organisation on the list first.
		$other = $this->submission();
		update_post_meta( $other, '_if_programme_suggestion', 'Winter School' );
		$this->assertSame( 409, $this->decide( 'add_suggestion', $other, array( 'field' => 'programme' ) )->get_status() );
	}

	public function test_students_cannot_moderate(): void {
		$id = $this->submission();
		wp_set_current_user( $this->student );

		$this->assertSame( 403, $this->decide( 'approve', $id )->get_status() );
		$this->assertSame( 'pending', get_post_status( $id ) );
	}

	public function test_the_queue_separates_new_and_imported_submissions(): void {
		$new      = $this->submission();
		$imported = $this->submission();
		update_post_meta( $imported, '_if_legacy_wp_id', 7 );
		$proof = $this->submission( 'publish' );
		update_post_meta( $proof, '_if_verification_status', 'pending' );

		$ids = static fn( string $tab ): array => wp_list_pluck( ( new WP_Query( Queue::query_args( $tab ) ) )->posts, 'ID' );

		$this->assertSame( array( $new ), $ids( 'new' ) );
		$this->assertSame( array( $imported ), $ids( 'imported' ) );
		$this->assertSame( array( $proof ), $ids( 'proofs' ) );
	}
}
