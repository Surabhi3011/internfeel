<?php
/**
 * The Certificates module: numbers issued on publish, revocation on removal, the PDF download and
 * the public verify page (architecture.md 5.5).
 *
 * @package InternFeel
 */

declare(strict_types=1);

namespace InternFeel\Certificates;

use InternFeel\Auth\Roles;
use InternFeel\Fields\Fields;
use InternFeel\Module;
use InternFeel\Submit\Form;
use InternFeel\Support\Rest;

defined( 'ABSPATH' ) || exit;

/**
 * GET certificates/{id} (author or admin), GET verify/{number} (anyone), and /verify/{number}/.
 *
 * @phpstan-type Status array{state: string, number: string, name: string, title: string, organisation: string, published: string, revoked: string, anonymous: bool}
 */
final class Certificates implements Module {

	public const QUERY_VAR = 'if_verify';
	public const PATTERN   = 'IF-[0-9]{4}-[0-9]{5}';

	/**
	 * Add the module's hooks.
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'rewrite' ) );
		add_filter( 'query_vars', array( self::class, 'query_vars' ) );
		add_action( 'if_experience_published', array( self::class, 'issue' ) );
		add_action( 'transition_post_status', array( self::class, 'on_transition' ), 10, 3 );
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
		add_filter( 'template_include', array( self::class, 'template' ) );
	}

	/**
	 * /verify/ and /verify/{number}/. Schema step 3 flushes the rules once.
	 */
	public static function rewrite(): void {
		add_rewrite_rule( '^verify/?$', 'index.php?' . self::QUERY_VAR . '=search', 'top' );
		add_rewrite_rule( '^verify/([A-Za-z0-9-]{1,20})/?$', 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top' );
	}

	/**
	 * Let WordPress read the verify query variable.
	 *
	 * @param string[] $vars Public query variables.
	 * @return string[]
	 */
	public static function query_vars( array $vars ): array {
		$vars[] = self::QUERY_VAR;

		return $vars;
	}

	/**
	 * Show the theme's verify.php on /verify/.
	 *
	 * @param string $template Template WordPress chose.
	 * @return string
	 */
	public static function template( string $template ): string {
		if ( '' === (string) get_query_var( self::QUERY_VAR ) ) {
			return $template;
		}

		$verify = locate_template( 'verify.php' );
		if ( '' === $verify ) {
			return $template;
		}

		// The rule matched, so this is not a 404 even though no post was queried.
		global $wp_query;
		$wp_query->is_404 = false;
		status_header( 200 );

		return $verify;
	}

	/**
	 * Issue a certificate when an experience is published, or reinstate it if it was revoked.
	 * Imported experiences have no student account, so they get none.
	 *
	 * @param int $post_id Experience ID.
	 */
	public static function issue( int $post_id ): void {
		$author = (int) get_post_field( 'post_author', $post_id );
		if ( $author <= 0 || (int) get_option( Roles::ARCHIVE_OPTION ) === $author ) {
			return;
		}

		if ( '' === self::number( $post_id ) ) {
			update_post_meta( $post_id, Fields::PREFIX . 'certificate_no', self::next_number() );
			update_post_meta( $post_id, Fields::PREFIX . 'certificate_issued_at', current_time( 'mysql', true ) );
		}
		delete_post_meta( $post_id, Fields::PREFIX . 'certificate_revoked_at' );
	}

	/**
	 * Revoke the certificate when a published experience is removed or deleted.
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Experience.
	 */
	public static function on_transition( string $new_status, string $old_status, \WP_Post $post ): void {
		if ( 'post' !== $post->post_type || 'publish' !== $old_status || ! in_array( $new_status, array( 'removed', 'trash' ), true ) ) {
			return;
		}
		if ( '' !== self::number( $post->ID ) ) {
			update_post_meta( $post->ID, Fields::PREFIX . 'certificate_revoked_at', current_time( 'mysql', true ) );
		}
	}

	/**
	 * The experience's certificate number, or an empty string.
	 *
	 * @param int $post_id Experience ID.
	 * @return string
	 */
	public static function number( int $post_id ): string {
		return (string) get_post_meta( $post_id, Fields::PREFIX . 'certificate_no', true );
	}

	/**
	 * The next number for this year, IF-YYYY-NNNNN. The counter is increased in one SQL statement,
	 * so two approvals at the same moment never share a number.
	 *
	 * @return string
	 */
	public static function next_number(): string {
		global $wpdb;

		$year   = gmdate( 'Y' );
		$option = 'if_certificate_seq_' . $year;
		add_option( $option, '0', '', false );
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = option_value + 1 WHERE option_name = %s", $option ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- atomic counter; the cache is cleared below.
		$next = (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $option ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- read back the counter just increased.
		wp_cache_delete( $option, 'options' );

		return sprintf( 'IF-%s-%05d', $year, $next );
	}

	/**
	 * The experience a certificate number belongs to, or 0.
	 *
	 * @param string $number Certificate number.
	 * @return int
	 */
	public static function find( string $number ): int {
		if ( 1 !== preg_match( '/^' . self::PATTERN . '$/', $number ) ) {
			return 0;
		}

		$ids = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => Fields::PREFIX . 'certificate_no', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- lookup by certificate number.
				'meta_value'     => $number, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- lookup by certificate number.
			)
		);

		return array() === $ids ? 0 : (int) $ids[0];
	}

