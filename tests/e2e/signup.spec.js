import { test, expect } from '@playwright/test';

// plan.md 5.1 "Done when": a new student signs up and completes their profile; sign-out works; the
// same account can log back in. Each project (desktop, mobile) uses its own address.

test.beforeEach( async ( { page } ) => {
	// The CookieYes banner can cover form buttons on small screens; answer it as a privacy-minded visitor would.
	await page.addLocatorHandler( page.getByRole( 'button', { name: 'Reject All' } ), async ( button ) => {
		await button.click();
	} );
} );

test( 'a student signs up, completes their profile, logs out and logs back in', async ( { page }, testInfo ) => {
	const email = `student.${ testInfo.project.name }.${ Date.now() }@example.com`;
	const password = 'a long enough password';

	await page.goto( '/signup/' );
	await page.getByLabel( 'Email' ).fill( email );
	await page.getByLabel( 'Password' ).fill( password );
	await page.getByRole( 'button', { name: 'Create account' } ).click();

	await expect( page.getByRole( 'heading', { name: 'Tell us about you' } ) ).toBeVisible();
	await expect( page ).toHaveURL( /step=profile/ );

	await page.getByRole( 'button', { name: 'Save and continue' } ).click();
	await expect( page.getByLabel( 'First name' ) ).toBeFocused();

	await page.getByLabel( 'First name' ).fill( 'Priya' );
	await page.getByLabel( 'Last name (optional)' ).fill( 'Sharma' );
	await page.getByLabel( 'College or university' ).fill( 'IIT Bombay' );
	await page.getByLabel( 'Course', { exact: true } ).fill( 'BTech Computer Science' );
	await page.getByLabel( 'Graduation year' ).fill( '2027' );
	await page.getByRole( 'radio', { name: 'No' } ).check();
	await page.getByRole( 'button', { name: 'Save and continue' } ).click();

	// The profile page already shows "Log out", so wait until the save has moved us on.
	await expect( page ).not.toHaveURL( /step=profile/ );
	await expect( page.getByRole( 'link', { name: 'Log out' } ) ).toBeVisible();

	// Students never see wp-admin.
	await page.goto( '/wp-admin/' );
	await expect( page ).not.toHaveURL( /wp-admin/ );

	await page.getByRole( 'link', { name: 'Log out' } ).click();
	await expect( page.getByRole( 'link', { name: 'Log in' } ) ).toBeVisible();

	await page.goto( '/login/' );
	await page.getByLabel( 'Email' ).fill( email );
	await page.getByLabel( 'Password' ).fill( password );
	await page.getByRole( 'button', { name: 'Log in' } ).click();
	await expect( page ).not.toHaveURL( /\/login\// );
	await expect( page.getByRole( 'link', { name: 'Log out' } ) ).toBeVisible();
} );

test( 'a wrong password shows an error without saying which part was wrong', async ( { page } ) => {
	await page.goto( '/login/' );
	await page.getByLabel( 'Email' ).fill( 'nobody@example.com' );
	await page.getByLabel( 'Password' ).fill( 'not the password' );
	await page.getByRole( 'button', { name: 'Log in' } ).click();

	await expect( page.getByRole( 'alert' ) ).toContainText( 'do not match' );
} );
