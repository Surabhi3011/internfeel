<?php
/*
 * InternFeel constants for wp-config.php (docs/development.md section 4).
 *
 * Copy the block for your environment into that environment's wp-config.php, ABOVE the line
 * "That's all, stop editing!". Every value below is FAKE. Real secrets live only in wp-config.php
 * on the server and in the owners' password manager: never in this repo, chat or tickets.
 * This repo is public, so paths are worked out from the file's location instead of naming the account.
 *
 * On cPanel, edit wp-config.php with File Manager → select the file → Edit.
 */

/* ---------- Every environment ---------- */

define( 'DISALLOW_FILE_EDIT', true );

// Sender of the password-reset email, sent through cPanel mail.
define( 'IF_MAIL_FROM', 'no-reply@internfeel.com' );
define( 'IF_MAIL_FROM_NAME', 'InternFeel' );

// Cloudflare Turnstile, after launch. Leave both unset until then and the forms skip the check.
// define( 'IF_TURNSTILE_SITE_KEY', '0x0000000000000000000000' );
// define( 'IF_TURNSTILE_SECRET', '0x0000000000000000000000000000000000' );

/* ---------- Production: internfeel.com ---------- */

define( 'WP_ENVIRONMENT_TYPE', 'production' );
// wp-config.php sits in /home/<cpanel-user>/public_html, so one level up is the home folder.
define( 'IF_PRIVATE_DIR', dirname( __DIR__ ) . '/internfeel-private' );
define( 'IF_ADSENSE_CLIENT', 'ca-pub-0000000000000000' );
define( 'IF_GA4_ID', 'G-XXXXXXXXXX' );

/* ---------- Staging: staging.internfeel.com (use instead of the production block) ----------

define( 'WP_ENVIRONMENT_TYPE', 'staging' ); // Must be set: WordPress treats a missing value as production.
// wp-config.php sits in /home/<cpanel-user>/public_html/staging.internfeel.com, so two levels up.
define( 'IF_PRIVATE_DIR', dirname( __DIR__, 2 ) . '/internfeel-private-staging' );
define( 'IF_ADSENSE_CLIENT', '' );          // Empty, so no ads show on staging.
// No IF_GA4_ID on staging.

----------------------------------------------------------------------------------------------- */
