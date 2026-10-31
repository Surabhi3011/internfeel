<?php
/**
 * Database schema versioning (development.md section 8).
 *
 * @package InternFeel
 */

declare(strict_types=1);

namespace InternFeel;

use InternFeel\Auth\Roles;
use InternFeel\Certificates\Certificates;
use InternFeel\Content\Pages;

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
	 * Run every step newer than the stored version. Called on activation and on init (after the
	 * content types are registered, so rewrite rules can be flushed).
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
	 * The current schema version, for tests and diagnostics.
	 *
	 * @return int
	 */
	public static function latest(): int {
		return (int) max( array_keys( self::steps() ) );
	}

	/**
	 * Upgrade steps keyed by schema version.
	 *
	 * @return array<int, callable(): void>
	 */
	private static function steps(): array {
		return array(
			// Day 2: data model, roles, archive user, sign-up and log-in pages, /slug/ permalinks.
			1 => static function (): void {
				self::create_moderation_log();
				Roles::install();
				Pages::ensure( 'signup', __( 'Sign up', 'internfeel' ) );
				Pages::ensure( 'login', __( 'Log in', 'internfeel' ) );
				self::use_slug_permalinks();
			},
			// Day 4: the submission form's page.
			2 => static function (): void {
				Pages::ensure( 'submit', __( 'Share your internship experience', 'internfeel' ) );
			},
			// Day 6: the dashboard page, and the /verify/{number}/ rewrite rules for certificates.
			3 => static function (): void {
				Pages::ensure( 'dashboard', __( 'Your dashboard', 'internfeel' ) );
				Certificates::rewrite();
				flush_rewrite_rules( false );
			},
			// Day 7: the experiences archive and search results page.
			4 => static function (): void {
				Pages::ensure( 'experiences', __( 'Internship experiences', 'internfeel' ) );
			},
			// Day 8: the invite landing page.
			5 => static function (): void {
				Pages::ensure( 'join', __( 'Join InternFeel', 'internfeel' ) );
			},
			// Day 9: the /lists/ archive and listicle pages need the rewrite rules refreshed once.
			6 => static function (): void {
				flush_rewrite_rules( false );
			},
			// Day 10: /org/, the index of organisations and their programmes.
			7 => static function (): void {
				Pages::ensure( 'org', __( 'Programmes', 'internfeel' ) );
			},
			// Day 11: legal and company pages as drafts, for an admin to complete and publish.
			8 => static function (): void {
				Pages::draft( 'privacy-policy', __( 'Privacy policy', 'internfeel' ), 'template-legal.php' );
				Pages::draft( 'terms', __( 'Terms of use', 'internfeel' ), 'template-legal.php' );
				Pages::draft( 'content-guidelines', __( 'Content guidelines', 'internfeel' ), 'template-legal.php' );
				Pages::draft( 'ad-guidelines', __( 'Ad guidelines', 'internfeel' ), 'template-legal.php' );
				Pages::draft( 'about', __( 'About InternFeel', 'internfeel' ) );
				Pages::draft( 'contact', __( 'Contact', 'internfeel' ) );
			},
		);
	}

	/**
	 * The moderation log: every admin decision and every reader report (architecture.md 4.5).
	 */
	private static function create_moderation_log(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = $wpdb->prefix . 'if_moderation_log';
		$collate = $wpdb->get_charset_collate();

		// dbDelta needs this exact layout: one column per line and two spaces after PRIMARY KEY.
		dbDelta(
			"CREATE TABLE {$table} (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
actor_id bigint(20) unsigned NOT NULL,
target_type varchar(20) NOT NULL,
target_id bigint(20) unsigned NOT NULL,
action varchar(40) NOT NULL,
reason varchar(255) NULL,
note text NULL,
created_at datetime NOT NULL,
PRIMARY KEY  (id),
KEY target (target_type,target_id),
KEY actor (actor_id,action)
) {$collate};"
		);
	}

	/**
	 * Experiences keep the old site's /slug/ URLs (plan.md section 4), so posts use /%postname%/.
	 */
	private static function use_slug_permalinks(): void {
		global $wp_rewrite;

		if ( '/%postname%/' !== get_option( 'permalink_structure' ) ) {
			$wp_rewrite->set_permalink_structure( '/%postname%/' );
		}
		flush_rewrite_rules( false );
	}
}
