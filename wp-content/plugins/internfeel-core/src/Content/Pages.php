<?php
/**
 * The pages the theme's student flows live on (/signup/, /login/, ...).
 *
 * @package InternFeel
 */

declare(strict_types=1);

namespace InternFeel\Content;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and finds the pages whose slugs the theme's page templates match (page-signup.php, ...).
 */
final class Pages {

	/**
	 * Create a published page with this slug unless one exists. Safe to run twice.
	 *
	 * @param string $slug  Page slug, such as "signup".
	 * @param string $title Page title.
	 * @return int Page ID, or 0 if it could not be created.
	 */
	public static function ensure( string $slug, string $title ): int {
		$page = get_page_by_path( $slug );
		if ( $page instanceof \WP_Post ) {
			return $page->ID;
		}

		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_name'    => $slug,
				'post_title'   => $title,
				'post_content' => '',
			),
			true
		);

		return is_wp_error( $id ) ? 0 : $id;
	}

	/**
	 * The URL of a student-flow page, or the home page if it does not exist yet.
	 *
	 * @param string $slug Page slug.
	 * @return string
	 */
	public static function url( string $slug ): string {
		$page = get_page_by_path( $slug );
		if ( $page instanceof \WP_Post && 'publish' === $page->post_status ) {
			return (string) get_permalink( $page );
		}

		return home_url( '/' );
	}

	/**
	 * Create a page as a draft from templates/pages/{slug}.html, if no page has that slug yet. Used for
	 * legal and company pages, which an admin reviews and publishes; an existing page is never touched.
	 *
	 * @param string $slug     Page slug, which is also the content file's name.
	 * @param string $title    Page title.
	 * @param string $template Page template file, or empty for the default.
	 * @return int Page ID, or 0.
	 */
	public static function draft( string $slug, string $title, string $template = '' ): int {
		$existing = get_posts(
			array(
				'post_type'      => 'page',
				'name'           => $slug,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);
		$file     = dirname( __DIR__, 2 ) . '/templates/pages/' . $slug . '.html';
		$content  = is_readable( $file ) ? (string) file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a bundled file.

		if ( array() !== $existing ) {
			$id = (int) $existing[0];
			// WordPress makes its own Privacy Policy draft at install. Replace that one while nobody
			// has edited it; any page an admin has worked on is left alone.
			if ( (int) get_option( 'wp_page_for_privacy_policy' ) === $id && 'draft' === get_post_status( $id ) && get_post_field( 'post_modified_gmt', $id ) === get_post_field( 'post_date_gmt', $id ) && '' !== $content ) {
				wp_update_post(
					wp_slash(
						array(
							'ID'           => $id,
							'post_title'   => $title,
							'post_content' => $content,
						)
					)
				);
				if ( '' !== $template ) {
					update_post_meta( $id, '_wp_page_template', $template );
				}
			}

			return $id;
		}

		$id = wp_insert_post(
			wp_slash(
				array(
					'post_type'    => 'page',
					'post_status'  => 'draft',
					'post_name'    => $slug,
					'post_title'   => $title,
					'post_content' => $content,
				)
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return 0;
		}
		if ( '' !== $template ) {
			update_post_meta( $id, '_wp_page_template', $template );
		}

		return $id;
	}
}
