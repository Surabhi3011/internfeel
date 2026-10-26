<?php
/**
 * The Import module: Tools → InternFeel import, its REST batch route and the WP-CLI command
 * (development.md section 9).
 *
 * @package InternFeel
 */

declare(strict_types=1);

namespace InternFeel\Import;

use InternFeel\Module;
use InternFeel\Support\PrivateStorage;
use InternFeel\Support\Rest;

defined( 'ABSPATH' ) || exit;

/**
 * Admin screen: upload the export's JSON files, review the category mapping, dry-run, then import in
 * batches of 20 through REST.
 */
final class Import implements Module {

	public const PAGE       = 'internfeel-import';
	public const MAX_UPLOAD = 20 * MB_IN_BYTES;

	/**
	 * Add the module's hooks.
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_post_if_import_upload', array( self::class, 'handle_upload' ) );
		add_action( 'rest_api_init', array( self::class, 'routes' ) );

		if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( '\WP_CLI' ) ) {
			\WP_CLI::add_command( 'internfeel import', Command::class );
		}
	}

	/**
	 * Add Tools → InternFeel import for administrators.
	 */
	public static function menu(): void {
		add_management_page(
			__( 'InternFeel import', 'internfeel' ),
			__( 'InternFeel import', 'internfeel' ),
			'manage_options',
			self::PAGE,
			array( self::class, 'render' )
		);
	}

	/**
	 * Where the uploaded export files live.
	 *
	 * @return string|\WP_Error
	 */
	public static function dir(): string|\WP_Error {
		return PrivateStorage::dir( 'import' );
	}

	/**
	 * Store the uploaded JSON files in private storage.
	 */
	public static function handle_upload(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to import.', 'internfeel' ), 403 );
		}
		check_admin_referer( 'if_import_upload' );

		$dir    = self::dir();
		$stored = array();
		$errors = array();

		if ( is_wp_error( $dir ) ) {
			$errors[] = $dir->get_error_message();
		} else {
			foreach ( Source::FILES as $name ) {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- file array; validated below.
				$file = isset( $_FILES[ $name ] ) && is_array( $_FILES[ $name ] ) ? $_FILES[ $name ] : null;
				if ( null === $file || UPLOAD_ERR_NO_FILE === (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
					continue;
				}

				$tmp = (string) ( $file['tmp_name'] ?? '' );
				if ( UPLOAD_ERR_OK !== (int) $file['error'] || ! is_uploaded_file( $tmp ) || (int) ( $file['size'] ?? 0 ) > self::MAX_UPLOAD ) {
					/* translators: %s: file name. */
					$errors[] = sprintf( __( '%s.json could not be uploaded.', 'internfeel' ), $name );
					continue;
				}
				if ( ! is_array( json_decode( (string) file_get_contents( $tmp ), true ) ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- the uploaded temporary file.
					/* translators: %s: file name. */
					$errors[] = sprintf( __( '%s.json is not valid JSON.', 'internfeel' ), $name );
					continue;
				}

				$target = $dir . '/' . $name . '.json';
				if ( move_uploaded_file( $tmp, $target ) ) { // phpcs:ignore Generic.PHP.ForbiddenFunctions.Found -- storing outside the media library, in private storage.
					chmod( $target, 0640 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- private storage permissions.
					$stored[] = $name;
				}
			}
		}

		set_transient(
			'if_import_notice_' . get_current_user_id(),
			array(
				'stored' => $stored,
				'errors' => $errors,
			),
			MINUTE_IN_SECONDS
		);
		wp_safe_redirect( admin_url( 'tools.php?page=' . self::PAGE ) );
		exit;
	}

	/**
	 * Register POST internfeel/v1/import/batch (administrators only).
	 */
	public static function routes(): void {
		register_rest_route(
			Rest::NS,
			'/import/batch',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'rest_batch' ),
				'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
				'args'                => array(
					'offset'  => array(
						'type'    => 'integer',
						'minimum' => 0,
						'default' => 0,
					),
					'dry_run' => array(
						'type'    => 'boolean',
						'default' => true,
					),
				),
			)
		);
	}

