'use strict';

const fs = require( 'fs' );
const path = require( 'path' );
const { chromium } = require( '@playwright/test' );
const config = require( './config.js' );
const { login } = require( './wiki.js' );

/**
 * Log in once as the sysop account and reuse the session in every test.
 */
module.exports = async () => {
	// eslint-disable-next-line security/detect-non-literal-fs-filename
	fs.mkdirSync( path.dirname( config.storageStatePath ), { recursive: true } );
	const browser = await chromium.launch();
	const context = await browser.newContext( { ignoreHTTPSErrors: true } );
	const page = await context.newPage();
	try {
		await login( page );
		await context.storageState( { path: config.storageStatePath } );
	} finally {
		await browser.close();
	}
};
