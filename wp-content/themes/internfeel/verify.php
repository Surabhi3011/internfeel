<?php
/**
 * Verify a certificate (/verify/ and /verify/{number}/), from the approved-designs README: one
 * bordered card at reading width with a status pill. No ads. Loaded by Certificates::template().
 *
 * @package InternFeel
 */

defined( 'ABSPATH' ) || exit;

use InternFeel\Certificates\Certificates;

$if_query  = (string) get_query_var( Certificates::QUERY_VAR );
$if_search = 'search' === $if_query;
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a public, read-only lookup.
$if_typed = isset( $_GET['number'] ) ? sanitize_text_field( wp_unslash( $_GET['number'] ) ) : '';
if ( $if_search && '' !== $if_typed ) {
	wp_safe_redirect( Certificates::verify_url( strtoupper( trim( $if_typed ) ) ) );
	exit;
}

$if_status = $if_search ? null : Certificates::status( $if_query );

/**
 * A GMT database date as "12 October 2026".
 *
 * @param string $gmt Date in GMT.
 * @return string
 */
$if_date = static fn( string $gmt ): string => '' === $gmt ? '' : (string) wp_date( 'j F Y', (int) strtotime( $gmt . ' UTC' ) );

add_filter(
	'document_title_parts',
	static function ( array $parts ): array {
		$parts['title'] = __( 'Verify a certificate', 'internfeel' );

		return $parts;
	}
);
add_filter( 'wp_robots', 'wp_robots_no_robots' );

get_header();
?>
<div class="if-container if-verify">
	<h1 class="heading-1"><?php esc_html_e( 'Verify a certificate', 'internfeel' ); ?></h1>

	<section class="if-verify__card" aria-live="polite">
		<?php if ( null === $if_status ) : ?>
			<p><?php esc_html_e( 'Enter the number printed on the certificate, such as IF-2026-00412.', 'internfeel' ); ?></p>
		<?php elseif ( 'valid' === $if_status['state'] ) : ?>
			<span class="if-status if-status--verified"><?php esc_html_e( 'Valid', 'internfeel' ); ?></span>
			<dl class="if-verify__facts">
				<dt><?php esc_html_e( 'Issued to', 'internfeel' ); ?></dt>
				<dd><?php echo esc_html( $if_status['name'] ); ?></dd>
				<?php if ( $if_status['anonymous'] ) : ?>
					<dt><?php esc_html_e( 'Experience', 'internfeel' ); ?></dt>
					<dd><?php esc_html_e( 'Posted anonymously, so its title is not shown here.', 'internfeel' ); ?></dd>
				<?php else : ?>
					<dt><?php esc_html_e( 'Experience', 'internfeel' ); ?></dt>
					<dd><?php echo esc_html( $if_status['title'] ); ?></dd>
					<?php if ( '' !== $if_status['organisation'] ) : ?>
						<dt><?php esc_html_e( 'Organisation', 'internfeel' ); ?></dt>
						<dd><?php echo esc_html( $if_status['organisation'] ); ?></dd>
					<?php endif; ?>
				<?php endif; ?>
				<dt><?php esc_html_e( 'Published', 'internfeel' ); ?></dt>
				<dd><?php echo esc_html( $if_date( $if_status['published'] ) ); ?></dd>
				<dt><?php esc_html_e( 'Certificate no.', 'internfeel' ); ?></dt>
				<dd class="mono"><?php echo esc_html( $if_status['number'] ); ?></dd>
			</dl>
		<?php elseif ( 'revoked' === $if_status['state'] ) : ?>
			<span class="if-status if-status--removed"><?php esc_html_e( 'Revoked', 'internfeel' ); ?></span>
			<p>
				<?php
				/* translators: 1: certificate number, 2: date. */
				echo esc_html( sprintf( __( 'Certificate %1$s was revoked on %2$s because the experience was removed from InternFeel.', 'internfeel' ), $if_status['number'], $if_date( $if_status['revoked'] ) ) );
				?>
			</p>
		<?php else : ?>
			<span class="if-status if-status--rejected"><?php esc_html_e( 'Not found', 'internfeel' ); ?></span>
			<p>
				<?php
				/* translators: %s: the number that was looked up. */
				echo esc_html( sprintf( __( 'No certificate has the number %s. Check the number on the certificate.', 'internfeel' ), $if_status['number'] ) );
				?>
			</p>
		<?php endif; ?>

		<form class="if-verify__form" method="get" action="<?php echo esc_url( home_url( '/verify/' ) ); ?>">
			<div class="if-field">
				<label for="if-verify-number"><?php esc_html_e( 'Certificate number', 'internfeel' ); ?></label>
				<input id="if-verify-number" class="mono" type="text" name="number" placeholder="IF-2026-00412" maxlength="20" autocomplete="off" required value="<?php echo esc_attr( null === $if_status ? '' : $if_status['number'] ); ?>">
			</div>
			<button class="if-btn if-btn--primary" type="submit"><?php esc_html_e( 'Check', 'internfeel' ); ?></button>
		</form>
	</section>
</div>
<?php
get_footer();
