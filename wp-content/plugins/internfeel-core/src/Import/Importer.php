<?php
/**
 * Imports the old site's posts as experiences (development.md section 9, plan.md section 6).
 *
 * @package InternFeel
 */

declare(strict_types=1);

namespace InternFeel\Import;

use InternFeel\Auth\Roles;
use InternFeel\Content\Content;
use InternFeel\Fields\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Works in batches and is safe to re-run: a post whose legacy_wp_id already exists is skipped.
 *
 * Every migrated experience:
 * - keeps its original slug, title and dates, and is owned by the InternFeel Archive user;
 * - arrives as Pending review (articles that are not experiences arrive as drafts), because Surabhi
 *   reviews each one before it is published;
 * - starts at 0 views and is never Verified;
 * - records legacy_wp_id, legacy_student_name and legacy_published_year.
 */
final class Importer {

	public const BATCH = 20;

	/**
	 * Posts never migrated (development.md section 9, step 5).
	 */
	public const SKIP = array(
		'kymlmu93um02iur24',
		'bad-internship-experience-anonymous-livon-virtual-internship',
	);

	/**
	 * The export.
	 *
	 * @var Source
	 */
	private Source $source;

	/**
	 * Whether to report only, writing nothing.
	 *
	 * @var bool
	 */
	private bool $dry_run;

	/**
	 * Set up an import.
	 *
	 * @param Source $source  The export.
	 * @param bool   $dry_run Report what would happen without writing anything.
	 */
	public function __construct( Source $source, bool $dry_run ) {
		$this->source  = $source;
		$this->dry_run = $dry_run;
	}

	/**
	 * Import one batch.
	 *
	 * @param int $offset Index of the first post in the batch.
	 * @param int $limit  Number of posts.
	 * @return array{total: int, next: int|null, rows: list<array<string, mixed>>}
	 */
	public function batch( int $offset, int $limit = self::BATCH ): array {
		$total = count( $this->source->posts );
		$rows  = array();

		foreach ( array_slice( $this->source->posts, $offset, $limit ) as $post ) {
			$rows[] = $this->import_post( $post );
		}

		$next = $offset + $limit;

		return array(
			'total' => $total,
			'next'  => $next < $total ? $next : null,
			'rows'  => $rows,
		);
	}

	/**
	 * Import, or report on, one old post.
	 *
	 * @param array<string, mixed> $post Old post from posts.json.
	 * @return array<string, mixed> Report row.
	 */
	private function import_post( array $post ): array {
		$legacy_id = (int) ( $post['id'] ?? 0 );
		$slug      = sanitize_title( (string) ( $post['slug'] ?? '' ) );
		$title     = self::decode( self::rendered( $post, 'title' ) );
		$html      = self::rendered( $post, 'content' );
		$slugs     = array_values(
			array_filter(
				array_map(
					fn( mixed $id ): string => $this->source->category_slugs[ (int) $id ] ?? '',
					(array) ( $post['categories'] ?? array() )
				)
			)
		);
		$mapped    = Mapping::for_post( $slugs );
		$text      = wp_strip_all_tags( $html );
		$name      = $mapped['anonymous'] ? '' : Extract::student_name( $title );
		$rating    = Extract::rating( $text );
		$image     = $this->featured_image( (int) ( $post['featured_media'] ?? 0 ) );
		$comments  = $this->source->comments[ $legacy_id ] ?? array();

		$row = array(
			'legacy_id'    => $legacy_id,
			'slug'         => $slug,
			'title'        => $title,
			'date'         => (string) ( $post['date'] ?? '' ),
			'kind'         => $mapped['kind'],
			'student_name' => $name,
			'anonymous'    => $mapped['anonymous'],
			'type'         => $mapped['type'],
			'field'        => $mapped['field'],
			'country'      => $mapped['country'],
			'organisation' => $mapped['organisation'],
			'rating'       => $rating,
			'image'        => $image['status'],
			'comments'     => count( $comments ),
			'action'       => '',
			'post_id'      => 0,
			'notes'        => array(),
		);

		if ( in_array( $slug, self::SKIP, true ) ) {
			$row['action'] = 'skip';
			$row['notes']  = array( 'On the skip list' );
			return $row;
		}

		$existing = self::existing( $legacy_id );
		if ( $existing > 0 ) {
			$row['action']  = 'already imported';
			$row['post_id'] = $existing;
			return $row;
		}

		$row['notes'] = self::review_notes( $row, $text );

		if ( $this->dry_run ) {
			$row['action'] = 'would import';
			return $row;
		}

		$post_id = $this->create( $post, $title, $slug, $html, $mapped, $name, $rating );
		if ( is_wp_error( $post_id ) ) {
			$row['action'] = 'failed';
			$row['notes']  = array( $post_id->get_error_message() );
			return $row;
		}

		if ( $image['attachment'] > 0 || '' !== $image['file'] ) {
			$attachment = $image['attachment'] > 0 ? $image['attachment'] : $this->register_attachment( $image, $post_id );
			if ( $attachment > 0 ) {
				set_post_thumbnail( $post_id, $attachment );
			}
		}

		$this->import_comments( $comments, $post_id );

		$row['action']  = 'imported';
		$row['post_id'] = $post_id;

		return $row;
	}

