'use strict';

const path = require( 'path' );
const { defineConfig, devices } = require( '@playwright/test' );
const config = require( './support/config.js' );

module.exports = defineConfig( {
	testDir: path.join( __dirname, 'specs' ),
	outputDir: path.join( __dirname, process.env.E2E_RESULTS_DIR || 'test-results' ),
	globalSetup: require.resolve( './support/globalSetup' ),
	// Saving the namespace configuration replaces the whole wiki configuration,
	// so the tests must not run concurrently against the same wiki.
	fullyParallel: false,
	workers: 1,
	forbidOnly: !!process.env.CI,
	retries: process.env.CI ? 1 : 0,
	timeout: 120000,
	expect: { timeout: 20000 },
	reporter: process.env.CI ?
		[
			[ 'list' ],
			[ 'html', {
				open: 'never',
				outputFolder: path.join( __dirname, process.env.E2E_REPORT_DIR || 'report' )
			} ]
		] :
		[ [ 'list' ] ],
	use: {
		baseURL: config.baseUrl,
		storageState: config.storageStatePath,
		ignoreHTTPSErrors: true,
		screenshot: 'only-on-failure',
		trace: 'retain-on-failure',
		video: 'retain-on-failure'
	},
	projects: [
		{
			name: 'chromium',
			use: Object.assign( {}, devices[ 'Desktop Chrome' ] )
		}
	]
} );
