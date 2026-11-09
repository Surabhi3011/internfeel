<?php
/**
 * The moderation log: every admin decision and every reader report (architecture.md 4.5).
 *
 * @package InternFeel
 */

declare(strict_types=1);

namespace InternFeel\Moderation;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the {prefix}if_moderation_log table, which Schema step 1 creates.
 *
 * @phpstan-type Entry array{id: int, actor_id: int, target_type: string, target_id: int, action: string, reason: string, note: string, created_at: string}
 */
final class Log {

	/**
	 * The table name.
	 *
	 * @return string
	 */
	public static function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'if_moderation_log';
	}

	/**
	 * Record one decision or report.
	 *
	 * @param int    $actor_id    Admin, or reporting reader.
	 * @param string $target_type experience, comment, user, proof or referral.
	 * @param int    $target_id   ID of the target.
	 * @param string $action      approve, reject, request_changes, verify, reject_proof, report, ...
	 * @param string $reason      Short reason, such as the field a suggestion belonged to.
	 * @param string $note        Note shown to the student, if any.
	 * @return int The new entry's ID, or 0 if it could not be written.
	 */
	public static function add( int $actor_id, string $target_type, int $target_id, string $action, string $reason = '', string $note = '' ): int {
		global $wpdb;

		$written = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom table.
			self::table(),
			array(
				'actor_id'    => $actor_id,
				'target_type' => substr( $target_type, 0, 20 ),
				'target_id'   => $target_id,
				'action'      => substr( $action, 0, 40 ),
				'reason'      => '' === $reason ? null : substr( $reason, 0, 255 ),
				'note'        => '' === $note ? null : $note,
				'created_at'  => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		return false === $written ? 0 : (int) $wpdb->insert_id;
	}

	/**
	 * Whether someone has already taken an action on a target, such as reporting it.
	 *
	 * @param int    $actor_id    User.
	 * @param string $target_type Target type.
	 * @param int    $target_id   Target ID.
	 * @param string $action      Action.
	 * @return bool
	 */
	public static function exists( int $actor_id, string $target_type, int $target_id, string $action ): bool {
		global $wpdb;

		$table = self::table();

		return null !== $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table; uses the actor index.
			$wpdb->prepare( "SELECT id FROM {$table} WHERE actor_id = %d AND action = %s AND target_type = %s AND target_id = %d LIMIT 1", $actor_id, $action, $target_type, $target_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix.
		);
	}

	/**
	 * Entries about one target, newest first.
	 *
	 * @param string $target_type Target type.
	 * @param int    $target_id   Target ID.
	 * @return list<array<string, mixed>>
	 * @phpstan-return list<Entry>
	 */
	public static function for_target( string $target_type, int $target_id ): array {
		global $wpdb;

		$table = self::table();
		$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table, admin screens only.
			$wpdb->prepare( "SELECT * FROM {$table} WHERE target_type = %s AND target_id = %d ORDER BY id DESC", $target_type, $target_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix.
			ARRAY_A
		);

		return self::entries( $rows );
	}

	/**
	 * The latest entries, newest first.
	 *
	 * @param int $limit How many.
	 * @return list<array<string, mixed>>
	 * @phpstan-return list<Entry>
	 */
	public static function recent( int $limit = 100 ): array {
		global $wpdb;

		$table = self::table();
		$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table, admin screens only.
			$wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix.
			ARRAY_A
		);

		return self::entries( $rows );
	}

	/**
	 * Typed rows.
	 *
	 * @param mixed $rows Rows from $wpdb.
	 * @return list<array<string, mixed>>
	 * @phpstan-return list<Entry>
	 */
	private static function entries( mixed $rows ): array {
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$entries = array();
		foreach ( $rows as $row ) {
			$entries[] = array(
				'id'          => (int) $row['id'],
				'actor_id'    => (int) $row['actor_id'],
				'target_type' => (string) $row['target_type'],
				'target_id'   => (int) $row['target_id'],
				'action'      => (string) $row['action'],
				'reason'      => (string) $row['reason'],
				'note'        => (string) $row['note'],
				'created_at'  => (string) $row['created_at'],
			);
		}

		return $entries;
	}
}
