<?php
/**
 * The importer for the old site's export (development.md section 9). Uses fictional fixtures; the real
 * export never goes into the repo.
 *
 * @package InternFeel
 */

use InternFeel\Auth\Roles;
use InternFeel\Fields\Fields;
use InternFeel\Import\Cleaner;
use InternFeel\Import\Extract;
use InternFeel\Import\Importer;
use InternFeel\Import\Mapping;
use InternFeel\Import\Source;

/**
 * @covers \InternFeel\Import\Importer
 * @covers \InternFeel\Import\Mapping
 * @covers \InternFeel\Import\Cleaner
 * @covers \InternFeel\Import\Extract
 * @covers \InternFeel\Import\Source
 */
class ImportTest extends WP_UnitTestCase {

	private const FIXTURES = __DIR__ . '/fixtures/import';

	public function set_up(): void {
		parent::set_up();
		if ( ! registered_meta_key_exists( 'post', '_if_paid', 'post' ) ) {
			Fields::register_meta();
		}
		// The importer must work without a signed-in user (WP-CLI runs as nobody).
		wp_set_current_user( 0 );
	}

	private function source(): Source {
		$source = Source::from_dir( self::FIXTURES );
		$this->assertInstanceOf( Source::class, $source );
		return $source;
	}

	private static function by_slug( array $rows ): array {
		return array_column( $rows, null, 'slug' );
	}

	public function test_mapping_splits_the_old_categories(): void {
		$mapped = Mapping::for_post( array( 'interviews', 'national-internships', 'research-internships', 'engineering', 'computer-science' ) );

		$this->assertSame( 'experience', $mapped['kind'] );
		$this->assertSame( 'research', $mapped['type'] );
		$this->assertSame( 'Computer Science', $mapped['field'], 'A specific field beats the generic Engineering.' );
		$this->assertSame( 'India', $mapped['country'] );
	}

	public function test_guides_are_articles_but_blog_posts_with_an_internship_type_are_experiences(): void {
		$this->assertSame( 'article', Mapping::for_post( array( 'blog', 'internship-guide' ) )['kind'] );
		$this->assertSame( 'experience', Mapping::for_post( array( 'blog', 'virtual' ) )['kind'] );
	}

	public function test_student_names_come_only_from_clear_titles(): void {
		$this->assertSame( 'Asha Verma', Extract::student_name( 'Internship Experience | Asha Verma | Northwind Labs | Research internship' ) );
		$this->assertSame( 'Ravi Kumar', Extract::student_name( 'Virtual Internship at Bluepeak by Ravi Kumar' ) );
		$this->assertSame( '', Extract::student_name( 'Summer Internship at Bosch India Pvt. Ltd (Nashik)' ) );
	}

	public function test_ratings_out_of_ten_become_one_to_five(): void {
		$this->assertSame( 5, Extract::rating( 'I would rate this internship 9 out of 10.' ) );
		$this->assertSame( 4, Extract::rating( 'Rating: 7/10' ) );
		$this->assertSame( 0, Extract::rating( 'No score here.' ) );
	}

	public function test_cleaner_removes_old_formatting_embeds_and_shortcodes(): void {
		$html = (string) json_decode( (string) file_get_contents( self::FIXTURES . '/posts.json' ), true )[0]['content']['rendered'];
		$out  = Cleaner::clean( $html, 'https://new.example' );

		$this->assertStringNotContainsString( 'style=', $out );
		$this->assertStringNotContainsString( '<span', $out );
		$this->assertStringNotContainsString( '<iframe', $out );
		$this->assertStringNotContainsString( '<h1', $out );
		$this->assertStringNotContainsString( 'su_button', $out );
		$this->assertStringNotContainsString( 'Subscribe', $out );
		$this->assertStringNotContainsString( 'sociallocker', $out );
		$this->assertStringContainsString( 'Hidden tips.', $out, 'Text behind a social locker is kept.' );
		$this->assertStringNotContainsString( 'internfeel.com', $out );
		$this->assertStringContainsString( '<a href="https://new.example/another-story/">https://new.example/another-story/</a>', $out );
		$this->assertStringContainsString( 'src="https://new.example/wp-content/uploads/2016/01/lab.jpg"', $out );
		$this->assertStringNotContainsString( 'sites.google.com', $out, 'Images hosted elsewhere are dropped.' );
	}

