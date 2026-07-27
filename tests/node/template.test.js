'use strict';

const assert = require( 'assert' );
const fs = require( 'fs' );
const path = require( 'path' );

const source = fs.readFileSync(
	path.join(
		__dirname,
		'../../resources/ext.namespaceManager.manage/App.vue'
	),
	'utf8'
);

[
	'cdx-button',
	'cdx-checkbox',
	'cdx-chip-input',
	'cdx-field',
	'cdx-icon',
	'cdx-message',
	'cdx-text-input'
].forEach( ( component ) => {
	assert.match( source, new RegExp( `<${ component }(?:\\s|>)` ) );
} );
assert.doesNotMatch( source, /<Cdx[A-Z]/ );
assert.strictEqual(
	( source.match( /v-cdx-tooltip:top/g ) || [] ).length,
	7,
	'Every non-checkbox input field must have a help tooltip.'
);
assert.strictEqual(
	( source.match( /namespacemanager-required/g ) || [] ).length,
	2,
	'Namespace ID and name must be marked as required.'
);
assert.strictEqual(
	( source.match( /namespacemanager-optional/g ) || [] ).length,
	5,
	'Every optional text or chip input must be marked as optional.'
);
assert.strictEqual(
	( source.match( /formatversion: 2/g ) || [] ).length,
	2,
	'Both NamespaceManager API requests must preserve JSON boolean types.'
);
assert.match(
	source,
	/return this\.loading \|\| this\.saving \|\| this\.loadFailed;/,
	'A failed initial load must keep destructive controls disabled.'
);
