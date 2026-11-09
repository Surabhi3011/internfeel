/**
 * Report buttons ([data-if-report="experience|comment"][data-id]) and the one-time comment edit
 * ([data-if-edit]). Plain ES module; the dialog is template-parts/report-dialog.php.
 */

import { GENERIC_ERROR } from './field-errors.js';

const dialog = document.querySelector( '[data-if-report-dialog]' );

/**
 * Call an internfeel/v1 route; throws an Error with the server's message on failure.
 *
 * @param {string} path
 * @param {string} method
 * @param {object} body
 */
async function request( path, method, body ) {
	const response = await fetch( dialog.dataset.rest + path, {
		method,
		body: JSON.stringify( body ),
		headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': dialog.dataset.nonce },
		credentials: 'same-origin',
	} );
	const data = await response.json().catch( () => null );
	if ( ! response.ok ) {
		throw new Error( data?.message || GENERIC_ERROR );
	}
	return data;
}

if ( dialog ) {
	const form = dialog.querySelector( 'form' );
	const status = dialog.querySelector( '[data-if-status]' );
	let target = null;

	document.addEventListener( 'click', ( event ) => {
		const button = event.target.closest( 'button[data-if-report]' );
		if ( ! button ) {
			return;
		}
		target = button;
		form.reset();
		status.textContent = '';
		dialog.showModal();
	} );

	dialog.querySelector( '[data-if-close]' ).addEventListener( 'click', () => dialog.close() );

	form.addEventListener( 'submit', async ( event ) => {
		event.preventDefault();
		const reason = form.elements.namedItem( 'reason' ).value;
		if ( ! reason ) {
			status.textContent = 'Choose a reason.';
			return;
		}
		const send = form.querySelector( 'button[type="submit"]' );
		send.disabled = true;
		try {
			const data = await request( 'report', 'POST', {
				type: target.dataset.ifReport,
				id: Number( target.dataset.id ),
				reason,
				note: form.elements.namedItem( 'note' ).value,
			} );
			dialog.close();
			target.textContent = 'Reported';
			target.disabled = true;
			target.setAttribute( 'title', data.message );
			target.focus();
		} catch ( error ) {
			status.textContent = error.message;
		} finally {
			send.disabled = false;
		}
	} );

	// Edit (once): swap the comment text for a textarea, then save it.
	document.addEventListener( 'click', async ( event ) => {
		const edit = event.target.closest( 'button[data-if-edit]' );
		if ( ! edit ) {
			return;
		}
		const body = edit.closest( '.if-comment__body' );
		const text = body.querySelector( '.if-comment__text' );
		const box = document.createElement( 'div' );
		box.className = 'if-comment__editor';
		box.innerHTML = '<label class="screen-reader-text"></label><textarea rows="4" maxlength="5000"></textarea><p class="small if-muted"></p><p class="if-field__error" role="alert"></p><div class="if-comment__actions"><button type="button" class="if-btn if-btn--primary if-btn--sm" data-save>Save edit</button> <button type="button" class="if-btn if-btn--ghost if-btn--sm" data-cancel>Cancel</button></div>';
		const area = box.querySelector( 'textarea' );
		const label = box.querySelector( 'label' );
		area.id = `if-edit-${ edit.dataset.id }`;
		label.htmlFor = area.id;
		label.textContent = 'Edit your comment';
		area.value = edit.dataset.text;
		box.querySelector( '.small' ).textContent = 'You can edit a comment once. After saving it shows “edited”.';
		text.hidden = true;
		edit.hidden = true;
		text.after( box );
		area.focus();

		box.querySelector( '[data-cancel]' ).addEventListener( 'click', () => {
			box.remove();
			text.hidden = false;
			edit.hidden = false;
			edit.focus();
		} );
		box.querySelector( '[data-save]' ).addEventListener( 'click', async () => {
			try {
				const data = await request( `comments/${ edit.dataset.id }`, 'PATCH', { content: area.value } );
				text.innerHTML = data.content;
				text.hidden = false;
				box.remove();
				edit.remove();
				body.querySelector( 'time' )?.append( ' · edited' );
			} catch ( error ) {
				box.querySelector( '.if-field__error' ).textContent = error.message;
			}
		} );
	} );
}
