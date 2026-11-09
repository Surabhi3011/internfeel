<?php
/**
 * Shared REST API pieces: the namespace, error responses and permission checks.
 *
 * @package InternFeel
 */

declare(strict_types=1);

namespace InternFeel\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Helpers for the internfeel/v1 REST routes (architecture.md 6.2).
 */
final class Rest {

	public const NS = 'internfeel/v1';

	/**
	 * A REST error with an HTTP status and, optionally, the form field it belongs to.
	 *
	 * @param string $code    Machine-readable error code.
	 * @param string $message Message shown to the visitor.
	 * @param int    $status  HTTP status code.
	 * @param string $field   Name of the form field the error is about, if any.
	 * @return \WP_Error
	 */
	public static function error( string $code, string $message, int $status = 400, string $field = '' ): \WP_Error {
		$data = array( 'status' => $status );
		if ( '' !== $field ) {
			$data['field'] = $field;
		}

		return new \WP_Error( $code, $message, $data );
	}

	/**
	 * Permission callback for routes that need a signed-in user.
	 *
	 * @return bool|\WP_Error
	 */
	public static function require_login(): bool|\WP_Error {
		if ( is_user_logged_in() ) {
			return true;
		}

		return self::error( 'if_not_logged_in', __( 'Please log in first.', 'internfeel' ), 401 );
	}
}
