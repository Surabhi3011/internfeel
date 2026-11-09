/**
 * Submit InternFeel forms to their REST route (plain ES module, no build step).
 *
 * A form opts in with data-if-rest (the route URL) and, when signed in, data-if-nonce (a wp_rest nonce). On success the
 * route returns { redirect }; on failure it returns a WP_Error with a message and, when it is about one
 * field, data.field. Errors are announced through the form's role="alert" status line.
 */

import { GENERIC_ERROR, clearFieldErrors, showFieldError } from './field-errors.js';

/**
 * @param {HTMLFormElement} form
 */
function enhance( form ) {
	const status = form.querySelector( '[data-if-status]' );
	const button = form.querySelector( '[type="submit"]' );

	form.addEventListener( 'submit', async ( event ) => {
		event.preventDefault();

		clearFieldErrors( form );
		status.textContent = '';
		button.disabled = true;

		try {
			const response = await fetch( form.dataset.ifRest, {
				method: 'POST',
				credentials: 'same-origin',
				// Signed-out forms (sign-up, log-in) send no wp_rest nonce: WordPress would check it against
				// a signed-out visitor and could refuse a stale page. They carry their own if_auth nonce.
				headers: form.dataset.ifNonce
					? { 'Content-Type': 'application/json', 'X-WP-Nonce': form.dataset.ifNonce }
					: { 'Content-Type': 'application/json' },
				body: JSON.stringify( Object.fromEntries( new FormData( form ) ) ),
			} );
			const data = await response.json().catch( () => ( {} ) );

			if ( response.ok && data.redirect ) {
				window.location.assign( data.redirect );
				return;
			}

			showError( form, status, data );
		} catch {
			status.textContent = GENERIC_ERROR;
		}

		button.disabled = false;
	} );
}

/**
 * @param {HTMLFormElement} form
 * @param {HTMLElement}     status
 * @param {object}          data   WP_Error response body.
 */
function showError( form, status, data ) {
	status.textContent = data.message || GENERIC_ERROR;

	if ( data.data?.login_url ) {
		const link = document.createElement( 'a' );
		link.href = data.data.login_url;
		link.textContent = 'Go to the admin log-in page';
		status.append( ' ', link );
	}

	showFieldError( form, data.data?.field, data.message );
}

for ( const form of document.querySelectorAll( 'form[data-if-rest]' ) ) {
	enhance( form );
}
