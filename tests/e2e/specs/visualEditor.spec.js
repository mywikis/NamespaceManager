'use strict';

const { test, expect } = require( '@playwright/test' );
const { ManageNamespacesPage } = require( '../support/manageNamespacesPage.js' );
const {
	articleUrl,
	indexUrl,
	isVisualEditorInstalled,
	resetNamespaces,
	storedNamespaces
} = require( '../support/wiki.js' );

const ID = 3010;
const NAME = 'E2E VisualEditor';

/**
 * @param {Page} page
 * @return {Promise<Object|null>} The VisualEditor configuration of the wiki.
 */
function visualEditorConfig( page ) {
	return page.evaluate(
		() => mw.config.get( 'wgVisualEditorConfig' ) || null
	);
}

test.describe.configure( { mode: 'serial' } );

test.describe( 'VisualEditor setting', () => {

	test.beforeEach( async ( { request } ) => {
		await resetNamespaces( request );
	} );

	test.afterEach( async ( { request } ) => {
		await resetNamespaces( request );
	} );

	test.describe( 'when VisualEditor is installed', () => {

		test.beforeEach( async ( { request } ) => {
			test.skip(
				!await isVisualEditorInstalled( request ),
				'VisualEditor is not installed on this wiki.'
			);
		} );

		test( 'an enabled namespace is available to VisualEditor', async ( { page } ) => {
			const manage = new ManageNamespacesPage( page );
			await manage.createNamespace( { id: ID, name: NAME, visualeditor: true } );

			await page.goto( articleUrl( `${ NAME }:Sample page` ) );
			expect( ( await visualEditorConfig( page ) ).namespaces ).toContain( ID );
		} );

		test( 'a disabled namespace is not available to VisualEditor', async ( { page } ) => {
			const manage = new ManageNamespacesPage( page );
			await manage.createNamespace( { id: ID, name: NAME, visualeditor: false } );

			await page.goto( articleUrl( `${ NAME }:Sample page` ) );
			expect( ( await visualEditorConfig( page ) ).namespaces ).not.toContain( ID );
		} );

		test( 'the setting can be turned off again', async ( { page } ) => {
			const manage = new ManageNamespacesPage( page );
			await manage.createNamespace( { id: ID, name: NAME, visualeditor: true } );

			await manage.open();
			await manage.checkbox( 0, 'visualeditor' ).setChecked( false );
			await manage.save();

			await page.goto( articleUrl( `${ NAME }:Sample page` ) );
			expect( ( await visualEditorConfig( page ) ).namespaces ).not.toContain( ID );
		} );
	} );

	test.describe( 'when VisualEditor is not installed', () => {

		test.beforeEach( async ( { request } ) => {
			test.skip(
				await isVisualEditorInstalled( request ),
				'VisualEditor is installed on this wiki.'
			);
		} );

		test( 'the namespace is still created and stored', async ( { page, request } ) => {
			const manage = new ManageNamespacesPage( page );
			await manage.createNamespace( { id: ID, name: NAME, visualeditor: true } );

			const definitions = await storedNamespaces( request );
			expect( definitions ).toHaveLength( 1 );
			expect( definitions[ 0 ] ).toMatchObject( {
				id: ID,
				name: NAME,
				visualeditor: true
			} );

			await manage.open();
			expect( await manage.readNamespace( 0 ) ).toMatchObject( {
				name: NAME,
				visualeditor: true
			} );
		} );

		test( 'the namespace can be edited with the wikitext editor', async ( { page } ) => {
			const manage = new ManageNamespacesPage( page );
			await manage.createNamespace( { id: ID, name: NAME, visualeditor: true } );

			await page.goto( indexUrl( {
				action: 'edit',
				title: `${ NAME }:Sample page`
			} ) );
			await expect( page.locator( '#wpTextbox1' ) ).toBeVisible();
			await expect( page.locator( '#mw-content-text' ) )
				.not.toContainText( /do not have permission/i );
		} );

		test( 'no VisualEditor configuration is exposed', async ( { page } ) => {
			const manage = new ManageNamespacesPage( page );
			await manage.createNamespace( { id: ID, name: NAME, visualeditor: true } );

			await page.goto( articleUrl( `${ NAME }:Sample page` ) );
			expect( await visualEditorConfig( page ) ).toBeNull();
		} );
	} );
} );
