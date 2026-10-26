<?php
/**
 * Dashboard notifications: the header bell and the dashboard list (plan.md 5.1). There are no
 * notification emails.
 *
 * @package InternFeel
 */

declare(strict_types=1);

namespace InternFeel\Notify;

use InternFeel\Fields\Fields;
use InternFeel\Module;
use InternFeel\Support\Rest;

defined( 'ABSPATH' ) || exit;

/**
 * Stores the latest notifications per student as JSON in the _if_notifications user meta.
 *
 * @phpstan-type Item array{id: string, type: string, message: string, link: string, key: string, created_at: string, read: bool}
 */
final class Notify implements Module {

	public const META = Fields::PREFIX . 'notifications';
	public const MAX  = 100;

	/**
	 * Every kind of event that raises a notification.
	 */
	public const TYPES = array(
		'submission_approved',
		'changes_requested',
		'submission_rejected',
		'proof_verified',
		'proof_rejected',
		'new_comment',
		'invite_used',
		'invite_published',
	);

	/**
	 * Add the module's hooks.
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}

	/**
	 * Add a notification to a user's dashboard.
	 *
	 * @param int    $user_id Recipient.
	 * @param string $type    One of self::TYPES.
	 * @param string $message Plain-text message.
	 * @param string $link    Optional link to a page on this site.
	 * @param string $key     Optional event key, such as "approved:123". A second notification with the
	 *                        same key is ignored, so each event creates exactly one notification.
	 * @return bool True when a notification was added.
	 */
	public static function add( int $user_id, string $type, string $message, string $link = '', string $key = '' ): bool {
		if ( $user_id <= 0 || ! in_array( $type, self::TYPES, true ) || ! get_userdata( $user_id ) instanceof \WP_User ) {
			return false;
		}

		$items = self::all( $user_id );
		if ( '' !== $key ) {
			foreach ( $items as $item ) {
				if ( $key === $item['key'] ) {
					return false;
				}
			}
		}

		$link = '' === $link ? '' : wp_validate_redirect( esc_url_raw( $link ), '' );

		array_unshift(
			$items,
			array(
				'id'         => wp_generate_uuid4(),
				'type'       => $type,
				'message'    => sanitize_text_field( $message ),
				'link'       => $link,
				'key'        => $key,
				'created_at' => gmdate( 'c' ),
				'read'       => false,
			)
		);

		self::save( $user_id, array_slice( $items, 0, self::MAX ) );

		return true;
	}

	/**
	 * A user's notifications, newest first.
	 *
	 * @param int $user_id User ID.
	 * @return list<Item>
	 */
	public static function all( int $user_id ): array {
		$raw  = get_user_meta( $user_id, self::META, true );
		$data = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : array();
		if ( ! is_array( $data ) ) {
			return array();
		}

		$items = array();
		foreach ( $data as $entry ) {
			if ( ! is_array( $entry ) || ! isset( $entry['id'], $entry['type'] ) ) {
				continue;
			}
			$items[] = array(
				'id'         => (string) $entry['id'],
				'type'       => (string) $entry['type'],
				'message'    => (string) ( $entry['message'] ?? '' ),
				'link'       => (string) ( $entry['link'] ?? '' ),
				'key'        => (string) ( $entry['key'] ?? '' ),
				'created_at' => (string) ( $entry['created_at'] ?? '' ),
				'read'       => (bool) ( $entry['read'] ?? false ),
			);
		}

		return $items;
	}

	/**
	 * How many notifications the user has not seen yet (the number on the bell).
	 *
	 * @param int $user_id User ID.
	 * @return int
	 */
	public static function unread_count( int $user_id ): int {
		return count( array_filter( self::all( $user_id ), static fn( array $item ): bool => ! $item['read'] ) );
	}

	/**
	 * Mark notifications as read: the given IDs, or all of them.
	 *
	 * @param int          $user_id User ID.
	 * @param list<string> $ids     Notification IDs; empty means all.
	 */
	public static function mark_read( int $user_id, array $ids = array() ): void {
		$items   = self::all( $user_id );
		$changed = false;
		foreach ( $items as $index => $item ) {
			if ( ! $item['read'] && ( array() === $ids || in_array( $item['id'], $ids, true ) ) ) {
				$items[ $index ]['read'] = true;
				$changed                 = true;
			}
		}

		if ( $changed ) {
			self::save( $user_id, $items );
		}
	}

	/**
	 * Register GET me/notifications and POST me/notifications/read.
	 */
	public static function register_routes(): void {
		register_rest_route(
			Rest::NS,
			'/me/notifications',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'rest_list' ),
				'permission_callback' => array( Rest::class, 'require_login' ),
			)
		);

		register_rest_route(
			Rest::NS,
			'/me/notifications/read',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'rest_mark_read' ),
				'permission_callback' => array( Rest::class, 'require_login' ),
				'args'                => array(
					'ids' => array(
						'type'    => 'array',
						'items'   => array( 'type' => 'string' ),
						'default' => array(),
					),
				),
			)
		);
	}

	/**
	 * GET me/notifications: the unread count and the list (without internal event keys).
	 *
	 * @return \WP_REST_Response
	 */
	public static function rest_list(): \WP_REST_Response {
		$user_id = get_current_user_id();
		$items   = array_map(
			static function ( array $item ): array {
				unset( $item['key'] );
				return $item;
			},
			self::all( $user_id )
		);

		return new \WP_REST_Response(
			array(
				'unread' => self::unread_count( $user_id ),
				'items'  => $items,
			),
			200
		);
	}

	/**
	 * POST me/notifications/read: opening the list marks everything read, or only the given IDs.
	 *
	 * @param \WP_REST_Request<array<string, mixed>> $request The request.
	 * @return \WP_REST_Response
	 */
	public static function rest_mark_read( \WP_REST_Request $request ): \WP_REST_Response {
		$user_id = get_current_user_id();
		$ids     = $request->get_param( 'ids' );
		$ids     = is_array( $ids ) ? array_values( array_filter( $ids, 'is_string' ) ) : array();

		self::mark_read( $user_id, $ids );

		return new \WP_REST_Response( array( 'unread' => self::unread_count( $user_id ) ), 200 );
	}

	/**
	 * Store the list.
	 *
	 * @param int        $user_id User ID.
	 * @param list<Item> $items   Notifications, newest first.
	 */
	private static function save( int $user_id, array $items ): void {
		// update_user_meta() unslashes its value, which would strip JSON's escapes, so slash it first.
		update_user_meta( $user_id, self::META, wp_slash( (string) wp_json_encode( array_values( $items ) ) ) );
	}
}
