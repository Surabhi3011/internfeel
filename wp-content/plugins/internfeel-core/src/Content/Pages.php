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
}
