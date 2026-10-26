<?php
/**
 * REST routes for sign-up, log-in and the student's profile (architecture.md 5.2 and 6.2).
 *
 * @package InternFeel
 */

declare(strict_types=1);

namespace InternFeel\Auth;

use InternFeel\Support\Http;
use InternFeel\Support\RateLimit;
use InternFeel\Support\Rest;

defined( 'ABSPATH' ) || exit;

/**
 * POST auth/signup, POST auth/login, GET and POST me.
 */
final class Routes {

	public const NONCE_ACTION = 'if_auth';
	public const HONEYPOT     = 'website';

	public const SIGNUP_LIMIT  = 10;
	public const SIGNUP_WINDOW = HOUR_IN_SECONDS;
	public const LOGIN_LIMIT   = 10;
	public const LOGIN_WINDOW  = 15 * MINUTE_IN_SECONDS;

	public const MIN_PASSWORD = 8;
	public const MAX_PASSWORD = 200;

	/**
	 * Register the routes.
	 */
	public static function register(): void {
		register_rest_route(
			Rest::NS,
			'/auth/signup',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'signup' ),
				'permission_callback' => array( self::class, 'check_form' ),
			)
		);

		register_rest_route(
			Rest::NS,
			'/auth/login',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'login' ),
				'permission_callback' => array( self::class, 'check_form' ),
			)
		);

		register_rest_route(
			Rest::NS,
			'/me',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'get_me' ),
					'permission_callback' => array( Rest::class, 'require_login' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( self::class, 'update_me' ),
					'permission_callback' => array( Rest::class, 'require_login' ),
				),
			)
		);
	}

	/**
	 * Permission check for the sign-up and log-in forms: same-origin request and a valid form nonce.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @return bool|\WP_Error
	 */
	public static function check_form( \WP_REST_Request $request ): bool|\WP_Error {
		if ( ! Http::is_same_origin( $request ) ) {
			return Rest::error( 'if_bad_origin', __( 'Please use the form on internfeel.com.', 'internfeel' ), 403 );
		}

		$nonce = $request->get_param( '_if_nonce' );
		if ( ! is_string( $nonce ) || false === wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return Rest::error( 'if_bad_nonce', __( 'This page has expired. Refresh it and try again.', 'internfeel' ), 403 );
		}

		return true;
	}

	/**
	 * Create a student account and sign it in. There is no email verification step (plan.md section 4).
	 *
	 * @param \WP_REST_Request $request The request.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function signup( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		if ( self::is_bot( $request ) ) {
			return Rest::error( 'if_rejected', __( 'Sign-up failed. Please try again.', 'internfeel' ) );
		}
		if ( ! RateLimit::attempt( 'signup', Http::client_ip(), self::SIGNUP_LIMIT, self::SIGNUP_WINDOW ) ) {
			return Rest::error( 'if_rate_limited', __( 'Too many sign-ups from this network. Please try again in an hour.', 'internfeel' ), 429 );
		}

		$email    = sanitize_email( self::param( $request, 'email' ) );
		$password = self::param( $request, 'password' );

		if ( ! is_email( $email ) ) {
			return Rest::error( 'if_invalid', __( 'Enter a valid email address.', 'internfeel' ), 400, 'email' );
		}
		if ( false !== email_exists( $email ) ) {
			return Rest::error( 'if_email_exists', __( 'An account with this email already exists. Log in instead, or reset your password.', 'internfeel' ), 409, 'email' );
		}

		$problem = self::password_problem( $password );
		if ( '' !== $problem ) {
			return Rest::error( 'if_invalid', $problem, 400, 'password' );
		}

		$user_id = wp_insert_user(
			array(
				'user_login'           => self::new_login(),
				'user_email'           => $email,
				'user_pass'            => $password,
				'role'                 => Roles::STUDENT,
				'display_name'         => __( 'InternFeel student', 'internfeel' ),
				'nickname'             => __( 'InternFeel student', 'internfeel' ),
				'show_admin_bar_front' => 'false',
			)
		);
		$user    = is_wp_error( $user_id ) ? false : get_userdata( $user_id );
		if ( ! $user instanceof \WP_User ) {
			return Rest::error( 'if_signup_failed', __( 'Sign-up failed. Please try again.', 'internfeel' ), 500 );
		}

		self::start_session( $user, true );

		return new \WP_REST_Response( array( 'redirect' => Profile::url() ), 201 );
	}

	/**
	 * Sign a student in with email (or username) and password. Admins use wp-login.php, where
	 * two-step sign-in applies.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function login( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		if ( self::is_bot( $request ) ) {
			return Rest::error( 'if_rejected', __( 'Log-in failed. Please try again.', 'internfeel' ) );
		}
		if ( ! RateLimit::attempt( 'login', Http::client_ip(), self::LOGIN_LIMIT, self::LOGIN_WINDOW ) ) {
			return Rest::error( 'if_rate_limited', __( 'Too many log-in attempts. Please wait 15 minutes and try again.', 'internfeel' ), 429 );
		}

		$user = wp_authenticate( trim( self::param( $request, 'login' ) ), self::param( $request, 'password' ) );
		if ( ! $user instanceof \WP_User ) {
			return Rest::error( 'if_login_failed', __( 'That email and password do not match. Check them, or reset your password.', 'internfeel' ), 401 );
		}

		if ( user_can( $user, 'edit_posts' ) ) {
			return new \WP_Error(
				'if_use_admin_login',
				__( 'Admin accounts sign in through the WordPress log-in page.', 'internfeel' ),
				array(
					'status'    => 403,
					'login_url' => wp_login_url(),
				)
			);
		}

		self::start_session( $user, rest_sanitize_boolean( self::param( $request, 'remember' ) ) );

		$redirect = Profile::home_url();
		if ( ! Profile::is_complete( $user->ID ) ) {
			$redirect = Profile::url();
		} elseif ( '' !== self::param( $request, 'redirect_to' ) ) {
			$redirect = wp_validate_redirect( self::param( $request, 'redirect_to' ), $redirect );
		}

		return new \WP_REST_Response( array( 'redirect' => $redirect ), 200 );
	}

	/**
	 * The signed-in user's profile.
	 *
	 * @return \WP_REST_Response
	 */
	public static function get_me(): \WP_REST_Response {
		return new \WP_REST_Response( Profile::get( get_current_user_id() ), 200 );
	}

	/**
	 * Save the signed-in user's profile.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function update_me( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$user_id = get_current_user_id();
		$result  = Profile::save( $user_id, $request->get_params() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new \WP_REST_Response(
			array(
				'profile'  => Profile::get( $user_id ),
				'redirect' => Profile::home_url(),
			),
			200
		);
	}

	/**
	 * Why a password is not acceptable, or an empty string if it is.
	 *
	 * @param string $password The password.
	 * @return string
	 */
	public static function password_problem( string $password ): string {
		$length = mb_strlen( $password );
		if ( $length < self::MIN_PASSWORD ) {
			/* translators: %d: minimum number of characters. */
			return sprintf( __( 'Use at least %d characters for your password.', 'internfeel' ), self::MIN_PASSWORD );
		}
		if ( $length > self::MAX_PASSWORD ) {
			/* translators: %d: maximum number of characters. */
			return sprintf( __( 'Use at most %d characters for your password.', 'internfeel' ), self::MAX_PASSWORD );
		}

		return '';
	}

	/**
	 * Whether the hidden honeypot field was filled in, which only bots do.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @return bool
	 */
	private static function is_bot( \WP_REST_Request $request ): bool {
		return '' !== trim( self::param( $request, self::HONEYPOT ) );
	}

	/**
	 * A request parameter as a string.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @param string           $name    Parameter name.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @return string
	 */
	private static function param( \WP_REST_Request $request, string $name ): string {
		$value = $request->get_param( $name );

		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * A random username. Students sign in with their email; the username is never shown, so it is not
	 * derived from the email address.
	 *
	 * @return string
	 */
	private static function new_login(): string {
		do {
			$login = 'if-' . strtolower( wp_generate_password( 10, false, false ) );
		} while ( false !== username_exists( $login ) );

		return $login;
	}

	/**
	 * Set the auth cookie and run the usual log-in hooks (Wordfence and others listen to wp_login).
	 *
	 * @param \WP_User $user     The student.
	 * @param bool     $remember Whether to keep them signed in for 14 days.
	 */
	private static function start_session( \WP_User $user, bool $remember ): void {
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, $remember, is_ssl() );
		do_action( 'wp_login', $user->user_login, $user );
	}
}
