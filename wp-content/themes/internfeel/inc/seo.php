<?php
/**
 * Search engines (architecture.md 11): noindex for staging and for private or utility pages, and a
 * sitemap of only experiences, hubs, listicles and public pages. WordPress's own sitemap is shaped
 * here; once Yoast SEO is installed it takes over, and the same pages are kept out of its sitemap.
 *
 * @package InternFeel
 */

defined( 'ABSPATH' ) || exit;

/**
 * Pages that are for signed-in students or one-off visits, never for search results.
 *
 * @return list<string>
 */
function if_utility_pages(): array {
	return array( 'dashboard', 'submit', 'signup', 'login', 'join' );
}

/**
 * The IDs of the utility pages that exist.
 *
 * @return list<int>
 */
function if_utility_page_ids(): array {
	$ids = array();
	foreach ( if_utility_pages() as $slug ) {
		$page = get_page_by_path( $slug );
		if ( $page instanceof WP_Post ) {
			$ids[] = $page->ID;
		}
	}

	return $ids;
}

/**
 * Noindex everything outside production, the utility pages, search results and filtered listings.
 *
 * @param array<string, bool|string> $robots Robots directives.
 * @return array<string, bool|string>
 */
function if_robots( array $robots ): array {
	// Only real filters count: tracking parameters such as utm_source leave the page indexable.
	$filtered = if_core_active() && ( is_front_page() || is_page( 'experiences' ) )
		&& \InternFeel\Search\Search::is_filtered( \InternFeel\Search\Search::filters( $_GET ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only check of the query string.

	if ( 'production' !== wp_get_environment_type() || is_page( if_utility_pages() ) || is_search() || $filtered ) {
		$robots['noindex'] = true;
		$robots['follow']  = true;
		unset( $robots['max-image-preview'] );
	}

	return $robots;
}
add_filter( 'wp_robots', 'if_robots', 20 );

// WordPress's sitemap: experiences, pages and listicles; the master list (hubs); no user pages.
add_filter( 'wp_sitemaps_add_provider', static fn( mixed $provider, string $name ): mixed => 'users' === $name ? false : $provider, 10, 2 );

add_filter(
	'wp_sitemaps_post_types',
	static fn( array $types ): array => array_intersect_key( $types, array_flip( array( 'post', 'page', 'if_list' ) ) )
);
add_filter(
	'wp_sitemaps_taxonomies',
	static fn( array $taxonomies ): array => array_intersect_key( $taxonomies, array_flip( array( 'if_master' ) ) )
);
add_filter(
	'wp_sitemaps_posts_query_args',
	static function ( array $args, string $post_type ): array {
		if ( 'page' === $post_type ) {
			$args['post__not_in'] = array_merge( (array) ( $args['post__not_in'] ?? array() ), if_utility_page_ids() );
		}

		return $args;
	},
	10,
	2
);

// Yoast SEO (installed on staging and production): the same exclusions in its sitemap.
add_filter( 'wpseo_exclude_from_sitemap_by_post_ids', static fn( mixed $ids ): array => array_merge( is_array( $ids ) ? $ids : array(), if_utility_page_ids() ) );
add_filter( 'wpseo_sitemap_exclude_author', '__return_true' );
add_filter(
	'wpseo_sitemap_exclude_taxonomy',
	static fn( bool $exclude, string $taxonomy ): bool => 'if_master' !== $taxonomy ? true : $exclude,
	10,
	2
);
