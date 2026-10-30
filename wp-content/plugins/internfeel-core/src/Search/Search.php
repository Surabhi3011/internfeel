<?php
/**
 * The Search module: one WP_Query for the filtered listing (architecture.md 5.7), GET experiences for
 * live results, and the home page's counts.
 *
 * @package InternFeel
 */

declare(strict_types=1);

namespace InternFeel\Search;

use InternFeel\Auth\Roles;
use InternFeel\Content\Content;
use InternFeel\Fields\Fields;
use InternFeel\Module;
use InternFeel\Support\Rest;

defined( 'ABSPATH' ) || exit;

/**
 * Filters live in the URL query string (q, field, type, organisation, location, year, pay, verified,
 * sort, page), so every filtered view can be shared and cached.
 *
 * @phpstan-type Filters array{q: string, field: int, type: string, organisation: int, location: string, year: int, pay: string, verified: bool, sort: string, page: int}
 */
final class Search implements Module {

	public const PER_PAGE = 12;

	/**
	 * Pay filter values: paid, unpaid, or paid within a stipend band.
	 */
	public const PAY = array( 'paid', 'unpaid', 'under10', '10to25', 'over25' );

	/**
	 * Add the module's hooks.
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
		add_action( 'save_post_post', array( self::class, 'forget_counts' ) );
	}

	/**
	 * Register the routes. GET experiences sits beside the Submit module's POST on the same path.
	 */
	public static function routes(): void {
		register_rest_route(
			Rest::NS,
			'/experiences',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'rest_list' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Clean filters from a request or the URL's query string.
	 *
	 * @param array<string, mixed> $input Raw values.
	 * @return array<string, mixed>
	 * @phpstan-return Filters
	 */
	public static function filters( array $input ): array {
		$text = static fn( string $key ): string => is_scalar( $input[ $key ] ?? null ) ? sanitize_text_field( wp_unslash( (string) $input[ $key ] ) ) : '';
		$type = $text( 'type' );
		$pay  = $text( 'pay' );
		$sort = $text( 'sort' );

		return array(
			'q'            => mb_substr( $text( 'q' ), 0, 100 ),
			'field'        => absint( $text( 'field' ) ),
			'type'         => array_key_exists( $type, Cards::types() ) ? $type : '',
			'organisation' => absint( $text( 'organisation' ) ),
			'location'     => mb_substr( $text( 'location' ), 0, 60 ),
			'year'         => (int) $text( 'year' ) >= 2000 && (int) $text( 'year' ) <= 2100 ? (int) $text( 'year' ) : 0,
			'pay'          => in_array( $pay, self::PAY, true ) ? $pay : '',
			'verified'     => in_array( $text( 'verified' ), array( '1', 'true', 'yes' ), true ),
			'sort'         => in_array( $sort, array( 'newest', 'views', 'rating' ), true ) ? $sort : 'newest',
			'page'         => max( 1, absint( $text( 'page' ) ) ),
		);
	}

	/**
	 * Whether any filter is set (sorting and paging do not count).
	 *
	 * @param array<string, mixed> $filters Clean filters.
	 * @phpstan-param Filters $filters
	 * @return bool
	 */
	public static function is_filtered( array $filters ): bool {
		return '' !== $filters['q'] || $filters['field'] > 0 || '' !== $filters['type'] || $filters['organisation'] > 0
			|| '' !== $filters['location'] || $filters['year'] > 0 || '' !== $filters['pay'] || $filters['verified'];
	}

	/**
	 * WP_Query arguments for the filters: published experiences only.
	 *
	 * @param array<string, mixed> $filters  Clean filters.
	 * @param int                  $per_page Results per page.
	 * @phpstan-param Filters $filters
	 * @return array<string, mixed>
	 */
	public static function query_args( array $filters, int $per_page = self::PER_PAGE ): array {
		$meta = array( 'relation' => 'AND' );
		$tax  = array( 'relation' => 'AND' );
		$key  = static fn( string $name ): string => Fields::PREFIX . $name;

		if ( '' !== $filters['type'] ) {
			$meta[] = array(
				'key'   => $key( 'type' ),
				'value' => $filters['type'],
			);
		}
		foreach ( array( 'field', 'organisation' ) as $term ) {
			if ( $filters[ $term ] > 0 ) {
				$tax[] = array(
					'taxonomy' => Content::TAXONOMY,
					'field'    => 'term_id',
					'terms'    => $filters[ $term ],
				);
			}
		}
		if ( '' !== $filters['location'] ) {
			$meta[] = array(
				'relation' => 'OR',
				array(
					'key'   => $key( 'city' ),
					'value' => $filters['location'],
				),
				array(
					'key'   => $key( 'country' ),
					'value' => $filters['location'],
				),
			);
		}
		if ( $filters['year'] > 0 ) {
			$meta[] = array(
				'relation' => 'OR',
				array(
					'key'     => $key( 'start_month' ),
					'value'   => $filters['year'] . '-',
					'compare' => 'LIKE',
				),
				array(
					'key'   => $key( 'legacy_published_year' ),
					'value' => (string) $filters['year'],
				),
			);
		}
		if ( 'unpaid' === $filters['pay'] ) {
			$meta[] = array(
				'key'   => $key( 'paid' ),
				'value' => '0',
			);
		} elseif ( '' !== $filters['pay'] ) {
			$meta[] = array(
				'key'   => $key( 'paid' ),
				'value' => '1',
			);
			$ranges = array(
				'under10' => array( array( 1, 9999 ), 'BETWEEN' ),
				'10to25'  => array( array( 10000, 25000 ), 'BETWEEN' ),
				'over25'  => array( 25000, '>' ),
			);
			if ( isset( $ranges[ $filters['pay'] ] ) ) {
				$meta[] = array(
					'key'     => $key( 'stipend_amount_inr' ),
					'value'   => $ranges[ $filters['pay'] ][0],
					'compare' => $ranges[ $filters['pay'] ][1],
					'type'    => 'NUMERIC',
				);
			}
		}
		if ( $filters['verified'] ) {
			$meta[] = array(
				'key'   => $key( 'verification_status' ),
				'value' => 'verified',
			);
		}

		$args = array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => $per_page,
			'paged'               => $filters['page'],
			'ignore_sticky_posts' => true,
			'meta_query'          => $meta, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- filtered listing (architecture.md 5.7).
			'tax_query'           => $tax, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- filtered listing.
		);
		if ( '' !== $filters['q'] ) {
			$args['s'] = $filters['q'];
		}
		if ( 'views' === $filters['sort'] || 'rating' === $filters['sort'] ) {
			$args['meta_key'] = $key( 'views' === $filters['sort'] ? 'view_count' : 'rating_overall' ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- sorting.
			$args['orderby']  = array(
				'meta_value_num' => 'DESC',
				'date'           => 'DESC',
			);
		}

		return $args;
	}

	/**
	 * Run a search.
	 *
	 * @param array<string, mixed> $filters  Clean filters.
	 * @param int                  $per_page Results per page.
	 * @phpstan-param Filters $filters
	 * @return array{cards: list<array<string, mixed>>, total: int, pages: int}
	 */
	public static function run( array $filters, int $per_page = self::PER_PAGE ): array {
		$query = new \WP_Query( self::query_args( $filters, $per_page ) );
		$cards = array();
		foreach ( $query->posts as $post ) {
			if ( $post instanceof \WP_Post ) {
				$cards[] = Cards::data( $post );
			}
		}

		return array(
			'cards' => $cards,
			'total' => (int) $query->found_posts,
			'pages' => (int) $query->max_num_pages,
		);
	}

	/**
	 * GET experiences: filtered cards.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @return \WP_REST_Response
	 */
	public static function rest_list( \WP_REST_Request $request ): \WP_REST_Response {
		$filters = self::filters( $request->get_query_params() );
		$result  = self::run( $filters );

		$response = new \WP_REST_Response( $result + array( 'page' => $filters['page'] ), 200 );
		$response->header( 'Cache-Control', 'public, max-age=300' );

		return $response;
	}

	/**
	 * Choices for the filter menus: fields and organisations used by published experiences, the years
	 * and locations that occur.
	 *
	 * @return array{field: array<int, string>, organisation: array<int, string>, location: list<string>, year: list<int>}
	 */
	public static function choices(): array {
		$cached = get_transient( 'if_search_choices' );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;
		$choices = array(
			'field'        => array(),
			'organisation' => array(),
			'location'     => array(),
			'year'         => array(),
		);

		$terms = get_terms(
			array(
				'taxonomy'   => Content::TAXONOMY,
				'hide_empty' => true,
			)
		);
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				$kind = (string) get_term_meta( $term->term_id, Fields::PREFIX . 'kind', true );
				if ( 'field' === $kind || 'organisation' === $kind ) {
					$choices[ $kind ][ $term->term_id ] = $term->name;
				}
			}
		}
		asort( $choices['field'] );
		asort( $choices['organisation'] );

		$published = "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish'";
		$values    = static fn( string $meta_key ): array => array_filter(
			array_map(
				'strval',
				(array) $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND post_id IN ({$published})", Fields::PREFIX . $meta_key ) ) // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- cached in a transient; the subquery has no input.
			)
		);

		$places = array_unique( array_merge( $values( 'city' ), $values( 'country' ) ) );
		natcasesort( $places );
		$choices['location'] = array_values( $places );

		$years = array_map( static fn( string $month ): int => (int) substr( $month, 0, 4 ), $values( 'start_month' ) );
		$years = array_merge( $years, array_map( 'intval', $values( 'legacy_published_year' ) ) );
		$years = array_values( array_unique( array_filter( $years, static fn( int $year ): bool => $year >= 2000 ) ) );
		rsort( $years );
		$choices['year'] = $years;

		set_transient( 'if_search_choices', $choices, HOUR_IN_SECONDS );

		return $choices;
	}

