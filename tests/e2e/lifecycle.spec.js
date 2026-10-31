import { test, expect } from '@playwright/test';
import { logInAdmin, rejectCookies, signUpStudent, submitExperience } from './helpers.js';

// The launch checklist's end-to-end flows (plan.md 8): submit, review, publish, verify, certificate,
// the verify page, invites with referral credit, comments with notifications, edit once and reports.
// One journey, with each person in their own browser context.

test.describe.configure( { mode: 'serial', timeout: 300_000 } );

test( 'an experience goes from submission to certificate, invite, comment and report', async ( { browser }, testInfo ) => {
	const run = `${ testInfo.project.name }-${ Date.now() }`;
	const organisation = `Northwind ${ run }`;

	const author = await ( await browser.newContext() ).newPage();
	const admin = await ( await browser.newContext() ).newPage();
	const friend = await ( await browser.newContext() ).newPage();
	for ( const page of [ author, admin, friend ] ) {
		await rejectCookies( page );
	}

	// 1. A student submits an experience with proof.
	await signUpStudent( author, `author-${ run }` );
	const title = await submitExperience( author, organisation );

	// 2. An admin approves it and verifies the proof in the review queue.
	await logInAdmin( admin );
	await admin.goto( '/wp-admin/admin.php?page=internfeel-review' );
	const item = admin.locator( '.if-review__item', { hasText: title } );
	await item.getByRole( 'button', { name: 'Approve and publish' } ).click();
	await expect( admin.locator( '.notice-success' ) ).toContainText( 'Published' );

	await admin.goto( '/wp-admin/admin.php?page=internfeel-review&tab=proofs' );
	await admin.locator( '.if-review__item', { hasText: title } ).getByRole( 'button', { name: 'Verify proof' } ).click();
	await expect( admin.locator( '.notice-success' ) ).toContainText( 'Proof verified' );

	// 3. The student sees it published and verified, with a certificate and three invites.
	await author.goto( '/dashboard/' );
	const card = author.locator( '.if-dash__item', { hasText: title } );
	await expect( card.locator( '.if-status--published' ) ).toBeVisible();
	await expect( card.locator( '.if-status--verified' ) ).toBeVisible();
	const number = ( await card.locator( '.mono' ).textContent() ).trim();
	expect( number ).toMatch( /^IF-\d{4}-\d{5}$/ );

	const download = await author.request.get( await card.getByRole( 'link', { name: 'Download certificate' } ).getAttribute( 'href' ) );
	expect( download.status() ).toBe( 200 );
	expect( download.headers()[ 'content-type' ] ).toContain( 'application/pdf' );

	// 4. Anyone can check the certificate.
	await friend.goto( `/verify/${ number }/` );
	await expect( friend.locator( '.if-verify__card' ) ).toContainText( 'Valid' );

	// 5. The student makes an invite link; a friend joins through it.
	await author.getByRole( 'tab', { name: 'Invites' } ).click();
	await expect( author.locator( '#if-panel-invites .if-dash__credits-number' ) ).toHaveText( '3' );
	await author.locator( '#if-panel-invites' ).getByRole( 'button', { name: 'Make an invite link' } ).click();
	const link = await author.locator( '#if-invite-link-tab' ).inputValue();
	expect( link ).toMatch( /\/join\/\?ref=[A-Z0-9]{8}$/ );

	await friend.goto( link );
	await expect( friend.getByRole( 'heading', { level: 1 } ) ).toContainText( 'invited you' );
	await signUpStudent( friend, `friend-${ run }` );

	// A full reload: the author is already on /dashboard/#invites, and going there again would only
	// move to the anchor.
	await author.reload();
	await expect( author.locator( '#if-panel-invites tbody td:nth-child(2)' ).first() ).toHaveText( 'Used' );
	await expect( author.locator( '.if-dash__counter' ).nth( 3 ).locator( '.if-dash__number' ) ).toHaveText( '1' );

	// 6. The friend comments on the experience, edits the comment once, and the author is notified.
	await friend.goto( `/experiences/?q=${ encodeURIComponent( organisation ) }` );
	await friend.locator( '[data-if-grid]' ).getByRole( 'link', { name: title } ).click();
	await friend.getByLabel( 'Your comment' ).fill( 'Was the stipend paid on time?' );
	await friend.getByRole( 'button', { name: 'Post comment' } ).click();
	await expect( friend.locator( '.if-comment__text' ) ).toContainText( 'Was the stipend paid on time?' );

	await friend.getByRole( 'button', { name: 'Edit (once)' } ).click();
	await friend.getByLabel( 'Edit your comment' ).fill( 'Was the stipend paid on time, and monthly?' );
	await friend.getByRole( 'button', { name: 'Save edit' } ).click();
	await expect( friend.locator( '.if-comment__text' ) ).toContainText( 'and monthly' );
	await expect( friend.getByRole( 'button', { name: 'Edit (once)' } ) ).toHaveCount( 0 );

	await author.goto( '/dashboard/' );
	await expect( author.locator( '[data-if-notes]' ) ).toContainText( 'commented on' );

	// 7. The friend reports the experience; it lands in Review → Reports.
	await friend.getByRole( 'button', { name: 'Report it' } ).click();
	await friend.getByLabel( 'False or misleading' ).check();
	await friend.getByRole( 'button', { name: 'Send report' } ).click();
	await expect( friend.getByRole( 'button', { name: 'Reported' } ) ).toBeDisabled();

	await admin.goto( '/wp-admin/admin.php?page=internfeel-reports' );
	await expect( admin.locator( '.card', { hasText: title } ) ).toContainText( 'False or misleading' );
} );
