/**
 * The student dashboard (page-dashboard.php). Plain ES module, no build step.
 *
 * - Tabs follow the ARIA tabs pattern: arrow keys, Home and End move between them.
 * - Proof uploads and draft deletion call the internfeel/v1 routes, then reload the page.
 * - Opening the dashboard marks the notifications read (plan.md 5.1).
 */

import { GENERIC_ERROR } from './field-errors.js';

const root = document.querySelector( '[data-if-dashboard]' );

if ( root ) {
	const status = root.querySelector( '[data-if-status]' );
	const tabs = [ ...root.querySelectorAll( '[role="tab"]' ) ];

	/**
	 * Call an internfeel/v1 route; throws an Error with the server's message on failure.
	 *
	 * @param {string} path
	 * @param {object} options
	 */
	async function request( path, { method = 'GET', body } = {} ) {
		const response = await fetch( root.dataset.rest + path, {
			method,
			body,
			headers: { 'X-WP-Nonce': root.dataset.nonce },
			credentials: 'same-origin',
		} );
		const data = await response.json().catch( () => null );
		if ( ! response.ok ) {
			throw new Error( data?.message || GENERIC_ERROR );
		}
		return data;
	}

	function showError( error ) {
		status.textContent = error.message || GENERIC_ERROR;
		status.scrollIntoView( { block: 'nearest' } );
	}

	function select( tab, focus = true ) {
		for ( const other of tabs ) {
			const selected = other === tab;
			other.setAttribute( 'aria-selected', String( selected ) );
			other.tabIndex = selected ? 0 : -1;
			document.getElementById( other.getAttribute( 'aria-controls' ) ).hidden = ! selected;
		}
		if ( focus ) {
			tab.focus();
		}
		history.replaceState( null, '', '#' + tab.id.replace( 'if-tab-', '' ) );
	}

	for ( const tab of tabs ) {
		tab.addEventListener( 'click', () => select( tab ) );
		tab.addEventListener( 'keydown', ( event ) => {
			const index = tabs.indexOf( tab );
			const next = {
				ArrowRight: tabs[ ( index + 1 ) % tabs.length ],
				ArrowLeft: tabs[ ( index - 1 + tabs.length ) % tabs.length ],
				Home: tabs[ 0 ],
				End: tabs[ tabs.length - 1 ],
			}[ event.key ];
			if ( next ) {
				event.preventDefault();
				select( next );
			}
		} );
	}

	// Open the tab named in the address, such as /dashboard/#profile.
	const linked = document.getElementById( 'if-tab-' + location.hash.slice( 1 ) );
	if ( linked && tabs.includes( linked ) ) {
		select( linked, false );
	}

	root.addEventListener( 'change', async ( event ) => {
		const input = event.target.closest( '[data-if-proof]' );
		const file = input?.files[ 0 ];
		if ( ! file ) {
			return;
		}
		const id = input.closest( '[data-id]' ).dataset.id;
		const body = new FormData();
		body.append( 'proof', file );
		status.textContent = '';
		input.disabled = true;
		try {
			await request( `experiences/${ id }/proof`, { method: 'POST', body } );
			location.reload();
		} catch ( error ) {
			showError( error );
			input.disabled = false;
			input.value = '';
		}
	} );

	root.addEventListener( 'click', async ( event ) => {
		const remove = event.target.closest( '[data-if-delete]' );
		if ( remove ) {
			const item = remove.closest( '[data-id]' );
			// eslint-disable-next-line no-alert -- a native confirm is accessible and enough here.
			if ( ! window.confirm( 'Delete this draft? This cannot be undone.' ) ) {
				return;
			}
			try {
				await request( `experiences/${ item.dataset.id }`, { method: 'DELETE' } );
				item.remove();
			} catch ( error ) {
				showError( error );
			}
			return;
		}

		const share = event.target.closest( '[data-if-share]' );
		if ( share ) {
			const url = share.dataset.ifShare;
			if ( navigator.share ) {
				navigator.share( { title: share.dataset.title, url } ).catch( () => {} );
			} else {
				await navigator.clipboard?.writeText( url );
				status.textContent = '';
				share.querySelector( '[data-if-label]' ).textContent = 'Link copied';
			}
			return;
		}

		const all = event.target.closest( '[data-if-all-notes]' );
		if ( all ) {
			root.querySelectorAll( '[data-if-notes] li[hidden]' ).forEach( ( item ) => ( item.hidden = false ) );
			all.setAttribute( 'aria-expanded', 'true' );
			all.hidden = true;
		}
	} );

	// Seeing the list counts as reading it. The dots stay until the next visit.
	const notes = root.querySelector( '[data-if-notes]' );
	if ( notes && Number( notes.dataset.unread ) > 0 ) {
		request( 'me/notifications/read', { method: 'POST' } ).catch( () => {} );
	}
}
