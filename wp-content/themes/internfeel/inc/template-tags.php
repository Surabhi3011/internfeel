<?php
/**
 * Small template helpers shared by the theme's templates.
 *
 * @package InternFeel
 */

defined( 'ABSPATH' ) || exit;

/**
 * Keep a page out of every cache. Pages with forms carry nonces, and pages for signed-in students
 * show personal details, so LiteSpeed Cache must never store them.
 */
function if_no_page_cache(): void {
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}
	nocache_headers();
}

/**
 * Whether InternFeel Core is active. Templates that need it show a short notice instead of failing.
 *
 * @return bool
 */
function if_core_active(): bool {
	return class_exists( '\InternFeel\Auth\Profile' );
}

/**
 * Print the hidden honeypot field used by the public forms. People never see it; bots fill it in.
 */
function if_honeypot_field(): void {
	?>
	<div class="if-honeypot" aria-hidden="true">
		<label for="if-website"><?php esc_html_e( 'Leave this field empty', 'internfeel' ); ?></label>
		<input type="text" id="if-website" name="website" value="" tabindex="-1" autocomplete="off">
	</div>
	<?php
}

/**
 * Print the "Continue with Google" button from Nextend Social Login, when it is set up.
 */
function if_google_button(): void {
	if ( ! shortcode_exists( 'nextend_social_login' ) ) {
		return;
	}

	$button = do_shortcode( '[nextend_social_login provider="google"]' );
	if ( '' === trim( $button ) ) {
		return;
	}
	?>
	<div class="if-auth__social">
		<?php echo $button; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup from Nextend Social Login's own shortcode. ?>
		<p class="if-auth__or"><span><?php esc_html_e( 'or', 'internfeel' ); ?></span></p>
	</div>
	<?php
}

/**
 * Print the InternFeel logo, linked to the home page.
 */
function if_the_logo(): void {
	printf(
		'<a class="if-header__logo" href="%1$s" rel="home"><img src="%2$s" width="129" height="36" alt="%3$s"></a>',
		esc_url( home_url( '/' ) ),
		esc_url( get_theme_file_uri( 'assets/img/internfeel-logo.png' ) ),
		esc_attr__( 'InternFeel home', 'internfeel' )
	);
}
