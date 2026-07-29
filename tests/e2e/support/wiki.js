'use strict';

const { expect } = require( '@playwright/test' );
const config = require( './config.js' );

/**
 * @param {string} title
 * @return {string} Absolute URL of the given wiki page.
 */
function articleUrl( title ) {
	const target = encodeURIComponent( title.replace( / /g, '_' ) )
		.replace( /%3A/g, ':' )
		.replace( /%2F/g, '/' );
	return config.baseUrl + config.articlePath.replace( '$1', target );
}

/**
 * @param {Object} [params]
 * @return {string} Absolute index.php URL with the given query parameters.
 */
function indexUrl( params ) {
	const query = new URLSearchParams( params || {} ).toString();
	return `${ config.baseUrl }${ config.scriptPath }/index.php${ query ? '?' + query : '' }`;
}

/**
 * @return {string} Absolute api.php URL.
 */
function apiUrl() {
	return `${ config.baseUrl }${ config.scriptPath }/api.php`;
}

/**
 * Log in through Special:UserLogin, the same way a human sysop would.
 *
 * @param {Page} page
 * @param {string} [username]
 * @param {string} [password]
 */
async function login( page, username, password ) {
	await page.goto( articleUrl( 'Special:UserLogin' ) );
	await page.locator( '#wpName1' ).fill( username || config.adminUser );
	await page.locator( '#wpPassword1' ).fill( password || config.adminPassword );
	await page.locator( '#wpLoginAttempt' ).click();
	await expect.poll(
		() => page.evaluate(
			() => ( typeof mw === 'undefined' ? null : mw.config.get( 'wgUserName' ) )
		),
		{ timeout: 30000 }
	).toBe( username || config.adminUser );
}

/**
 * @param {APIRequestContext} request
 * @param {Object} params
 * @return {Promise<Object>}
 */
async function apiGet( request, params ) {
	const response = await request.get( apiUrl(), {
		params: Object.assign( { format: 'json', formatversion: 2 }, params )
	} );
	expect( response.ok(), `API request failed: ${ response.status() }` ).toBeTruthy();
	return response.json();
}

/**
 * @param {APIRequestContext} request
 * @return {Promise<string>}
 */
async function csrfToken( request ) {
	const data = await apiGet( request, {
		action: 'query',
		meta: 'tokens',
		type: 'csrf'
	} );
	return data.query.tokens.csrftoken;
}

/**
 * @param {APIRequestContext} request
 * @param {Object} params
 * @return {Promise<Object>}
 */
async function apiPost( request, params ) {
	const response = await request.post( apiUrl(), {
		form: Object.assign(
			{ format: 'json', formatversion: 2, token: await csrfToken( request ) },
			params
		)
	} );
	return response.json();
}

/**
 * @param {APIRequestContext} request
 * @return {Promise<Object>} Namespace metadata, keyed by namespace ID.
 */
async function namespaceInfo( request ) {
	const data = await apiGet( request, {
		action: 'query',
		meta: 'siteinfo',
		siprop: 'namespaces|namespacealiases'
	} );
	return {
		aliases: data.query.namespacealiases,
		namespaces: data.query.namespaces
	};
}

/**
 * @param {APIRequestContext} request
 * @return {Promise<Array>} The namespace definitions stored by the extension.
 */
async function storedNamespaces( request ) {
	const data = await apiPost( request, {
		action: 'namespacemanager',
		operation: 'get'
	} );
	expect( data.error, JSON.stringify( data.error ) ).toBeUndefined();
	return data.namespacemanager.namespaces;
}

/**
 * @param {APIRequestContext} request
 * @param {string} name Name of the extension, as reported by the API.
 * @return {Promise<boolean>} Whether that extension is installed on the wiki.
 */
async function isExtensionInstalled( request, name ) {
	const data = await apiGet( request, {
		action: 'query',
		meta: 'siteinfo',
		siprop: 'extensions'
	} );
	return data.query.extensions.some( ( extension ) => extension.name === name );
}

/**
 * Determine whether VisualEditor is installed, and check that against the
 * expectation given through `E2E_VISUALEDITOR`, if any.
 *
 * @param {APIRequestContext} request
 * @return {Promise<boolean>} Whether VisualEditor is installed on the wiki.
 */
async function isVisualEditorInstalled( request ) {
	const installed = await isExtensionInstalled( request, 'VisualEditor' );
	if ( config.expectVisualEditor !== null ) {
		expect(
			installed,
			config.expectVisualEditor ?
				'VisualEditor was expected to be installed on the wiki under test.' :
				'VisualEditor was expected to be absent from the wiki under test.'
		).toBe( config.expectVisualEditor );
	}
	return installed;
}

/**
 * @param {APIRequestContext} request
 * @return {Promise<Object>} Preferences of the logged-in user.
 */
async function userOptions( request ) {
	const data = await apiGet( request, {
		action: 'query',
		meta: 'userinfo',
		uiprop: 'options'
	} );
	return data.query.userinfo.options;
}

/**
 * Remove every custom namespace so that each test starts from a clean wiki.
 *
 * @param {APIRequestContext} request
 */
async function resetNamespaces( request ) {
	const data = await apiPost( request, {
		action: 'namespacemanager',
		namespaces: '[]',
		operation: 'save'
	} );
	expect( data.error, JSON.stringify( data.error ) ).toBeUndefined();
}

module.exports = {
	apiGet,
	apiPost,
	apiUrl,
	articleUrl,
	indexUrl,
	isExtensionInstalled,
	isVisualEditorInstalled,
	login,
	namespaceInfo,
	resetNamespaces,
	storedNamespaces,
	userOptions
};
