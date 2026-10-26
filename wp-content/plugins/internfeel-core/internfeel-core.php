<?php
/**
 * Plugin Name:       InternFeel Core
 * Plugin URI:        https://internfeel.com
 * Description:       Accounts, submissions, verification, invites, badges, certificates and moderation for InternFeel.
 * Version:           0.1.0
 * Requires at least: 7.1
 * Requires PHP:      8.3
 * Author:            InternFeel
 * License:           Proprietary
 * Text Domain:       internfeel
 *
 * @package InternFeel
 */

declare(strict_types=1);

namespace InternFeel;

defined( 'ABSPATH' ) || exit;

const VERSION     = '0.1.0';
const PLUGIN_FILE = __FILE__;

// Runtime dependencies (Dompdf, QR codes) are committed in vendor/, because the server has no Composer.
if ( is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

// PSR-4: InternFeel\Invites\Codes lives in src/Invites/Codes.php.
spl_autoload_register(
	static function ( string $class_name ): void {
		if ( ! str_starts_with( $class_name, __NAMESPACE__ . '\\' ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( __NAMESPACE__ ) + 1 );
		$file     = __DIR__ . '/src/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

register_activation_hook( __FILE__, array( Plugin::class, 'activate' ) );
add_action( 'plugins_loaded', array( Plugin::class, 'boot' ) );
