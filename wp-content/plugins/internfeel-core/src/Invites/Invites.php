<?php
/**
 * The Invites module: credits, single-use invite links, the /join cookie and referral attribution
 * (plan.md 5.5, architecture.md 5.3).
 *
 * @package InternFeel
 */

declare(strict_types=1);

namespace InternFeel\Invites;

use InternFeel\Content\Pages;
use InternFeel\Fields\Fields;
use InternFeel\Moderation\Log;
use InternFeel\Module;
use InternFeel\Notify\Notify;
use InternFeel\Support\Rest;

defined( 'ABSPATH' ) || exit;

/**
 * Codes live in the inviter's _if_invite_codes JSON, and each code is also stored as one
 * _if_invite_code row so the owner can be found by code.
 *
 * @phpstan-type Invite array{code: string, created_at: string, ip: string, used_by: int, used_at: string}
 */
final class Invites implements Module {

	public const CREDITS_PER_EXPERIENCE = 3;
	public const COOKIE                 = 'if_ref';
	public const COOKIE_DAYS            = 30;
	public const INDEX_KEY              = Fields::PREFIX . 'invite_code';

	/**
	 * Same IP within this long of the invite being made: the sign-up is flagged for review.
	 */
	public const FLAG_WINDOW = DAY_IN_SECONDS;

