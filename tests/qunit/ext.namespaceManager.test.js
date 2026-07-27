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
