/**
 * The four-step submission form (page-submit.php). Plain ES module, no build step.
 *
 * - Each step is checked in the browser before moving on; the server checks everything again on submit.
 * - The draft is created on the first save and autosaved every 10 seconds while something has changed.
 * - Proof files upload straight away (the draft is saved first if needed).
 * - Step 4 shows a preview built from the answers.
 */

import { GENERIC_ERROR, clearFieldErrors, showFieldError } from './field-errors.js';

const form = document.querySelector( 'form[data-if-submit]' );

if ( form ) {
	const steps = [ ...form.querySelectorAll( '[data-step]' ) ];
	const stepper = [ ...document.querySelectorAll( '[data-if-stepper] li' ) ];
	const status = form.querySelector( '[data-if-status]' );
	const saved = form.querySelector( '[data-if-saved]' );
	const back = form.querySelector( '[data-if-back]' );
	const next = form.querySelector( '[data-if-next]' );
	const nextLabel = form.querySelector( '[data-if-next-label]' );
	const saveExit = form.querySelector( '[data-if-save-exit]' );
	const proofInput = form.querySelector( '#if-proof' );
	const proofList = form.querySelector( '[data-if-proof-list]' );
	const preview = form.querySelector( '[data-if-preview]' );
	const done = document.querySelector( '[data-if-done]' );
	const labels = [ 'Next: your story', 'Next: ratings and advice', 'Next: review', 'Submit for review' ];

	let id = Number( form.dataset.id ) || 0;
	let current = 1;
	let dirty = false;
	let savedAt = null;
	let saving = null;

	form.addEventListener( 'input', () => ( dirty = true ) );
	form.addEventListener( 'change', () => ( dirty = true ) );

	// Autosave every 10 seconds while something has changed (plan.md 5.1).
	setInterval( () => {
		if ( dirty && ! saving ) {
			save().catch( () => {} );
		}
		showSavedTime();
	}, 10_000 );

	// Warn before leaving with unsaved changes.
	window.addEventListener( 'beforeunload', ( event ) => {
		if ( dirty ) {
			event.preventDefault();
		}
	} );

	back.addEventListener( 'click', () => go( current - 1 ) );

	saveExit.addEventListener( 'click', async () => {
		if ( await run( () => save() ) ) {
			window.location.assign( form.dataset.home );
		}
	} );

	form.addEventListener( 'submit', async ( event ) => {
		event.preventDefault();
		if ( ! checkStep( current ) ) {
			return;
		}
		if ( current < steps.length ) {
			if ( await run( () => save() ) ) {
				go( current + 1 );
			}
			return;
		}
		const result = await run( async () => {
			const data = await save( true );
			// Only the server saying "pending" means it really went to review.
			if ( data.status !== 'pending' ) {
				throw failure( 'Your experience was saved but not sent for review. Please try again.' );
			}
			return data;
		} );
		if ( result ) {
			dirty = false;
			form.hidden = true;
			document.querySelector( '[data-if-stepper]' ).hidden = true;
			document.querySelector( '.if-submit__aside' ).hidden = true;
			document.querySelector( '.if-submit__layout' ).classList.add( 'is-done' );
			done.hidden = false;
			done.querySelector( 'h2' ).focus();
		}
	} );

	proofInput.addEventListener( 'change', async () => {
		const file = proofInput.files[ 0 ];
		if ( ! file ) {
			return;
		}
		await run( async () => {
			if ( ! id ) {
				await save();
			}
			const body = new FormData();
			body.append( 'proof', file );
			const data = await request( `experiences/${ id }/proof`, { method: 'POST', body } );
			renderProofs( data.proofs );
			return data;
		} );
		proofInput.value = '';
	} );

	proofList.addEventListener( 'click', async ( event ) => {
		const button = event.target.closest( '[data-if-proof-remove]' );
		if ( ! button ) {
			return;
		}
		const file = button.closest( 'li' ).dataset.file;
		await run( async () => {
			const data = await request( `experiences/${ id }/proof/${ file }`, { method: 'DELETE' } );
			renderProofs( data.proofs );
			return data;
		} );
	} );

	/**
	 * Run an action, showing any error. Returns its result, or null when it failed.
	 *
	 * @param {Function} action
	 */
	async function run( action ) {
		clearFieldErrors( form );
		status.textContent = '';
		next.disabled = true;
		try {
			return await action();
		} catch ( error ) {
			const data = error.data || {};
			status.textContent = data.message || GENERIC_ERROR;
			if ( data.data?.step && data.data.step !== current ) {
				go( data.data.step );
			}
			showFieldError( form, data.data?.field, data.message );
			return null;
		} finally {
			next.disabled = false;
		}
	}

	/**
	 * Save the draft, creating it on the first save. With submit, also send it for review.
	 *
	 * @param {boolean} submit
	 */
	async function save( submit = false ) {
		const body = values();
		if ( submit ) {
			body.submit = true;
		}
		dirty = false;
		saving = id
			? request( `experiences/${ id }`, { method: 'PATCH', body: JSON.stringify( body ), json: true } )
			: request( 'experiences', { method: 'POST', body: JSON.stringify( body ), json: true } );
		try {
			const data = await saving;
			if ( ! id ) {
				id = data.id;
				const url = new URL( form.dataset.formUrl );
				url.searchParams.set( 'id', String( id ) );
				window.history.replaceState( null, '', url );
			}
			savedAt = Date.now();
			showSavedTime();
			return data;
		} catch ( error ) {
			dirty = true;
			throw error;
		} finally {
			saving = null;
		}
	}

	/**
	 * Call an internfeel/v1 route; throws { data } with the WP_Error body on failure.
	 *
	 * @param {string} path
	 * @param {object} options
	 */
	async function request( path, { method, body, json = false } ) {
		const headers = { 'X-WP-Nonce': form.dataset.nonce };
		if ( json ) {
			headers[ 'Content-Type' ] = 'application/json';
		}
		const base = form.dataset.create.replace( /experiences\/?$/, '' );

		let response;
		try {
			response = await fetch( base + path, { method, body, headers, credentials: 'same-origin' } );
		} catch {
			throw failure( 'We could not reach InternFeel. Check your connection. Your draft is saved, so you can try again.' );
		}

		// Read text first: a reply that is not JSON (an error page, or a PHP warning printed before the
		// JSON) must never count as success.
		const text = await response.text();
		let data = null;
		try {
			data = JSON.parse( text );
		} catch {}

		if ( ! response.ok || ! data || typeof data !== 'object' ) {
			window.console.error( 'InternFeel request failed:', method, path, response.status, text.slice( 0, 500 ) );
			throw failure(
				data?.message || `Something went wrong (error ${ response.status }). Your draft is saved, so try again in a moment.`,
				data?.data
			);
		}
		return data;
	}

	/**
	 * An error in the shape run() expects: { data: { message, data } }.
	 *
	 * @param {string} message
	 * @param {object} details WP_Error data (field, step), if any.
	 */
	function failure( message, details = undefined ) {
		return Object.assign( new Error( message ), { data: { message, data: details } } );
	}

	/** The form's answers as the REST route expects them. */
	function values() {
		const data = { story: {} };
		for ( const [ name, value ] of new FormData( form ) ) {
			if ( name === 'proof' ) {
				continue;
			}
			const story = name.match( /^story\[(q\d)\]$/ );
			if ( story ) {
				data.story[ story[ 1 ] ] = value;
			} else {
				data[ name ] = value;
			}
		}
		data.guidelines = form.elements.namedItem( 'guidelines' ).checked;
		return data;
	}

	/**
	 * Check the required fields on one step, showing the first problem.
	 *
	 * @param {number} step
	 */
	function checkStep( step ) {
		clearFieldErrors( form );
		for ( const field of steps[ step - 1 ].querySelectorAll( 'input, textarea, select' ) ) {
			if ( ! field.checkValidity() ) {
				const message = field.type === 'radio' ? 'Choose one of the options.' : field.validationMessage;
				showFieldError( form, field.name, message );
				return false;
			}
		}
		return true;
	}

	/**
	 * Show one step.
	 *
	 * @param {number} step
	 */
	function go( step ) {
		current = Math.min( Math.max( step, 1 ), steps.length );
		steps.forEach( ( section, index ) => ( section.hidden = index !== current - 1 ) );
		stepper.forEach( ( item, index ) => {
			item.classList.toggle( 'is-done', index < current - 1 );
			if ( index === current - 1 ) {
				item.setAttribute( 'aria-current', 'step' );
			} else {
				item.removeAttribute( 'aria-current' );
			}
		} );
		back.hidden = current === 1;
		nextLabel.textContent = labels[ current - 1 ];
		next.querySelector( 'svg' ).hidden = current === steps.length;
		if ( current === steps.length ) {
			renderPreview();
		}
		steps[ current - 1 ].querySelector( 'h2' ).focus();
	}

	function showSavedTime() {
		if ( ! savedAt ) {
			return;
		}
		const seconds = Math.round( ( Date.now() - savedAt ) / 1000 );
		saved.textContent = seconds < 10 ? 'Draft saved just now' : `Draft saved ${ seconds < 60 ? `${ seconds } seconds` : `${ Math.round( seconds / 60 ) } minutes` } ago`;
	}

	/**
	 * @param {Array<{file: string, name: string}>} proofs
	 */
	function renderProofs( proofs ) {
		proofList.replaceChildren(
			...proofs.map( ( proof ) => {
				const item = document.createElement( 'li' );
				item.dataset.file = proof.file;
				const name = document.createElement( 'span' );
				name.textContent = proof.name;
				const remove = document.createElement( 'button' );
				remove.type = 'button';
				remove.className = 'if-btn if-btn--ghost if-btn--sm';
				remove.dataset.ifProofRemove = '';
				remove.textContent = 'Remove';
				item.append( name, ' ', remove );
				return item;
			} )
		);
	}

	/** The review preview, built with the same components as the experience page. */
	function renderPreview() {
		const v = values();
		const text = ( selector ) => form.querySelector( selector )?.closest( 'label' )?.textContent.trim() ?? '';
		const facts = [
			[ 'Organisation', v.organisation ],
			[ 'Programme', v.programme ],
			[ 'Type', text( `input[name="type"][value="${ v.type }"]` ) ],
			[ 'Field', v.field ],
			[ 'Location', [ v.city, v.country ].filter( Boolean ).join( ', ' ) ],
			[ 'Started', v.start_month ],
			[ 'Duration', v.duration_weeks ? `${ v.duration_weeks } weeks` : '' ],
			[ 'Stipend', v.paid === '1' ? ( v.stipend_amount_inr ? `Paid · ₹${ Number( v.stipend_amount_inr ).toLocaleString( 'en-IN' ) } a month` : 'Paid' ) : ( v.paid === '0' ? 'Unpaid' : '' ) ],
			[ 'Selection', v.selection_difficulty ? `${ v.selection_difficulty } of 5` : '' ],
		].filter( ( [ , value ] ) => value );

		const box = document.createElement( 'div' );
		box.className = 'if-facts';
		const list = document.createElement( 'dl' );
		for ( const [ term, value ] of facts ) {
			const wrap = document.createElement( 'div' );
			const dt = document.createElement( 'dt' );
			const dd = document.createElement( 'dd' );
			dt.textContent = term;
			dd.textContent = value;
			wrap.append( dt, dd );
			list.append( wrap );
		}
		box.append( list );

		const answers = [ ...steps[ 1 ].querySelectorAll( 'textarea' ) ]
			.filter( ( textarea ) => textarea.value.trim() )
			.map( ( textarea ) => {
				const qa = document.createElement( 'div' );
				qa.className = 'if-qa';
				const q = document.createElement( 'p' );
				q.className = 'if-qa__q';
				q.textContent = form.querySelector( `label[for="${ textarea.id }"]` ).textContent.replace( /\*\s*$/, '' ).trim();
				const a = document.createElement( 'div' );
				a.className = 'if-qa__a';
				for ( const paragraph of textarea.value.trim().split( /\n\s*\n/ ) ) {
					const p = document.createElement( 'p' );
					p.textContent = paragraph;
					a.append( p );
				}
				qa.append( q, a );
				return qa;
			} );

		preview.replaceChildren( box, ...answers );
	}
}
