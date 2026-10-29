import { test, expect } from '@playwright/test';
import { rejectCookies, signUpStudent } from './helpers.js';

// plan.md 5.1 "Submission form" and "Verify your experience": each step validates its required fields,
// the preview matches the answers, proof uploads privately, and the experience goes to review.

// A 1×1 PNG, so the proof upload passes the real-type check.
const PNG = Buffer.from( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', 'base64' );

test.beforeEach( async ( { page } ) => rejectCookies( page ) );

test( 'a student fills in all four steps, adds proof and submits for review', async ( { page }, testInfo ) => {
	await signUpStudent( page, `submit-${ testInfo.project.name }` );
	await page.goto( '/submit/' );

	// Step 1 will not move on with required fields missing.
	await page.getByRole( 'button', { name: 'Next: your story' } ).click();
	await expect( page.getByLabel( 'Organisation' ) ).toBeFocused();
	await expect( page.locator( '.if-field__error' ).first() ).toBeVisible();

	await page.getByLabel( 'Organisation' ).fill( 'Northwind Labs' );
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

	// Step 2: the first four questions are required.
	await expect( page.getByRole( 'heading', { name: 'Your story' } ) ).toBeVisible();
	await expect( page ).toHaveURL( /[?&]id=\d+/ );
	const answers = [ 'Second-year student of electronics.', 'Applied through the department notice board.', 'Two interviews; my project helped.', 'Designed a sensor board and its firmware.' ];
	for ( const [ index, answer ] of answers.entries() ) {
		await page.locator( `textarea[name="story[q${ index + 1 }]"]` ).fill( answer );
	}
	await page.getByRole( 'button', { name: 'Next: ratings and advice' } ).click();

	// Step 3: four ratings.
	await expect( page.getByRole( 'heading', { name: 'Ratings and advice' } ) ).toBeVisible();
	for ( const name of [ 'rating_overall', 'rating_learning', 'rating_mentorship', 'rating_culture' ] ) {
		await page.locator( `input[name="${ name }"][value="5"]` ).check( { force: true } );
	}
	await page.getByLabel( 'Advice for students applying' ).fill( 'Make the proposal specific.' );
	await page.getByRole( 'button', { name: 'Next: review' } ).click();

	// Step 4: proof, preview, guidelines, submit.
	await expect( page.getByRole( 'heading', { name: 'Review and submit' } ) ).toBeVisible();
	await page.getByLabel( 'Proof (optional)' ).setInputFiles( { name: 'offer-letter.png', mimeType: 'image/png', buffer: PNG } );
	await expect( page.locator( '[data-if-proof-list] li' ) ).toHaveCount( 1 );

	const preview = page.locator( '[data-if-preview]' );
	await expect( preview ).toContainText( 'Northwind Labs' );
	await expect( preview ).toContainText( 'Paid · ₹15,000 a month' );
	await expect( preview ).toContainText( 'Designed a sensor board and its firmware.' );

	await page.getByRole( 'button', { name: 'Submit for review' } ).click();
	await expect( page.getByRole( 'checkbox' ) ).toBeFocused();

	await page.getByRole( 'checkbox' ).check();
	await page.getByRole( 'button', { name: 'Submit for review' } ).click();
	await expect( page.getByRole( 'heading', { name: 'Submitted for review' } ) ).toBeVisible();
} );

test( 'signed-out visitors are sent to log in first', async ( { page } ) => {
	await page.goto( '/submit/' );
	await expect( page ).toHaveURL( /\/login\/\?redirect_to=/ );
} );
