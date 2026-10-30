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

// Copy-link buttons ([data-if-copy] holds the URL; [data-if-label] inside it shows the result).
document.addEventListener( 'click', async ( event ) => {
	const button = event.target.closest( '[data-if-copy]' );
	if ( ! button ) {
		return;
	}
	const label = button.querySelector( '[data-if-label]' ) ?? button;
	try {
		await navigator.clipboard.writeText( button.dataset.ifCopy );
		label.textContent = 'Link copied';
	} catch {
		label.textContent = 'Copy failed';
	}
} );
