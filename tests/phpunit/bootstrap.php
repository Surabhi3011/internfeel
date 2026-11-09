<?php
/**
 * PHPUnit bootstrap: the WordPress test suite from wp-env, with InternFeel Core loaded.
 *
 * Runs inside the wp-env cli container (npm run test:unit). The test suite reinstalls WordPress in the
 * wp-env database, so it resets the local development site.
 *
 * @package InternFeel
 */

$if_tests_dir = getenv( 'WP_TESTS_DIR' );
$if_tests_dir = false === $if_tests_dir || '' === $if_tests_dir ? '/wordpress-phpunit' : $if_tests_dir;

require_once dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';
require_once $if_tests_dir . '/includes/functions.php';

// Logging in through the REST routes must not try to send cookies from the command line.
tests_add_filter( 'send_auth_cookies', '__return_false' );

tests_add_filter(
	'muplugins_loaded',
	static function (): void {
		require WP_PLUGIN_DIR . '/internfeel-core/internfeel-core.php';
	}
);

require $if_tests_dir . '/includes/bootstrap.php';
