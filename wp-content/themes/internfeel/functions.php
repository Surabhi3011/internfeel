<?php
/**
 * InternFeel theme bootstrap.
 *
 * Presentation only. Behaviour (accounts, submissions, invites, ...) lives in the internfeel-core plugin.
 *
 * @package InternFeel
 */

defined( 'ABSPATH' ) || exit;

define( 'IF_THEME_VERSION', '0.1.0' );

require_once __DIR__ . '/inc/template-tags.php';

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
 * Enqueue the stylesheet and the front-end ES module. No jQuery, no build step.
 */
function if_theme_assets(): void {
	wp_enqueue_style( 'internfeel', get_stylesheet_uri(), array(), IF_THEME_VERSION );
	wp_enqueue_script_module( 'internfeel-main', get_theme_file_uri( 'assets/js/main.js' ), array(), IF_THEME_VERSION );
}
add_action( 'wp_enqueue_scripts', 'if_theme_assets' );
