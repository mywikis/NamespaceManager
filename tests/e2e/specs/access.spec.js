'use strict';

const { test, expect } = require( '@playwright/test' );
const { ManageNamespacesPage } = require( '../support/manageNamespacesPage.js' );
const { articleUrl } = require( '../support/wiki.js' );

test.describe( 'Special:ManageNamespaces access control', () => {

	test( 'the extension is installed on the wiki', async ( { page } ) => {
		await page.goto( articleUrl( 'Special:Version' ) );
		await expect( page.locator( '#mw-content-text' ) )
			.toContainText( 'NamespaceManager' );
	} );

	test( 'a sysop can open the namespace manager', async ( { page } ) => {
		const manage = new ManageNamespacesPage( page );
		await manage.open();
		await expect( page.locator( '#firstHeading' ) ).toContainText( 'Manage namespaces' );
		await expect( manage.saveButton ).toBeEnabled();
	} );

	test( 'an anonymous user cannot open the namespace manager', async ( { browser } ) => {
		const context = await browser.newContext( {
			ignoreHTTPSErrors: true,
			storageState: undefined
		} );
		const page = await context.newPage();
		try {
			await page.goto( articleUrl( 'Special:ManageNamespaces' ) );
			await expect( page.locator( '#mw-content-text' ) )
				.toContainText( /not allowed|permission/i );
			await expect( page.getByRole( 'button', { name: 'Add namespace' } ) )
				.toHaveCount( 0 );
		} finally {
			await context.close();
		}
	} );
} );
