<?php
/**
 * Runs when the plugin is deleted from wp-admin.
 *
 * Only settings are removed. Experiences, user meta, the moderation log, proofs and certificates are
 * kept on purpose: they are the site's content and its audit trail, and deleting the plugin must never
 * delete them.
 *
 * @package InternFeel
 */

declare(strict_types=1);

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'if_db_version' );
