<?php
/**
 * The Share module: Open Graph and Twitter tags, and a 1200 × 630 preview image per experience with
 * its title, organisation and the logo (plan.md 5.1, "Share").
 *
 * @package InternFeel
 */

declare(strict_types=1);

namespace InternFeel\Share;

use InternFeel\Module;
use InternFeel\Search\Cards;
use InternFeel\Submit\Form;

defined( 'ABSPATH' ) || exit;

/**
 * Images are made with GD on first need and kept in uploads/internfeel-share/, named after the
 * experience and a hash of what they show, so an edited title makes a fresh one.
 */
final class Share implements Module {

	public const WIDTH  = 1200;
	public const HEIGHT = 630;
	public const FOLDER = 'internfeel-share';

	/**
	 * Add the module's hooks.
	 */
	public static function register(): void {
		add_action( 'wp_head', array( self::class, 'meta_tags' ), 5 );
		// Once Yoast SEO is installed (day 11) it prints these tags; it then only borrows our image.
		add_filter( 'wpseo_opengraph_image', array( self::class, 'yoast_image' ) );
		add_filter( 'wpseo_twitter_image', array( self::class, 'yoast_image' ) );
	}

	/**
	 * The experience's image for Yoast SEO's tags.
	 *
	 * @param mixed $image Yoast's choice.
	 * @return mixed
	 */
	public static function yoast_image( mixed $image ): mixed {
		$post = is_singular( 'post' ) ? get_queried_object() : null;
		$url  = $post instanceof \WP_Post ? self::image_url( $post ) : '';

		return '' === $url ? $image : $url;
	}

	/**
	 * Print the tags for the current page. Experiences get their own image; other pages the site's.
	 */
	public static function meta_tags(): void {
		if ( defined( 'WPSEO_VERSION' ) ) {
			return;
		}

		$title       = wp_get_document_title();
		$description = get_bloginfo( 'description' );
		$url         = home_url( add_query_arg( array() ) );
		$image       = '';
		$type        = 'website';

		if ( is_singular( 'post' ) ) {
			$post        = get_queried_object();
			$post        = $post instanceof \WP_Post ? $post : null;
			$title       = null === $post ? $title : html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' );
			$description = null === $post ? $description : wp_trim_words( wp_strip_all_tags( $post->post_excerpt ? $post->post_excerpt : $post->post_content ), 30, '…' );
			$url         = null === $post ? $url : (string) get_permalink( $post );
			$image       = null === $post ? '' : self::image_url( $post );
			$type        = 'article';
		} elseif ( is_singular() ) {
			$url = (string) get_permalink();
		}

		if ( '' === $image ) {
			$image = self::default_image_url();
		}

		$tags = array(
			'og:site_name'   => 'InternFeel',
			'og:type'        => $type,
			'og:title'       => $title,
			'og:description' => $description,
			'og:url'         => $url,
			'og:image'       => $image,
		);
		foreach ( $tags as $property => $content ) {
			if ( '' !== $content ) {
				printf( '<meta property="%s" content="%s">' . "\n", esc_attr( $property ), esc_attr( $content ) );
			}
		}
		if ( '' !== $image ) {
			printf( '<meta property="og:image:width" content="%d">' . "\n" . '<meta property="og:image:height" content="%d">' . "\n", (int) self::WIDTH, (int) self::HEIGHT );
		}
		echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
	}

	/**
	 * The experience's preview image, made if needed. Empty when GD or the uploads folder is missing.
	 *
	 * @param \WP_Post $post Experience.
	 * @return string
	 */
	public static function image_url( \WP_Post $post ): string {
		$data = self::data( $post );
		$name = $post->ID . '-' . substr( md5( (string) wp_json_encode( $data ) ), 0, 10 ) . '.png';
		$dir  = self::dir();
		if ( null === $dir ) {
			return '';
		}

		$path = $dir['path'] . '/' . $name;
		if ( ! is_readable( $path ) ) {
			foreach ( (array) glob( $dir['path'] . '/' . $post->ID . '-*.png' ) as $old ) {
				wp_delete_file( (string) $old );
			}
			if ( ! self::render( $data['title'], $data['subtitle'], $path ) ) {
				return '';
			}
		}

		return $dir['url'] . '/' . $name;
	}

	/**
	 * The site-wide image, for pages that are not experiences.
	 *
	 * @return string
	 */
	public static function default_image_url(): string {
		$dir = self::dir();
		if ( null === $dir ) {
			return '';
		}

		$tagline = __( 'Real internship experiences from students who’ve been there', 'internfeel' );
		$name    = 'site-' . substr( md5( $tagline ), 0, 10 ) . '.png';
		$path    = $dir['path'] . '/' . $name;
		if ( ! is_readable( $path ) && ! self::render( $tagline, __( 'Peep before you leap', 'internfeel' ), $path ) ) {
			return '';
		}

		return $dir['url'] . '/' . $name;
	}

