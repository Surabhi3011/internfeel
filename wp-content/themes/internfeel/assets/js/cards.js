/**
 * Build an experience card from GET internfeel/v1/experiences data. Mirrors template-parts/card.php.
 */

const VERIFIED = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4.5 4.5L19 7"/></svg>';
const EYE = '<svg class="if-icon if-icon--sm" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>';
const INVITE = '<svg class="if-icon if-icon--sm" viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="4"/><path d="M2 21c0-3.9 3.1-7 7-7s7 3.1 7 7"/><path d="M19 8v6M16 11h6"/></svg>';

/**
 * Create an element with a class and text.
 *
 * @param {string} tag
 * @param {string} className
 * @param {string} text
 */
function el( tag, className = '', text = '' ) {
	const node = document.createElement( tag );
	if ( className ) {
		node.className = className;
	}
	if ( text ) {
		node.textContent = text;
	}
	return node;
}

/**
 * @param {number} rating 0 to 5 in halves.
 */
function ratingBar( rating ) {
	const value = rating.toFixed( 1 );
	const wrap = el( 'span', 'if-rating' );
	wrap.setAttribute( 'role', 'img' );
	wrap.setAttribute( 'aria-label', `Rated ${ value } out of 5` );
	const bar = el( 'span', 'if-rating__bar' );
	bar.setAttribute( 'aria-hidden', 'true' );
	for ( let i = 1; i <= 5; i++ ) {
		let segment = '';
		if ( rating >= i ) {
			segment = 'on';
		} else if ( rating >= i - 0.5 ) {
			segment = 'half';
		}
		bar.append( el( 'i', segment ) );
	}
	const number = el( 'span', 'if-rating__value', value );
	const of = el( 'span', 'if-rating__of', '/ 5' );
	number.setAttribute( 'aria-hidden', 'true' );
	of.setAttribute( 'aria-hidden', 'true' );
	wrap.append( bar, number, of );
	return wrap;
}

/**
 * @param {object} card One item from the cards array.
 * @returns {HTMLElement} The card.
 */
export function renderCard( card ) {
	const article = el( 'article', 'if-card' );

	if ( card.organisation ) {
		const top = el( 'div', 'if-card__top' );
		const initial = el( 'span', 'if-card__org', card.initial );
		initial.setAttribute( 'aria-hidden', 'true' );
		const name = el( 'span', 'if-card__orgname' );
		name.append( el( 'strong', '', card.organisation ), card.place );
		top.append( initial, name );
		article.append( top );
	}

	const title = el( 'h3', 'if-card__title' );
	const link = el( 'a', '', card.title );
	link.href = card.url;
	title.append( link );
	article.append( title );

	const facts = [ card.type, card.field, card.when, card.pay ].filter( Boolean );
	if ( facts.length ) {
		const list = el( 'ul', 'if-card__facts' );
		facts.forEach( ( fact ) => list.append( el( 'li', '', fact ) ) );
		article.append( list );
	}

	const foot = el( 'div', 'if-card__foot' );
	const marks = el( 'span', 'if-card__marks' );
	if ( card.rating > 0 ) {
		marks.append( ratingBar( card.rating ) );
	}
	if ( card.verified ) {
		const verified = el( 'span', 'if-verified' );
		verified.innerHTML = VERIFIED;
		verified.append( 'Verified' );
		marks.append( verified );
	}
	const views = el( 'span', 'if-card__views' );
	views.innerHTML = EYE;
	views.append( card.views.toLocaleString( 'en-IN' ), el( 'span', 'screen-reader-text', ' views' ) );
	foot.append( marks, views );
	article.append( foot );

	if ( card.invited ) {
		const credit = el( 'span', 'if-card__credit' );
		credit.innerHTML = INVITE;
		credit.append( 'Shared through an invite' );
		article.append( credit );
	}

	return article;
}
