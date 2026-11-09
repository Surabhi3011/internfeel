<?php
/**
 * The Verify module's REST routes: upload, remove and download proof (architecture.md 6.2). Admin
 * decisions (verify or reject) arrive with the review queue.
 *
 * @package InternFeel
 */

declare(strict_types=1);

namespace InternFeel\Verify;

use InternFeel\Module;
use InternFeel\Support\RateLimit;
use InternFeel\Support\Rest;

defined( 'ABSPATH' ) || exit;

/**
 * POST experiences/{id}/proof, DELETE experiences/{id}/proof/{file}, GET proof/{id}/{file}.
 */
final class Verify implements Module {

	public const UPLOAD_LIMIT  = 15;
	public const UPLOAD_WINDOW = HOUR_IN_SECONDS;

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
			'/experiences/(?P<id>\d+)/proof',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'upload' ),
				'permission_callback' => array( self::class, 'is_author' ),
			)
		);

		register_rest_route(
			Rest::NS,
			'/experiences/(?P<id>\d+)/proof/(?P<file>[A-Za-z0-9]{24}\.(?:pdf|jpg|png))',
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( self::class, 'remove' ),
				'permission_callback' => array( self::class, 'is_author' ),
			)
		);

		register_rest_route(
			Rest::NS,
			'/proof/(?P<id>\d+)/(?P<file>[A-Za-z0-9]{24}\.(?:pdf|jpg|png))',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'download' ),
				'permission_callback' => array( self::class, 'can_view' ),
			)
		);
	}

	/**
	 * Only the experience's author may add or remove its proof.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @return bool|\WP_Error
	 */
	public static function is_author( \WP_REST_Request $request ): bool|\WP_Error {
		$post = get_post( (int) $request->get_param( 'id' ) );
		if ( ! is_user_logged_in() ) {
			return Rest::error( 'if_not_logged_in', __( 'Please log in first.', 'internfeel' ), 401 );
		}
		if ( ! $post instanceof \WP_Post || 'post' !== $post->post_type || get_current_user_id() !== (int) $post->post_author ) {
			return Rest::error( 'if_not_found', __( 'That experience was not found.', 'internfeel' ), 404 );
		}

		return true;
	}

	/**
	 * Only the author and admins may open a proof. Everyone else gets "not found", so the URL
	 * reveals nothing.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @return bool|\WP_Error
	 */
	public static function can_view( \WP_REST_Request $request ): bool|\WP_Error {
		if ( Proofs::can_view( (int) $request->get_param( 'id' ), get_current_user_id() ) ) {
			return true;
		}

		return Rest::error( 'if_not_found', __( 'That file was not found.', 'internfeel' ), 404 );
	}

	/**
	 * Store an uploaded proof file (form field "proof").
	 *
	 * @param \WP_REST_Request $request The request.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function upload( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$post_id = (int) $request->get_param( 'id' );
		if ( ! RateLimit::attempt( 'proof', (string) get_current_user_id(), self::UPLOAD_LIMIT, self::UPLOAD_WINDOW ) ) {
			return Rest::error( 'if_rate_limited', __( 'Too many uploads. Please try again later.', 'internfeel' ), 429 );
		}

		$files = $request->get_file_params();
		$file  = is_array( $files['proof'] ?? null ) ? $files['proof'] : array();
		$tmp   = (string) ( $file['tmp_name'] ?? '' );
		if ( UPLOAD_ERR_OK !== (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) || '' === $tmp || ! is_uploaded_file( $tmp ) ) {
			return Rest::error( 'if_proof_missing', __( 'Choose a file to upload.', 'internfeel' ), 400, 'proof' );
		}

		$stored = Proofs::store( $post_id, $tmp, (string) ( $file['name'] ?? '' ) );
		if ( is_wp_error( $stored ) ) {
			return $stored;
		}

		return new \WP_REST_Response( array( 'proofs' => Proofs::list_for( $post_id ) ), 201 );
	}

	/**
	 * Remove one proof file.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function remove( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$post_id = (int) $request->get_param( 'id' );
		$result  = Proofs::delete( $post_id, (string) $request->get_param( 'file' ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new \WP_REST_Response( array( 'proofs' => Proofs::list_for( $post_id ) ), 200 );
	}

	/**
	 * Stream a proof file as a download, never inline, so a crafted file cannot run in the site's origin.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @return \WP_Error Only on failure; on success the file is sent and the request ends.
	 */
	public static function download( \WP_REST_Request $request ): \WP_Error {
		$post_id = (int) $request->get_param( 'id' );
		$file    = (string) $request->get_param( 'file' );
		$path    = Proofs::path( $post_id, $file );
		if ( null === $path ) {
			return Rest::error( 'if_not_found', __( 'That file was not found.', 'internfeel' ), 404 );
		}

		$proof = current( array_filter( Proofs::all( $post_id ), static fn( array $p ): bool => $p['file'] === $file ) );
		$name  = is_array( $proof ) && '' !== $proof['name'] ? $proof['name'] : $file;

		nocache_headers();
		header( 'Content-Type: ' . ( is_array( $proof ) ? $proof['type'] : 'application/octet-stream' ) );
		header( 'Content-Disposition: attachment; filename="' . str_replace( '"', '', $name ) . '"' );
		header( 'Content-Length: ' . (string) filesize( $path ) );
		header( 'X-Content-Type-Options: nosniff' );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- streaming a private file after a permission check.
		exit;
	}
}
