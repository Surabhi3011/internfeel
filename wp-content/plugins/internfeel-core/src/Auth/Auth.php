<?php
/**
 * Accounts: sign-up and sign-in on theme pages, the student role, wp-admin lock-out and the
 * password-reset sender (architecture.md 3.2 and 5.2).
 *
 * @package InternFeel
 */

declare(strict_types=1);

namespace InternFeel\Auth;

use InternFeel\Module;

defined( 'ABSPATH' ) || exit;

/**
 * The Auth module.
 */
final class Auth implements Module {

	/**
	 * Add the module's hooks.
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( Routes::class, 'register' ) );
		add_action( 'admin_init', array( self::class, 'keep_students_out_of_admin' ) );
		add_filter( 'show_admin_bar', array( self::class, 'admin_bar_for_staff_only' ) );
		add_filter( 'authenticate', array( self::class, 'block_archive_user' ), 100 );
		add_action( 'template_redirect', array( self::class, 'require_profile' ) );
		add_filter( 'wp_mail_from', array( self::class, 'mail_from' ) );
		add_filter( 'wp_mail_from_name', array( self::class, 'mail_from_name' ) );
	}

	/**
	 * Students never open wp-admin: send them to the site. AJAX and admin-post requests still work.
	 */
	public static function keep_students_out_of_admin(): void {
		global $pagenow;

		if ( wp_doing_ajax() || 'admin-post.php' === $pagenow || ! is_user_logged_in() || current_user_can( 'edit_posts' ) ) {
			return;
		}

		wp_safe_redirect( Profile::home_url() );
		exit;
	}

	/**
	 * Only staff see the admin bar.
	 *
	 * @param bool $show Whether WordPress would show it.
	 * @return bool
	 */
	public static function admin_bar_for_staff_only( bool $show ): bool {
		return current_user_can( 'edit_posts' ) ? $show : false;
	}

	/**
	 * The InternFeel Archive user owns migrated experiences and must never sign in.
	 *
	 * @param mixed $user The result of earlier authentication: a user, an error or null. Typed loosely
	 *                    because other plugins' authenticate filters run first.
	 * @return mixed
	 */
	public static function block_archive_user( mixed $user ): mixed {
		if ( $user instanceof \WP_User && Roles::has( $user, Roles::ARCHIVE ) ) {
			return new \WP_Error( 'if_archive_login', __( 'This account cannot sign in.', 'internfeel' ) );
		}

		return $user;
	}

	/**
	 * Students who have not finished their profile (including new Google sign-ups) are sent to the
	 * profile step first.
	 */
	public static function require_profile(): void {
		if ( ! is_user_logged_in() || current_user_can( 'edit_posts' ) || is_feed() || is_robots() ) {
			return;
		}
		if ( is_page( array( 'signup', 'login' ) ) || Profile::is_complete( get_current_user_id() ) ) {
			return;
		}

		wp_safe_redirect( Profile::url() );
		exit;
	}

	/**
	 * Sender address of the password-reset email, the only email the site sends.
	 *
	 * @param string $from WordPress's default sender.
	 * @return string
	 */
	public static function mail_from( string $from ): string {
		$configured = defined( 'IF_MAIL_FROM' ) ? (string) constant( 'IF_MAIL_FROM' ) : '';

		return is_email( $configured ) ? $configured : $from;
	}

	/**
	 * Sender name of the password-reset email.
	 *
	 * @param string $name WordPress's default sender name.
	 * @return string
	 */
	public static function mail_from_name( string $name ): string {
		$configured = defined( 'IF_MAIL_FROM_NAME' ) ? trim( (string) constant( 'IF_MAIL_FROM_NAME' ) ) : '';

		return '' === $configured ? $name : $configured;
	}
}
