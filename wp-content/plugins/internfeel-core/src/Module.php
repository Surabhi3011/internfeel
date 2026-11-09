<?php
/**
 * Contract for a plugin module (Auth, Submit, Verify, Invites, ...).
 *
 * @package InternFeel
 */

declare(strict_types=1);

namespace InternFeel;

defined( 'ABSPATH' ) || exit;

/**
 * A module adds its hooks in register() and keeps everything else inside its own src/ folder.
 */
interface Module {

	/**
	 * Add the module's actions, filters and REST routes.
	 */
	public static function register(): void;
}
