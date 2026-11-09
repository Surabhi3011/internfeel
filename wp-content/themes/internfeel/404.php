<?php
/**
 * Page not found: a short message, the search box and the main ways back in.
 *
 * @package InternFeel
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="if-container if-legal">
	<h1 class="heading-1"><?php esc_html_e( 'Page not found', 'internfeel' ); ?></h1>
	<p><?php esc_html_e( 'This page has moved or no longer exists. Try searching the experiences instead.', 'internfeel' ); ?></p>
	<form role="search" method="get" action="<?php echo esc_url( home_url( '/experiences/' ) ); ?>">
		<div class="if-field">
			<label for="if-404-q"><?php esc_html_e( 'Search experiences', 'internfeel' ); ?></label>
			<input id="if-404-q" type="search" name="q" placeholder="<?php esc_attr_e( 'Organisation, programme or field', 'internfeel' ); ?>">
		</div>
		<p><button class="if-btn" type="submit"><?php esc_html_e( 'Search', 'internfeel' ); ?></button></p>
	</form>
	<p>
		<a class="if-btn if-btn--primary" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Go to the home page', 'internfeel' ); ?></a>
		<a class="if-btn if-btn--ghost" href="<?php echo esc_url( home_url( '/experiences/' ) ); ?>"><?php esc_html_e( 'Browse experiences', 'internfeel' ); ?></a>
	</p>
</div>
<?php
get_footer();
