import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

// WCAG 2.2 AA checks with axe (docs/development.md section 7). Experience, submit and dashboard
// pages are added here as they are built.
const pages = [
	{ name: 'home', path: '/' },
	{ name: 'sign-up', path: '/signup/' },
	{ name: 'log-in', path: '/login/' },
];

for ( const { name, path } of pages ) {
	test( `${ name } has no serious accessibility issues @a11y`, async ( { page } ) => {
		await page.goto( path );

		const results = await new AxeBuilder( { page } )
			.withTags( [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa' ] )
			.analyze();
		const serious = results.violations.filter( ( v ) => [ 'serious', 'critical' ].includes( v.impact ) );

		expect( serious, JSON.stringify( serious, null, 2 ) ).toEqual( [] );
	} );
}
