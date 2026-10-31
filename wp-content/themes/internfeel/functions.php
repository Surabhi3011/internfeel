<?php
/**
 * InternFeel theme bootstrap.
 *
 * Presentation only. Behaviour (accounts, submissions, invites, ...) lives in the internfeel-core plugin.
 *
 * @package InternFeel
 */

defined( 'ABSPATH' ) || exit;

define( 'IF_THEME_VERSION', '0.2.0' );

require_once __DIR__ . '/inc/template-tags.php';
require_once __DIR__ . '/inc/ads.php';
require_once __DIR__ . '/inc/seo.php';

/**
 * Register theme supports and menus.
 */
function if_theme_setup(): void {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'responsive-embeds' );

	register_nav_menus(
		array(
			'primary' => __( 'Primary navigation', 'internfeel' ),
			'footer'  => __( 'Footer links', 'internfeel' ),
		)
	);
}
add_action( 'after_setup_theme', 'if_theme_setup' );

/**
 * Enqueue the design system (fonts, tokens, components), the theme layer and the front-end ES module.
 * No jQuery, no build step.
 */
function if_theme_assets(): void {
	wp_enqueue_style( 'internfeel-fonts', get_theme_file_uri( 'assets/css/fonts.css' ), array(), IF_THEME_VERSION );
	wp_enqueue_style( 'internfeel-tokens', get_theme_file_uri( 'assets/css/tokens.css' ), array( 'internfeel-fonts' ), IF_THEME_VERSION );
	wp_enqueue_style( 'internfeel-components', get_theme_file_uri( 'assets/css/components.css' ), array( 'internfeel-tokens' ), IF_THEME_VERSION );
	wp_enqueue_style( 'internfeel', get_stylesheet_uri(), array( 'internfeel-components' ), IF_THEME_VERSION );
	wp_enqueue_script_module( 'internfeel-main', get_theme_file_uri( 'assets/js/main.js' ), array(), IF_THEME_VERSION );

	if ( is_page( array( 'signup', 'login', 'dashboard' ) ) ) {
		wp_enqueue_script_module( 'internfeel-forms', get_theme_file_uri( 'assets/js/forms.js' ), array(), IF_THEME_VERSION );
	}
	if ( is_front_page() || is_page( 'experiences' ) ) {
		wp_enqueue_script_module( 'internfeel-browse', get_theme_file_uri( 'assets/js/browse.js' ), array(), IF_THEME_VERSION );
	}
	if ( is_singular( 'post' ) && 'publish' === get_post_status() ) {
		wp_enqueue_script_module( 'internfeel-views', get_theme_file_uri( 'assets/js/views.js' ), array(), IF_THEME_VERSION );
	}
	if ( ( is_singular( 'post' ) || is_page( 'dashboard' ) ) && is_user_logged_in() ) {
		wp_enqueue_script_module( 'internfeel-report', get_theme_file_uri( 'assets/js/report.js' ), array(), IF_THEME_VERSION );
	}
	if ( is_singular( 'post' ) && comments_open() && is_user_logged_in() ) {
		wp_enqueue_script( 'comment-reply' );
	}
	if ( is_page( 'dashboard' ) ) {
		wp_enqueue_script_module( 'internfeel-dashboard', get_theme_file_uri( 'assets/js/dashboard.js' ), array(), IF_THEME_VERSION );
	}
	if ( is_page( 'submit' ) ) {
		wp_enqueue_script_module( 'internfeel-submit', get_theme_file_uri( 'assets/js/submit.js' ), array(), IF_THEME_VERSION );
	}
}
add_action( 'wp_enqueue_scripts', 'if_theme_assets' );

/**
 * Preload the reading font on every page, and the hero's heading weight on the home page only.
 */
function if_theme_preload_fonts(): void {
	$fonts = array( 'atkinson-hyperlegible-next-400' );
	if ( is_front_page() ) {
		$fonts[] = 'schibsted-grotesk-800';
	}

	foreach ( $fonts as $font ) {
		printf(
			'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
			esc_url( get_theme_file_uri( "assets/fonts/{$font}.woff2" ) )
		);
	}
}
add_action( 'wp_head', 'if_theme_preload_fonts', 1 );

/**
 * Favicon from the mark, until a site icon is set in wp-admin.
 */
function if_theme_favicon(): void {
	if ( has_site_icon() ) {
		return;
	}
	printf( '<link rel="icon" href="%s" type="image/png">' . "\n", esc_url( get_theme_file_uri( 'assets/img/if-mark.png' ) ) );
}
add_action( 'wp_head', 'if_theme_favicon' );

/**
 * Comments are threaded one level deep (approved-designs README, Experience page section 5).
 */
add_filter( 'pre_option_thread_comments', static fn(): string => '1' );
add_filter( 'pre_option_thread_comments_depth', static fn(): string => '2' );

/**
 * Keep pages light (plan.md 5.2: Lighthouse mobile 90 or above). The theme draws no emoji images
 * and uses no block styles, so WordPress's emoji script and the block editor's front-end CSS are
 * dropped; legal pages' simple paragraphs, headings and lists are styled by style.css.
 */
function if_theme_lean(): void {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
	add_filter( 'emoji_svg_url', '__return_false' );

	// theme.json's global styles: the design system's own CSS covers everything they would set.
	remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
	remove_action( 'wp_footer', 'wp_enqueue_global_styles', 1 );
}
add_action( 'init', 'if_theme_lean' );

/**
 * Dequeue the block editor's front-end styles, which the classic theme never uses.
 */
function if_theme_dequeue_block_styles(): void {
	foreach ( array( 'wp-block-library', 'wp-block-library-theme', 'classic-theme-styles', 'global-styles' ) as $handle ) {
		wp_dequeue_style( $handle );
	}
	// Per-block styles (wp-block-paragraph and so on) are added as content renders.
	foreach ( wp_styles()->queue as $handle ) {
		if ( str_starts_with( $handle, 'wp-block-' ) ) {
			wp_dequeue_style( $handle );
		}
	}
}
add_action( 'wp_enqueue_scripts', 'if_theme_dequeue_block_styles', 100 );
add_action( 'wp_print_styles', 'if_theme_dequeue_block_styles', 100 );
add_action( 'wp_print_footer_scripts', 'if_theme_dequeue_block_styles', 1 );
