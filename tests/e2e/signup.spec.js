import { test, expect } from '@playwright/test';
import { rejectCookies } from './helpers.js';

// plan.md 5.1 "Done when": a new student signs up and completes their profile; sign-out works; the
// same account can log back in. Each project (desktop, mobile) uses its own address.

test.beforeEach( async ( { page } ) => rejectCookies( page ) );

test( 'a student signs up, completes their profile, logs out and logs back in', async ( { page }, testInfo ) => {
	const email = `student.${ testInfo.project.name }.${ Date.now() }@example.com`;
	const password = 'a long enough password';

	await page.goto( '/signup/' );
	await page.getByLabel( 'Email' ).fill( email );
	await page.getByLabel( 'Password' ).fill( password );
	await page.getByRole( 'button', { name: 'Create account' } ).click();

	await expect( page.getByRole( 'heading', { name: 'Tell us a little about you' } ) ).toBeVisible();
	await expect( page ).toHaveURL( /step=profile/ );

	await page.getByRole( 'button', { name: 'Go to your dashboard' } ).click();
	await expect( page.getByLabel( 'Full name' ) ).toBeFocused();

	await page.getByLabel( 'Full name' ).fill( 'Priya Sharma' );
	await page.getByLabel( 'College' ).fill( 'IIT Bombay' );
	await page.getByLabel( 'Course' ).fill( 'BTech Computer Science' );
	await page.getByLabel( 'Graduation year' ).fill( '2027' );
	await page.getByText( 'No', { exact: true } ).click();
	await page.getByRole( 'button', { name: 'Go to your dashboard' } ).click();

	// Wait until the save has moved us on; signed in, the header shows the notifications bell.
	await expect( page ).not.toHaveURL( /step=profile/ );
	await expect( page.getByRole( 'link', { name: /^Notifications/ } ) ).toBeVisible();

	// Students never see wp-admin.
	await page.goto( '/wp-admin/' );
	await expect( page ).not.toHaveURL( /wp-admin/ );

	// Log out sits in the avatar menu (or the Menu button on phones).
	await openMenu( page );
	await page.getByRole( 'banner' ).getByRole( 'link', { name: 'Log out' } ).filter( { visible: true } ).click();
	await openMenu( page );
	await expect( page.getByRole( 'banner' ).getByRole( 'link', { name: 'Log in' } ).filter( { visible: true } ) ).toHaveCount( 1 );

	await page.goto( '/login/' );
	await page.getByLabel( 'Email' ).fill( email );
	await page.getByLabel( 'Password' ).fill( password );
	await page.getByRole( 'button', { name: 'Log in' } ).click();
	await expect( page ).not.toHaveURL( /\/login\// );
	await expect( page.getByRole( 'link', { name: /^Notifications/ } ) ).toBeVisible();
} );

/**
 * Open the header menu that this screen width shows: the avatar menu when signed in on a wide
 * screen, otherwise the Menu button.
 *
 * @param {import('@playwright/test').Page} page
 */
async function openMenu( page ) {
	const account = page.getByRole( 'banner' ).locator( 'summary[aria-label="Your account menu"]' );
	if ( await account.isVisible() ) {
		await account.click();
		return;
	}
	const menu = page.getByRole( 'banner' ).locator( 'summary[aria-label="Menu"]' );
	if ( await menu.isVisible() ) {
		await menu.click();
	}
}

test( 'a wrong password shows an error without saying which part was wrong', async ( { page } ) => {
	await page.goto( '/login/' );
	await page.getByLabel( 'Email' ).fill( 'nobody@example.com' );
	await page.getByLabel( 'Password' ).fill( 'not the password' );
	await page.getByRole( 'button', { name: 'Log in' } ).click();

	await expect( page.getByRole( 'alert' ) ).toContainText( 'do not match' );
} );
