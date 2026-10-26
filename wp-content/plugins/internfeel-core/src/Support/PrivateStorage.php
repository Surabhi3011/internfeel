<?php
/**
 * Private storage outside the web root (architecture.md 7: IF_PRIVATE_DIR, 0750 folders, 0640 files).
 *
 * @package InternFeel
 */

declare(strict_types=1);

namespace InternFeel\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Folders under IF_PRIVATE_DIR for proofs, certificates and import files.
 */
final class PrivateStorage {

	/**
	 * A sub-folder of private storage, created if needed.
	 *
	 * @param string $name Sub-folder, such as "import" or "proofs".
	 * @return string|\WP_Error Absolute path without a trailing slash.
	 */
	public static function dir( string $name ): string|\WP_Error {
		$base = defined( 'IF_PRIVATE_DIR' ) ? (string) constant( 'IF_PRIVATE_DIR' ) : '';
		if ( '' === $base ) {
			return new \WP_Error( 'if_no_private_dir', __( 'IF_PRIVATE_DIR is not set in wp-config.php.', 'internfeel' ) );
		}

		$path = untrailingslashit( $base ) . '/' . sanitize_file_name( $name );
		if ( ! is_dir( $path ) && ! wp_mkdir_p( $path ) ) {
			/* translators: %s: folder path. */
			return new \WP_Error( 'if_private_dir_failed', sprintf( __( 'Could not create %s.', 'internfeel' ), $path ) );
		}
		chmod( $path, 0750 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- private storage permissions (architecture.md 7).

		return $path;
	}
}
