<?php
/**
 * Certificates and the dashboard (plan.md 5.1, architecture.md 5.5).
 *
 * @package InternFeel
 */

use InternFeel\Auth\Roles;
use InternFeel\Certificates\Certificates;
use InternFeel\Certificates\Pdf;
use InternFeel\Dashboard\Dashboard;
use InternFeel\Fields\Fields;
use InternFeel\Moderation\Decisions;
use InternFeel\Schema;

/**
 * @covers \InternFeel\Certificates\Certificates
 * @covers \InternFeel\Certificates\Pdf
 * @covers \InternFeel\Dashboard\Dashboard
 * @covers \InternFeel\Submit\Submit::delete
 */
class CertificatesTest extends WP_UnitTestCase {

	private int $admin;
	private int $student;

	public function set_up(): void {
		parent::set_up();
		if ( ! registered_meta_key_exists( 'post', '_if_paid', 'post' ) ) {
			Fields::register_meta();
		}
		update_option( Schema::OPTION, 0 );
		Schema::upgrade();

		$this->admin   = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->student = self::factory()->user->create(
			array(
				'role'         => Roles::STUDENT,
				'display_name' => 'Rahul M.',
			)
		);
		update_user_meta( $this->student, '_if_full_name', 'Rahul Mehta' );
		wp_set_current_user( $this->admin );
	}

	private function published( array $meta = array() ): int {
		$id = self::factory()->post->create(
			array(
				'post_status' => 'pending',
				'post_author' => $this->student,
				'post_title'  => 'Summer Research Fellowship at Northwind Labs',
			)
		);
		foreach ( $meta as $key => $value ) {
			update_post_meta( $id, '_if_' . $key, $value );
		}
		Decisions::apply( 'approve', $id, $this->admin );

		return $id;
	}

	public function test_publishing_issues_a_numbered_certificate_once(): void {
		$first  = $this->published();
		$second = $this->published();
		$year   = gmdate( 'Y' );

		$this->assertMatchesRegularExpression( "/^IF-{$year}-\\d{5}$/", Certificates::number( $first ) );
		$this->assertSame( (int) substr( Certificates::number( $first ), -5 ) + 1, (int) substr( Certificates::number( $second ), -5 ) );

		// Issuing again (a republish) keeps the number.
		$number = Certificates::number( $first );
		Certificates::issue( $first );
		$this->assertSame( $number, Certificates::number( $first ) );
	}

	public function test_imported_experiences_get_no_certificate(): void {
		$id = self::factory()->post->create(
			array(
				'post_status' => 'pending',
				'post_author' => Roles::ensure_archive_user(),
			)
		);
		Decisions::apply( 'approve', $id, $this->admin );

		$this->assertSame( '', Certificates::number( $id ) );
	}

	public function test_verify_shows_valid_then_revoked_and_unknown_numbers(): void {
		$id     = $this->published();
		$number = Certificates::number( $id );

		$valid = Certificates::status( strtolower( $number ) );
		$this->assertSame( 'valid', $valid['state'] );
		$this->assertSame( 'Rahul Mehta', $valid['name'] );
		$this->assertSame( 'Summer Research Fellowship at Northwind Labs', $valid['title'] );

		wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => 'removed',
			)
		);
		$this->assertSame( 'revoked', Certificates::status( $number )['state'] );

		$this->assertSame( 'not_found', Certificates::status( 'IF-1999-00001' )['state'] );
		$this->assertSame( 'not_found', Certificates::status( "' OR 1=1 --" )['state'] );
	}

	public function test_verify_never_links_a_name_to_an_anonymous_experience(): void {
		$id     = $this->published( array( 'is_anonymous' => true ) );
		$status = Certificates::status( Certificates::number( $id ) );

		$this->assertSame( 'valid', $status['state'] );
		$this->assertSame( '', $status['title'] );
		$this->assertSame( '', $status['organisation'] );

		$response = rest_do_request( new WP_REST_Request( 'GET', '/internfeel/v1/verify/' . Certificates::number( $id ) ) );
		$this->assertStringNotContainsString( 'Northwind', (string) wp_json_encode( $response->get_data() ) );
	}

	public function test_only_the_author_and_admins_can_download(): void {
		$id      = $this->published();
		$request = new WP_REST_Request( 'GET', "/internfeel/v1/certificates/{$id}" );

		wp_set_current_user( self::factory()->user->create( array( 'role' => Roles::STUDENT ) ) );
		$this->assertWPError( Certificates::can_download( $request ) );

		wp_set_current_user( 0 );
		$this->assertWPError( Certificates::can_download( $request ) );

		wp_set_current_user( $this->student );
		$this->assertTrue( Certificates::can_download( $request ) );
	}

	public function test_the_pdf_prints_the_real_name_and_number(): void {
		$id   = $this->published( array( 'is_anonymous' => true ) );
		$data = Pdf::data( $id );

		$this->assertSame( 'Rahul Mehta', $data['name'] );
		$this->assertSame( Certificates::number( $id ), $data['number'] );
		$this->assertStringContainsString( '/verify/' . Certificates::number( $id ), $data['verify_url'] );

		$pdf = Pdf::render( $data );
		$this->assertIsString( $pdf );
		$this->assertStringStartsWith( '%PDF-', $pdf );
	}

	public function test_dashboard_counts_and_lists_the_students_own_work(): void {
		$live = $this->published();
		update_post_meta( $live, '_if_view_count', 40 );
		$other = $this->published();
		update_post_meta( $other, '_if_view_count', 2 );
		self::factory()->comment->create( array( 'comment_post_ID' => $live ) );
		$draft = self::factory()->post->create(
			array(
				'post_status' => 'draft',
				'post_author' => $this->student,
			)
		);
		self::factory()->post->create( array( 'post_status' => 'publish' ) ); // Someone else's.

		$stats = Dashboard::stats( $this->student );
		$this->assertSame( 2, $stats['published'] );
		$this->assertSame( 42, $stats['views'] );
		$this->assertSame( 1, $stats['comments'] );

		$items = Dashboard::experiences( $this->student );
		$this->assertEqualsCanonicalizing( array( $live, $other, $draft ), array_column( $items, 'id' ) );
		$by_id = array_column( $items, null, 'id' );
		$this->assertSame( 1, $by_id[ $draft ]['step'] );
		$this->assertNotSame( '', $by_id[ $live ]['certificate'] );
	}

	public function test_students_delete_only_their_own_drafts(): void {
		$draft = self::factory()->post->create(
			array(
				'post_status' => 'draft',
				'post_author' => $this->student,
			)
		);
		$live  = $this->published();

		wp_set_current_user( $this->student );
		$this->assertSame( 409, rest_do_request( new WP_REST_Request( 'DELETE', "/internfeel/v1/experiences/{$live}" ) )->get_status() );
		$this->assertSame( 200, rest_do_request( new WP_REST_Request( 'DELETE', "/internfeel/v1/experiences/{$draft}" ) )->get_status() );
		$this->assertNull( get_post( $draft ) );
		$this->assertSame( 'publish', get_post_status( $live ) );
	}
}
