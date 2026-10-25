<?php
/**
 * Plugin bootstrap: runs pending schema upgrades, then registers each module.
 *
 * @package InternFeel
 */

declare(strict_types=1);

namespace InternFeel;

defined( 'ABSPATH' ) || exit;

/**
 * Boots InternFeel Core on plugins_loaded.
 */
final class Plugin {

	/**
	 * Run upgrades and register every module.
	 */
	public static function boot(): void {
		Schema::upgrade();

		foreach ( self::modules() as $module ) {
			$module::register();
		}
	}

	/**
	 * Module classes, in load order. Modules are added here as they are built (architecture.md 3.2).
	 *
	 * @return list<class-string<Module>>
	 */
	private static function modules(): array {
		return array();
	}
}