	/**
	 * Add the module's hooks.
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
		add_action( 'if_experience_verified', array( self::class, 'grant' ), 10, 2 );
		add_action( 'template_redirect', array( self::class, 'remember_code' ) );
		add_action( 'user_register', array( self::class, 'attribute' ) );
	}

	/**
	 * Register the routes.
	 */
	public static function routes(): void {
		register_rest_route(
			Rest::NS,
			'/invites',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => static fn(): \WP_REST_Response => new \WP_REST_Response( self::summary( get_current_user_id() ), 200 ),
					'permission_callback' => array( Rest::class, 'require_login' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( self::class, 'rest_create' ),
					'permission_callback' => array( Rest::class, 'require_login' ),
				),
			)
		);
	}

	/**
	 * Three credits when an experience becomes published and verified (fired once per experience).
	 *
	 * @param int $post_id Experience ID.
	 * @param int $author  Its author.
	 */
	public static function grant( int $post_id, int $author ): void {
		self::change_credits( $author, self::CREDITS_PER_EXPERIENCE );
	}

	/**
	 * Add or remove credits, never going below 0.
	 *
	 * @param int $user_id Student.
	 * @param int $delta   Change.
	 * @return int The new balance.
	 */
	public static function change_credits( int $user_id, int $delta ): int {
		$credits = max( 0, self::credits( $user_id ) + $delta );
		update_user_meta( $user_id, Fields::PREFIX . 'invite_credits', $credits );

		return $credits;
	}

	/**
	 * Credits held.
	 *
	 * @param int $user_id Student.
	 * @return int
	 */
	public static function credits( int $user_id ): int {
		return max( 0, (int) get_user_meta( $user_id, Fields::PREFIX . 'invite_credits', true ) );
	}

	/**
	 * Spend one credit on a new single-use code.
	 *
	 * @param int    $user_id Student.
	 * @param string $ip      Their IP address, kept only as a salted hash for the abuse check.
	 * @return array<string, mixed>|\WP_Error The new invite.
	 * @phpstan-return Invite|\WP_Error
	 */
	public static function create( int $user_id, string $ip = '' ): array|\WP_Error {
		if ( self::credits( $user_id ) < 1 ) {
			return Rest::error( 'if_no_credits', __( 'You have no invites left. You earn 3 for every experience that is published and verified.', 'internfeel' ), 403 );
		}

		do {
			$code = strtoupper( wp_generate_password( 8, false ) );
		} while ( 0 !== self::owner( $code ) );

		$invite = array(
			'code'       => $code,
			'created_at' => current_time( 'mysql', true ),
			'ip'         => '' === $ip ? '' : wp_hash( $ip ),
			'used_by'    => 0,
			'used_at'    => '',
		);

		$invites   = self::all( $user_id );
		$invites[] = $invite;
		self::save( $user_id, $invites );
		add_user_meta( $user_id, self::INDEX_KEY, $code );
		self::change_credits( $user_id, -1 );

		return $invite;
	}

	/**
	 * POST invites: make one link.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function rest_create( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$invite = self::create( get_current_user_id(), self::ip() );
		if ( is_wp_error( $invite ) ) {
			return $invite;
		}

		return new \WP_REST_Response(
			array(
				'code' => $invite['code'],
				'link' => self::link( $invite['code'] ),
			) + self::summary( get_current_user_id() ),
			201
		);
	}

	/**
	 * The invites panel: credits, each link with who used it, and what they published.
	 *
	 * @param int $user_id Student.
	 * @return array{credits: int, joined: int, published: int, invites: list<array{code: string, link: string, used: bool, used_by: string, used_at: string, published: int}>}
	 */
	public static function summary( int $user_id ): array {
		$rows      = array();
		$published = 0;
		foreach ( array_reverse( self::all( $user_id ) ) as $invite ) {
			$friend     = $invite['used_by'] > 0 ? get_userdata( $invite['used_by'] ) : false;
			$count      = $friend instanceof \WP_User ? (int) count_user_posts( $friend->ID, 'post', true ) : 0;
			$rows[]     = array(
				'code'      => $invite['code'],
				'link'      => self::link( $invite['code'] ),
				'used'      => $invite['used_by'] > 0,
				'used_by'   => $friend instanceof \WP_User ? $friend->display_name : '',
				'used_at'   => $invite['used_at'],
				'published' => $count,
			);
			$published += $count;
		}

		return array(
			'credits'   => self::credits( $user_id ),
			'joined'    => (int) get_user_meta( $user_id, Fields::PREFIX . 'invites_joined', true ),
			'published' => $published,
			'invites'   => $rows,
		);
	}

	/**
	 * The student's invites.
	 *
	 * @param int $user_id Student.
	 * @return list<array<string, mixed>>
	 * @phpstan-return list<Invite>
	 */
	public static function all( int $user_id ): array {
		$decoded = json_decode( (string) get_user_meta( $user_id, Fields::PREFIX . 'invite_codes', true ), true );
		if ( ! is_array( $decoded ) ) {
			return array();
		}

		$invites = array();
		foreach ( $decoded as $item ) {
			if ( ! is_array( $item ) || ! is_string( $item['code'] ?? null ) ) {
				continue;
			}
			$invites[] = array(
				'code'       => $item['code'],
				'created_at' => (string) ( $item['created_at'] ?? '' ),
				'ip'         => (string) ( $item['ip'] ?? '' ),
				'used_by'    => (int) ( $item['used_by'] ?? 0 ),
				'used_at'    => (string) ( $item['used_at'] ?? '' ),
			);
		}

		return $invites;
	}

	/**
	 * Who made an invite code, or 0.
	 *
	 * @param string $code Invite code.
	 * @return int
	 */
	public static function owner( string $code ): int {
		if ( 1 !== preg_match( '/^[A-Z0-9]{8}$/', $code ) ) {
			return 0;
		}

		$users = get_users(
			array(
				'meta_key'   => self::INDEX_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- lookup by invite code.
				'meta_value' => $code, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- lookup by invite code.
				'number'     => 1,
				'fields'     => 'ID',
			)
		);

		return array() === $users ? 0 : (int) $users[0];
	}

	/**
	 * A code's invite record if it exists and is unused, else null.
	 *
	 * @param string $code Invite code.
	 * @return array{0: int, 1: array<string, mixed>}|null Owner and invite.
	 * @phpstan-return array{0: int, 1: Invite}|null
	 */
	public static function open_invite( string $code ): ?array {
		$owner = self::owner( $code );
		if ( 0 === $owner ) {
			return null;
		}
		foreach ( self::all( $owner ) as $invite ) {
			if ( $code === $invite['code'] && 0 === $invite['used_by'] ) {
				return array( $owner, $invite );
			}
		}

		return null;
	}

	/**
	 * On /join?ref=CODE, keep a valid, unused code in a cookie for 30 days. An inviter opening their
	 * own link is ignored (no self-referral).
	 */
	public static function remember_code(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a public invite link.
		$code = isset( $_GET['ref'] ) ? strtoupper( sanitize_key( wp_unslash( $_GET['ref'] ) ) ) : '';
		if ( '' === $code || ! is_page( 'join' ) ) {
			return;
		}

		$open = self::open_invite( $code );
		if ( null === $open || get_current_user_id() === $open[0] || headers_sent() ) {
			return;
		}

		setcookie(
			self::COOKIE,
			$code,
			array(
				'expires'  => time() + self::COOKIE_DAYS * DAY_IN_SECONDS,
				'path'     => self::cookie_path(),
				'domain'   => COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
		$_COOKIE[ self::COOKIE ] = $code;
	}

	/**
	 * When someone signs up (email or Google) holding an invite cookie: record the referral once, use
	 * up the code, count the join and tell the inviter. A sign-up from the inviter's own IP within 24
	 * hours of the invite is flagged for review.
	 *
	 * @param int $user_id New user.
	 */
	public static function attribute( int $user_id ): void {
		$code = isset( $_COOKIE[ self::COOKIE ] ) ? strtoupper( sanitize_key( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) ) : '';
		if ( '' === $code ) {
			return;
		}
		self::forget_cookie();

		$open = self::open_invite( $code );
		if ( null === $open || $open[0] === $user_id ) {
			return;
		}
		self::accept( $user_id, $open[0], $code, self::ip() );
	}

	/**
	 * Record that a new student joined through an inviter's code.
	 *
	 * @param int    $user_id New student.
	 * @param int    $inviter Inviter.
	 * @param string $code    Code used.
	 * @param string $ip      New student's IP address.
	 */
	public static function accept( int $user_id, int $inviter, string $code, string $ip ): void {
		if ( metadata_exists( 'user', $user_id, Fields::PREFIX . 'referred_by_user_id' ) ) {
			return;
		}

		$invites = self::all( $inviter );
		$flagged = false;
		foreach ( $invites as $index => $invite ) {
			if ( $code !== $invite['code'] || 0 !== $invite['used_by'] ) {
				continue;
			}
			$invites[ $index ]['used_by'] = $user_id;
			$invites[ $index ]['used_at'] = current_time( 'mysql', true );

			$recent  = time() - (int) strtotime( $invite['created_at'] . ' UTC' ) < self::FLAG_WINDOW;
			$flagged = $recent && '' !== $ip && '' !== $invite['ip'] && hash_equals( $invite['ip'], wp_hash( $ip ) );
		}
		self::save( $inviter, $invites );

		update_user_meta( $user_id, Fields::PREFIX . 'referred_by_user_id', $inviter );
		update_user_meta( $user_id, Fields::PREFIX . 'referred_at', current_time( 'mysql', true ) );
		update_user_meta( $inviter, Fields::PREFIX . 'invites_joined', (int) get_user_meta( $inviter, Fields::PREFIX . 'invites_joined', true ) + 1 );

		if ( $flagged ) {
			update_user_meta( $user_id, Fields::PREFIX . 'referral_flagged', 'same_ip' );
			Log::add( 0, 'referral', $user_id, 'flag', 'same_ip', $code );
		}

		Notify::add( $inviter, 'invite_used', __( 'A friend joined InternFeel through your invite.', 'internfeel' ), Pages::url( 'dashboard' ) . '#invites', "invite_used:{$code}" );
	}

	/**
	 * Remove a referral (an admin decision on a flagged sign-up): the student is no longer credited
	 * to the inviter, and the decision is logged. Experiences already submitted keep their referrer.
	 *
	 * @param int    $user_id Student who joined.
	 * @param int    $actor   Admin.
	 * @param string $note    Why.
	 * @return bool
	 */
	public static function remove_referral( int $user_id, int $actor, string $note = '' ): bool {
		$inviter = (int) get_user_meta( $user_id, Fields::PREFIX . 'referred_by_user_id', true );
		if ( $inviter <= 0 ) {
			return false;
		}

		delete_user_meta( $user_id, Fields::PREFIX . 'referred_by_user_id' );
		delete_user_meta( $user_id, Fields::PREFIX . 'referral_flagged' );
		update_user_meta( $inviter, Fields::PREFIX . 'invites_joined', max( 0, (int) get_user_meta( $inviter, Fields::PREFIX . 'invites_joined', true ) - 1 ) );
		Log::add( $actor, 'referral', $user_id, 'remove_referral', (string) $inviter, $note );

		return true;
	}

	/**
	 * Keep a flagged referral after review.
	 *
	 * @param int $user_id Student who joined.
	 * @param int $actor   Admin.
	 */
	public static function clear_flag( int $user_id, int $actor ): void {
		delete_user_meta( $user_id, Fields::PREFIX . 'referral_flagged' );
		Log::add( $actor, 'referral', $user_id, 'keep_referral' );
	}

	/**
	 * Remove one credit from a student (admin decision), logged.
	 *
	 * @param int    $user_id Student.
	 * @param int    $actor   Admin.
	 * @param string $note    Why.
	 * @return bool False when they had none.
	 */
	public static function remove_credit( int $user_id, int $actor, string $note = '' ): bool {
		if ( self::credits( $user_id ) < 1 ) {
			return false;
		}
		self::change_credits( $user_id, -1 );
		Log::add( $actor, 'user', $user_id, 'remove_credit', '', $note );

		return true;
	}

	/**
	 * The shareable link for a code.
	 *
	 * @param string $code Invite code.
	 * @return string
	 */
	public static function link( string $code ): string {
		return add_query_arg( 'ref', $code, home_url( '/join/' ) );
	}

	/**
	 * Whether the student has asked for their name to stay off the credit line.
	 *
	 * @param int $user_id Inviter.
	 * @return bool
	 */
	public static function hides_name( int $user_id ): bool {
		return '1' === (string) get_user_meta( $user_id, Fields::PREFIX . 'hide_invite_credit', true );
	}

	/**
	 * Store the invite list.
	 *
	 * @param int                              $user_id Student.
	 * @param array<int, array<string, mixed>> $invites Invites.
	 */
	private static function save( int $user_id, array $invites ): void {
		update_user_meta( $user_id, Fields::PREFIX . 'invite_codes', wp_slash( (string) wp_json_encode( array_values( $invites ) ) ) );
	}

	/**
	 * Expire the invite cookie.
	 */
	private static function forget_cookie(): void {
		unset( $_COOKIE[ self::COOKIE ] );
		if ( ! headers_sent() ) {
			setcookie( self::COOKIE, '', time() - HOUR_IN_SECONDS, self::cookie_path(), COOKIE_DOMAIN ? COOKIE_DOMAIN : '' );
		}
	}

	/**
	 * The visitor's IP address as the server saw it. Not stored, only hashed.
	 *
	 * @return string
	 */
	private static function ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		return false === filter_var( $ip, FILTER_VALIDATE_IP ) ? '' : $ip;
	}

	/**
	 * WordPress's cookie path, or the site root before it is defined.
	 *
	 * @return string
	 */
	private static function cookie_path(): string {
		$path = defined( 'COOKIEPATH' ) ? (string) constant( 'COOKIEPATH' ) : '';

		return '' === $path ? '/' : $path;
	}
}
