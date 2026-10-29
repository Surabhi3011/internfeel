<?php
/**
 * Submission form and proof upload (plan.md 5.1, architecture.md 6.2).
 *
 * @package InternFeel
 */

use InternFeel\Auth\Roles;
use InternFeel\Content\Content;
use InternFeel\Fields\Fields;
use InternFeel\Submit\Form;
use InternFeel\Verify\Proofs;

/**
 * @covers \InternFeel\Submit\Submit
 * @covers \InternFeel\Submit\Form
 * @covers \InternFeel\Verify\Proofs
 * @covers \InternFeel\Verify\Verify
 */
class SubmitTest extends WP_UnitTestCase {

	private int $student;

	public function set_up(): void {
		parent::set_up();
		if ( ! registered_meta_key_exists( 'post', '_if_paid', 'post' ) ) {
			Fields::register_meta();
		}
		$this->student = self::factory()->user->create( array( 'role' => Roles::STUDENT ) );
		wp_set_current_user( $this->student );
	}

	private static function complete(): array {
		return array(
			'organisation'         => 'Northwind Labs',
			'programme'            => 'Summer Research Fellowship',
			'type'                 => 'research',
			'field'                => 'Electronics',
			'mode'                 => 'on-site',
			'city'                 => 'Bengaluru',
			'country'              => 'India',
			'start_month'          => '2025-05',
			'duration_weeks'       => 8,
			'paid'                 => '0',
			'selection_difficulty' => 4,
			'rating_overall'       => 5,
			'rating_learning'      => 5,
			'rating_mentorship'    => 4,
			'rating_culture'       => 4,
			'story'                => array(
				'q1' => 'Second-year electronics student.',
				'q2' => 'Applied through the notice board.',
				'q3' => 'Two interviews.',
				'q4' => "Designed a sensor board.\n\nWrote its firmware.",
			),
		);
	}

