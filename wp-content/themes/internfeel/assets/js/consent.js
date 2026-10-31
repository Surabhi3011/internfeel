/**
 * Load AdSense and GA4 only after the visitor consents (architecture.md 10, plan.md 5.6), through
 * CookieYes: advertisement consent loads AdSense, analytics consent loads GA4. Without CookieYes
 * nothing loads, because consent comes first. Signed-in under-18 students get non-personalised ads.
 */

const config = JSON.parse( document.getElementById( 'if-consent-config' )?.textContent || '{}' );
const loaded = { ads: false, ga4: false };

/**
 * Add an external script once.
 *
 * @param {string} src
 */
function addScript( src ) {
	const script = document.createElement( 'script' );
	script.async = true;
	script.src = src;
	script.crossOrigin = 'anonymous';
	document.head.append( script );
}

function loadAds() {
	if ( loaded.ads || ! config.adsense ) {
		return;
	}
	// The sidebar slot is hidden below 1000 px; remove hidden slots rather than fill them.
	document.querySelectorAll( '.if-ad' ).forEach( ( ad ) => {
		if ( ad.offsetParent === null ) {
			ad.remove();
		}
	} );
	const slots = document.querySelectorAll( 'ins.adsbygoogle' );
	if ( ! slots.length ) {
		return;
	}
	loaded.ads = true;
	window.adsbygoogle = window.adsbygoogle || [];
	if ( config.nonPersonalised ) {
		window.adsbygoogle.requestNonPersonalizedAds = 1;
	}
	addScript( `https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=${ encodeURIComponent( config.adsense ) }` );
	slots.forEach( () => window.adsbygoogle.push( {} ) );
}

function loadAnalytics() {
	if ( loaded.ga4 || ! config.ga4 ) {
		return;
	}
	loaded.ga4 = true;
	window.dataLayer = window.dataLayer || [];
	window.gtag = function gtag() {
		window.dataLayer.push( arguments ); // eslint-disable-line prefer-rest-params -- gtag needs the arguments object.
	};
	window.gtag( 'js', new Date() );
	window.gtag( 'config', config.ga4 );
	addScript( `https://www.googletagmanager.com/gtag/js?id=${ encodeURIComponent( config.ga4 ) }` );
}

/**
 * Act on the consent CookieYes holds.
 *
 * @param {object} categories CookieYes categories, such as { advertisement: true, analytics: false }.
 */
function apply( categories = {} ) {
	if ( categories.advertisement ) {
		loadAds();
	}
	if ( categories.analytics ) {
		loadAnalytics();
	}
}

// Consent given on an earlier visit.
if ( typeof window.getCkyConsent === 'function' ) {
	apply( window.getCkyConsent()?.categories );
}

// CookieYes may load after this script; it announces itself and every later choice.
document.addEventListener( 'cookieyes_banner_load', () => apply( window.getCkyConsent?.()?.categories ) );
document.addEventListener( 'cookieyes_consent_update', ( event ) => {
	const accepted = event.detail?.accepted || [];
	apply( { advertisement: accepted.includes( 'advertisement' ), analytics: accepted.includes( 'analytics' ) } );
} );
