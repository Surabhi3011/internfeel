<?php
/**
 * The Reports module: readers report an experience or a comment once; three reports hide it from
 * listings until an admin decides (architecture.md 5.8).
 *
 * @package InternFeel
 */

declare(strict_types=1);

namespace InternFeel\Reports;

use InternFeel\Fields\Fields;
use InternFeel\Module;
use InternFeel\Moderation\Log;
use InternFeel\Support\RateLimit;
use InternFeel\Support\Rest;

defined( 'ABSPATH' ) || exit;

/**
 * POST report { type: experience|comment, id, reason, note }. Every report is a moderation log row,
 * which is also how "once per reader" is checked.
 */
final class Reports implements Module {

	public const FLAG_AT      = 3;
	public const RATE_LIMIT   = 20;
	public const RATE_WINDOW  = HOUR_IN_SECONDS;
	public const COUNT_KEY    = Fields::PREFIX . 'report_count';
	public const FLAGGED_KEY  = Fields::PREFIX . 'flagged';
	public const TARGET_TYPES = array( 'experience', 'comment' );

	/**
	 * Add the module's hooks.
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	/**
	 * Reasons a reader can pick.
	 *
	 * @return array<string, string>
	 */
	public static function reasons(): array {
		return array(
			'spam'      => __( 'Spam or advertising', 'internfeel' ),
			'abuse'     => __( 'Offensive or abusive', 'internfeel' ),
			'false'     => __( 'False or misleading', 'internfeel' ),
			'personal'  => __( 'Shares someone’s personal details', 'internfeel' ),
			'copyright' => __( 'Copied from somewhere else', 'internfeel' ),
			'other'     => __( 'Something else', 'internfeel' ),
		);
	}

