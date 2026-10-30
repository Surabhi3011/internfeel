<?php
/**
 * The Comments module: edit once, and a dashboard notification for every new comment (plan.md 5.1).
 *
 * @package InternFeel
 */

declare(strict_types=1);

namespace InternFeel\Comments;

use InternFeel\Auth\Roles;
use InternFeel\Fields\Fields;
use InternFeel\Module;
use InternFeel\Notify\Notify;
use InternFeel\Support\Rest;

defined( 'ABSPATH' ) || exit;

/**
 * PATCH comments/{id}: the comment's author may change its text once; it then shows "edited".
 */
final class Comments implements Module {

	public const MAX_LENGTH = 5000;

	/**
	 * Add the module's hooks.
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
		add_action( 'wp_insert_comment', array( self::class, 'on_insert' ), 10, 2 );
		add_action( 'transition_comment_status', array( self::class, 'on_approve' ), 10, 3 );
	}

	/**
	 * Register the route.
	 */
	public static function routes(): void {
		register_rest_route(
			Rest::NS,
			'/comments/(?P<id>\d+)',
			array(
				'methods'             => 'PATCH',
				'callback'            => array( self::class, 'rest_edit' ),
				'permission_callback' => array( self::class, 'can_edit' ),
			)
		);
	}

	/**
	 * Only the comment's own author, once.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @return bool|\WP_Error
	 */
	public static function can_edit( \WP_REST_Request $request ): bool|\WP_Error {
		$allowed = Rest::require_login();
		if ( true !== $allowed ) {
			return $allowed;
		}

		$comment = get_comment( (int) $request->get_param( 'id' ) );
		if ( ! $comment instanceof \WP_Comment || get_current_user_id() !== (int) $comment->user_id ) {
			return Rest::error( 'if_not_found', __( 'That comment was not found.', 'internfeel' ), 404 );
		}
		if ( self::edited( (int) $comment->comment_ID ) ) {
			return Rest::error( 'if_edited_once', __( 'You have already edited this comment once, so it can’t be changed again.', 'internfeel' ), 409 );
		}

		return true;
	}

	/**
	 * Save the one edit.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function rest_edit( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id   = (int) $request->get_param( 'id' );
		$text = trim( sanitize_textarea_field( (string) $request->get_param( 'content' ) ) );
		if ( '' === $text ) {
			return Rest::error( 'if_required', __( 'Write something, or keep the comment as it is.', 'internfeel' ), 400, 'content' );
		}

		$updated = wp_update_comment(
			array(
				'comment_ID'      => $id,
				'comment_content' => mb_substr( $text, 0, self::MAX_LENGTH ),
			),
			true
		);
		if ( is_wp_error( $updated ) ) {
			return Rest::error( 'if_edit_failed', __( 'Your comment could not be saved. Please try again.', 'internfeel' ), 500 );
		}

		update_comment_meta( $id, Fields::PREFIX . 'edit_count', 1 );
		update_comment_meta( $id, Fields::PREFIX . 'edited_at', current_time( 'mysql', true ) );

		return new \WP_REST_Response(
			array(
				'id'      => $id,
				'content' => (string) apply_filters( 'comment_text', get_comment_text( $id ), get_comment( $id ), array() ),
				'edited'  => true,
			),
			200
		);
	}

	/**
	 * Whether a comment has used its one edit.
	 *
	 * @param int $comment_id Comment ID.
	 * @return bool
	 */
	public static function edited( int $comment_id ): bool {
		return (int) get_comment_meta( $comment_id, Fields::PREFIX . 'edit_count', true ) >= 1;
	}

	/**
	 * A new, already approved comment notifies the experience's author.
	 *
	 * @param int         $id      Comment ID.
	 * @param \WP_Comment $comment Comment.
	 */
	public static function on_insert( int $id, \WP_Comment $comment ): void {
		if ( '1' === (string) $comment->comment_approved ) {
			self::notify( $comment );
		}
	}

	/**
	 * A comment approved later (it was held for moderation) notifies too, once.
	 *
	 * @param string      $new_status New status.
	 * @param string      $old_status Old status.
	 * @param \WP_Comment $comment    Comment.
	 */
	public static function on_approve( string $new_status, string $old_status, \WP_Comment $comment ): void {
		if ( 'approved' === $new_status && 'approved' !== $old_status ) {
			self::notify( $comment );
		}
	}

	/**
	 * Tell the author about a comment on their experience, unless they wrote it. The event key makes
	 * it one notification per comment.
	 *
	 * @param \WP_Comment $comment Comment.
	 */
	private static function notify( \WP_Comment $comment ): void {
		$post_id = (int) $comment->comment_post_ID;
		$author  = (int) get_post_field( 'post_author', $post_id );
		if ( 'post' !== get_post_type( $post_id ) || $author <= 0 || (int) $comment->user_id === $author || (int) get_option( Roles::ARCHIVE_OPTION ) === $author ) {
			return;
		}

		Notify::add(
			$author,
			'new_comment',
			/* translators: 1: commenter's name, 2: experience title. */
			sprintf( __( '%1$s commented on “%2$s”.', 'internfeel' ), get_comment_author( $comment ), get_the_title( $post_id ) ),
			(string) get_comment_link( $comment ),
			'comment:' . $comment->comment_ID
		);
	}
}