	/**
	 * The trust strip's numbers: experiences shared, verified with proof, colleges contributing.
	 *
	 * @return array{experiences: int, verified: int, colleges: int}
	 */
	public static function counts(): array {
		$cached = get_transient( 'if_search_counts' );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;
		$archive = (int) get_option( Roles::ARCHIVE_OPTION );

		$counts = array(
			'experiences' => (int) wp_count_posts( 'post' )->publish,
			'verified'    => (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- cached in a transient.
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id
					WHERE m.meta_key = %s AND m.meta_value = 'verified' AND p.post_type = 'post' AND p.post_status = 'publish'",
					Fields::PREFIX . 'verification_status'
				)
			),
			'colleges'    => (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- cached in a transient.
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT LOWER(TRIM(um.meta_value))) FROM {$wpdb->usermeta} um
					INNER JOIN {$wpdb->posts} p ON p.post_author = um.user_id
					WHERE um.meta_key = %s AND um.meta_value <> '' AND p.post_type = 'post' AND p.post_status = 'publish' AND p.post_author <> %d",
					Fields::PREFIX . 'college',
					$archive
				)
			),
		);

		set_transient( 'if_search_counts', $counts, 10 * MINUTE_IN_SECONDS );

		return $counts;
	}

	/**
	 * Drop the cached counts and choices when an experience changes.
	 */
	public static function forget_counts(): void {
		delete_transient( 'if_search_counts' );
		delete_transient( 'if_search_choices' );
	}
}
