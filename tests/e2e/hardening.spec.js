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

// WordPress would normally redirect ?author=1 to /author/{login}/. Our mu-plugin redirects home, but where
// Wordfence is active (wp-env, staging, production) its own author-scan block answers first with a 404.
// Either way the login must never appear.
test( 'author enumeration through ?author=1 does not reveal a username', async ( { request, baseURL } ) => {
	const response = await request.get( '/?author=1', { maxRedirects: 0 } );
	expect( [ 301, 302, 404 ] ).toContain( response.status() );

	if ( response.status() !== 404 ) {
		expect( response.headers().location ).toBe( new URL( '/', baseURL ).href );
	}
	expect( await response.text() ).not.toContain( '/author/' );
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
