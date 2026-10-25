<?php
/**
 * Small template helpers shared by the theme's templates.
 *
 * @package InternFeel
 */

defined( 'ABSPATH' ) || exit;

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