	/**
	 * What the verify page shows for a number. For an anonymous experience the title and
	 * organisation are left out, so a certificate number never links a name to an anonymous post.
	 *
	 * @param string $number Certificate number, as typed.
	 * @return array<string, mixed>
	 * @phpstan-return Status
	 */
	public static function status( string $number ): array {
		$number  = strtoupper( trim( $number ) );
		$post_id = self::find( $number );
		$status  = array(
			'state'        => 'not_found',
			'number'       => $number,
			'name'         => '',
			'title'        => '',
			'organisation' => '',
			'published'    => '',
			'revoked'      => '',
			'anonymous'    => false,
		);
		if ( 0 === $post_id ) {
			return $status;
		}

		$revoked   = (string) get_post_meta( $post_id, Fields::PREFIX . 'certificate_revoked_at', true );
		$anonymous = (bool) get_post_meta( $post_id, Fields::PREFIX . 'is_anonymous', true );

		return array(
			'state'        => '' === $revoked ? 'valid' : 'revoked',
			'number'       => $number,
			'name'         => self::holder( $post_id ),
			'title'        => $anonymous ? '' : get_the_title( $post_id ),
			'organisation' => $anonymous ? '' : (string) Form::values( $post_id )['organisation'],
			'published'    => (string) get_post_meta( $post_id, Fields::PREFIX . 'certificate_issued_at', true ),
			'revoked'      => $revoked,
			'anonymous'    => $anonymous,
		);
	}

	/**
	 * The name printed on the certificate: the student's full name, even for anonymous posts.
	 *
	 * @param int $post_id Experience ID.
	 * @return string
	 */
	public static function holder( int $post_id ): string {
		$author = (int) get_post_field( 'post_author', $post_id );
		$name   = (string) get_user_meta( $author, Fields::PREFIX . 'full_name', true );
		if ( '' !== $name ) {
			return $name;
		}
		$user = get_userdata( $author );

		return $user instanceof \WP_User ? $user->display_name : '';
	}

	/**
	 * Register the routes.
	 */
	public static function routes(): void {
		register_rest_route(
			Rest::NS,
			'/certificates/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'download' ),
				'permission_callback' => array( self::class, 'can_download' ),
			)
		);

		register_rest_route(
			Rest::NS,
			'/verify/(?P<number>[A-Za-z0-9-]{1,20})',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => static fn( \WP_REST_Request $request ): \WP_REST_Response => new \WP_REST_Response( self::status( (string) $request->get_param( 'number' ) ), 200 ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Only the author and admins may download, and only a certificate that exists and is valid.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @return bool|\WP_Error
	 */
	public static function can_download( \WP_REST_Request $request ): bool|\WP_Error {
		$post_id = (int) $request->get_param( 'id' );
		$user_id = get_current_user_id();
		$allowed = $user_id > 0 && ( (int) get_post_field( 'post_author', $post_id ) === $user_id || current_user_can( Roles::MODERATE ) );

		if ( ! $allowed || '' === self::number( $post_id ) || 'valid' !== self::status( self::number( $post_id ) )['state'] ) {
			return Rest::error( 'if_not_found', __( 'That certificate was not found.', 'internfeel' ), 404 );
		}

		return true;
	}

	/**
	 * Send the PDF as a download.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @return \WP_Error Only on failure; on success the file is sent and the request ends.
	 */
	public static function download( \WP_REST_Request $request ): \WP_Error {
		$post_id = (int) $request->get_param( 'id' );
		$path    = Pdf::file( $post_id );
		if ( is_wp_error( $path ) ) {
			return Rest::error( 'if_certificate_failed', $path->get_error_message(), 500 );
		}

		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="InternFeel-certificate-' . self::number( $post_id ) . '.pdf"' );
		header( 'Content-Length: ' . (string) filesize( $path ) );
		header( 'X-Content-Type-Options: nosniff' );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- streaming a private file after a permission check.
		exit;
	}

	/**
	 * The download address, with a REST nonce so a plain link works for the signed-in student.
	 *
	 * @param int $post_id Experience ID.
	 * @return string
	 */
	public static function download_url( int $post_id ): string {
		return add_query_arg( '_wpnonce', wp_create_nonce( 'wp_rest' ), rest_url( Rest::NS . '/certificates/' . $post_id ) );
	}

	/**
	 * The public verify address for a number.
	 *
	 * @param string $number Certificate number.
	 * @return string
	 */
	public static function verify_url( string $number ): string {
		return home_url( '/verify/' . rawurlencode( $number ) . '/' );
	}
}
