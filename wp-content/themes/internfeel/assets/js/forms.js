/**
 * Submit InternFeel forms to their REST route (plain ES module, no build step).
 *
 * A form opts in with data-if-rest (the route URL) and data-if-nonce (a wp_rest nonce). On success the
 * route returns { redirect }; on failure it returns a WP_Error with a message and, when it is about one
 * field, data.field. Errors are announced through the form's role="alert" status line.
 */

const GENERIC_ERROR = 'Something went wrong. Check your connection and try again.';

/**
 * @param {HTMLFormElement} form
 */
function enhance( form ) {
	const status = form.querySelector( '[data-if-status]' );
	const button = form.querySelector( '[type="submit"]' );

	form.addEventListener( 'submit', async ( event ) => {
		event.preventDefault();

		for ( const field of form.querySelectorAll( '[aria-invalid]' ) ) {
			field.removeAttribute( 'aria-invalid' );
		}
		status.textContent = '';
		button.disabled = true;

		try {
			const response = await fetch( form.dataset.ifRest, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': form.dataset.ifNonce,
				},
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

	const field = data.data?.field && form.elements.namedItem( data.data.field );
	if ( field instanceof HTMLElement ) {
		field.setAttribute( 'aria-invalid', 'true' );
		field.focus();
	} else if ( field instanceof RadioNodeList && field[ 0 ] ) {
		field[ 0 ].focus();
	}
}

for ( const form of document.querySelectorAll( 'form[data-if-rest]' ) ) {
	enhance( form );
}