	/**
	 * Create the experience.
	 *
	 * @param array<string, mixed>                                                                                                   $post   Old post.
	 * @param string                                                                                                                 $title  Decoded title.
	 * @param string                                                                                                                 $slug   Original slug.
	 * @param string                                                                                                                 $html   Original HTML.
	 * @param array{kind: string, type: string, mode: string, field: string, country: string, organisation: string, anonymous: bool} $mapped What the categories say.
	 * @param string                                                                                                                 $name   Student's name, if found.
	 * @param int                                                                                                                    $rating Overall rating, 0 if none.
	 * @return int|\WP_Error
	 */
	private function create( array $post, string $title, string $slug, string $html, array $mapped, string $name, int $rating ): int|\WP_Error {
		$date     = (string) ( $post['date'] ?? '' );
		$date_gmt = (string) ( $post['date_gmt'] ?? '' );
		$excerpt  = self::decode( wp_strip_all_tags( self::rendered( $post, 'excerpt' ) ) );

		$post_id = wp_insert_post(
			wp_slash(
				array(
					'post_type'      => 'post',
					'post_status'    => 'article' === $mapped['kind'] ? 'draft' : 'pending',
					'post_title'     => $title,
					'post_name'      => $slug,
					'post_content'   => Cleaner::clean( $html, home_url() ),
					'post_excerpt'   => trim( $excerpt ),
					'post_author'    => Roles::ensure_archive_user(),
					'post_date'      => str_replace( 'T', ' ', $date ),
					'post_date_gmt'  => str_replace( 'T', ' ', $date_gmt ),
					'comment_status' => 'open',
					'ping_status'    => 'closed',
				)
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Keep the original slug, so the old URL works once the experience is published. wp_insert_post()
		// clears the slug of a pending post when the current user cannot publish (WP-CLI runs as nobody),
		// and may add a suffix to make it unique, so write it directly.
		if ( get_post_field( 'post_name', $post_id ) !== $slug ) {
			global $wpdb;
			$wpdb->update( $wpdb->posts, array( 'post_name' => $slug ), array( 'ID' => $post_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off import; the cache is cleared below.
			clean_post_cache( $post_id );
		}

		$year = (int) substr( $date, 0, 4 );
		$meta = array(
			'legacy_wp_id'          => (int) ( $post['id'] ?? 0 ),
			'legacy_student_name'   => $name,
			'legacy_published_year' => $year,
			'view_count'            => 0,
			'verification_status'   => 'not_submitted',
			'is_anonymous'          => $mapped['anonymous'],
			'type'                  => $mapped['type'],
			'mode'                  => $mapped['mode'],
			'country'               => $mapped['country'],
			'rating_overall'        => $rating,
		);
		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, Fields::PREFIX . $key, $value );
		}
		update_post_meta( $post_id, Fields::PREFIX . 'import_kind', $mapped['kind'] );

		$terms = array();
		if ( '' !== $mapped['field'] ) {
			$field = self::master_term( $mapped['field'], 'field' );
			if ( $field > 0 ) {
				$terms[] = $field;
				update_post_meta( $post_id, Fields::PREFIX . 'field_term', $field );
			}
		}
		if ( '' !== $mapped['organisation'] ) {
			$organisation = self::master_term( $mapped['organisation'], 'organisation' );
			if ( $organisation > 0 ) {
				$terms[] = $organisation;
				update_post_meta( $post_id, Fields::PREFIX . 'organisation_term', $organisation );
			}
		}
		if ( array() !== $terms ) {
			wp_set_object_terms( $post_id, $terms, Content::TAXONOMY );
		}

		return $post_id;
	}

	/**
	 * Find the old featured image and its file in this site's uploads.
	 *
	 * @param int $media_id Old media ID.
	 * @return array{status: string, attachment: int, file: string, url: string, alt: string, title: string, mime: string, legacy_id: int}
	 */
	private function featured_image( int $media_id ): array {
		$none = array(
			'status'     => 'none',
			'attachment' => 0,
			'file'       => '',
			'url'        => '',
			'alt'        => '',
			'title'      => '',
			'mime'       => '',
			'legacy_id'  => $media_id,
		);
		if ( $media_id <= 0 ) {
			return $none;
		}

		$item = $this->source->media[ $media_id ] ?? null;
		if ( null === $item ) {
			$none['status'] = 'missing from export';
			return $none;
		}

		$existing = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'meta_key'       => Fields::PREFIX . 'legacy_media_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- one-off import lookup.
				'meta_value'     => (string) $media_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- one-off import lookup.
			)
		);

		$url      = (string) ( $item['source_url'] ?? '' );
		$relative = 1 === preg_match( '#/wp-content/uploads/(.+)$#', (string) wp_parse_url( $url, PHP_URL_PATH ), $match ) ? $match[1] : '';
		$uploads  = wp_get_upload_dir();
		$present  = '' !== $relative && is_readable( trailingslashit( $uploads['basedir'] ) . $relative );

		$status = 'file not in uploads yet';
		if ( $existing ) {
			$status = 'already registered';
		} elseif ( $present ) {
			$status = 'file found';
		}

		return array(
			'status'     => $status,
			'attachment' => $existing ? (int) $existing[0] : 0,
			'file'       => $relative,
			'url'        => $url,
			'alt'        => (string) ( $item['alt_text'] ?? '' ),
			'title'      => self::decode( self::rendered( $item, 'title' ) ),
			'mime'       => (string) ( $item['mime_type'] ?? '' ),
			'legacy_id'  => $media_id,
		);
	}

