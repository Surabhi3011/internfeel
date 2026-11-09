/**
 * Tools → InternFeel import: run the dry run or the import in batches of 20 and show the report.
 */

const root = document.querySelector( '[data-if-import]' );

if ( root ) {
	const status = root.querySelector( '[data-if-import-status]' );
	const report = root.querySelector( '[data-if-import-report]' );
	const buttons = root.querySelectorAll( '[data-if-import-run]' );

	for ( const button of buttons ) {
		button.addEventListener( 'click', () => run( button.dataset.ifImportRun === 'dry' ) );
	}

	async function run( dryRun ) {
		if ( ! dryRun && ! window.confirm( 'Import every post now? Posts already imported are skipped.' ) ) {
			return;
		}

		buttons.forEach( ( b ) => ( b.disabled = true ) );
		report.replaceChildren();
		const rows = [];
		let offset = 0;

		try {
			while ( offset !== null ) {
				const response = await fetch( root.dataset.endpoint, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': root.dataset.nonce },
					body: JSON.stringify( { offset, dry_run: dryRun } ),
				} );
				const data = await response.json();
				if ( ! response.ok ) {
					throw new Error( data.message || `HTTP ${ response.status }` );
				}
				rows.push( ...data.rows );
				offset = data.next;
				status.textContent = `${ dryRun ? 'Dry run' : 'Importing' }: ${ rows.length } of ${ data.total }`;
			}
			status.textContent = summary( rows, dryRun );
			report.append( table( rows ) );
		} catch ( error ) {
			status.textContent = `Stopped after ${ rows.length } posts: ${ error.message }. Run it again to continue; finished posts are skipped.`;
		}

		buttons.forEach( ( b ) => ( b.disabled = false ) );
	}

	function summary( rows, dryRun ) {
		const counts = {};
		for ( const row of rows ) {
			counts[ row.action ] = ( counts[ row.action ] || 0 ) + 1;
		}
		const parts = Object.entries( counts ).map( ( [ action, n ] ) => `${ action }: ${ n }` );
		return `${ dryRun ? 'Dry run finished, nothing written.' : 'Import finished.' } ${ parts.join( ', ' ) }.`;
	}

	function table( rows ) {
		const columns = [ 'action', 'slug', 'title', 'kind', 'student_name', 'type', 'field', 'country', 'organisation', 'rating', 'image', 'comments', 'notes' ];
		const el = document.createElement( 'table' );
		el.className = 'widefat striped';
		const head = el.createTHead().insertRow();
		for ( const column of columns ) {
			const th = document.createElement( 'th' );
			th.textContent = column.replace( '_', ' ' );
			head.append( th );
		}
		const body = el.createTBody();
		for ( const row of rows ) {
			const tr = body.insertRow();
			for ( const column of columns ) {
				const value = row[ column ];
				tr.insertCell().textContent = Array.isArray( value ) ? value.join( '; ' ) : String( value ?? '' );
			}
		}
		return el;
	}
}
