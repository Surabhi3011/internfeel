<?php
/**
 * The Dashboard module: the student's counters, experiences and comments (plan.md 5.1). The theme's
 * page-dashboard.php renders them; GET me/stats returns the counters.
 *
 * @package InternFeel
 */

declare(strict_types=1);

namespace InternFeel\Dashboard;

use InternFeel\Certificates\Certificates;
use InternFeel\Fields\Fields;
use InternFeel\Module;
use InternFeel\Submit\Form;
use InternFeel\Submit\Submit;
use InternFeel\Support\Rest;
use InternFeel\Verify\Proofs;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only views of a student's own data. Nothing here is public.
 *
 * @phpstan-type Stats array{published: int, views: int, comments: int, joined: int}
 * @phpstan-type Item array{id: int, title: string, status: string, proof: string, note: string, proof_note: string, organisation: string, programme: string, date: string, submitted: string, modified: string, views: int, comments: int, certificate: string, step: int, link: string, proofs: int}
 */
final class Dashboard implements Module {

	/**
	 * Statuses a student sees on their dashboard.
	 */
	public const STATUSES = array( 'draft', 'pending', 'publish', 'rejected', 'removed' );

	/**
	 * Add the module's hooks.
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
		add_filter( 'the_posts', array( self::class, 'own_preview' ), 10, 2 );
	}

	/**
	 * Let a student see their own experience while it waits for review. WordPress's preview needs
	 * editing rights, which students never get, so the dashboard links to ?p={id}&if_preview=1 and
	 * this puts the post back after WordPress has hidden it. Only the author, only in review.
	 *
	 * @param array<int, mixed> $posts Posts WordPress found.
	 * @param \WP_Query         $query The query.
	 * @return array<int, mixed>
	 */
	public static function own_preview( array $posts, \WP_Query $query ): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, checked against the author below.
		if ( array() !== $posts || ! $query->is_main_query() || empty( $_GET['if_preview'] ) ) {
			return $posts;
		}

		$post = get_post( (int) $query->get( 'p' ) );
		if ( ! $post instanceof \WP_Post || 'post' !== $post->post_type || 'pending' !== $post->post_status || get_current_user_id() !== (int) $post->post_author || 0 === get_current_user_id() ) {
			return $posts;
		}

		$query->is_404      = false;
		$query->is_single   = true;
		$query->is_singular = true;
		nocache_headers();
		add_filter( 'wp_robots', 'wp_robots_no_robots' );

		return array( $post );
	}

	/**
	 * Where the student sees an experience that is in review.
	 *
	 * @param int $post_id Experience ID.
	 * @return string
	 */
	public static function preview_url( int $post_id ): string {
		return add_query_arg(
			array(
				'p'          => $post_id,
				'if_preview' => 1,
			),
			home_url( '/' )
		);
	}

	/**
	 * Register the route.
	 */
	public static function routes(): void {
		register_rest_route(
			Rest::NS,
			'/me/stats',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => static fn(): \WP_REST_Response => new \WP_REST_Response( self::stats( get_current_user_id() ), 200 ),
				'permission_callback' => array( Rest::class, 'require_login' ),
			)
		);
	}

	/**
	 * The four counters.
	 *
	 * @param int $user_id Student.
	 * @return array<string, int>
	 * @phpstan-return Stats
	 */
	public static function stats( int $user_id ): array {
		global $wpdb;

		$published = (int) count_user_posts( $user_id, 'post', true );

		// One query for the views across every published experience.
		$views = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- dashboard counter.
			$wpdb->prepare(
				"SELECT COALESCE(SUM(CAST(m.meta_value AS UNSIGNED)), 0) FROM {$wpdb->postmeta} m
				INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id
				WHERE m.meta_key = %s AND p.post_author = %d AND p.post_type = 'post' AND p.post_status = 'publish'",
				Fields::PREFIX . 'view_count',
				$user_id
			)
		);

		// Comments from other people: the student's own replies are not "received".
		$comments = (int) get_comments(
			array(
				'post_author'    => $user_id,
				'author__not_in' => array( $user_id ),
				'post_type'      => 'post',
				'status'         => 'approve',
				'count'          => true,
			)
		);

		return array(
			'published' => $published,
			'views'     => $views,
			'comments'  => $comments,
			'joined'    => (int) get_user_meta( $user_id, Fields::PREFIX . 'invites_joined', true ),
		);
	}

	/**
	 * The student's experiences, newest change first, with what each card needs.
	 *
	 * @param int $user_id Student.
	 * @return list<array<string, mixed>>
	 * @phpstan-return list<Item>
	 */
	public static function experiences( int $user_id ): array {
		$posts = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => self::STATUSES,
				'author'         => $user_id,
				'posts_per_page' => 100,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);

		$items = array();
		foreach ( $posts as $post ) {
			$values  = Form::values( $post->ID );
			$valid   = 'draft' === $post->post_status ? Form::validate( $post->ID ) : true;
			$step    = is_wp_error( $valid ) ? (int) ( $valid->get_error_data()['step'] ?? 1 ) : 4;
			$items[] = array(
				'id'           => $post->ID,
				'title'        => get_the_title( $post ),
				'status'       => $post->post_status,
				'proof'        => (string) get_post_meta( $post->ID, Fields::PREFIX . 'verification_status', true ),
				'note'         => (string) get_post_meta( $post->ID, Fields::PREFIX . 'moderation_note', true ),
				'proof_note'   => (string) get_post_meta( $post->ID, Fields::PREFIX . 'verification_note', true ),
				'organisation' => (string) $values['organisation'],
				'programme'    => (string) $values['programme'],
				'date'         => $post->post_date_gmt,
				'submitted'    => (string) get_post_meta( $post->ID, Fields::PREFIX . 'submitted_at', true ),
				'modified'     => (string) get_post_modified_time( 'Y-m-d H:i:s', true, $post ),
				'views'        => (int) get_post_meta( $post->ID, Fields::PREFIX . 'view_count', true ),
				'comments'     => (int) $post->comment_count,
				'certificate'  => 'publish' === $post->post_status ? Certificates::number( $post->ID ) : '',
				'step'         => max( 1, min( 4, $step ) ),
				'link'         => 'draft' === $post->post_status ? Submit::url( $post->ID ) : ( 'publish' === $post->post_status ? (string) get_permalink( $post ) : self::preview_url( $post->ID ) ),
				'proofs'       => count( Proofs::all( $post->ID ) ),
			);
		}

		return $items;
	}

	/**
	 * Comments on the student's experiences, newest first.
	 *
	 * @param int $user_id Student.
	 * @param int $limit   How many.
	 * @return list<\WP_Comment>
	 */
	public static function comments( int $user_id, int $limit = 50 ): array {
		$comments = get_comments(
			array(
				'post_author' => $user_id,
				'post_type'   => 'post',
				'status'      => 'approve',
				'number'      => $limit,
				'orderby'     => 'comment_date_gmt',
				'order'       => 'DESC',
			)
		);

		return is_array( $comments ) ? array_values( array_filter( $comments, static fn( mixed $c ): bool => $c instanceof \WP_Comment ) ) : array();
	}
}