	/**
	 * Register an attachment for an image file that is already in uploads (from internfeel-images.tar),
	 * so old image URLs keep working and the experience has its featured image.
	 *
	 * @param array{status: string, attachment: int, file: string, url: string, alt: string, title: string, mime: string, legacy_id: int} $image   Image details.
	 * @param int                                                                                                                         $post_id Experience it belongs to.
	 * @return int Attachment ID, or 0.
	 */
	private function register_attachment( array $image, int $post_id ): int {
		$uploads = wp_get_upload_dir();
		$path    = trailingslashit( $uploads['basedir'] ) . $image['file'];
		if ( '' === $image['file'] || ! is_readable( $path ) ) {
			return 0;
		}

		$attachment = wp_insert_attachment(
			array(
				'guid'           => trailingslashit( $uploads['baseurl'] ) . $image['file'],
				'post_mime_type' => '' !== $image['mime'] ? $image['mime'] : (string) wp_check_filetype( $path )['type'],
				'post_title'     => '' !== $image['title'] ? $image['title'] : basename( $image['file'] ),
				'post_status'    => 'inherit',
			),
			$image['file'],
			$post_id,
			true
		);
		if ( is_wp_error( $attachment ) ) {
			return 0;
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		wp_update_attachment_metadata( $attachment, wp_generate_attachment_metadata( $attachment, $path ) );
		update_post_meta( $attachment, Fields::PREFIX . 'legacy_media_id', $image['legacy_id'] );
		if ( '' !== $image['alt'] ) {
			update_post_meta( $attachment, '_wp_attachment_image_alt', sanitize_text_field( $image['alt'] ) );
		}

		return $attachment;
	}

	/**
	 * Import an old post's public comments with their original names and dates.
	 *
	 * @param list<array<string, mixed>> $comments Old comments, oldest first.
	 * @param int                        $post_id  Experience ID.
	 */
	private function import_comments( array $comments, int $post_id ): void {
		$map = array();
		foreach ( $comments as $comment ) {
			$old_id = (int) ( $comment['id'] ?? 0 );
			$parent = (int) ( $comment['parent'] ?? 0 );
			$new_id = wp_insert_comment(
				wp_slash(
					array(
						'comment_post_ID'  => $post_id,
						'comment_author'   => sanitize_text_field( self::decode( (string) ( $comment['author_name'] ?? '' ) ) ),
						'comment_content'  => wp_kses_post( self::rendered( $comment, 'content' ) ),
						'comment_date'     => str_replace( 'T', ' ', (string) ( $comment['date'] ?? '' ) ),
						'comment_date_gmt' => str_replace( 'T', ' ', (string) ( $comment['date_gmt'] ?? '' ) ),
						'comment_approved' => 1,
						'comment_parent'   => $map[ $parent ] ?? 0,
					)
				)
			);
			if ( false !== $new_id ) {
				$map[ $old_id ] = $new_id;
				add_comment_meta( $new_id, Fields::PREFIX . 'legacy_comment_id', $old_id, true );
			}
		}
	}

	/**
	 * What the review needs to complete for this experience.
	 *
	 * @param array<string, mixed> $row  Report row.
	 * @param string               $text Plain text of the post.
	 * @return list<string>
	 */
	private static function review_notes( array $row, string $text ): array {
		$notes = array();
		if ( 'article' === $row['kind'] ) {
			$notes[] = 'Article, not an experience: decide whether to keep it';
		}
		if ( 'experience' === $row['kind'] && '' === $row['student_name'] && ! $row['anonymous'] ) {
			$notes[] = 'Student name not found in the title';
		}
		foreach ( array( 'type', 'field', 'organisation' ) as $key ) {
			if ( '' === $row[ $key ] ) {
				$notes[] = "No {$key}";
			}
		}
		if ( 0 === $row['rating'] ) {
			$notes[] = 'No rating';
		}
		if ( 'none' === $row['image'] || 'missing from export' === $row['image'] ) {
			$notes[] = 'No featured image';
		}
		if ( str_word_count( $text ) < 200 ) {
			$notes[] = 'Very short';
		}

		return $notes;
	}

	/**
	 * A REST field's rendered value, such as title.rendered.
	 *
	 * @param array<string, mixed> $item Record from the export.
	 * @param string               $key  Field name.
	 * @return string
	 */
	private static function rendered( array $item, string $key ): string {
		$field = $item[ $key ] ?? null;

		return is_array( $field ) && is_scalar( $field['rendered'] ?? null ) ? (string) $field['rendered'] : '';
	}

	/**
	 * Decode HTML entities from the REST API's rendered strings.
	 *
	 * @param string $text Encoded text.
	 * @return string
	 */
	private static function decode( string $text ): string {
		return html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	/**
	 * ID of the experience already imported from an old post, or 0.
	 *
	 * @param int $legacy_id Old post ID.
	 * @return int
	 */
	private static function existing( int $legacy_id ): int {
		if ( $legacy_id <= 0 ) {
			return 0;
		}

		$found = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'meta_key'       => Fields::PREFIX . 'legacy_wp_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- one-off import lookup.
				'meta_value'     => (string) $legacy_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- one-off import lookup.
			)
		);

		return $found ? (int) $found[0] : 0;
	}

	/**
	 * A master-list term, created if needed, with its kind set.
	 *
	 * @param string $name Term name.
	 * @param string $kind organisation, programme or field.
	 * @return int Term ID, or 0.
	 */
	private static function master_term( string $name, string $kind ): int {
		$term = term_exists( $name, Content::TAXONOMY );
		if ( is_array( $term ) ) {
			return (int) $term['term_id'];
		}

		$created = wp_insert_term( $name, Content::TAXONOMY );
		if ( is_wp_error( $created ) ) {
			return 0;
		}

		update_term_meta( (int) $created['term_id'], Fields::PREFIX . 'kind', $kind );

		return (int) $created['term_id'];
	}
}