	/**
	 * What the image shows: the title, then the organisation and type. Never the author.
	 *
	 * @param \WP_Post $post Experience.
	 * @return array{title: string, subtitle: string}
	 */
	public static function data( \WP_Post $post ): array {
		$values = Form::values( $post->ID );
		$types  = Cards::types();

		return array(
			'title'    => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
			'subtitle' => implode( ' · ', array_filter( array( (string) $values['organisation'], $types[ (string) $values['type'] ] ?? '' ) ) ),
		);
	}

	/**
	 * Draw an image: tint ground, the brand strip, the logo, a wrapped title and one muted line.
	 *
	 * @param string $title    Main text.
	 * @param string $subtitle Second line.
	 * @param string $path     Where to save the PNG.
	 * @return bool
	 */
	public static function render( string $title, string $subtitle, string $path ): bool {
		if ( ! function_exists( 'imagecreatetruecolor' ) || ! function_exists( 'imagettftext' ) ) {
			return false;
		}

		$assets  = dirname( __DIR__, 2 ) . '/assets';
		$heading = $assets . '/fonts/schibsted-grotesk-800.ttf';
		$body    = $assets . '/fonts/atkinson-hyperlegible-next-400.ttf';
		$image   = imagecreatetruecolor( self::WIDTH, self::HEIGHT );
		if ( false === $image ) {
			return false;
		}

		$colour = static fn( int $r, int $g, int $b ): int => (int) imagecolorallocate( $image, $r, $g, $b );
		imagefilledrectangle( $image, 0, 0, self::WIDTH, self::HEIGHT, $colour( 0xff, 0xff, 0xff ) );
		// The brand strip from the certificate, on the left.
		imagefilledrectangle( $image, 0, 0, 160, self::HEIGHT, $colour( 0xee, 0xf7, 0xf7 ) );
		imagefilledrectangle( $image, 0, 0, 96, 260, $colour( 0x33, 0x99, 0x99 ) );
		imagefilledrectangle( $image, 96, 0, 160, 160, $colour( 0x29, 0x77, 0x77 ) );
		imagefilledrectangle( $image, 0, 480, 78, self::HEIGHT, $colour( 0x3a, 0x38, 0x39 ) );

		$logo = @imagecreatefrompng( $assets . '/img/internfeel-logo.png' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a missing logo just leaves it out.
		if ( false !== $logo ) {
			$height = 72;
			$width  = (int) round( imagesx( $logo ) * $height / imagesy( $logo ) );
			imagecopyresampled( $image, $logo, 220, 64, 0, 0, $width, $height, imagesx( $logo ), imagesy( $logo ) );
		}

		$ink   = $colour( 0x1d, 0x1c, 0x1c );
		$muted = $colour( 0x5f, 0x5c, 0x5d );
		$y     = 250;
		foreach ( array_slice( self::wrap( $title, $heading, 54, 900 ), 0, 3 ) as $line ) {
			imagettftext( $image, 54, 0, 220, $y, $ink, $heading, $line );
			$y += 72;
		}
		if ( '' !== $subtitle ) {
			$lines = self::wrap( $subtitle, $body, 28, 900 );
			imagettftext( $image, 28, 0, 220, min( $y + 30, 560 ), $muted, $body, $lines[0] ?? '' );
		}

		$saved = imagepng( $image, $path, 6 );

		return $saved && is_readable( $path );
	}

	/**
	 * Break text into lines that fit a width; the last line gets an ellipsis if text was cut.
	 *
	 * @param string $text  Text.
	 * @param string $font  TTF path.
	 * @param int    $size  Point size.
	 * @param int    $width Maximum width in pixels.
	 * @return list<string>
	 */
	private static function wrap( string $text, string $font, int $size, int $width ): array {
		$lines   = array();
		$current = '';
		$words   = preg_split( '/\s+/', trim( $text ) );
		foreach ( false === $words ? array() : $words as $word ) {
			$try = '' === $current ? $word : $current . ' ' . $word;
			$box = imagettfbbox( $size, 0, $font, $try );
			if ( false !== $box && $box[2] - $box[0] > $width && '' !== $current ) {
				$lines[] = $current;
				$current = $word;
			} else {
				$current = $try;
			}
		}
		if ( '' !== $current ) {
			$lines[] = $current;
		}
		if ( count( $lines ) > 3 ) {
			$lines[2] = rtrim( $lines[2], ' ,.;:' ) . '…';
		}

		return $lines;
	}

	/**
	 * The public folder for share images, created if needed.
	 *
	 * @return array{path: string, url: string}|null
	 */
	private static function dir(): ?array {
		$uploads = wp_upload_dir( null, false );
		if ( ! empty( $uploads['error'] ) ) {
			return null;
		}

		$path = untrailingslashit( $uploads['basedir'] ) . '/' . self::FOLDER;
		if ( ! is_dir( $path ) && ! wp_mkdir_p( $path ) ) {
			return null;
		}

		return array(
			'path' => $path,
			'url'  => untrailingslashit( $uploads['baseurl'] ) . '/' . self::FOLDER,
		);
	}
}
