<?php
/**
 * Fixed-window rate limits stored in transients (architecture.md 9.7).
 *
 * @package InternFeel
 */

declare(strict_types=1);

namespace InternFeel\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Counts attempts per action and subject (usually an IP address). Subjects are hashed, so no raw IP
 * address is stored.
 */
final class RateLimit {

	/**
	 * Count one attempt and report whether it is within the limit.
	 *
	 * @param string $action  What is being limited, such as "signup".
	 * @param string $subject Who is being limited, such as an IP address.
	 * @param int    $limit   Attempts allowed per window. Ten times higher on local environments, where
	 *                        every test run comes from the same address; filterable through if_rate_limit.
	 * @param int    $window  Window length in seconds.
	 * @return bool True when the attempt is allowed.
	 */
	public static function attempt( string $action, string $subject, int $limit, int $window ): bool {
		if ( 'local' === wp_get_environment_type() ) {
			$limit *= 10;
		}
		$limit = (int) apply_filters( 'if_rate_limit', $limit, $action );
		$key   = 'if_rl_' . substr( hash( 'sha256', $action . '|' . $subject ), 0, 32 );
		$state = get_transient( $key );
		$now   = time();
		$count = is_array( $state ) ? (int) ( $state['count'] ?? 0 ) : 0;
		$reset = is_array( $state ) ? (int) ( $state['reset'] ?? 0 ) : 0;

		if ( $reset <= $now ) {
			$count = 0;
			$reset = $now + $window;
		}

		if ( $count >= $limit ) {
			return false;
		}

		set_transient(
			$key,
			array(
				'count' => $count + 1,
				'reset' => $reset,
			),
			max( 1, $reset - $now )
		);

		return true;
	}
}
