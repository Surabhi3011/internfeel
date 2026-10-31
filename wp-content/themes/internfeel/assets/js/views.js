/**
 * Count a view of this experience (architecture.md 5.6). Runs in the browser because cached pages
 * never reach PHP; the server skips bots, the author and repeat visits within a day.
 */

const article = document.querySelector( '[data-if-view]' );

if ( article ) {
	// After the page has settled, so counting never competes with reading.
	window.addEventListener( 'load', () => {
		fetch( article.dataset.rest, { method: 'POST', credentials: 'same-origin', keepalive: true } ).catch( () => {} );
	} );
}
