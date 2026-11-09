<?php
/**
 * View counting (architecture.md 5.6, plan.md 5.1 "View count").
 *
 * @package InternFeel
 */

use InternFeel\Fields\Fields;
use InternFeel\Views\Views;

/**
 * @covers \InternFeel\Views\Views
 */
class ViewsTest extends WP_UnitTestCase {

	private const BROWSER = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/130.0 Safari/537.36';

	public function set_up(): void {
		parent::set_up();
		if ( ! registered_meta_key_exists( 'post', '_if_paid', 'post' ) ) {
			Fields::register_meta();
		}
	}

	private function views( int $id ): int {
		return (int) get_post_meta( $id, '_if_view_count', true );
	}

	public function test_a_visit_counts_once_per_browser_per_day(): void {
		$id = self::factory()->post->create();

		$this->assertTrue( Views::count( $id, 0, self::BROWSER, false ) );
		$this->assertFalse( Views::count( $id, 0, self::BROWSER, true ), 'The cookie says it was counted today.' );
		$this->assertTrue( Views::count( $id, 0, self::BROWSER, false ), 'Another browser.' );

		$this->assertSame( 2, $this->views( $id ) );
	}

	public function test_bots_the_author_and_unpublished_posts_are_not_counted(): void {
		$author = self::factory()->user->create();
		$id     = self::factory()->post->create( array( 'post_author' => $author ) );
		$draft  = self::factory()->post->create( array( 'post_status' => 'draft' ) );

		$this->assertFalse( Views::count( $id, 0, 'Mozilla/5.0 (compatible; Googlebot/2.1)', false ) );
		$this->assertFalse( Views::count( $id, 0, 'WhatsApp/2.23', false ) );
		$this->assertFalse( Views::count( $id, 0, '', false ) );
		$this->assertFalse( Views::count( $id, $author, self::BROWSER, false ) );
		$this->assertFalse( Views::count( $draft, 0, self::BROWSER, false ) );

		$this->assertSame( 0, $this->views( $id ) );
	}

	public function test_counting_adds_to_an_existing_count(): void {
		$id = self::factory()->post->create();
		update_post_meta( $id, '_if_view_count', 40 );

		Views::count( $id, 0, self::BROWSER, false );

		$this->assertSame( 41, $this->views( $id ) );
		$this->assertCount( 1, get_post_meta( $id, '_if_view_count' ), 'One counter row, never two.' );
	}
}
