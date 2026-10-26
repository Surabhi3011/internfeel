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

		clearFieldErrors( form );
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

	const named = data.data?.field && form.elements.namedItem( data.data.field );
	const input = named instanceof RadioNodeList ? named[ 0 ] : named;
	if ( ! ( input instanceof HTMLElement ) ) {
		return;
	}

	// Also show the message under the field itself, as the design system's .if-field__error.
	const wrapper = input.closest( '.if-field' );
	if ( wrapper ) {
		const error = document.createElement( 'span' );
		error.className = 'if-field__error';
		error.id = `${ input.id || input.name }-error`;
		error.dataset.ifFieldError = '';
		error.textContent = data.message || GENERIC_ERROR;
		wrapper.classList.add( 'is-error' );
		wrapper.append( error );
		input.setAttribute( 'aria-describedby', [ input.getAttribute( 'aria-describedby' ), error.id ].filter( Boolean ).join( ' ' ) );
	}
	input.setAttribute( 'aria-invalid', 'true' );
	input.focus();
}

/**
 * @param {HTMLFormElement} form
 */
function clearFieldErrors( form ) {
	for ( const error of form.querySelectorAll( '[data-if-field-error]' ) ) {
		const input = form.querySelector( `[aria-describedby~="${ error.id }"]` );
		if ( input ) {
			const ids = input.getAttribute( 'aria-describedby' ).split( ' ' ).filter( ( id ) => id !== error.id );
			if ( ids.length ) {
				input.setAttribute( 'aria-describedby', ids.join( ' ' ) );
			} else {
				input.removeAttribute( 'aria-describedby' );
			}
		}
		error.closest( '.if-field' )?.classList.remove( 'is-error' );
		error.remove();
	}
	for ( const field of form.querySelectorAll( '[aria-invalid]' ) ) {
		field.removeAttribute( 'aria-invalid' );
	}
}

for ( const form of document.querySelectorAll( 'form[data-if-rest]' ) ) {
	enhance( form );
}
