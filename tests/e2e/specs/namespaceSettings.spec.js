'use strict';

const { test, expect } = require( '@playwright/test' );
const { ManageNamespacesPage } = require( '../support/manageNamespacesPage.js' );
const {
	articleUrl,
	indexUrl,
	namespaceInfo,
	resetNamespaces,
	userOptions
} = require( '../support/wiki.js' );

const ID = 3000;
const TALK_ID = ID + 1;
const NAME = 'E2E Project';
const TALK_NAME = `${ NAME } talk`;

test.describe.configure( { mode: 'serial' } );

test.describe( 'Namespace settings', () => {

	test.beforeEach( async ( { request } ) => {
		await resetNamespaces( request );
	} );

	test.afterEach( async ( { request } ) => {
		await resetNamespaces( request );
	} );

	test( 'the content setting controls the content namespaces', async ( {
		page,
		request
	} ) => {
		const manage = new ManageNamespacesPage( page );
		await manage.createNamespace( { content: false, id: ID, name: NAME } );
		let { namespaces } = await namespaceInfo( request );
		expect( Boolean( namespaces[ ID ].content ) ).toBe( false );

		await manage.open();
		await manage.checkbox( 0, 'content' ).setChecked( true );
		await manage.save();

		( { namespaces } = await namespaceInfo( request ) );
		expect( Boolean( namespaces[ ID ].content ) ).toBe( true );
	} );

	test( 'the subpage settings apply to both namespaces', async ( { page, request } ) => {
		const manage = new ManageNamespacesPage( page );
		await manage.createNamespace( {
			id: ID,
			name: NAME,
			subpages: true,
			talksubpages: false
		} );

		const { namespaces } = await namespaceInfo( request );
		expect( Boolean( namespaces[ ID ].subpages ) ).toBe( true );
		expect( Boolean( namespaces[ TALK_ID ].subpages ) ).toBe( false );
	} );

	test( 'the talk subpage setting can be enabled separately', async ( {
		page,
		request
	} ) => {
		const manage = new ManageNamespacesPage( page );
		await manage.createNamespace( {
			id: ID,
			name: NAME,
			subpages: false,
			talksubpages: true
		} );

		const { namespaces } = await namespaceInfo( request );
		expect( Boolean( namespaces[ ID ].subpages ) ).toBe( false );
		expect( Boolean( namespaces[ TALK_ID ].subpages ) ).toBe( true );
	} );

	test( 'the transclusion settings mark namespaces as non-includable', async ( {
		page,
		request
	} ) => {
		const manage = new ManageNamespacesPage( page );
		await manage.createNamespace( {
			id: ID,
			includable: false,
			name: NAME,
			talkincludable: true
		} );

		let { namespaces } = await namespaceInfo( request );
		expect( Boolean( namespaces[ ID ].nonincludable ) ).toBe( true );
		expect( Boolean( namespaces[ TALK_ID ].nonincludable ) ).toBe( false );

		await manage.open();
		await manage.checkbox( 0, 'includable' ).setChecked( true );
		await manage.checkbox( 0, 'talkincludable' ).setChecked( false );
		await manage.save();

		( { namespaces } = await namespaceInfo( request ) );
		expect( Boolean( namespaces[ ID ].nonincludable ) ).toBe( false );
		expect( Boolean( namespaces[ TALK_ID ].nonincludable ) ).toBe( true );
	} );

	test( 'the search settings change the default searched namespaces', async ( {
		page,
		request
	} ) => {
		const manage = new ManageNamespacesPage( page );
		await manage.createNamespace( {
			id: ID,
			name: NAME,
			searchdefault: true,
			talksearchdefault: false
		} );

		const options = await userOptions( request );
		expect( Number( options[ `searchNs${ ID }` ] ) ).toBe( 1 );
		expect( Number( options[ `searchNs${ TALK_ID }` ] ) ).toBe( 0 );
	} );

	test( 'the talk search setting can be enabled separately', async ( {
		page,
		request
	} ) => {
		const manage = new ManageNamespacesPage( page );
		await manage.createNamespace( {
			id: ID,
			name: NAME,
			searchdefault: false,
			talksearchdefault: true
		} );

		const options = await userOptions( request );
		expect( Number( options[ `searchNs${ ID }` ] ) ).toBe( 0 );
		expect( Number( options[ `searchNs${ TALK_ID }` ] ) ).toBe( 1 );
	} );

	test( 'aliases resolve to the namespace and its talk namespace', async ( {
		page,
		request
	} ) => {
		const manage = new ManageNamespacesPage( page );
		await manage.createNamespace( {
			aliases: [ 'E2EProj' ],
			id: ID,
			name: NAME,
			talkaliases: [ 'E2EProjTalk' ]
		} );

		const { aliases } = await namespaceInfo( request );
		expect( aliases ).toContainEqual(
			expect.objectContaining( { alias: 'E2EProj', id: ID } )
		);
		expect( aliases ).toContainEqual(
			expect.objectContaining( { alias: 'E2EProjTalk', id: TALK_ID } )
		);

		await page.goto( articleUrl( 'E2EProj:Sample page' ) );
		expect( await page.evaluate( () => mw.config.get( 'wgNamespaceNumber' ) ) )
			.toBe( ID );
		await page.goto( articleUrl( 'E2EProjTalk:Sample page' ) );
		expect( await page.evaluate( () => mw.config.get( 'wgNamespaceNumber' ) ) )
			.toBe( TALK_ID );
	} );

	test( 'required edit rights protect the subject namespace', async ( { page } ) => {
		const manage = new ManageNamespacesPage( page );
		await manage.createNamespace( {
			editpermissions: [ 'namespacemanager-e2e-right' ],
			id: ID,
			name: NAME
		} );

		await page.goto( indexUrl( {
			action: 'edit',
			title: `${ NAME }:Sample page`
		} ) );
		await expect( page.locator( '#mw-content-text' ) )
			.toContainText( /do not have permission|not allowed/i );

		await page.goto( indexUrl( {
			action: 'edit',
			title: `${ TALK_NAME }:Sample page`
		} ) );
		await expect( page.locator( '#mw-content-text' ) )
			.not.toContainText( /do not have permission/i );
	} );

	test( 'required talk edit rights protect the talk namespace', async ( { page } ) => {
		const manage = new ManageNamespacesPage( page );
		await manage.createNamespace( {
			id: ID,
			name: NAME,
			talkeditpermissions: [ 'namespacemanager-e2e-right' ]
		} );

		await page.goto( indexUrl( {
			action: 'edit',
			title: `${ TALK_NAME }:Sample page`
		} ) );
		await expect( page.locator( '#mw-content-text' ) )
			.toContainText( /do not have permission|not allowed/i );

		await page.goto( indexUrl( {
			action: 'edit',
			title: `${ NAME }:Sample page`
		} ) );
		await expect( page.locator( '#mw-content-text' ) )
			.not.toContainText( /do not have permission/i );
	} );

	test( 'every setting can be enabled at once', async ( { page, request } ) => {
		const manage = new ManageNamespacesPage( page );
		await manage.createNamespace( {
			aliases: [ 'E2EAll' ],
			content: true,
			editpermissions: [ 'edit' ],
			id: ID,
			includable: false,
			name: NAME,
			searchdefault: true,
			subpages: true,
			talkaliases: [ 'E2EAllTalk' ],
			talkeditpermissions: [ 'edit' ],
			talkincludable: false,
			talkname: 'E2E Discussion',
			talksearchdefault: true,
			talksubpages: true,
			visualeditor: true
		} );

		const { namespaces } = await namespaceInfo( request );
		expect( namespaces[ ID ].name ).toBe( NAME );
		expect( namespaces[ TALK_ID ].name ).toBe( 'E2E Discussion' );
		expect( Boolean( namespaces[ ID ].content ) ).toBe( true );
		expect( Boolean( namespaces[ ID ].subpages ) ).toBe( true );
		expect( Boolean( namespaces[ TALK_ID ].subpages ) ).toBe( true );
		expect( Boolean( namespaces[ ID ].nonincludable ) ).toBe( true );
		expect( Boolean( namespaces[ TALK_ID ].nonincludable ) ).toBe( true );

		const options = await userOptions( request );
		expect( Number( options[ `searchNs${ ID }` ] ) ).toBe( 1 );
		expect( Number( options[ `searchNs${ TALK_ID }` ] ) ).toBe( 1 );

		await manage.open();
		const definition = await manage.readNamespace( 0 );
		expect( definition ).toMatchObject( {
			aliases: [ 'E2EAll' ],
			content: true,
			editpermissions: [ 'edit' ],
			includable: false,
			searchdefault: true,
			subpages: true,
			talkaliases: [ 'E2EAllTalk' ],
			talkeditpermissions: [ 'edit' ],
			talkincludable: false,
			talksearchdefault: true,
			talksubpages: true,
			visualeditor: true
		} );
	} );

	test( 'every setting can be disabled at once', async ( { page, request } ) => {
		const manage = new ManageNamespacesPage( page );
		await manage.createNamespace( {
			content: false,
			id: ID,
			includable: true,
			name: NAME,
			searchdefault: false,
			subpages: false,
			talkincludable: true,
			talksearchdefault: false,
			talksubpages: false,
			visualeditor: false
		} );

		const { namespaces } = await namespaceInfo( request );
		expect( Boolean( namespaces[ ID ].content ) ).toBe( false );
		expect( Boolean( namespaces[ ID ].subpages ) ).toBe( false );
		expect( Boolean( namespaces[ TALK_ID ].subpages ) ).toBe( false );
		expect( Boolean( namespaces[ ID ].nonincludable ) ).toBe( false );
		expect( Boolean( namespaces[ TALK_ID ].nonincludable ) ).toBe( false );

		const options = await userOptions( request );
		expect( Number( options[ `searchNs${ ID }` ] ) ).toBe( 0 );
		expect( Number( options[ `searchNs${ TALK_ID }` ] ) ).toBe( 0 );

		await page.goto( indexUrl( { action: 'edit', title: `${ NAME }:Sample page` } ) );
		await expect( page.locator( '#mw-content-text' ) )
			.not.toContainText( /do not have permission/i );
	} );

	test( 'several namespaces can be configured together', async ( { page, request } ) => {
		const manage = new ManageNamespacesPage( page );
		await manage.open();
		await manage.addNamespace( { content: true, id: ID, name: NAME } );
		await manage.addNamespace( {
			id: ID + 2,
			name: 'E2E Archive',
			subpages: true
		} );
		await manage.save();

		const { namespaces } = await namespaceInfo( request );
		expect( namespaces[ ID ].name ).toBe( NAME );
		expect( namespaces[ ID + 2 ].name ).toBe( 'E2E Archive' );
		expect( Boolean( namespaces[ ID ].content ) ).toBe( true );
		expect( Boolean( namespaces[ ID + 2 ].content ) ).toBe( false );
		expect( Boolean( namespaces[ ID + 2 ].subpages ) ).toBe( true );
	} );
} );
