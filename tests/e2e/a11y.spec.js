import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { rejectCookies, signUpStudent } from './helpers.js';

// WCAG 2.2 AA checks with axe (docs/development.md section 7) on every public page type. The
// experience page uses WordPress's sample post, which exists on a fresh install.
const pages = [
	{ name: 'home', path: '/' },
	{ name: 'home with filters', path: '/?type=research&pay=paid' },
	{ name: 'experiences', path: '/experiences/' },
	{ name: 'experience', path: '/hello-world/' },
	{ name: 'programmes', path: '/org/' },
	{ name: 'listicles', path: '/lists/' },
	{ name: 'verify', path: '/verify/' },
	{ name: 'verify, not found', path: '/verify/IF-2000-00001/' },
	{ name: 'join', path: '/join/?ref=NOTACODE' },
	{ name: 'sign-up', path: '/signup/' },
	{ name: 'log-in', path: '/login/' },
];

/**
 * Serious and critical axe violations on the page as it stands.
 *
 * @param {import('@playwright/test').Page} page
 */
async function serious( page ) {
	const results = await new AxeBuilder( { page } )
		.withTags( [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa' ] )
		.analyze();

	return results.violations.filter( ( v ) => [ 'serious', 'critical' ].includes( v.impact ) );
}

for ( const { name, path } of pages ) {
	test( `${ name } has no serious accessibility issues @a11y`, async ( { page } ) => {
		await page.goto( path );

		const found = await serious( page );
		expect( found, JSON.stringify( found, null, 2 ) ).toEqual( [] );
	} );
}

test( 'submission form has no serious accessibility issues @a11y', async ( { page }, testInfo ) => {
	await rejectCookies( page );
	await signUpStudent( page, `a11y-${ testInfo.project.name }` );
	await page.goto( '/submit/' );

	const found = await serious( page );
	expect( found, JSON.stringify( found, null, 2 ) ).toEqual( [] );
} );

test( 'dashboard, its tabs and the open header menu have no serious accessibility issues @a11y', async ( { page }, testInfo ) => {
	await rejectCookies( page );
	await signUpStudent( page, `a11y-dash-${ testInfo.project.name }` );
	await page.goto( '/dashboard/' );

	for ( const tab of [ 'My experiences', 'Comments', 'Invites', 'Profile' ] ) {
		await page.getByRole( 'tab', { name: tab } ).click();
		const found = await serious( page );
		expect( found, `${ tab }: ${ JSON.stringify( found, null, 2 ) }` ).toEqual( [] );
	}

	const menu = page.getByRole( 'banner' ).locator( 'summary:visible' ).last();
	await menu.click();
	const found = await serious( page );
	expect( found, JSON.stringify( found, null, 2 ) ).toEqual( [] );
} );

