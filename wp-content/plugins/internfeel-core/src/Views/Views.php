<?php
/**
 * The Views module: counts experience views from the page's script, because LiteSpeed serves cached
 * pages without running PHP (architecture.md 5.6).
 *
 * @package InternFeel
 */

declare(strict_types=1);

namespace InternFeel\Views;

use InternFeel\Fields\Fields;
use InternFeel\Module;
use InternFeel\Support\Rest;

defined( 'ABSPATH' ) || exit;

/**
 * POST view/{id}. Skips bots, the author and a browser that already counted this experience today
 * (cookie if_v_{id}); counts with one SQL UPDATE so simultaneous visits are not lost.
 */
final class Views implements Module {

	public const COOKIE = 'if_v_';

	/**
	 * Add the module's hooks.
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	/**
	 * Register the route.
	 */
	public static function routes(): void {
		register_rest_route(
			Rest::NS,
			'/view/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'rest_view' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * POST view/{id}.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @return \WP_REST_Response
	 */
	public static function rest_view( \WP_REST_Request $request ): \WP_REST_Response {
		$id    = (int) $request->get_param( 'id' );
		$agent = (string) $request->get_header( 'user_agent' );
		$seen  = isset( $_COOKIE[ self::COOKIE . $id ] );
		// The page is cached, so the script sends no REST nonce and WordPress treats the request as
		// signed out. Read the sign-in cookie directly, only to leave the author's own visits out.
		$user    = (int) wp_validate_auth_cookie( '', 'logged_in' );
		$counted = self::count( $id, $user, $agent, $seen );

		if ( $counted && ! headers_sent() ) {
			setcookie(
				self::COOKIE . $id,
				'1',
				array(
					'expires'  => time() + DAY_IN_SECONDS,
					'path'     => '/',
					'secure'   => is_ssl(),
					'httponly' => true,
					'samesite' => 'Lax',
				)
			);
		}

		$response = new \WP_REST_Response( array( 'counted' => $counted ), 200 );
		$response->header( 'Cache-Control', 'no-store' );

		return $response;
	}

	/**
	 * Count one view if it should be counted.
	 *
	 * @param int    $post_id Experience.
	 * @param int    $user_id Visitor, 0 when signed out.
	 * @param string $agent   User agent.
	 * @param bool   $seen    This browser already counted it today.
	 * @return bool Whether it was counted.
	 */
	public static function count( int $post_id, int $user_id, string $agent, bool $seen ): bool {
		if ( $seen || self::is_bot( $agent ) || 'post' !== get_post_type( $post_id ) || 'publish' !== get_post_status( $post_id ) ) {
			return false;
		}
		if ( $user_id > 0 && (int) get_post_field( 'post_author', $post_id ) === $user_id ) {
			return false;
		}

		global $wpdb;
		$key     = Fields::PREFIX . 'view_count';
		$updated = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- atomic counter; the cache is cleared below.
			$wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_value = meta_value + 1 WHERE post_id = %d AND meta_key = %s", $post_id, $key )
		);
		if ( 0 === $updated ) {
			// No counter row yet: create it (another request may have created it in the meantime).
			if ( ! add_post_meta( $post_id, $key, 1, true ) ) {
				$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_value = meta_value + 1 WHERE post_id = %d AND meta_key = %s", $post_id, $key ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- atomic counter.
			}
		}
		wp_cache_delete( $post_id, 'post_meta' );

		return true;
	}

	/**
	 * Crawlers, link previewers and headless browsers.
	 *
	 * @param string $agent User agent.
	 * @return bool
	 */
	public static function is_bot( string $agent ): bool {
		return '' === trim( $agent ) || 1 === preg_match( '/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp|headless|lighthouse|curl|wget|python|httpclient/i', $agent );
	}
}
