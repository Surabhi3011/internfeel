<?php
/**
 * Renders a certificate PDF with Dompdf from templates/certificate.php (docs/approved-designs/certificate.html).
 *
 * @package InternFeel
 */

declare(strict_types=1);

namespace InternFeel\Certificates;

use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Dompdf\Dompdf;
use Dompdf\Options;
use InternFeel\Fields\Fields;
use InternFeel\Submit\Form;
use InternFeel\Support\PrivateStorage;

defined( 'ABSPATH' ) || exit;

/**
 * PDFs are kept in private storage, named after the number and a hash of what is printed, so a
 * changed name or title produces a fresh file and an unchanged one is served from disk.
 */
final class Pdf {

	/**
	 * Path of the experience's certificate PDF, rendering it if needed.
	 *
	 * @param int $post_id Experience ID.
	 * @return string|\WP_Error
	 */
	public static function file( int $post_id ): string|\WP_Error {
		$number = Certificates::number( $post_id );
		if ( '' === $number ) {
			return new \WP_Error( 'if_no_certificate', __( 'This experience has no certificate.', 'internfeel' ) );
		}

		$dir = PrivateStorage::dir( 'certificates' );
		if ( is_wp_error( $dir ) ) {
			return $dir;
		}

		$data = self::data( $post_id );
		$path = $dir . '/' . $number . '-' . substr( md5( (string) wp_json_encode( $data ) ), 0, 12 ) . '.pdf';
		if ( is_readable( $path ) ) {
			return $path;
		}

		// Drop older versions of this certificate.
		foreach ( (array) glob( $dir . '/' . $number . '-*.pdf' ) as $old ) {
			wp_delete_file( (string) $old );
		}

		$pdf = self::render( $data );
		if ( is_wp_error( $pdf ) ) {
			return $pdf;
		}
		if ( false === file_put_contents( $path, $pdf ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- private storage outside the web root.
			return new \WP_Error( 'if_certificate_write', __( 'The certificate could not be saved.', 'internfeel' ) );
		}
		chmod( $path, 0640 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- private storage permissions (architecture.md 7).

		return $path;
	}

	/**
	 * What the certificate prints.
	 *
	 * @param int $post_id Experience ID.
	 * @return array<string, string>
	 */
	public static function data( int $post_id ): array {
		$values = Form::values( $post_id );
		$number = Certificates::number( $post_id );
		$issued = (string) get_post_meta( $post_id, Fields::PREFIX . 'certificate_issued_at', true );

		$dates = '';
		if ( '' !== (string) $values['start_month'] ) {
			$start = strtotime( $values['start_month'] . '-01' );
			$dates = false === $start ? '' : wp_date( 'F Y', $start );
		}

		return array(
			'name'         => Certificates::holder( $post_id ),
			'title'        => html_entity_decode( get_the_title( $post_id ), ENT_QUOTES, 'UTF-8' ),
			'details'      => implode( ' · ', array_filter( array( (string) $values['organisation'], (string) $values['programme'], $dates ) ) ),
			'published'    => '' === $issued ? '' : (string) wp_date( 'j F Y', (int) strtotime( $issued . ' UTC' ) ),
			'number'       => $number,
			'verify_label' => preg_replace( '#^https?://#', '', untrailingslashit( Certificates::verify_url( $number ) ) ) ?? '',
			'verify_url'   => Certificates::verify_url( $number ),
		);
	}

	/**
	 * Render the PDF.
	 *
	 * @param array<string, string> $data What to print.
	 * @return string|\WP_Error PDF bytes.
	 */
	public static function render( array $data ): string|\WP_Error {
		if ( ! class_exists( Dompdf::class ) ) {
			return new \WP_Error( 'if_no_dompdf', __( 'The PDF library is missing.', 'internfeel' ) );
		}

		$fonts = PrivateStorage::dir( 'dompdf' );
		if ( is_wp_error( $fonts ) ) {
			return $fonts;
		}

		$root    = dirname( __DIR__, 2 );
		$options = new Options();
		$options->setChroot( array( $root ) );
		$options->setIsRemoteEnabled( false );
		$options->setIsPhpEnabled( false );
		$options->setFontDir( $fonts );
		$options->setFontCache( $fonts );
		$options->setTempDir( $fonts );
		$options->setDefaultFont( 'Atkinson' );
		$options->setDpi( 96 );

		$assets = $root . '/assets';
		$qr     = self::qr( $data['verify_url'] );

		ob_start();
		require $root . '/templates/certificate.php';
		$html = (string) ob_get_clean();

		$dompdf = new Dompdf( $options );
		$dompdf->setPaper( 'A4', 'landscape' );
		$dompdf->loadHtml( $html, 'UTF-8' );
		$dompdf->render();

		return (string) $dompdf->output();
	}

	/**
	 * The QR code for the verify page as a PNG data URI, or empty without GD.
	 *
	 * @param string $url Verify page.
	 * @return string
	 */
	private static function qr( string $url ): string {
		if ( ! extension_loaded( 'gd' ) || ! class_exists( QRCode::class ) ) {
			return '';
		}

		$options = new QROptions(
			array(
				'outputInterface'  => QRGdImagePNG::class,
				'outputBase64'     => true,
				// Opaque, so Dompdf never hands it to Imagick (see the note on the certificate images).
				'imageTransparent' => false,
				'scale'            => 6,
				'quietzoneSize'    => 1,
			)
		);

		return (string) ( new QRCode( $options ) )->render( $url );
	}
}
