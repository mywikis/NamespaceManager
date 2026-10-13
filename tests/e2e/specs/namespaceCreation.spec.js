'use strict';

const { test, expect } = require( '@playwright/test' );
const { ManageNamespacesPage } = require( '../support/manageNamespacesPage.js' );
const { articleUrl, namespaceInfo, resetNamespaces } = require( '../support/wiki.js' );

const NAMESPACE_ID = 3000;
const NAMESPACE_NAME = 'E2E Project';

test.describe.configure( { mode: 'serial' } );

test.describe( 'Creating and removing namespaces', () => {

	test.beforeEach( async ( { request } ) => {
		await resetNamespaces( request );
	} );

	test.afterEach( async ( { request } ) => {
		await resetNamespaces( request );
	} );

	test( 'a sysop can create a namespace and its talk namespace', async ( {
		page,
		request
	} ) => {
		const manage = new ManageNamespacesPage( page );
		await manage.createNamespace( { id: NAMESPACE_ID, name: NAMESPACE_NAME } );

		const { namespaces } = await namespaceInfo( request );
		expect( namespaces[ NAMESPACE_ID ].name ).toBe( NAMESPACE_NAME );
		expect( namespaces[ NAMESPACE_ID + 1 ].name ).toBe( `${ NAMESPACE_NAME } talk` );
	} );

	test( 'a custom talk namespace name is used', async ( { page, request } ) => {
		const manage = new ManageNamespacesPage( page );
		await manage.createNamespace( {
			id: NAMESPACE_ID,
			name: NAMESPACE_NAME,
			talkname: 'E2E Discussion'
		} );

		const { namespaces } = await namespaceInfo( request );
		expect( namespaces[ NAMESPACE_ID + 1 ].name ).toBe( 'E2E Discussion' );
	} );

	test( 'pages resolve into the new namespace', async ( { page } ) => {
		const manage = new ManageNamespacesPage( page );
		await manage.createNamespace( { id: NAMESPACE_ID, name: NAMESPACE_NAME } );

		await page.goto( articleUrl( `${ NAMESPACE_NAME }:Sample page` ) );
		expect( await page.evaluate( () => mw.config.get( 'wgNamespaceNumber' ) ) )
			.toBe( NAMESPACE_ID );

		await page.goto( articleUrl( `${ NAMESPACE_NAME } talk:Sample page` ) );
		expect( await page.evaluate( () => mw.config.get( 'wgNamespaceNumber' ) ) )
			.toBe( NAMESPACE_ID + 1 );
	} );

	test( 'the saved definition is shown again after a reload', async ( { page } ) => {
		const manage = new ManageNamespacesPage( page );
		await manage.createNamespace( {
			aliases: [ 'E2EProj' ],
			content: true,
			id: NAMESPACE_ID,
			name: NAMESPACE_NAME,
			subpages: true,
			talkname: 'E2E Discussion'
		} );

		await manage.open();
		const definition = await manage.readNamespace( 0 );
		expect( definition.id ).toBe( String( NAMESPACE_ID ) );
		expect( definition.name ).toBe( NAMESPACE_NAME.replace( / /g, '_' ) );
		expect( definition.talkname ).toBe( 'E2E_Discussion' );
		expect( definition.aliases ).toEqual( [ 'E2EProj' ] );
		expect( definition.content ).toBe( true );
		expect( definition.subpages ).toBe( true );
		expect( definition.searchdefault ).toBe( false );
		expect( definition.includable ).toBe( true );
	} );

	test( 'a namespace can be removed again', async ( { page, request } ) => {
		const manage = new ManageNamespacesPage( page );
		await manage.createNamespace( { id: NAMESPACE_ID, name: NAMESPACE_NAME } );

		await manage.open();
		await manage.removeNamespace( 0 );
		await manage.save();

		const { namespaces } = await namespaceInfo( request );
		expect( namespaces[ NAMESPACE_ID ] ).toBeUndefined();
		expect( namespaces[ NAMESPACE_ID + 1 ] ).toBeUndefined();
	} );

	test( 'an odd namespace ID is rejected', async ( { page } ) => {
		const manage = new ManageNamespacesPage( page );
		await manage.open();
		await manage.addNamespace( { id: 3001, name: NAMESPACE_NAME } );
		const message = await manage.saveExpectingFailure();
		await expect( message )
			.toContainText( 'Use an unused even ID from 3000 through 4998.' );
	} );

	test( 'a namespace without a name is rejected', async ( { page } ) => {
		const manage = new ManageNamespacesPage( page );
		await manage.open();
		await manage.addNamespace( { id: NAMESPACE_ID, name: '' } );
		const message = await manage.saveExpectingFailure();
		await expect( message )
			.toContainText( 'Enter a non-empty name without a colon.' );
	} );

	test( 'a namespace name containing a colon is rejected', async ( { page } ) => {
		const manage = new ManageNamespacesPage( page );
		await manage.open();
		await manage.addNamespace( { id: NAMESPACE_ID, name: 'Invalid:Name' } );
		const message = await manage.saveExpectingFailure();
		await expect( message )
			.toContainText( 'Enter a non-empty name without a colon.' );
	} );

	test( 'two namespaces cannot share an ID', async ( { page } ) => {
		const manage = new ManageNamespacesPage( page );
		await manage.open();
		await manage.addNamespace( { id: NAMESPACE_ID, name: NAMESPACE_NAME } );
		await manage.addNamespace( { id: NAMESPACE_ID, name: 'E2E Second' } );
		const message = await manage.saveExpectingFailure();
		await expect( message )
			.toContainText( 'Use an unused even ID from 3000 through 4998.' );
	} );

	test( 'the wiki rejects two namespaces with the same name', async ( { page } ) => {
		const manage = new ManageNamespacesPage( page );
		await manage.open();
		await manage.addNamespace( { id: NAMESPACE_ID, name: NAMESPACE_NAME } );
		await manage.addNamespace( { id: NAMESPACE_ID + 2, name: NAMESPACE_NAME } );
		const message = await manage.saveExpectingFailure();
		await expect( message )
			.toContainText( 'This name or alias conflicts with another namespace.' );
	} );

	test( 'a namespace cannot reuse a built-in namespace name', async ( { page } ) => {
		const manage = new ManageNamespacesPage( page );
		await manage.open();
		await manage.addNamespace( { id: NAMESPACE_ID, name: 'Help' } );
		const message = await manage.saveExpectingFailure();
		await expect( message )
			.toContainText( 'This name or alias conflicts with another namespace.' );
	} );
} );