	private function call( string $method, string $route, array $body = array() ): WP_REST_Response {
		$request = new WP_REST_Request( $method, '/internfeel/v1/' . $route );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $body ) );

		return rest_do_request( $request );
	}

	private function draft( array $body = array() ): int {
		$response = $this->call( 'POST', 'experiences', $body );
		$this->assertSame( 201, $response->get_status() );

		return (int) $response->get_data()['id'];
	}

	public function test_a_draft_belongs_to_the_student_and_saves_partial_answers(): void {
		$id = $this->draft( array( 'city' => 'Pune' ) );

		$this->assertSame( 'draft', get_post_status( $id ) );
		$this->assertSame( $this->student, (int) get_post_field( 'post_author', $id ) );
		$this->assertSame( 'Pune', Form::values( $id )['city'] );
		$this->assertSame( 'not_submitted', get_post_meta( $id, '_if_verification_status', true ) );
	}

	public function test_signed_out_visitors_cannot_start_a_draft(): void {
		wp_set_current_user( 0 );

		$this->assertSame( 401, $this->call( 'POST', 'experiences' )->get_status() );
	}

	public function test_another_student_cannot_read_or_change_a_draft(): void {
		$id = $this->draft();
		wp_set_current_user( self::factory()->user->create( array( 'role' => Roles::STUDENT ) ) );

		$this->assertSame( 404, $this->call( 'GET', "experiences/{$id}" )->get_status() );
		$this->assertSame( 404, $this->call( 'PATCH', "experiences/{$id}", array( 'city' => 'Hacked' ) )->get_status() );
	}

	public function test_unpaid_is_kept_apart_from_unanswered(): void {
		$id = $this->draft();
		$this->assertNull( Form::values( $id )['paid'] );

		$this->call( 'PATCH', "experiences/{$id}", array( 'paid' => '0' ) );
		$this->assertFalse( Form::values( $id )['paid'] );
	}

	public function test_submitting_reports_the_first_missing_field_and_its_step(): void {
		$id   = $this->draft( array_diff_key( self::complete(), array( 'duration_weeks' => true ) ) );
		$data = $this->call( 'PATCH', "experiences/{$id}", array( 'submit' => true, 'guidelines' => true ) )->get_data();

		$this->assertSame( 'duration_weeks', $data['data']['field'] );
		$this->assertSame( 1, $data['data']['step'] );
		$this->assertSame( 'draft', get_post_status( $id ) );
	}

	public function test_the_first_four_questions_are_required(): void {
		$body = self::complete();
		unset( $body['story']['q4'] );
		$id   = $this->draft( $body );
		$data = $this->call( 'PATCH', "experiences/{$id}", array( 'submit' => true, 'guidelines' => true ) )->get_data();

		$this->assertSame( 'story[q4]', $data['data']['field'] );
		$this->assertSame( 2, $data['data']['step'] );
	}

	public function test_submitting_needs_the_guidelines_box(): void {
		$id   = $this->draft( self::complete() );
		$data = $this->call( 'PATCH', "experiences/{$id}", array( 'submit' => true ) )->get_data();

		$this->assertSame( 'guidelines', $data['data']['field'] );
	}

	public function test_a_complete_draft_goes_to_review_with_its_title_and_story(): void {
		update_user_meta( $this->student, '_if_referred_by_user_id', 77 );
		$id       = $this->draft( self::complete() );
		$response = $this->call( 'PATCH', "experiences/{$id}", array( 'submit' => true, 'guidelines' => true ) );
		$post     = get_post( $id );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'pending', $post->post_status );
		$this->assertSame( 'Summer Research Fellowship at Northwind Labs', $post->post_title );
		$this->assertStringContainsString( '<h2>What work did you do during the internship?</h2>', $post->post_content );
		$this->assertStringContainsString( '<p>Wrote its firmware.</p>', $post->post_content );
		$this->assertSame( '77', get_post_meta( $id, '_if_referrer_user_id', true ) );

		// Once submitted, the student can no longer change it through the form.
		$this->assertSame( 409, $this->call( 'PATCH', "experiences/{$id}", array( 'city' => 'Pune' ) )->get_status() );
	}

	public function test_known_organisations_link_to_the_master_list_and_new_ones_become_suggestions(): void {
		$term = self::factory()->term->create(
			array(
				'taxonomy' => Content::TAXONOMY,
				'name'     => 'Indian Institute of Science',
			)
		);

		$known = $this->draft( array( 'organisation' => 'Indian Institute of Science' ) );
		$this->assertSame( (string) $term, get_post_meta( $known, '_if_organisation_term', true ) );
		$this->assertSame( '', get_post_meta( $known, '_if_organisation_suggestion', true ) );

		$new = $this->draft( array( 'organisation' => 'Brand New Startup' ) );
		$this->assertSame( '0', get_post_meta( $new, '_if_organisation_term', true ) );
		$this->assertSame( 'Brand New Startup', get_post_meta( $new, '_if_organisation_suggestion', true ) );
		$this->assertFalse( term_exists( 'Brand New Startup', Content::TAXONOMY ), 'Students never create master-list entries.' );
	}

	public function test_proof_accepts_real_images_and_marks_the_experience_pending(): void {
		$id   = $this->draft();
		$file = $this->temp_file( base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==' ) );

		$proof = Proofs::store( $id, $file, 'offer letter.png' );

		$this->assertIsArray( $proof );
		$this->assertSame( 'image/png', $proof['type'] );
		$this->assertSame( 'pending', get_post_meta( $id, '_if_verification_status', true ) );
		$this->assertNotNull( Proofs::path( $id, $proof['file'] ) );
	}

	public function test_proof_rejects_files_that_only_claim_to_be_images(): void {
		$id = $this->draft();

		$this->assertWPError( Proofs::store( $id, $this->temp_file( '<?php echo "not an image";' ), 'shell.png' ) );
		$this->assertSame( array(), Proofs::all( $id ) );
	}

	public function test_at_most_three_proof_files(): void {
		$id  = $this->draft();
		$pdf = "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";
		for ( $i = 1; $i <= 3; $i++ ) {
			$this->assertIsArray( Proofs::store( $id, $this->temp_file( $pdf ), "proof-{$i}.pdf" ) );
		}

		$this->assertWPError( Proofs::store( $id, $this->temp_file( $pdf ), 'proof-4.pdf' ) );
	}

	public function test_only_the_author_and_admins_can_open_proof(): void {
		$id = $this->draft();

		$this->assertTrue( Proofs::can_view( $id, $this->student ) );
		$this->assertTrue( Proofs::can_view( $id, self::factory()->user->create( array( 'role' => 'administrator' ) ) ) );
		$this->assertFalse( Proofs::can_view( $id, self::factory()->user->create( array( 'role' => Roles::STUDENT ) ) ) );
		$this->assertFalse( Proofs::can_view( $id, 0 ) );
	}

	public function test_the_download_route_hides_proof_from_other_users(): void {
		$id    = $this->draft();
		$proof = Proofs::store( $id, $this->temp_file( "%PDF-1.4\n%%EOF\n" ), 'letter.pdf' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => Roles::STUDENT ) ) );

		$this->assertSame( 404, rest_do_request( new WP_REST_Request( 'GET', "/internfeel/v1/proof/{$id}/{$proof['file']}" ) )->get_status() );
	}

	private function temp_file( string $contents ): string {
		$path = wp_tempnam( 'if-proof' );
		file_put_contents( $path, $contents );

		return $path;
	}
}