	/**
	 * Import, or dry-run, one batch of 20.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function rest_batch( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$dir = self::dir();
		if ( is_wp_error( $dir ) ) {
			return Rest::error( 'if_import_dir', $dir->get_error_message(), 500 );
		}

		$source = Source::from_dir( $dir );
		if ( is_wp_error( $source ) ) {
			return Rest::error( 'if_import_source', $source->get_error_message(), 400 );
		}

		$importer = new Importer( $source, rest_sanitize_boolean( $request->get_param( 'dry_run' ) ) );

		return new \WP_REST_Response( $importer->batch( (int) $request->get_param( 'offset' ) ), 200 );
	}

	/**
	 * The Tools page.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$dir    = self::dir();
		$notice = get_transient( 'if_import_notice_' . get_current_user_id() );
		delete_transient( 'if_import_notice_' . get_current_user_id() );

		wp_enqueue_script_module( 'internfeel-import', plugins_url( 'assets/js/import.js', \InternFeel\PLUGIN_FILE ), array(), \InternFeel\VERSION );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'InternFeel import', 'internfeel' ); ?></h1>
			<p><?php esc_html_e( 'Imports the old site’s posts as experiences (development.md section 9). Run it on staging first. It is safe to run again: posts already imported are skipped. Every imported experience arrives as Pending review; guides and blog articles arrive as drafts.', 'internfeel' ); ?></p>

			<?php if ( is_array( $notice ) ) : ?>
				<?php if ( ! empty( $notice['stored'] ) ) : ?>
					<div class="notice notice-success"><p>
						<?php
						/* translators: %s: list of file names. */
						echo esc_html( sprintf( __( 'Stored: %s.', 'internfeel' ), implode( ', ', (array) $notice['stored'] ) ) );
						?>
					</p></div>
				<?php endif; ?>
				<?php foreach ( (array) $notice['errors'] as $error ) : ?>
					<div class="notice notice-error"><p><?php echo esc_html( (string) $error ); ?></p></div>
				<?php endforeach; ?>
			<?php endif; ?>

			<h2><?php esc_html_e( '1. Upload the export files', 'internfeel' ); ?></h2>
			<?php if ( is_wp_error( $dir ) ) : ?>
				<div class="notice notice-error inline"><p><?php echo esc_html( $dir->get_error_message() ); ?></p></div>
			<?php else : ?>
				<p><?php esc_html_e( 'From internfeel-export-content.zip, folder content/. They are stored in private storage, outside the website.', 'internfeel' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
					<input type="hidden" name="action" value="if_import_upload">
					<?php wp_nonce_field( 'if_import_upload' ); ?>
					<table class="form-table" role="presentation">
						<?php foreach ( Source::FILES as $name ) : ?>
							<tr>
								<th scope="row"><label for="if-import-<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $name . '.json' ); ?></label></th>
								<td>
									<input type="file" id="if-import-<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>" accept="application/json,.json">
									<?php if ( is_readable( $dir . '/' . $name . '.json' ) ) : ?>
										<span class="description"><?php esc_html_e( 'Already uploaded.', 'internfeel' ); ?></span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</table>
					<?php submit_button( __( 'Upload', 'internfeel' ), 'secondary' ); ?>
				</form>
			<?php endif; ?>

			<h2><?php esc_html_e( '2. Check the category mapping', 'internfeel' ); ?></h2>
			<p><?php esc_html_e( 'How the old categories fill the new fields. Anything left empty goes on the review list.', 'internfeel' ); ?></p>
			<table class="widefat striped" style="max-width:60rem">
				<thead><tr><th><?php esc_html_e( 'Old category', 'internfeel' ); ?></th><th><?php esc_html_e( 'Sets', 'internfeel' ); ?></th></tr></thead>
				<tbody>
					<?php foreach ( Mapping::table() as $slug => $rule ) : ?>
						<tr>
							<td><code><?php echo esc_html( $slug ); ?></code></td>
							<td>
								<?php
								$parts = array();
								foreach ( $rule as $key => $value ) {
									$parts[] = $key . ': ' . ( true === $value ? 'yes' : (string) $value );
								}
								echo esc_html( implode( ' · ', $parts ) );
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( '3. Dry run, then import', 'internfeel' ); ?></h2>
			<div data-if-import data-endpoint="<?php echo esc_url( rest_url( Rest::NS . '/import/batch' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>">
				<p>
					<button type="button" class="button" data-if-import-run="dry"><?php esc_html_e( 'Dry run (writes nothing)', 'internfeel' ); ?></button>
					<button type="button" class="button button-primary" data-if-import-run="live"><?php esc_html_e( 'Import', 'internfeel' ); ?></button>
				</p>
				<p role="status" aria-live="polite" data-if-import-status></p>
				<div data-if-import-report></div>
			</div>
		</div>
		<?php
	}
}
