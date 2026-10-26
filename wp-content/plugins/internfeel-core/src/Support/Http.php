<?php
/**
 * Request helpers: the client's IP address and same-origin checks.
 *
 * @package InternFeel
 */

declare(strict_types=1);

namespace InternFeel\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Small helpers for incoming requests.
 */
final class Http {

	/**
	 * The client's IP address. LiteSpeed serves the site directly with no proxy in front, so REMOTE_ADDR
	 * is the address to trust; forwarded headers are ignored because clients can set them to anything.
	 *
	 * @return string IP address, or an empty string when it is unknown.
	 */
	public static function client_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		return false === filter_var( $ip, FILTER_VALIDATE_IP ) ? '' : $ip;
	}

	/**
	 * Whether a request was sent from a page on this site.
	 *
	 * Browsers send an Origin header with cross-site POSTs, so a different host means another site is
	 * submitting our form (login CSRF). Requests with neither Origin nor Referer are not from a browser
	 * page and carry no visitor's cookies, so they are allowed; rate limits still apply to them.
	 *
	 * @param \WP_REST_Request<array<string, mixed>> $request The REST request.
	 * @return bool
	 */
	public static function is_same_origin( \WP_REST_Request $request ): bool {
		$source = (string) $request->get_header( 'origin' );
		if ( '' === $source ) {
			$source = (string) $request->get_header( 'referer' );
		}
		if ( '' === $source ) {
			return true;
		}

		$home = wp_parse_url( home_url() );
		$from = wp_parse_url( $source );
		if ( ! is_array( $home ) || ! is_array( $from ) ) {
			return false;
		}

		return strtolower( $home['host'] ?? '' ) === strtolower( $from['host'] ?? '' )
			&& ( $home['port'] ?? null ) === ( $from['port'] ?? null );
	}
}
