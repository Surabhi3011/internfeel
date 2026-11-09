<?php
/**
 * Plugin Name: InternFeel Hardening
 * Description: Always-on security settings (architecture.md section 9). A must-use plugin, so it cannot be switched off from wp-admin.
 * Version:     0.1.0
 * Author:      InternFeel
 * License:     Proprietary
 *
 * @package InternFeel
 */

declare(strict_types=1);

namespace InternFeel\Hardening;

defined( 'ABSPATH' ) || exit;

// Backstop for wp-config.php: no theme or plugin file editor in wp-admin.
if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
	define( 'DISALLOW_FILE_EDIT', true );
}

// XML-RPC off, including pingbacks, and stop advertising it.
add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter( 'xmlrpc_methods', static fn(): array => array() );
add_filter( 'pings_open', '__return_false' );
add_filter(
	'wp_headers',
	static function ( array $headers ): array {
		unset( $headers['X-Pingback'] );
		return $headers;
	}
);
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wp_generator' );

// Close /wp/v2/users to everyone who cannot already list users in wp-admin.
add_filter(
	'rest_endpoints',
	static function ( array $endpoints ): array {
		if ( current_user_can( 'list_users' ) ) {
			return $endpoints;
		}

		foreach ( array_keys( $endpoints ) as $route ) {
			if ( str_starts_with( (string) $route, '/wp/v2/users' ) ) {
				unset( $endpoints[ $route ] );
			}
		}

		return $endpoints;
	}
);

// Block author enumeration: ?author=N would redirect to /author/{login}/, and author archives are not used.
add_action(
	'template_redirect',
	static function (): void {
		if ( isset( $_GET['author'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- presence check only; the value is never read.
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}

		if ( is_author() ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
			remove_action( 'template_redirect', 'redirect_canonical' );
		}
	},
	1
);

// Keep author names out of oEmbed responses (anonymous experiences must never reveal their author).
add_filter(
	'oembed_response_data',
	static function ( array $data ): array {
		unset( $data['author_name'], $data['author_url'] );
		return $data;
	}
);

// No users sitemap.
add_filter(
	'wp_sitemaps_add_provider',
	static fn( $provider, string $name ) => 'users' === $name ? false : $provider,
	10,
	2
);

/**
 * Security headers for front-end, wp-admin and log-in responses.
 */
function send_security_headers(): void {
	if ( headers_sent() ) {
		return;
	}

	header( 'X-Content-Type-Options: nosniff' );
	header( 'Referrer-Policy: strict-origin-when-cross-origin' );
	header( 'X-Frame-Options: SAMEORIGIN' );

	// Starter policy: tightened once AdSense, GA4, CookieYes and Google sign-in are in place.
	$csp = "base-uri 'self'; object-src 'none'; frame-ancestors 'self'";
	if ( is_ssl() ) {
		$csp .= '; upgrade-insecure-requests';
	}
	header( 'Content-Security-Policy: ' . $csp );
}
add_action( 'send_headers', __NAMESPACE__ . '\\send_security_headers' );
add_action( 'admin_init', __NAMESPACE__ . '\\send_security_headers' );
add_action( 'login_init', __NAMESPACE__ . '\\send_security_headers' );

// Local and staging are never indexed. WP_ENVIRONMENT_TYPE defaults to production when unset.
if ( 'production' !== wp_get_environment_type() ) {
	add_filter( 'wp_robots', 'wp_robots_no_robots' );
	add_action(
		'send_headers',
		static function (): void {
			if ( ! headers_sent() ) {
				header( 'X-Robots-Tag: noindex, nofollow' );
			}
		}
	);
}
