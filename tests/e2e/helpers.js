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

// A 1×1 PNG, so a proof upload passes the real-type check.
export const PNG = Buffer.from( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', 'base64' );

/**
 * Submit a complete experience with proof, as a signed-in student.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} organisation Unique per run, so the admin can find it in the queue.
 * @return {Promise<string>} The experience's title.
 */
export async function submitExperience( page, organisation ) {
	await page.goto( '/submit/' );
	await page.getByLabel( 'Organisation' ).fill( organisation );
	await page.getByLabel( 'Programme or role' ).fill( 'Summer Research Fellowship' );
	await page.getByText( 'Research', { exact: true } ).click();
	await page.getByLabel( 'Field' ).fill( 'Electronics' );
	await page.getByText( 'On-site', { exact: true } ).click();
	await page.getByLabel( 'City' ).fill( 'Bengaluru' );
	await page.getByLabel( 'Start month' ).fill( '2026-05' );
	await page.getByLabel( 'Duration in weeks' ).fill( '8' );
	await page.getByText( 'Paid', { exact: true } ).click();
	await page.getByLabel( 'Stipend a month' ).fill( '15000' );
	await page.locator( 'input[name="selection_difficulty"][value="4"]' ).check( { force: true } );
	await page.getByRole( 'button', { name: 'Next: your story' } ).click();

	await expect( page.getByRole( 'heading', { name: 'Your story' } ) ).toBeVisible();
	for ( let q = 1; q <= 5; q++ ) {
		await page.locator( `textarea[name="story[q${ q }]"]` ).fill( `Answer ${ q }: what happened, in enough detail to help.` );
	}
	await page.getByRole( 'button', { name: 'Next: ratings and advice' } ).click();

	await expect( page.getByRole( 'heading', { name: 'Ratings and advice' } ) ).toBeVisible();
	for ( const name of [ 'rating_overall', 'rating_learning', 'rating_mentorship', 'rating_culture' ] ) {
		await page.locator( `input[name="${ name }"][value="5"]` ).check( { force: true } );
	}
	await page.getByRole( 'button', { name: 'Next: review' } ).click();

	await expect( page.getByRole( 'heading', { name: 'Review and submit' } ) ).toBeVisible();
	await page.getByLabel( 'Proof (optional)' ).setInputFiles( { name: 'offer-letter.png', mimeType: 'image/png', buffer: PNG } );
	await expect( page.locator( '[data-if-proof-list] li' ) ).toHaveCount( 1 );
	await page.getByRole( 'checkbox' ).check();
	await page.getByRole( 'button', { name: 'Submit for review' } ).click();
	await expect( page.getByRole( 'heading', { name: 'Submitted for review' } ) ).toBeVisible();

	return `Summer Research Fellowship at ${ organisation }`;
}

/**
 * Sign in to wp-admin as the local or CI site's admin (wp-env and Playground both use admin/password).
 *
 * @param {import('@playwright/test').Page} page
 */
export async function logInAdmin( page ) {
	await page.goto( '/wp-login.php' );
	await page.locator( '#user_login' ).fill( 'admin' );
	await page.locator( '#user_pass' ).fill( 'password' );
	await page.locator( '#wp-submit' ).click();
	await expect( page ).toHaveURL( /wp-admin/ );
}
