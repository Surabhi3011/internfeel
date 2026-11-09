<?php
/**
 * Share images and tags (plan.md 5.1 "Share"), and hub page facts (approved-designs README).
 *
 * @package InternFeel
 */

use InternFeel\Content\Content;
use InternFeel\Fields\Fields;
use InternFeel\Schema;
use InternFeel\Search\Search;
use InternFeel\Share\Share;

/**
 * @covers \InternFeel\Share\Share
 * @covers \InternFeel\Search\Search::hub_facts
 */
class ShareHubTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		if ( ! registered_meta_key_exists( 'post', '_if_paid', 'post' ) ) {
			Fields::register_meta();
		}
		update_option( Schema::OPTION, 0 );
		Schema::upgrade();
	}

	private function experience( int $term, array $meta ): int {
		$id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		foreach ( $meta as $key => $value ) {
			update_post_meta( $id, '_if_' . $key, $value );
		}
		wp_set_object_terms( $id, array( $term ), Content::TAXONOMY );

		return $id;
	}

	public function test_an_experience_gets_a_1200_by_630_share_image(): void {
		if ( ! function_exists( 'imagettftext' ) ) {
			$this->markTestSkipped( 'GD with FreeType is not available.' );
		}
		$post = get_post( self::factory()->post->create( array( 'post_title' => 'Eight weeks in an ecology lab' ) ) );

		$url = Share::image_url( $post );
		$this->assertStringContainsString( '/' . Share::FOLDER . '/' . $post->ID . '-', $url );

		$path = wp_upload_dir()['basedir'] . '/' . Share::FOLDER . '/' . basename( $url );
		$size = getimagesize( $path );
		$this->assertSame( array( Share::WIDTH, Share::HEIGHT ), array( $size[0], $size[1] ) );

		// The same title reuses the file; a new title makes a new one.
		$this->assertSame( $url, Share::image_url( $post ) );
		wp_update_post(
			array(
				'ID'         => $post->ID,
				'post_title' => 'A different title',
			)
		);
		$this->assertNotSame( $url, Share::image_url( get_post( $post->ID ) ) );
		$this->assertFileDoesNotExist( $path, 'The old image is removed.' );
	}

	public function test_the_image_never_shows_the_author(): void {
		$author = self::factory()->user->create( array( 'display_name' => 'Secret Student' ) );
		$post   = get_post(
			self::factory()->post->create(
				array(
					'post_author' => $author,
					'post_title'  => 'Anonymous story',
				)
			)
		);
		update_post_meta( $post->ID, '_if_is_anonymous', true );

		$this->assertStringNotContainsString( 'Secret', (string) wp_json_encode( Share::data( $post ) ) );
	}

	public function test_experience_pages_print_open_graph_tags(): void {
		$id = self::factory()->post->create( array( 'post_title' => 'Lab internship' ) );
		$this->go_to( get_permalink( $id ) );

		ob_start();
		Share::meta_tags();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( '<meta property="og:title" content="Lab internship">', $html );
		$this->assertStringContainsString( '<meta property="og:type" content="article">', $html );
		$this->assertStringContainsString( 'twitter:card', $html );
	}

	public function test_hub_facts_summarise_published_experiences(): void {
		$org = self::factory()->term->create( array( 'taxonomy' => Content::TAXONOMY ) );
		$this->experience(
			$org,
			array(
				'rating_overall'     => 4,
				'paid'               => '1',
				'stipend_amount_inr' => 12000,
				'start_month'        => '2025-05',
			)
		);
		$this->experience(
			$org,
			array(
				'rating_overall'     => 5,
				'paid'               => '1',
				'stipend_amount_inr' => 15000,
				'start_month'        => '2024-05',
			)
		);
		$this->experience(
			$org,
			array(
				'rating_overall' => 3,
				'paid'           => '0',
				'start_month'    => '2025-06',
			)
		);

		$facts = Search::hub_facts( $org );

		$this->assertSame( 3, $facts['experiences'] );
		$this->assertSame( 4.0, $facts['rating'] );
		$this->assertSame( '₹10k–25k', $facts['band'] );
		$this->assertSame( 'May', $facts['months'][0] );
	}
}
