<?php
/**
 * AdSense slots and their rules (plan.md 5.6), ads.txt, and GA4, both loaded only after consent
 * (architecture.md 10).
 *
 * Settings in wp-config.php: IF_ADSENSE_CLIENT ("ca-pub-…", empty on staging, so no slot renders),
 * IF_ADSENSE_SLOTS (an array of AdSense ad-unit IDs keyed by place: inline, end, sidebar, card, home,
 * hub; a place without an ID renders nothing) and IF_GA4_ID.
 *
 * @package InternFeel
 */

defined( 'ABSPATH' ) || exit;

/**
 * Most ad units on one page (plan.md 5.6).
 */
const IF_MAX_ADS = 3;

/**
 * Heights reserved for each place, so the page doesn't jump when an ad loads: desktop, phone.
 */
const IF_AD_HEIGHTS = array(
	'inline'  => array( 120, 250 ),
	'end'     => array( 120, 250 ),
	'sidebar' => array( 250, 250 ),
	'card'    => array( 250, 250 ),
	'home'    => array( 180, 250 ),
	'hub'     => array( 180, 250 ),
);

/**
 * The AdSense publisher ID, or an empty string.
 *
 * @return string
 */
function if_adsense_client(): string {
	$client = defined( 'IF_ADSENSE_CLIENT' ) ? (string) constant( 'IF_ADSENSE_CLIENT' ) : '';

	return 1 === preg_match( '/^ca-pub-\d{10,20}$/', $client ) ? $client : '';
}

/**
 * The ad-unit ID for a place, or an empty string.
 *
 * @param string $place inline, end, sidebar, card, home or hub.
 * @return string
 */
function if_ad_slot_id( string $place ): string {
	$slots = defined( 'IF_ADSENSE_SLOTS' ) ? constant( 'IF_ADSENSE_SLOTS' ) : array();
	$slot  = is_array( $slots ) && isset( $slots[ $place ] ) ? (string) $slots[ $place ] : '';

	return 1 === preg_match( '/^\d{6,20}$/', $slot ) ? $slot : '';
}

/**
 * Pages that never carry ads: dashboard, submit, sign-in, legal, verify, join (plan.md 5.6).
 *
 * @return bool
 */
function if_page_allows_ads(): bool {
	if ( is_page( array( 'dashboard', 'submit', 'signup', 'login', 'join', 'privacy-policy', 'terms', 'content-guidelines', 'ad-guidelines', 'about', 'contact' ) ) ) {
		return false;
	}
	if ( '' !== (string) get_query_var( 'if_verify' ) || is_404() || is_search() ) {
		return false;
	}
	if ( is_page() && 'template-legal.php' === get_page_template_slug() ) {
		return false;
	}

	return true;
}

/**
 * Print one ad slot: a labelled box that reserves its height. Nothing when ads are off, the place has
 * no unit, the page carries no ads, or the page already has three.
 *
 * @param string $place inline, end, sidebar, card, home or hub.
 * @param string $label Advertisement, or Sponsored for card-shaped slots.
 */
function if_the_ad( string $place, string $label = '' ): void {
	static $shown = 0;

	$client = if_adsense_client();
	$slot   = if_ad_slot_id( $place );
	if ( '' === $client || '' === $slot || $shown >= IF_MAX_ADS || ! if_page_allows_ads() ) {
		return;
	}
	++$shown;

	$heights = IF_AD_HEIGHTS[ $place ] ?? array( 250, 250 );
	$label   = '' === $label ? __( 'Advertisement', 'internfeel' ) : $label;
	?>
	<aside class="if-ad if-ad--<?php echo esc_attr( $place ); ?>" aria-label="<?php echo esc_attr( $label ); ?>" style="--if-ad-h: <?php echo (int) $heights[0]; ?>px; --if-ad-h-phone: <?php echo (int) $heights[1]; ?>px;">
		<span class="if-ad__label"><?php echo esc_html( $label ); ?></span>
		<div class="if-ad__box">
			<ins class="adsbygoogle" style="display:block" data-ad-client="<?php echo esc_attr( $client ); ?>" data-ad-slot="<?php echo esc_attr( $slot ); ?>" data-ad-format="auto" data-full-width-responsive="true"></ins>
		</div>
	</aside>
	<?php
}

/**
 * Load the consent-aware loader on pages that may show ads or count visits. The page tells it the
 * AdSense client, the GA4 ID and whether to ask for non-personalised ads (signed-in under-18
 * students).
 */
function if_ads_assets(): void {
	$client = if_page_allows_ads() ? if_adsense_client() : '';
	$ga4    = defined( 'IF_GA4_ID' ) ? (string) constant( 'IF_GA4_ID' ) : '';
	$ga4    = 1 === preg_match( '/^G-[A-Z0-9]{4,12}$/', $ga4 ) ? $ga4 : '';
	if ( '' === $client && '' === $ga4 ) {
		return;
	}

	$minor = is_user_logged_in() && '1' === (string) get_user_meta( get_current_user_id(), '_if_is_under_18', true );

	wp_enqueue_script_module( 'internfeel-consent', get_theme_file_uri( 'assets/js/consent.js' ), array(), IF_THEME_VERSION );
	add_action(
		'wp_footer',
		static function () use ( $client, $ga4, $minor ): void {
			printf(
				'<script type="application/json" id="if-consent-config">%s</script>' . "\n",
				wp_json_encode(
					array(
						'adsense'         => $client,
						'ga4'             => $ga4,
						'nonPersonalised' => $minor,
					)
				)
			);
		},
		5
	);
}
add_action( 'wp_enqueue_scripts', 'if_ads_assets' );

/**
 * Serve /ads.txt from the AdSense client ID, so it is never out of step with wp-config.php
 * (plan.md 5.6). 404 when ads are off.
 */
function if_ads_txt(): void {
	$path = (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '', PHP_URL_PATH );
	if ( '/ads.txt' !== $path ) {
		return;
	}

	$client = if_adsense_client();
	if ( '' === $client ) {
		status_header( 404 );
		exit;
	}

	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'Cache-Control: public, max-age=86400' );
	echo 'google.com, ' . esc_html( str_replace( 'ca-', '', $client ) ) . ", DIRECT, f08c47fec0942fa0\n";
	exit;
}
add_action( 'init', 'if_ads_txt', 0 );