	public function test_dry_run_reports_and_writes_nothing(): void {
		$result = ( new Importer( $this->source(), true ) )->batch( 0 );
		$rows   = self::by_slug( $result['rows'] );

		$this->assertSame( 5, $result['total'] );
		$this->assertNull( $result['next'] );
		$this->assertSame( 'skip', $rows['kymlmu93um02iur24']['action'] );
		$this->assertSame( 'would import', $rows['internship-experience-asha-verma-northwind-labs-research']['action'] );
		$this->assertSame( 0, (int) wp_count_posts( 'post' )->pending );
	}

	public function test_import_keeps_slugs_and_dates_and_is_never_verified(): void {
		$rows = self::by_slug( ( new Importer( $this->source(), false ) )->batch( 0 )['rows'] );
		$id   = (int) $rows['internship-experience-asha-verma-northwind-labs-research']['post_id'];
		$post = get_post( $id );

		$this->assertSame( 'internship-experience-asha-verma-northwind-labs-research', $post->post_name );
		$this->assertSame( '2016-05-01 10:00:00', $post->post_date );
		$this->assertSame( 'pending', $post->post_status );
		$this->assertSame( Roles::ensure_archive_user(), (int) $post->post_author );
		$this->assertSame( '101', get_post_meta( $id, '_if_legacy_wp_id', true ) );
		$this->assertSame( 'Asha Verma', get_post_meta( $id, '_if_legacy_student_name', true ) );
		$this->assertSame( '2016', get_post_meta( $id, '_if_legacy_published_year', true ) );
		$this->assertSame( '0', get_post_meta( $id, '_if_view_count', true ) );
		$this->assertSame( 'not_submitted', get_post_meta( $id, '_if_verification_status', true ) );
		$this->assertSame( 'research', get_post_meta( $id, '_if_type', true ) );
		$this->assertSame( '5', get_post_meta( $id, '_if_rating_overall', true ) );
		$this->assertContains( 'Computer Science', wp_get_object_terms( $id, 'if_master', array( 'fields' => 'names' ) ) );
		$this->assertSame( 2, (int) get_comments( array( 'post_id' => $id, 'count' => true ) ) );
	}

	public function test_articles_arrive_as_drafts_and_anonymous_posts_have_no_name(): void {
		$rows = self::by_slug( ( new Importer( $this->source(), false ) )->batch( 0 )['rows'] );

		$this->assertSame( 'draft', get_post_status( (int) $rows['how-to-write-to-a-professor']['post_id'] ) );
		$anonymous = (int) $rows['anonymous-experience-at-harbourline']['post_id'];
		$this->assertTrue( Fields::is_anonymous( $anonymous ) );
		$this->assertSame( '', get_post_meta( $anonymous, '_if_legacy_student_name', true ) );
	}

	public function test_running_again_imports_nothing_twice(): void {
		$first = self::by_slug( ( new Importer( $this->source(), false ) )->batch( 0 )['rows'] );
		$again = ( new Importer( $this->source(), false ) )->batch( 0 );
		$id    = (int) $first['internship-experience-asha-verma-northwind-labs-research']['post_id'];

		$this->assertSame( array( 'already imported', 'skip' ), array_values( array_unique( array_column( $again['rows'], 'action' ) ) ) );
		$this->assertSame( 3, (int) wp_count_posts( 'post' )->pending );
		$this->assertSame(
			2,
			(int) get_comments(
				array(
					'post_id' => $id,
					'count'   => true,
				)
			)
		);
	}
}
