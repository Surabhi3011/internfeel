<?php
/**
 * Database schema versioning (development.md section 8).
 *
 * @package InternFeel
 */

declare(strict_types=1);

namespace InternFeel;

defined( 'ABSPATH' ) || exit;

/**
 * Runs pending upgrade steps in order and records the schema version in the option if_db_version.
 *
 * Steps are additive only (add tables and columns, never rename or drop them in the same release)
 * and must be safe to run twice.
 */
final class Schema {

	public const OPTION = 'if_db_version';

	/**
	 * Run every step newer than the stored version. Called on activation and on plugins_loaded.
	 */
	public static function upgrade(): void {
		$current = (int) get_option( self::OPTION, 0 );
		$steps   = self::steps();
		ksort( $steps );

		foreach ( $steps as $version => $step ) {
			if ( $version <= $current ) {
				continue;
			}

			$step();
			update_option( self::OPTION, $version );
		}
	}

	/**
	 * Upgrade steps keyed by schema version. The moderation log table arrives with the data model on day 2.
	 *
	 * @return array<int, callable(): void>
	 */
	private static function steps(): array {
		return array();
	}
}
