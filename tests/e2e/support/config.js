'use strict';

const path = require( 'path' );

/**
 * @param {string} name
 * @param {string} fallback
 * @return {string} The environment variable, which may be empty, or the fallback.
 */
function envOrDefault( name, fallback ) {
	return process.env[ name ] === undefined ? fallback : process.env[ name ];
}

const baseUrl = ( process.env.E2E_BASE_URL || 'http://localhost:8080' )
	.replace( /\/+$/, '' );

module.exports = {
	adminPassword: process.env.E2E_ADMIN_PASSWORD || 'CanastaE2EPassword1',
	adminUser: process.env.E2E_ADMIN_USER || 'Admin',
	articlePath: envOrDefault( 'E2E_ARTICLE_PATH', '/wiki/$1' ),
	baseUrl,
	scriptPath: envOrDefault( 'E2E_SCRIPT_PATH', '/w' ).replace( /\/+$/, '' ),
	storageStatePath: path.join( __dirname, '..', '.auth', 'sysop.json' )
};
