<?php
/**
 * The Submit module: drafts, autosave and submitting for review (architecture.md 3.2 and 6.2).
 *
 * @package InternFeel
 */

declare(strict_types=1);

namespace InternFeel\Submit;

use InternFeel\Content\Pages;
use InternFeel\Fields\Fields;
use InternFeel\Module;
use InternFeel\Support\RateLimit;
use InternFeel\Support\Rest;
use InternFeel\Verify\Proofs;

defined( 'ABSPATH' ) || exit;

/**
 * POST experiences (new draft), GET, PATCH and DELETE experiences/{id} (read, save, submit, delete a draft).
 */
final class Submit implements Module {

	public const DRAFT_LIMIT  = 20;
	public const DRAFT_WINDOW = HOUR_IN_SECONDS;

	/**
	 * Add the module's hooks.
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	/**
	 * Register the routes.
	 */
	public static function routes(): void {
		register_rest_route(
			Rest::NS,
			'/experiences',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'create' ),
				'permission_callback' => array( self::class, 'can_submit' ),
			)
		);

		register_rest_route(
			Rest::NS,
			'/experiences/(?P<id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'read' ),
					'permission_callback' => array( self::class, 'can_edit_draft' ),
				),
				array(
					'methods'             => 'PATCH',
					'callback'            => array( self::class, 'update' ),
					'permission_callback' => array( self::class, 'can_edit_draft' ),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( self::class, 'delete' ),
					'permission_callback' => array( self::class, 'can_edit_draft' ),
				),
			)
		);
	}

	/**
	 * Students (and admins testing the form) may start an experience.
	 *
	 * @return bool|\WP_Error
	 */
	public static function can_submit(): bool|\WP_Error {
		if ( ! is_user_logged_in() ) {
			return Rest::error( 'if_not_logged_in', __( 'Please log in first.', 'internfeel' ), 401 );
		}

		return current_user_can( 'if_submit' ) || current_user_can( 'edit_posts' )
			? true
			: Rest::error( 'if_forbidden', __( 'Your account cannot submit experiences.', 'internfeel' ), 403 );
	}

	/**
	 * Only the author may read or change their own draft.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @return bool|\WP_Error
	 */
	public static function can_edit_draft( \WP_REST_Request $request ): bool|\WP_Error {
		$allowed = self::can_submit();
		if ( true !== $allowed ) {
			return $allowed;
		}

		$post = get_post( (int) $request->get_param( 'id' ) );
		if ( ! $post instanceof \WP_Post || 'post' !== $post->post_type || get_current_user_id() !== (int) $post->post_author ) {
			return Rest::error( 'if_not_found', __( 'That experience was not found.', 'internfeel' ), 404 );
		}
		if ( 'draft' !== $post->post_status ) {
			return Rest::error( 'if_not_editable', __( 'This experience has been submitted and can no longer be changed here.', 'internfeel' ), 409 );
		}

		return true;
	}

	/**
	 * Start a draft with whatever the form has so far.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function create( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$user_id = get_current_user_id();
		if ( ! RateLimit::attempt( 'draft', (string) $user_id, self::DRAFT_LIMIT, self::DRAFT_WINDOW ) ) {
			return Rest::error( 'if_rate_limited', __( 'You have started a lot of drafts in the last hour. Please continue one of them.', 'internfeel' ), 429 );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_status' => 'draft',
				'post_title'  => __( 'Draft experience', 'internfeel' ),
				'post_author' => $user_id,
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return Rest::error( 'if_draft_failed', __( 'Your draft could not be saved. Please try again.', 'internfeel' ), 500 );
		}

		update_post_meta( $post_id, Fields::PREFIX . 'verification_status', 'not_submitted' );
		Form::save( $post_id, $request->get_params() );

		return self::response( $post_id, 201 );
	}

	/**
	 * The draft's saved values.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @return \WP_REST_Response
	 */
	public static function read( \WP_REST_Request $request ): \WP_REST_Response {
		return self::response( (int) $request->get_param( 'id' ) );
	}

	/**
	 * Save the draft; with submit set, check it and send it for review.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function update( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$post_id = (int) $request->get_param( 'id' );
		Form::save( $post_id, $request->get_params() );

		if ( ! rest_sanitize_boolean( (string) $request->get_param( 'submit' ) ) ) {
			return self::response( $post_id );
		}

		$valid = Form::validate( $post_id );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		if ( ! rest_sanitize_boolean( (string) $request->get_param( 'guidelines' ) ) ) {
			$error = new \WP_Error(
				'if_guidelines',
				__( 'Please confirm that your experience follows the content guidelines.', 'internfeel' ),
				array(
					'status' => 400,
					'field'  => 'guidelines',
					'step'   => 4,
				)
			);
			return $error;
		}

		self::submit( $post_id );

		return self::response( $post_id );
	}

	/**
	 * Delete a draft and its proof files. Only drafts: can_edit_draft() refuses anything submitted.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @return \WP_REST_Response
	 */
	public static function delete( \WP_REST_Request $request ): \WP_REST_Response {
		$post_id = (int) $request->get_param( 'id' );
		foreach ( Proofs::all( $post_id ) as $proof ) {
			Proofs::delete( $post_id, $proof['file'] );
		}
		wp_delete_post( $post_id, true );

		return new \WP_REST_Response( array( 'deleted' => true ), 200 );
	}

	/**
	 * Send a complete draft for review: build its title, story and excerpt, credit the referrer once,
	 * and move it to Pending review.
	 *
	 * @param int $post_id Draft experience ID.
	 */
	public static function submit( int $post_id ): void {
		$author = (int) get_post_field( 'post_author', $post_id );

		// The referrer is set once, at the first submission, and never changes (architecture.md 5.1).
		// metadata_exists(), because the registered default (0) makes "never set" read as 0.
		if ( ! metadata_exists( 'post', $post_id, Fields::PREFIX . 'referrer_user_id' ) ) {
			$referrer = (int) get_user_meta( $author, Fields::PREFIX . 'referred_by_user_id', true );
			update_post_meta( $post_id, Fields::PREFIX . 'referrer_user_id', $referrer );
		}

		// The review queue measures the 24-hour promise from here.
		update_post_meta( $post_id, Fields::PREFIX . 'submitted_at', current_time( 'mysql', true ) );
		delete_post_meta( $post_id, Fields::PREFIX . 'moderation_note' );

		wp_update_post(
			wp_slash(
				array_merge(
					Form::render( $post_id ),
					array(
						'ID'          => $post_id,
						'post_status' => 'pending',
					)
				)
			)
		);
	}

	/**
	 * Where the form lives, optionally continuing a draft.
	 *
	 * @param int $post_id Draft to continue, or 0.
	 * @return string
	 */
	public static function url( int $post_id = 0 ): string {
		$url = Pages::url( 'submit' );

		return $post_id > 0 ? add_query_arg( 'id', $post_id, $url ) : $url;
	}

	/**
	 * The draft as the form needs it.
	 *
	 * @param int $post_id Experience ID.
	 * @param int $status  HTTP status.
	 * @return \WP_REST_Response
	 */
	private static function response( int $post_id, int $status = 200 ): \WP_REST_Response {
		return new \WP_REST_Response(
			array(
				'id'       => $post_id,
				'status'   => get_post_status( $post_id ),
				'saved_at' => gmdate( 'c' ),
				'values'   => Form::values( $post_id ),
				'proofs'   => Proofs::list_for( $post_id ),
			),
			$status
		);
	}
}
