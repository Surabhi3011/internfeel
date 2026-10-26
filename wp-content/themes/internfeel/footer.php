<?php
/**
 * Newsletter band and site footer (docs/approved-designs/README.md, "Header and footer").
 *
 * The footer sits on the teal tint, never charcoal: there is no reversed logo.
 *
 * The newsletter email service is still to be chosen (plan.md section 10), so the band only shows
 * once IF_NEWSLETTER_ENDPOINT is set in wp-config.php to the service's form URL.
 *
 * @package InternFeel
 */

defined( 'ABSPATH' ) || exit;

$if_newsletter = defined( 'IF_NEWSLETTER_ENDPOINT' ) ? (string) constant( 'IF_NEWSLETTER_ENDPOINT' ) : '';

/**
 * Footer columns from plan.md 5.4.
 *
 * @var array<string, array<string, string>> $if_columns
 */
$if_columns = array(
	__( 'Explore', 'internfeel' )    => array(
		__( 'Experiences', 'internfeel' )          => '/experiences/',
		__( 'Programmes', 'internfeel' )           => '/org/',
		__( 'Listicles', 'internfeel' )            => '/lists/',
		__( 'Verified experiences', 'internfeel' ) => '/experiences/?verified=1',
	),
	__( 'Contribute', 'internfeel' ) => array(
		__( 'Submit experience', 'internfeel' )       => '/submit/',
		__( 'Invite friends', 'internfeel' )          => '/dashboard/',
		__( 'Contributor certificate', 'internfeel' ) => '/about/',
		__( 'Content guidelines', 'internfeel' )      => '/content-guidelines/',
	),
	__( 'Company', 'internfeel' )    => array(
		__( 'About', 'internfeel' )             => '/about/',
		__( 'Contact', 'internfeel' )           => '/contact/',
		__( 'Advertise with us', 'internfeel' ) => '/advertise/',
		__( 'Report a problem', 'internfeel' )  => '/contact/',
	),
	__( 'Legal', 'internfeel' )      => array(
		__( 'Privacy policy', 'internfeel' )       => '/privacy-policy/',
		__( 'Terms of use', 'internfeel' )         => '/terms/',
		__( 'Cookie settings', 'internfeel' )      => '/privacy-policy/#cookies',
		__( 'Verify a certificate', 'internfeel' ) => '/verify/',
	),
);
?>
</main>

<?php if ( '' !== $if_newsletter ) : ?>
	<section class="if-newsletter" aria-labelledby="if-newsletter-title">
		<div class="if-container if-newsletter__inner">
			<div>
				<h2 id="if-newsletter-title" class="heading-3"><?php esc_html_e( 'New experiences in your inbox', 'internfeel' ); ?></h2>
				<p><?php esc_html_e( 'The best new experiences and programme deadlines, once a month.', 'internfeel' ); ?></p>
			</div>
			<form method="post" action="<?php echo esc_url( $if_newsletter ); ?>">
				<div class="if-field">
					<label for="if-newsletter-email"><?php esc_html_e( 'Email address', 'internfeel' ); ?></label>
					<input type="email" id="if-newsletter-email" name="email" autocomplete="email" required>
				</div>
				<button type="submit" class="if-btn if-btn--primary"><?php esc_html_e( 'Subscribe', 'internfeel' ); ?></button>
			</form>
		</div>
	</section>
<?php endif; ?>

<footer class="if-footer">
	<div class="if-footer__inner">
		<div class="if-footer__cols">
			<?php foreach ( $if_columns as $if_heading => $if_links ) : ?>
				<nav aria-label="<?php echo esc_attr( $if_heading ); ?>">
					<h2><?php echo esc_html( $if_heading ); ?></h2>
					<ul>
						<?php foreach ( $if_links as $if_label => $if_path ) : ?>
							<li><a href="<?php echo esc_url( home_url( $if_path ) ); ?>"><?php echo esc_html( $if_label ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				</nav>
			<?php endforeach; ?>
		</div>

		<div class="if-footer__base">
			<div class="if-footer__brand">
				<img src="<?php echo esc_url( get_theme_file_uri( 'assets/img/internfeel-logo.png' ) ); ?>" width="114" height="32" alt="<?php esc_attr_e( 'InternFeel, peep before you leap', 'internfeel' ); ?>">
				<a href="mailto:contact@internfeel.com">contact@internfeel.com</a>
			</div>
			<?php if_social_links(); ?>
			<span>
				<?php
				/* translators: %s: current year. */
				echo esc_html( sprintf( __( '© %s InternFeel', 'internfeel' ), wp_date( 'Y' ) ) );
				?>
			</span>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
