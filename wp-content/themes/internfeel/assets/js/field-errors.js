/**
 * Field-level error messages in the design system's style (.if-field.is-error and .if-field__error),
 * shared by the account forms and the submission form.
 */

export const GENERIC_ERROR = 'Something went wrong. Check your connection and try again.';

/**
 * Show a message under one field, mark it invalid and move focus to it.
 *
 * @param {HTMLFormElement} form
 * @param {string}          name    The field's name attribute.
 * @param {string}          message
 * @return {boolean} Whether the field was found.
 */
export function showFieldError( form, name, message ) {
	const named = name && form.elements.namedItem( name );
	const input = named instanceof RadioNodeList ? named[ 0 ] : named;
	if ( ! ( input instanceof HTMLElement ) ) {
		return false;
	}

	const wrapper = input.closest( '.if-field' );
	if ( wrapper ) {
		const error = document.createElement( 'span' );
		error.className = 'if-field__error';
		error.id = `${ ( input.id || input.name ).replace( /[^\w-]/g, '-' ) }-error`;
		error.dataset.ifFieldError = '';
		error.textContent = message || GENERIC_ERROR;
		wrapper.classList.add( 'is-error' );
		wrapper.append( error );
		input.setAttribute( 'aria-describedby', [ input.getAttribute( 'aria-describedby' ), error.id ].filter( Boolean ).join( ' ' ) );
	}
	input.setAttribute( 'aria-invalid', 'true' );
	input.focus();
	return true;
}

/**
 * Remove every field error shown in a form.
 *
 * @param {HTMLFormElement} form
 */
export function clearFieldErrors( form ) {
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
