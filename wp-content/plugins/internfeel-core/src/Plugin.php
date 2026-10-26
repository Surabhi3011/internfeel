<?php
/**
 * Plugin bootstrap: registers each module, then runs pending schema upgrades on init.
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
	 * Register every module and schedule the schema upgrade.
	 */
	public static function boot(): void {
		foreach ( self::modules() as $module ) {
			$module::register();
		}

		add_action( 'init', array( Schema::class, 'upgrade' ), 20 );
	}

	/**
	 * Activation: init has already run for this request, so register the content types first, then
	 * upgrade, so the rewrite rules include them.
	 */
	public static function activate(): void {
		Content\Content::register_types();
		Schema::upgrade();
	}

	/**
	 * Module classes, in load order. Modules are added here as they are built (architecture.md 3.2).
	 * Hardening lives in the must-use plugin so it cannot be switched off.
	 *
	 * @return list<class-string<Module>>
	 */
	private static function modules(): array {
		return array(
			Content\Content::class,
			Fields\Fields::class,
			Auth\Auth::class,
			Notify\Notify::class,
		);
	}
}
