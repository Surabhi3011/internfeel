<?php
/**
 * The Moderation module: the review queue, admin decisions and the moderation log (architecture.md 3.2).
 *
 * @package InternFeel
 */

declare(strict_types=1);

namespace InternFeel\Moderation;

use InternFeel\Auth\Roles;
use InternFeel\Module;
use InternFeel\Support\Rest;

defined( 'ABSPATH' ) || exit;

/**
 * POST moderation/{action} for scripts and tests, and the queue's form handler in wp-admin.
 */
final class Moderation implements Module {

	public const FORM_ACTION = 'if_moderate';

	/**
	 * Add the module's hooks.
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
		add_action( 'admin_menu', array( Queue::class, 'menu' ) );
		add_action( 'admin_post_' . self::FORM_ACTION, array( self::class, 'handle_form' ) );
	}

	/**
	 * Register the route.
	 */
	public static function routes(): void {
		register_rest_route(
			Rest::NS,
			'/moderation/(?P<action>[a-z_]+)',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'rest_decide' ),
				'permission_callback' => static fn(): bool|\WP_Error => current_user_can( Roles::MODERATE )
					? true
					: Rest::error( 'if_forbidden', __( 'Only admins can moderate.', 'internfeel' ), 403 ),
				'args'                => array(
					'id' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Apply a decision through REST.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function rest_decide( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$result = Decisions::apply(
			(string) $request->get_param( 'action' ),
			(int) $request->get_param( 'id' ),
			get_current_user_id(),
			(string) $request->get_param( 'note' ),
			(string) $request->get_param( 'field' ),
			(string) $request->get_param( 'name' )
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new \WP_REST_Response(
			array(
				'message' => $result,
				'status'  => get_post_status( (int) $request->get_param( 'id' ) ),
			),
			200
		);
	}

	/**
	 * Apply a decision from the queue's form, then go back to the queue with the result.
	 */
	public static function handle_form(): void {
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! current_user_can( Roles::MODERATE ) ) {
			wp_die( esc_html__( 'Only admins can moderate.', 'internfeel' ), 403 );
		}
		check_admin_referer( self::FORM_ACTION . '_' . $post_id );

		$text   = static fn( string $key ): string => isset( $_POST[ $key ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked above.
		$result = Decisions::apply( sanitize_key( $text( 'decision' ) ), $post_id, get_current_user_id(), $text( 'note' ), sanitize_key( $text( 'field' ) ), $text( 'name' ) );

		set_transient(
			'if_moderation_notice_' . get_current_user_id(),
			array(
				'ok'      => ! is_wp_error( $result ),
				'message' => is_wp_error( $result ) ? $result->get_error_message() : $result,
				'title'   => get_the_title( $post_id ),
			),
			MINUTE_IN_SECONDS
		);

		wp_safe_redirect( Queue::url( sanitize_key( $text( 'tab' ) ) ) );
		exit;
	}
}
