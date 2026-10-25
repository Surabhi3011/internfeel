import { test, expect } from '@playwright/test';

// Checks the internfeel-hardening mu-plugin (architecture.md section 9) against a running site.

test( 'home page loads with security headers and noindex outside production', async ( { page } ) => {
	const response = await page.goto( '/' );
	expect( response.status() ).toBe( 200 );
	await expect( page.getByRole( 'link', { name: 'InternFeel home' } ) ).toBeVisible();

	const headers = response.headers();
	expect( headers[ 'x-content-type-options' ] ).toBe( 'nosniff' );
	expect( headers[ 'referrer-policy' ] ).toBe( 'strict-origin-when-cross-origin' );
	expect( headers[ 'x-frame-options' ] ).toBe( 'SAMEORIGIN' );
	expect( headers[ 'content-security-policy' ] ).toContain( "object-src 'none'" );
	expect( headers[ 'x-robots-tag' ] ).toContain( 'noindex' );
	expect( headers[ 'x-pingback' ] ).toBeUndefined();
} );

test( 'author enumeration through ?author=1 goes to the home page', async ( { page, baseURL } ) => {
	await page.goto( '/?author=1' );
	expect( page.url() ).toBe( new URL( '/', baseURL ).href );
} );

test( 'the users REST route is closed to visitors', async ( { request } ) => {
	const response = await request.get( '/?rest_route=/wp/v2/users' );
	expect( response.status() ).toBe( 404 );
	expect( ( await response.json() ).code ).toBe( 'rest_no_route' );
} );

test( 'XML-RPC exposes no WordPress or pingback methods', async ( { request } ) => {
	const response = await request.post( '/xmlrpc.php', {
		headers: { 'Content-Type': 'text/xml' },
		data: '<?xml version="1.0"?><methodCall><methodName>system.listMethods</methodName></methodCall>',
	} );
	const body = await response.text();
	expect( body ).not.toContain( 'wp.getUsersBlogs' );
	expect( body ).not.toContain( 'pingback.ping' );
} );
