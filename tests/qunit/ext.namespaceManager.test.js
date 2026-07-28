QUnit.module( 'ext.namespaceManager.utils', () => {
	const utils = mw.loader.require( 'ext.namespaceManager.utils' );

	QUnit.test( 'selects the next free even namespace ID', ( assert ) => {
		assert.strictEqual( utils.nextAvailableId( [] ), 3000 );
		assert.strictEqual(
			utils.nextAvailableId( [ { id: 3000 }, { id: 3004 } ] ),
			3002
		);
	} );

	QUnit.test( 'validates IDs and names', ( assert ) => {
		assert.deepEqual(
			utils.validateBasic( [
				{ id: 3001, name: '' },
				{ id: 3000, name: 'Valid' },
				{ id: 3000, name: 'Also valid' }
			] ),
			{
				'0:id': 'invalid-id',
				'0:name': 'invalid-name',
				'2:id': 'invalid-id'
			}
		);
	} );

	QUnit.test( 'clears a corrected namespace ID error', ( assert ) => {
		const namespaces = [ { id: 3001, name: 'Example' } ];
		let errors = utils.validateBasic( namespaces );
		assert.strictEqual( errors[ '0:id' ], 'invalid-id' );

		namespaces[ 0 ].id = 3000;
		errors = utils.updateBasicFieldError( errors, namespaces, 0, 'id' );
		assert.false( Object.prototype.hasOwnProperty.call( errors, '0:id' ) );
	} );

	QUnit.test( 'revalidates every duplicate ID after a correction', ( assert ) => {
		const namespaces = [
			{ id: 3000, name: 'First' },
			{ id: 3000, name: 'Second' }
		];
		let errors = utils.validateBasic( namespaces );
		assert.strictEqual( errors[ '1:id' ], 'invalid-id' );

		namespaces[ 0 ].id = 3002;
		errors = utils.updateBasicFieldError( errors, namespaces, 0, 'id' );
		assert.deepEqual( errors, {} );
	} );
} );

QUnit.module( 'ext.namespaceManager.manage', ( hooks ) => {
	const Vue = mw.loader.require( 'vue' );
	const App = mw.loader.require( 'ext.namespaceManager.manage' );
	let api;
	let vueApp;
	let vm;

	function resolvedRequest( response ) {
		return {
			then( callback ) {
				callback( response );
				return this;
			},
			catch() {
				return this;
			},
			always( callback ) {
				callback();
				return this;
			}
		};
	}

	function rejectedRequest( code, response ) {
		return {
			then() {
				return this;
			},
			catch( callback ) {
				callback( code, response );
				return this;
			},
			always( callback ) {
				callback();
				return this;
			}
		};
	}

	async function mount( request ) {
		api.post.returns( request );
		const mountPoint = document.createElement( 'div' );
		document.getElementById( 'qunit-fixture' ).appendChild( mountPoint );
		vueApp = Vue.createMwApp( App );
		vm = vueApp.mount( mountPoint );
		await Vue.nextTick();
	}

	hooks.beforeEach( function () {
		api = {
			post: this.sandbox.stub(),
			postWithToken: this.sandbox.stub()
		};
		this.sandbox.stub( mw, 'Api' ).returns( api );
	} );

	hooks.afterEach( () => {
		if ( vueApp ) {
			vueApp.unmount();
		}
		vueApp = null;
		vm = null;
	} );

	QUnit.test( 'loads and renders configured namespaces', async ( assert ) => {
		await mount( resolvedRequest( {
			namespacemanager: {
				namespaces: [ { id: 3000, name: 'Property' } ]
			}
		} ) );

		assert.deepEqual( api.post.firstCall.args[ 0 ], {
			action: 'namespacemanager',
			formatversion: 2,
			operation: 'get'
		} );
		assert.strictEqual(
			document.querySelector( '.namespacemanager-block h2' ).textContent.trim(),
			'Property'
		);
		assert.strictEqual(
			document.querySelector( '.namespacemanager-block input[type="number"]' ).value,
			'3000'
		);
	} );

	QUnit.test( 'adds a namespace with the next ID and focuses it', async ( assert ) => {
		await mount( resolvedRequest( {
			namespacemanager: {
				namespaces: [ { id: 3000, name: 'Property' } ]
			}
		} ) );

		document.querySelector( '.namespacemanager-actions button' ).click();
		await Vue.nextTick();

		assert.strictEqual( vm.namespaces.length, 2 );
		assert.strictEqual( vm.namespaces[ 1 ].id, 3002 );
		assert.strictEqual(
			document.activeElement,
			document.querySelectorAll( '.namespacemanager-block' )[ 1 ]
				.querySelector( 'input' ),
			'The new namespace ID receives focus.'
		);
	} );

	QUnit.test( 'shows validation errors instead of saving invalid input', async ( assert ) => {
		await mount( resolvedRequest( {
			namespacemanager: {
				namespaces: []
			}
		} ) );

		const buttons = document.querySelectorAll( '.namespacemanager-actions button' );
		buttons[ 0 ].click();
		await Vue.nextTick();
		buttons[ 1 ].click();
		await Vue.nextTick();

		assert.false( api.postWithToken.called );
		assert.strictEqual( vm.message.type, 'error' );
		assert.strictEqual( vm.message.details.length, 1 );
		assert.strictEqual(
			document.querySelectorAll( '.namespacemanager-field--error' ).length,
			1
		);
	} );

	QUnit.test( 'saves the edited definitions and reports success', async ( assert ) => {
		const definition = {
			aliases: [ 'Properties' ],
			id: 3000,
			name: 'Property'
		};
		await mount( resolvedRequest( {
			namespacemanager: { namespaces: [ definition ] }
		} ) );
		api.postWithToken.returns( resolvedRequest( {
			namespacemanager: { namespaces: [ definition ] }
		} ) );

		const buttons = document.querySelectorAll( '.namespacemanager-actions button' );
		buttons[ 1 ].click();
		await Vue.nextTick();

		assert.strictEqual( api.postWithToken.firstCall.args[ 0 ], 'csrf' );
		const request = api.postWithToken.firstCall.args[ 1 ];
		assert.strictEqual( request.operation, 'save' );
		assert.deepEqual( JSON.parse( request.namespaces )[ 0 ].aliases, [ 'Properties' ] );
		assert.false(
			Object.prototype.hasOwnProperty.call(
				JSON.parse( request.namespaces )[ 0 ],
				'key'
			)
		);
		assert.strictEqual( vm.message.type, 'success' );
	} );

	QUnit.test( 'keeps editing disabled when the initial load fails', async ( assert ) => {
		await mount( rejectedRequest( 'http' ) );

		assert.true( vm.loadFailed );
		assert.strictEqual( vm.message.type, 'error' );
		Array.prototype.forEach.call(
			document.querySelectorAll( '.namespacemanager-actions button' ),
			( button ) => {
				assert.true( button.disabled );
			}
		);
	} );
} );
