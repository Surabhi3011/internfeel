<?php
/**
 * The exported JSON files the importer reads (development.md section 9, step 2).
 *
 * @package InternFeel
 */

declare(strict_types=1);

namespace InternFeel\Import;

defined( 'ABSPATH' ) || exit;

/**
 * Loads posts.json, categories.json, comments.json and media.json from one folder.
 */
final class Source {

	public const FILES = array( 'posts', 'categories', 'comments', 'media' );

	/**
	 * Category ID => slug.
	 *
	 * @var array<int, string>
	 */
	public array $category_slugs = array();

	/**
	 * Media ID => record.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public array $media = array();

	/**
	 * Old post ID => its comments, oldest first.
	 *
	 * @var array<int, list<array<string, mixed>>>
	 */
	public array $comments = array();

	/**
	 * Posts, oldest first, so a re-run imports in the same order.
	 *
	 * @var list<array<string, mixed>>
	 */
	public array $posts = array();

	/**
	 * Read the export from a folder.
	 *
	 * @param string $dir Folder holding the four JSON files.
	 * @return self|\WP_Error
	 */
	public static function from_dir( string $dir ): self|\WP_Error {
		$data = array();
		foreach ( self::FILES as $name ) {
			$path = trailingslashit( $dir ) . $name . '.json';
			if ( ! is_readable( $path ) ) {
				/* translators: %s: file name. */
				return new \WP_Error( 'if_import_missing', sprintf( __( '%s.json is missing. Upload it first.', 'internfeel' ), $name ) );
			}
			$decoded = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local file in private storage.
			if ( ! is_array( $decoded ) ) {
				/* translators: %s: file name. */
				return new \WP_Error( 'if_import_invalid', sprintf( __( '%s.json is not valid JSON.', 'internfeel' ), $name ) );
			}
			$data[ $name ] = $decoded;
		}

		$source = new self();
		foreach ( $data['categories'] as $category ) {
			if ( is_array( $category ) && isset( $category['id'], $category['slug'] ) ) {
				$source->category_slugs[ (int) $category['id'] ] = (string) $category['slug'];
			}
		}
		foreach ( $data['media'] as $item ) {
			if ( is_array( $item ) && isset( $item['id'] ) ) {
				$source->media[ (int) $item['id'] ] = $item;
			}
		}
		foreach ( $data['comments'] as $comment ) {
			if ( is_array( $comment ) && isset( $comment['post'] ) ) {
				$source->comments[ (int) $comment['post'] ][] = $comment;
			}
		}
		foreach ( $source->comments as $post_id => $list ) {
			usort( $list, static fn( array $a, array $b ): int => strcmp( (string) ( $a['date_gmt'] ?? '' ), (string) ( $b['date_gmt'] ?? '' ) ) );
			$source->comments[ $post_id ] = $list;
		}

		$posts = array_values( array_filter( $data['posts'], 'is_array' ) );
		usort( $posts, static fn( array $a, array $b ): int => strcmp( (string) ( $a['date_gmt'] ?? '' ), (string) ( $b['date_gmt'] ?? '' ) ) );
		$source->posts = $posts;

		return $source;
	}
}
