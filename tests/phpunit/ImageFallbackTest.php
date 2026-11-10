<?php
/**
 * Old posts' missing resized images fall back to the original file.
 *
 * @package InternFeel
 */

use InternFeel\Content\Content;

/**
 * @covers \InternFeel\Content\Content::fallback_missing_sizes
 */
class ImageFallbackTest extends WP_UnitTestCase {

	private string $dir;
	private string $url;

	public function set_up(): void {
		parent::set_up();
		$uploads   = wp_get_upload_dir();
		$this->dir = trailingslashit( $uploads['basedir'] ) . 'if-fallback-test';
		$this->url = trailingslashit( $uploads['baseurl'] ) . 'if-fallback-test';
		wp_mkdir_p( $this->dir );
		file_put_contents( $this->dir . '/photo.jpg', 'x' );
		file_put_contents( $this->dir . '/kept.jpg', 'x' );
		file_put_contents( $this->dir . '/kept-300x200.jpg', 'x' );
	}

	public function tear_down(): void {
		foreach ( (array) glob( $this->dir . '/*' ) as $file ) {
			unlink( (string) $file );
		}
		rmdir( $this->dir );
		parent::tear_down();
	}

	public function test_a_missing_resized_copy_shows_the_original(): void {
		$html = '<p><img src="' . $this->url . '/photo-1024x575.jpg" width="605" height="340" alt="A"></p>';
		$out  = Content::fallback_missing_sizes( $html );

		$this->assertStringContainsString( 'src="' . $this->url . '/photo.jpg"', $out );
		$this->assertStringContainsString( 'width="605"', $out );
	}

	public function test_an_existing_resized_copy_is_left_alone(): void {
		$html = '<img src="' . $this->url . '/kept-300x200.jpg" alt="">';
		$this->assertSame( $html, Content::fallback_missing_sizes( $html ) );
	}

	public function test_nothing_changes_without_the_original(): void {
		$html = '<img src="' . $this->url . '/gone-1024x575.jpg" alt="">';
		$this->assertSame( $html, Content::fallback_missing_sizes( $html ) );
	}

	public function test_other_sites_images_are_left_alone(): void {
		$html = '<img src="https://example.org/wp-content/uploads/if-fallback-test/photo-1024x575.jpg" alt="">';
		$this->assertSame( $html, Content::fallback_missing_sizes( $html ) );
	}

	public function test_it_runs_on_the_content(): void {
		$post = self::factory()->post->create( array( 'post_content' => '<img src="' . $this->url . '/photo-1024x575.jpg" alt="A">' ) );
		$this->go_to( get_permalink( $post ) );
		the_post();

		$this->assertStringContainsString( '/if-fallback-test/photo.jpg"', (string) apply_filters( 'the_content', get_the_content() ) );
	}
}
