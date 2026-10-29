import { expect } from '@playwright/test';

/**
 * Answer the CookieYes banner as a privacy-minded visitor would; it can cover buttons on small screens.
 *
 * @param {import('@playwright/test').Page} page
 */
export async function rejectCookies( page ) {
	await page.addLocatorHandler( page.getByRole( 'button', { name: 'Reject All' } ), async ( button ) => {
		await button.click();
	} );
}

/**
 * Sign up a new student and complete the first-sign-in profile.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} label Distinguishes the email address, e.g. the test project's name.
 * @return {Promise<{email: string, password: string}>}
 */
export async function signUpStudent( page, label ) {
	const email = `student.${ label }.${ Date.now() }@example.com`;
	const password = 'a long enough password';

	await page.goto( '/signup/' );
	await page.getByLabel( 'Email' ).fill( email );
	await page.getByLabel( 'Password' ).fill( password );
	await page.getByRole( 'button', { name: 'Create account' } ).click();
	await expect( page ).toHaveURL( /step=profile/ );

	await page.getByLabel( 'Full name' ).fill( 'Priya Sharma' );
	await page.getByLabel( 'College' ).fill( 'IIT Bombay' );
	await page.getByLabel( 'Course' ).fill( 'BTech Computer Science' );
	await page.getByLabel( 'Graduation year' ).fill( '2027' );
	await page.getByText( 'No', { exact: true } ).click();
	await page.getByRole( 'button', { name: 'Go to your dashboard' } ).click();
	await expect( page ).not.toHaveURL( /step=profile/ );

	return { email, password };
}
