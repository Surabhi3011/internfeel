/**
 * InternFeel front-end entry point (plain ES module, no build step).
 */

// Shrink the fixed header once the page scrolls (plan.md 5.3).
const header = document.querySelector( '[data-if-header]' );

if ( header ) {
	const sentinel = document.createElement( 'div' );
	sentinel.setAttribute( 'aria-hidden', 'true' );
	header.before( sentinel );

	new IntersectionObserver( ( [ entry ] ) => {
		header.classList.toggle( 'is-compact', ! entry.isIntersecting );
	} ).observe( sentinel );
}
