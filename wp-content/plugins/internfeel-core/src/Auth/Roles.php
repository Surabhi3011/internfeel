<?php
/**
 * Roles and the InternFeel Archive system user (architecture.md 4.1).
 *
 * @package InternFeel
 */

declare(strict_types=1);

namespace InternFeel\Auth;

defined( 'ABSPATH' ) || exit;

/**
 * Creates the student and archive roles, gives administrators the InternFeel capabilities, and makes
 * the system user that owns migrated experiences.
 */
final class Roles {

	public const STUDENT  = 'if_student';
	public const ARCHIVE  = 'if_archive';
	public const MODERATE = 'if_moderate';

	public const ARCHIVE_LOGIN  = 'internfeel-archive';
	public const ARCHIVE_OPTION = 'if_archive_user_id';

	/**
	 * Primitive capabilities of the if_list post type, granted to administrators only.
	 */
	private const LIST_CAPS = array(
		'edit_if_lists',
		'edit_others_if_lists',
		'edit_private_if_lists',
		'edit_published_if_lists',
		'publish_if_lists',
		'read_private_if_lists',
		'delete_if_lists',
		'delete_others_if_lists',
		'delete_private_if_lists',
		'delete_published_if_lists',
	);

	/**
	 * Create or update the roles and the archive user. Safe to run twice.
	 */
	public static function install(): void {
		// Students hold only read plus their own InternFeel capability; ownership is checked on every write.
		self::ensure_role(
			self::STUDENT,
			__( 'Student', 'internfeel' ),
			array(
				'read'      => true,
				'if_submit' => true,
			)
		);
		self::ensure_role( self::ARCHIVE, __( 'InternFeel Archive', 'internfeel' ), array( 'read' => true ) );

		$admin = get_role( 'administrator' );
		if ( $admin instanceof \WP_Role ) {
			$admin->add_cap( self::MODERATE );
			foreach ( self::LIST_CAPS as $cap ) {
				$admin->add_cap( $cap );
			}
		}

		// New accounts, including Google sign-ups through Nextend Social Login, become students.
		update_option( 'default_role', self::STUDENT );

		self::ensure_archive_user();
	}

	/**
	 * The ID of the InternFeel Archive user, creating it if needed. It has a random password and no
	 * email, and Auth refuses to sign it in.
	 *
	 * @return int User ID, or 0 if it could not be created.
	 */
	public static function ensure_archive_user(): int {
		$id = (int) get_option( self::ARCHIVE_OPTION, 0 );
		if ( $id > 0 && get_userdata( $id ) instanceof \WP_User ) {
			return $id;
		}

		$existing = get_user_by( 'login', self::ARCHIVE_LOGIN );
		if ( $existing instanceof \WP_User ) {
			$id = $existing->ID;
		} else {
			$id = wp_insert_user(
				array(
					'user_login'   => self::ARCHIVE_LOGIN,
					'user_pass'    => wp_generate_password( 64, true, true ),
					'user_email'   => '',
					'display_name' => 'InternFeel Archive',
					'nickname'     => 'InternFeel Archive',
					'role'         => self::ARCHIVE,
				)
			);
			if ( is_wp_error( $id ) ) {
				return 0;
			}
		}

		update_option( self::ARCHIVE_OPTION, $id, false );

		return $id;
	}

	/**
	 * Whether a user has a role.
	 *
	 * @param \WP_User $user User.
	 * @param string   $role Role name.
	 * @return bool
	 */
	public static function has( \WP_User $user, string $role ): bool {
		return in_array( $role, (array) $user->roles, true );
	}

	/**
	 * Add a role, or add any missing capabilities to an existing one.
	 *
	 * @param string              $name  Role name.
	 * @param string              $label Display name.
	 * @param array<string, bool> $caps  Capabilities.
	 */
	private static function ensure_role( string $name, string $label, array $caps ): void {
		$role = get_role( $name );
		if ( ! $role instanceof \WP_Role ) {
			add_role( $name, $label, $caps );
			return;
		}

		foreach ( $caps as $cap => $grant ) {
			$role->add_cap( $cap, $grant );
		}
	}
}
