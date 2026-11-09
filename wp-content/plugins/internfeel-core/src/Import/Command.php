<?php
/**
 * WP-CLI: wp internfeel import.
 *
 * @package InternFeel
 */

declare(strict_types=1);

namespace InternFeel\Import;

defined( 'ABSPATH' ) || exit;

/**
 * Imports the old site's export from a folder, or reports what it would do.
 */
final class Command {

	/**
	 * Import the old site's posts as experiences.
	 *
	 * ## OPTIONS
	 *
	 * [--dir=<dir>]
	 * : Folder with posts.json, categories.json, comments.json and media.json. Defaults to the import
	 * folder in private storage.
	 *
	 * [--dry-run]
	 * : Report only; write nothing.
	 *
	 * [--report=<file>]
	 * : Also write the report as CSV.
	 *
	 * ## EXAMPLES
	 *
	 *     wp internfeel import --dir=/path/to/content --dry-run --report=report.csv
	 *
	 * @param string[]              $args       Positional arguments (none).
	 * @param array<string, string> $assoc_args Options.
	 * @phpstan-param list<string> $args
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		unset( $args );

		$dir = $assoc_args['dir'] ?? Import::dir();
		if ( is_wp_error( $dir ) ) {
			\WP_CLI::error( $dir->get_error_message() );
		}

		$source = Source::from_dir( (string) $dir );
		if ( is_wp_error( $source ) ) {
			\WP_CLI::error( $source->get_error_message() );
		}

		$dry_run  = isset( $assoc_args['dry-run'] );
		$importer = new Importer( $source, $dry_run );
		$rows     = array();
		$offset   = 0;

		do {
			$batch  = $importer->batch( $offset );
			$rows   = array_merge( $rows, $batch['rows'] );
			$offset = (int) $batch['next'];
			\WP_CLI::log( sprintf( '%d of %d', count( $rows ), $batch['total'] ) );
		} while ( null !== $batch['next'] );

		$actions = array_count_values( array_map( static fn( array $row ): string => (string) $row['action'], $rows ) );
		foreach ( $actions as $action => $count ) {
			\WP_CLI::log( sprintf( '%s: %d', $action, $count ) );
		}

		if ( isset( $assoc_args['report'] ) ) {
			self::write_csv( $assoc_args['report'], $rows );
			\WP_CLI::log( 'Report: ' . $assoc_args['report'] );
		}

		\WP_CLI::success( $dry_run ? 'Dry run finished; nothing was written.' : 'Import finished.' );
	}

	/**
	 * Write the report rows as CSV.
	 *
	 * @param string                     $file Path.
	 * @param list<array<string, mixed>> $rows Report rows.
	 */
	private static function write_csv( string $file, array $rows ): void {
		$handle = fopen( $file, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- CLI report file.
		if ( false === $handle ) {
			\WP_CLI::error( "Cannot write {$file}" );
		}

		fputcsv( $handle, array_keys( $rows[0] ?? array() ), ',', '"', '' );
		foreach ( $rows as $row ) {
			$row['notes']     = implode( '; ', (array) $row['notes'] );
			$row['anonymous'] = $row['anonymous'] ? 'yes' : '';
			fputcsv( $handle, array_map( 'strval', $row ), ',', '"', '' );
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- CLI report file.
	}
}