	/**
	 * Register the route.
	 */
	public static function routes(): void {
		register_rest_route(
			Rest::NS,
			'/report',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'rest_report' ),
				'permission_callback' => array( Rest::class, 'require_login' ),
			)
		);
	}

	/**
	 * POST report.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function rest_report( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$user_id = get_current_user_id();
		if ( ! RateLimit::attempt( 'report', (string) $user_id, self::RATE_LIMIT, self::RATE_WINDOW ) ) {
			return Rest::error( 'if_rate_limited', __( 'You have sent a lot of reports in the last hour. Please try again later.', 'internfeel' ), 429 );
		}

		$result = self::report(
			$user_id,
			sanitize_key( (string) $request->get_param( 'type' ) ),
			(int) $request->get_param( 'id' ),
			sanitize_key( (string) $request->get_param( 'reason' ) ),
			sanitize_textarea_field( (string) $request->get_param( 'note' ) )
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new \WP_REST_Response( array( 'message' => __( 'Thanks. An admin will look at it.', 'internfeel' ) ), 201 );
	}

	/**
	 * Record one report.
	 *
	 * @param int    $user_id Reporting reader.
	 * @param string $type    experience or comment.
	 * @param int    $id      Target ID.
	 * @param string $reason  One of self::reasons().
	 * @param string $note    Optional details.
	 * @return true|\WP_Error
	 */
	public static function report( int $user_id, string $type, int $id, string $reason, string $note = '' ): true|\WP_Error {
		if ( ! in_array( $type, self::TARGET_TYPES, true ) || ! self::is_public( $type, $id ) ) {
			return Rest::error( 'if_not_found', __( 'That was not found.', 'internfeel' ), 404 );
		}
		if ( ! array_key_exists( $reason, self::reasons() ) ) {
			return Rest::error( 'if_required', __( 'Choose a reason.', 'internfeel' ), 400, 'reason' );
		}
		if ( self::owner( $type, $id ) === $user_id ) {
			return Rest::error( 'if_own', __( 'You can’t report your own writing. Edit or delete it instead.', 'internfeel' ), 400 );
		}
		if ( Log::exists( $user_id, $type, $id, 'report' ) ) {
			return Rest::error( 'if_already_reported', __( 'You have already reported this. An admin will look at it.', 'internfeel' ), 409 );
		}

		Log::add( $user_id, $type, $id, 'report', $reason, mb_substr( $note, 0, 1000 ) );
		$count = self::count( $type, $id ) + 1;
		self::set_meta( $type, $id, self::COUNT_KEY, $count );

		if ( $count >= self::FLAG_AT && ! self::is_flagged( $type, $id ) ) {
			self::set_meta( $type, $id, self::FLAGGED_KEY, true );
			if ( 'comment' === $type ) {
				// Held comments disappear from the page until an admin decides.
				wp_set_comment_status( $id, 'hold' );
			}
			Log::add( 0, $type, $id, 'flag', 'reports' );
			delete_transient( 'if_reports_flagged' );
		}

		return true;
	}

	/**
	 * An admin keeps the item: reports are cleared and it shows again.
	 *
	 * @param string $type  experience or comment.
	 * @param int    $id    Target ID.
	 * @param int    $actor Admin.
	 */
	public static function dismiss( string $type, int $id, int $actor ): void {
		$was_flagged = self::is_flagged( $type, $id );
		self::set_meta( $type, $id, self::COUNT_KEY, 0 );
		self::set_meta( $type, $id, self::FLAGGED_KEY, false );
		if ( 'comment' === $type && $was_flagged ) {
			wp_set_comment_status( $id, 'approve' );
		}
		Log::add( $actor, $type, $id, 'dismiss_reports' );
		delete_transient( 'if_reports_flagged' );
	}

	/**
	 * An admin upholds the reports: an experience moves to Removed (its certificate is revoked), a
	 * comment goes to the trash.
	 *
	 * @param string $type  experience or comment.
	 * @param int    $id    Target ID.
	 * @param int    $actor Admin.
	 * @param string $note  Reason, kept for the student.
	 */
	public static function remove( string $type, int $id, int $actor, string $note = '' ): void {
		if ( 'experience' === $type ) {
			wp_update_post(
				array(
					'ID'          => $id,
					'post_status' => 'removed',
				)
			);
			if ( '' !== $note ) {
				update_post_meta( $id, Fields::PREFIX . 'moderation_note', wp_slash( $note ) );
			}
		} else {
			wp_trash_comment( $id );
		}
		self::set_meta( $type, $id, self::FLAGGED_KEY, false );
		Log::add( $actor, $type, $id, 'remove', 'reports', $note );
		delete_transient( 'if_reports_flagged' );
	}

	/**
	 * Reports received.
	 *
	 * @param string $type experience or comment.
	 * @param int    $id   Target ID.
	 * @return int
	 */
	public static function count( string $type, int $id ): int {
		return (int) ( 'comment' === $type ? get_comment_meta( $id, self::COUNT_KEY, true ) : get_post_meta( $id, self::COUNT_KEY, true ) );
	}

	/**
	 * Whether the item is hidden pending a decision.
	 *
	 * @param string $type experience or comment.
	 * @param int    $id   Target ID.
	 * @return bool
	 */
	public static function is_flagged( string $type, int $id ): bool {
		return (bool) ( 'comment' === $type ? get_comment_meta( $id, self::FLAGGED_KEY, true ) : get_post_meta( $id, self::FLAGGED_KEY, true ) );
	}

	/**
	 * Only published experiences and approved comments on them can be reported.
	 *
	 * @param string $type experience or comment.
	 * @param int    $id   Target ID.
	 * @return bool
	 */
	private static function is_public( string $type, int $id ): bool {
		if ( 'comment' === $type ) {
			$comment = get_comment( $id );

			return $comment instanceof \WP_Comment && '1' === (string) $comment->comment_approved && 'publish' === get_post_status( (int) $comment->comment_post_ID );
		}

		return 'post' === get_post_type( $id ) && 'publish' === get_post_status( $id );
	}

	/**
	 * Who wrote the item.
	 *
	 * @param string $type experience or comment.
	 * @param int    $id   Target ID.
	 * @return int
	 */
	private static function owner( string $type, int $id ): int {
		if ( 'comment' === $type ) {
			$comment = get_comment( $id );

			return $comment instanceof \WP_Comment ? (int) $comment->user_id : 0;
		}

		return (int) get_post_field( 'post_author', $id );
	}

	/**
	 * Write post or comment meta.
	 *
	 * @param string          $type  experience or comment.
	 * @param int             $id    Target ID.
	 * @param string          $key   Meta key.
	 * @param int|bool|string $value Value.
	 */
	private static function set_meta( string $type, int $id, string $key, int|bool|string $value ): void {
		if ( 'comment' === $type ) {
			update_comment_meta( $id, $key, $value );
		} else {
			update_post_meta( $id, $key, $value );
		}
	}
}
