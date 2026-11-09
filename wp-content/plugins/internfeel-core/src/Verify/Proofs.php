<?php
/**
 * Proof files: stored privately, checked by real type, opened only by the author and admins
 * (plan.md 5.1 "Verify your experience", architecture.md 9.4).
 *
 * @package InternFeel
 */

declare(strict_types=1);

namespace InternFeel\Verify;

use InternFeel\Auth\Roles;
use InternFeel\Fields\Fields;
use InternFeel\Support\PrivateStorage;

defined( 'ABSPATH' ) || exit;

/**
 * Files live in IF_PRIVATE_DIR/proofs/{experience ID}/ under random names; the experience's
 * _if_proof_files meta lists them with the student's original file name.
 *
 * @phpstan-type Proof array{file: string, name: string, size: int, type: string, uploaded_at: string}
 */
final class Proofs {

	public const MAX_FILES = 3;
	public const MAX_BYTES = 5 * MB_IN_BYTES;

	/**
	 * Allowed real types (checked from the file's contents) and the extension each is stored with.
	 */
	public const TYPES = array(
		'application/pdf' => 'pdf',
		'image/jpeg'      => 'jpg',
		'image/png'       => 'png',
	);

	/**
	 * The experience's proofs, as the form and dashboard show them.
	 *
	 * @param int $post_id Experience ID.
	 * @return list<array<string, mixed>> Each with name, size, type, uploaded_at and url (the permission-checked download).
	 * @phpstan-return list<array{file: string, name: string, size: int, type: string, uploaded_at: string, url: string}>
	 */
	public static function list_for( int $post_id ): array {
		return array_map(
			static fn( array $proof ): array => $proof + array( 'url' => rest_url( "internfeel/v1/proof/{$post_id}/{$proof['file']}" ) ),
			self::all( $post_id )
		);
	}

