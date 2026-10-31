<?php
/**
 * Search, filters and card data (plan.md 5.2, architecture.md 5.7).
 *
 * @package InternFeel
 */

use InternFeel\Auth\Roles;
use InternFeel\Content\Content;
use InternFeel\Fields\Fields;
use InternFeel\Schema;
use InternFeel\Search\Cards;
use InternFeel\Search\Search;

/**
 * @covers \InternFeel\Search\Search
 * @covers \InternFeel\Search\Cards
 */
class SearchTest extends WP_UnitTestCase {

	private int $student;

	public function set_up(): void {
		parent::set_up();
		if ( ! registered_meta_key_exists( 'post', '_if_paid', 'post' ) ) {
			Fields::register_meta();
		}
		update_option( Schema::OPTION, 0 );
		Schema::upgrade();
		$this->student = self::factory()->user->create(
			array(
				'role'         => Roles::STUDENT,
				'display_name' => 'Secret Student',
			)
		);
		Search::forget_counts();
	}

	private function experience( array $meta, string $status = 'publish', string $title = 'An internship' ): int {
		$id = self::factory()->post->create(
			array(
				'post_status' => $status,
				'post_author' => $this->student,
				'post_title'  => $title,
			)
		);
		foreach ( $meta as $key => $value ) {
			update_post_meta( $id, '_if_' . $key, $value );
		}

		return $id;
	}

	private function ids( array $filters ): array {
		return array_column( Search::run( Search::filters( $filters ) )['cards'], 'id' );
	}

	public function test_filters_are_cleaned(): void {
		$filters = Search::filters(
			array(
				'type'     => 'pirate',
				'pay'      => 'lots',
				'year'     => '1890',
				'verified' => '1',
				'q'        => '<b>lab</b>',
			)
		);

		$this->assertSame( '', $filters['type'] );
		$this->assertSame( '', $filters['pay'] );
		$this->assertSame( 0, $filters['year'] );
		$this->assertTrue( $filters['verified'] );
		$this->assertSame( 'lab', $filters['q'] );
	}

	public function test_type_pay_location_year_and_verified_filters(): void {
		$research = $this->experience(
			array(
				'type'                => 'research',
				'paid'                => '1',
				'stipend_amount_inr'  => 12000,
				'city'                => 'Bengaluru',
				'country'             => 'India',
				'start_month'         => '2025-05',
				'verification_status' => 'verified',
			)
		);
		$ngo      = $this->experience(
			array(
				'type'    => 'ngo',
				'paid'    => '0',
				'country' => 'Kenya',
			)
		);
		$legacy   = $this->experience(
			array(
				'type'                  => 'research',
				'legacy_published_year' => 2016,
			)
		);
		$this->experience( array( 'type' => 'research' ), 'pending' );

		$this->assertEqualsCanonicalizing( array( $research, $legacy ), $this->ids( array( 'type' => 'research' ) ) );
		$this->assertSame( array( $ngo ), $this->ids( array( 'pay' => 'unpaid' ) ) );
		$this->assertSame( array( $research ), $this->ids( array( 'pay' => '10to25' ) ) );
		$this->assertSame( array(), $this->ids( array( 'pay' => 'over25' ) ) );
		$this->assertSame( array( $research ), $this->ids( array( 'location' => 'India' ) ) );
		$this->assertSame( array( $research ), $this->ids( array( 'year' => '2025' ) ) );
		$this->assertSame( array( $legacy ), $this->ids( array( 'year' => '2016' ) ) );
		$this->assertSame( array( $research ), $this->ids( array( 'verified' => '1' ) ) );
	}

	public function test_field_filter_uses_the_master_list(): void {
		$term = self::factory()->term->create(
			array(
				'taxonomy' => Content::TAXONOMY,
				'name'     => 'Ecology',
			)
		);
		$id   = $this->experience( array() );
		$this->experience( array() );
		wp_set_object_terms( $id, array( $term ), Content::TAXONOMY );

		$this->assertSame( array( $id ), $this->ids( array( 'field' => (string) $term ) ) );
	}

	public function test_cards_show_bands_and_never_the_author(): void {
		$id   = $this->experience(
			array(
				'paid'                => '1',
				'stipend_amount_inr'  => 30000,
				'duration_weeks'      => 8,
				'start_month'         => '2025-05',
				'type'                => 'industry',
				'is_anonymous'        => true,
				'rating_overall'      => 4,
				'referrer_user_id'    => 9,
				'verification_status' => 'verified',
			)
		);
		$card = Cards::data( get_post( $id ) );

		$this->assertSame( 'Paid · above ₹25k', $card['pay'] );
		$this->assertSame( '2025 · 8 weeks', $card['when'] );
		$this->assertSame( 'Industry', $card['type'] );
		$this->assertTrue( $card['verified'] );
		$this->assertTrue( $card['invited'] );

		$json = (string) wp_json_encode( $card );
		$this->assertStringNotContainsString( 'Secret Student', $json );
		$this->assertStringNotContainsString( (string) $this->student, wp_json_encode( array_diff_key( $card, array( 'id' => 1 ) ) ) );
	}

	public function test_the_rest_route_is_public_and_lists_only_published(): void {
		$this->experience( array( 'type' => 'virtual' ), 'publish', 'Visible one' );
		$this->experience( array( 'type' => 'virtual' ), 'draft', 'Hidden draft' );
		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'GET', '/internfeel/v1/experiences' );
		$request->set_query_params( array( 'type' => 'virtual' ) );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 1, $response->get_data()['total'] );
		$this->assertSame( 'Visible one', $response->get_data()['cards'][0]['title'] );
	}

	public function test_sorting_by_views_keeps_experiences_without_a_count(): void {
		$popular = $this->experience( array( 'view_count' => 50 ) );
		$quiet   = $this->experience( array( 'view_count' => 3 ) );
		$new     = $this->experience( array() );

		$this->assertSame( array( $popular, $quiet, $new ), $this->ids( array( 'sort' => 'views' ) ) );
	}

	public function test_the_page_number_is_read_from_pg_not_page(): void {
		$this->assertSame( 3, Search::filters( array( 'pg' => '3' ) )['page'] );
		$this->assertSame( 1, Search::filters( array( 'page' => '3' ) )['page'], 'WordPress owns "page".' );
	}

	public function test_the_authors_comments_on_an_anonymous_experience_stay_anonymous(): void {
		$id      = $this->experience( array( 'is_anonymous' => true ) );
		$comment = self::factory()->comment->create(
			array(
				'comment_post_ID' => $id,
				'user_id'         => $this->student,
				'comment_author'  => 'Secret Student',
			)
		);

		$this->assertSame( 'Anonymous', get_comment_author( $comment ) );

		$data = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/comments/' . $comment ) )->get_data();
		$this->assertSame( 0, $data['author'] );
		$this->assertSame( 'Anonymous', $data['author_name'] );
		$this->assertSame( array(), $data['author_avatar_urls'] );
	}

	public function test_counts_cover_experiences_verification_and_colleges(): void {
		update_user_meta( $this->student, '_if_college', 'IIT Bombay' );
		$this->experience( array( 'verification_status' => 'verified' ) );
		$this->experience( array() );
		Search::forget_counts();

		$counts = Search::counts();
		$this->assertSame( 2, $counts['experiences'] );
		$this->assertSame( 1, $counts['verified'] );
		$this->assertSame( 1, $counts['colleges'] );
	}
}
