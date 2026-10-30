/**
 * Live results for the Browse form (template-parts/browse.php): any change fetches
 * GET internfeel/v1/experiences, replaces the grid and puts the filters in the URL, so the view can
 * be shared. Without JavaScript the form submits normally and the page renders the same results.
 */

import { renderCard } from './cards.js';

const form = document.querySelector( 'form[data-if-browse]' );

if ( form ) {
	const results = document.querySelector( '[data-if-results]' );
	const grid = results.querySelector( '[data-if-grid]' );
	const status = results.querySelector( '[data-if-status]' );
	const empty = results.querySelector( '[data-if-empty]' );
	const more = results.querySelector( '[data-if-more]' );
	const count = document.querySelector( '[data-if-count]' );
	const clear = form.querySelector( '[data-if-clear]' );
	const defaults = document.querySelectorAll( '[data-if-default]' );
	let page = Number( new URLSearchParams( location.search ).get( 'page' ) ) || 1;
	let controller = null;
	let typing = null;

	/** The form's filters, without empty values. */
	function params() {
		const query = new URLSearchParams();
		for ( const [ name, value ] of new FormData( form ) ) {
			if ( value ) {
				query.set( name, value );
			}
		}
		return query;
	}

	/** Mark selected chips, as the server does. */
	function markChips() {
		form.querySelectorAll( '.if-chip--select' ).forEach( ( chip ) => {
			chip.classList.toggle( 'is-selected', chip.querySelector( 'select' ).value !== '' );
		} );
		form.querySelectorAll( '.if-chip--toggle' ).forEach( ( chip ) => {
			chip.classList.toggle( 'is-selected', chip.querySelector( 'input' ).checked );
		} );
	}

	/**
	 * Fetch a page of results.
	 *
	 * @param {boolean} append Add to the grid (Load more) instead of replacing it.
	 */
	async function load( append = false ) {
		const query = params();
		const filtered = [ ...query.keys() ].length > 0;
		page = append ? page + 1 : 1;
		if ( page > 1 ) {
			query.set( 'page', String( page ) );
		}

		const url = new URL( form.action );
		url.search = query.toString();
		history.replaceState( null, '', filtered || page > 1 ? url : form.action );
		clear.hidden = ! filtered;
		markChips();

		// The home page's rows give way to results once something is chosen.
		const showResults = filtered || form.dataset.alwaysResults !== undefined;
		defaults.forEach( ( section ) => ( section.hidden = showResults ) );
		results.hidden = ! showResults;
		if ( ! showResults ) {
			return;
		}

		controller?.abort();
		controller = new AbortController();
		status.textContent = 'Loading…';
		try {
			const response = await fetch( `${ form.dataset.rest }?${ query }`, { signal: controller.signal } );
			const data = await response.json();
			if ( ! response.ok ) {
				throw new Error( data?.message );
			}
			const cards = data.cards.map( renderCard );
			if ( append ) {
				grid.append( ...cards );
				cards[ 0 ]?.querySelector( 'a' ).focus();
			} else {
				grid.replaceChildren( ...cards );
			}
			const total = data.total.toLocaleString( 'en-IN' );
			count.textContent = `${ total } ${ data.total === 1 ? 'experience' : 'experiences' }`;
			status.textContent = `${ total } ${ data.total === 1 ? 'experience matches' : 'experiences match' }.`;
			empty.hidden = data.total > 0;
			more.hidden = page >= data.pages;
		} catch ( error ) {
			if ( error.name !== 'AbortError' ) {
				status.textContent = 'Results could not be loaded. Check your connection and try again.';
			}
		}
	}

	form.addEventListener( 'change', () => load() );
	form.addEventListener( 'input', ( event ) => {
		if ( event.target.type === 'search' ) {
			clearTimeout( typing );
			typing = setTimeout( () => load(), 300 );
		}
	} );
	form.addEventListener( 'submit', ( event ) => {
		event.preventDefault();
		load();
	} );
	more.addEventListener( 'click', ( event ) => {
		event.preventDefault();
		load( true );
	} );
	clear.addEventListener( 'click', ( event ) => {
		event.preventDefault();
		form.reset();
		form.querySelectorAll( 'select' ).forEach( ( select ) => ( select.value = '' ) );
		form.querySelector( 'input[name="q"]' ).value = '';
		form.querySelector( 'input[name="verified"]' ).checked = false;
		form.querySelector( 'input[name="type"][value=""]' ).checked = true;
		load();
	} );
}