	/**
	 * Store one uploaded file as proof for an experience.
	 *
	 * @param int    $post_id Experience ID.
	 * @param string $path    Path of the uploaded temporary file. The REST route checks it is a real upload.
	 * @param string $name    The student's file name, kept only for display.
	 * @return array<string, mixed>|\WP_Error The stored proof.
	 * @phpstan-return Proof|\WP_Error
	 */
	public static function store( int $post_id, string $path, string $name ): array|\WP_Error {
		$proofs = self::all( $post_id );
		if ( count( $proofs ) >= self::MAX_FILES ) {
			/* translators: %d: maximum number of files. */
			return new \WP_Error( 'if_proof_limit', sprintf( __( 'You can add at most %d proof files. Remove one first.', 'internfeel' ), self::MAX_FILES ), array( 'status' => 400 ) );
		}

		$size = (int) filesize( $path );
		if ( $size <= 0 || $size > self::MAX_BYTES ) {
			return new \WP_Error( 'if_proof_size', __( 'Each proof file must be smaller than 5 MB.', 'internfeel' ), array( 'status' => 400 ) );
		}

		$type = self::real_type( $path );
		if ( ! isset( self::TYPES[ $type ] ) ) {
			return new \WP_Error( 'if_proof_type', __( 'Proof must be a PDF, JPG or PNG file.', 'internfeel' ), array( 'status' => 400 ) );
		}

		$dir = self::dir( $post_id );
		if ( is_wp_error( $dir ) ) {
			return $dir;
		}

		$file   = wp_generate_password( 24, false, false ) . '.' . self::TYPES[ $type ];
		$target = $dir . '/' . $file;
		if ( ! copy( $path, $target ) ) {
			return new \WP_Error( 'if_proof_save', __( 'Your proof could not be saved. Please try again.', 'internfeel' ), array( 'status' => 500 ) );
		}
		chmod( $target, 0640 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- private storage permissions (architecture.md 7).

		$proof    = array(
			'file'        => $file,
			'name'        => mb_substr( sanitize_file_name( $name ), 0, 120 ),
			'size'        => $size,
			'type'        => $type,
			'uploaded_at' => gmdate( 'c' ),
		);
		$proofs[] = $proof;
		self::save( $post_id, $proofs );

		// Adding proof (again, after a rejection) puts the experience back in the verification queue.
		if ( 'verified' !== get_post_meta( $post_id, Fields::PREFIX . 'verification_status', true ) ) {
			update_post_meta( $post_id, Fields::PREFIX . 'verification_status', 'pending' );
		}

		return $proof;
	}

	/**
	 * Remove one proof file. Not possible once the experience is verified.
	 *
	 * @param int    $post_id Experience ID.
	 * @param string $file    Stored file name.
	 * @return true|\WP_Error
	 */
	public static function delete( int $post_id, string $file ): true|\WP_Error {
		if ( 'verified' === get_post_meta( $post_id, Fields::PREFIX . 'verification_status', true ) ) {
			return new \WP_Error( 'if_proof_locked', __( 'This experience is verified, so its proof is kept.', 'internfeel' ), array( 'status' => 409 ) );
		}

		$proofs = self::all( $post_id );
		$kept   = array_values( array_filter( $proofs, static fn( array $proof ): bool => $proof['file'] !== $file ) );
		if ( count( $kept ) === count( $proofs ) ) {
			return new \WP_Error( 'if_proof_not_found', __( 'That proof file was not found.', 'internfeel' ), array( 'status' => 404 ) );
		}

		$path = self::path( $post_id, $file );
		if ( null !== $path ) {
			wp_delete_file( $path );
		}
		self::save( $post_id, $kept );

		if ( array() === $kept ) {
			update_post_meta( $post_id, Fields::PREFIX . 'verification_status', 'not_submitted' );
		}

		return true;
	}

	/**
	 * Whether a user may open an experience's proof: its author, or an admin who verifies.
	 *
	 * @param int $post_id Experience ID.
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function can_view( int $post_id, int $user_id ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}

		return (int) get_post_field( 'post_author', $post_id ) === $user_id || user_can( $user_id, Roles::MODERATE );
	}

	/**
	 * Absolute path of a stored proof, or null if it is not one of this experience's files.
	 *
	 * @param int    $post_id Experience ID.
	 * @param string $file    Stored file name.
	 * @return string|null
	 */
	public static function path( int $post_id, string $file ): ?string {
		$known = array_column( self::all( $post_id ), 'file' );
		if ( ! in_array( $file, $known, true ) ) {
			return null;
		}

		$dir = self::dir( $post_id );
		if ( is_wp_error( $dir ) ) {
			return null;
		}

		$path = $dir . '/' . $file;

		return is_readable( $path ) ? $path : null;
	}

	/**
	 * The proof list from the experience's meta.
	 *
	 * @param int $post_id Experience ID.
	 * @return list<array<string, mixed>>
	 * @phpstan-return list<Proof>
	 */
	public static function all( int $post_id ): array {
		$decoded = json_decode( (string) get_post_meta( $post_id, Fields::PREFIX . 'proof_files', true ), true );
		if ( ! is_array( $decoded ) ) {
			return array();
		}

		$proofs = array();
		foreach ( $decoded as $item ) {
			if ( ! is_array( $item ) || ! is_string( $item['file'] ?? null ) || 1 !== preg_match( '/^[A-Za-z0-9]{24}\.(pdf|jpg|png)$/', $item['file'] ) ) {
				continue;
			}
			$proofs[] = array(
				'file'        => $item['file'],
				'name'        => (string) ( $item['name'] ?? $item['file'] ),
				'size'        => (int) ( $item['size'] ?? 0 ),
				'type'        => (string) ( $item['type'] ?? '' ),
				'uploaded_at' => (string) ( $item['uploaded_at'] ?? '' ),
			);
		}

		return $proofs;
	}

	/**
	 * The file's real type, read from its contents rather than its name.
	 *
	 * @param string $path File path.
	 * @return string MIME type, or an empty string.
	 */
	public static function real_type( string $path ): string {
		$info = finfo_open( FILEINFO_MIME_TYPE );
		if ( false === $info ) {
			return '';
		}
		$type = finfo_file( $info, $path );

		return false === $type ? '' : $type;
	}

	/**
	 * The experience's private folder, created if needed.
	 *
	 * @param int $post_id Experience ID.
	 * @return string|\WP_Error
	 */
	private static function dir( int $post_id ): string|\WP_Error {
		$base = PrivateStorage::dir( 'proofs' );
		if ( is_wp_error( $base ) ) {
			return $base;
		}

		$dir = $base . '/' . $post_id;
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return new \WP_Error( 'if_proof_dir', __( 'Proof storage is not available.', 'internfeel' ), array( 'status' => 500 ) );
		}
		chmod( $dir, 0750 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- private storage permissions (architecture.md 7).

		return $dir;
	}

	/**
	 * Store the proof list.
	 *
	 * @param int                              $post_id Experience ID.
	 * @param array<int, array<string, mixed>> $proofs  Proofs.
	 */
	private static function save( int $post_id, array $proofs ): void {
		update_post_meta( $post_id, Fields::PREFIX . 'proof_files', wp_slash( (string) wp_json_encode( array_values( $proofs ) ) ) );
	}
}
