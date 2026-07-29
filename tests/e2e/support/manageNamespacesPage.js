'use strict';

const { expect } = require( '@playwright/test' );
const { articleUrl } = require( './wiki.js' );

const CHECKBOX_LABELS = {
	content: 'Content namespace',
	includable: 'Allow transclusion',
	searchdefault: 'Search by default',
	subpages: 'Enable subpages',
	talkincludable: 'Allow talk transclusion',
	talksearchdefault: 'Search talk namespace by default',
	talksubpages: 'Enable talk subpages',
	visualeditor: 'Enable VisualEditor'
};

const CHECKBOX_DEFAULTS = {
	content: false,
	includable: true,
	searchdefault: false,
	subpages: false,
	talkincludable: true,
	talksearchdefault: false,
	talksubpages: false,
	visualeditor: false
};

const TEXT_FIELDS = [ 'id', 'name', 'talkname' ];
const CHIP_FIELDS = [ 'aliases', 'talkaliases', 'editpermissions', 'talkeditpermissions' ];

/**
 * Page object for Special:ManageNamespaces.
 */
class ManageNamespacesPage {

	/**
	 * @param {Page} page
	 */
	constructor( page ) {
		this.page = page;
		this.addButton = page.getByRole( 'button', { name: 'Add namespace' } );
		this.saveButton = page.getByRole( 'button', { name: 'Save namespaces' } );
		this.blocks = page.locator( '.namespacemanager-block' );
	}

	/**
	 * Open the special page and wait for the Vue application to finish loading.
	 */
	async open() {
		await this.page.goto( articleUrl( 'Special:ManageNamespaces' ) );
		await expect( this.addButton ).toBeEnabled( { timeout: 30000 } );
		await expect( this.page.locator( '.namespacemanager-loading' ) ).toHaveCount( 0 );
	}

	/**
	 * @param {number} index
	 * @return {Locator}
	 */
	block( index ) {
		return this.blocks.nth( index );
	}

	/**
	 * @param {number} index
	 * @param {string} field
	 * @return {Locator}
	 */
	textInput( index, field ) {
		return this.block( index )
			.locator( '.cdx-text-input__input' )
			.nth( TEXT_FIELDS.indexOf( field ) );
	}

	/**
	 * @param {number} index
	 * @param {string} field
	 * @return {Locator}
	 */
	chipInput( index, field ) {
		return this.block( index )
			.locator( '.cdx-chip-input' )
			.nth( CHIP_FIELDS.indexOf( field ) );
	}

	/**
	 * @param {number} index
	 * @param {string} field
	 * @return {Locator}
	 */
	checkbox( index, field ) {
		return this.block( index )
			.locator( '.cdx-checkbox' )
			.filter( { hasText: CHECKBOX_LABELS[ field ] } )
			.locator( 'input[type="checkbox"]' );
	}

	/**
	 * Add a namespace block and populate it with the given definition.
	 *
	 * @param {Object} definition
	 * @return {Promise<number>} Index of the added block.
	 */
	async addNamespace( definition ) {
		const index = await this.blocks.count();
		await this.addButton.click();
		await expect( this.blocks ).toHaveCount( index + 1 );
		await this.fillNamespace( index, definition );
		return index;
	}

	/**
	 * @param {number} index
	 * @param {Object} definition
	 */
	async fillNamespace( index, definition ) {
		for ( const field of TEXT_FIELDS ) {
			if ( definition[ field ] !== undefined ) {
				await this.textInput( index, field ).fill( String( definition[ field ] ) );
			}
		}

		for ( const field of CHIP_FIELDS ) {
			for ( const value of definition[ field ] || [] ) {
				const input = this.chipInput( index, field ).locator( 'input' );
				await input.fill( value );
				await input.press( 'Enter' );
			}
		}

		for ( const field of Object.keys( CHECKBOX_LABELS ) ) {
			if ( definition[ field ] === undefined ) {
				continue;
			}
			await this.checkbox( index, field ).setChecked( definition[ field ] );
		}
	}

	/**
	 * @param {number} index
	 * @return {Promise<Object>} The values currently shown in the form.
	 */
	async readNamespace( index ) {
		const definition = {};
		for ( const field of TEXT_FIELDS ) {
			definition[ field ] = await this.textInput( index, field ).inputValue();
		}
		for ( const field of CHIP_FIELDS ) {
			definition[ field ] = await this.chipInput( index, field )
				.locator( '.cdx-input-chip__text' )
				.allInnerTexts();
		}
		for ( const field of Object.keys( CHECKBOX_LABELS ) ) {
			definition[ field ] = await this.checkbox( index, field ).isChecked();
		}
		return definition;
	}

	/**
	 * @param {number} index
	 */
	async removeNamespace( index ) {
		const count = await this.blocks.count();
		await this.block( index )
			.getByRole( 'button', { name: 'Remove namespace' } )
			.click();
		await expect( this.blocks ).toHaveCount( count - 1 );
	}

	/**
	 * Save the form and assert that the wiki accepted the configuration.
	 */
	async save() {
		await this.saveButton.click();
		await expect( this.page.getByText( 'The namespace configuration was saved.' ) )
			.toBeVisible( { timeout: 30000 } );
	}

	/**
	 * Save the form and assert that it was rejected.
	 *
	 * @return {Locator} The error message element.
	 */
	async saveExpectingFailure() {
		await this.saveButton.click();
		const message = this.page.locator(
			'.cdx-message--block.cdx-message--error'
		);
		await expect( message ).toBeVisible( { timeout: 30000 } );
		return message;
	}

	/**
	 * Create a single namespace from scratch, through the user interface.
	 *
	 * @param {Object} definition
	 */
	async createNamespace( definition ) {
		await this.open();
		await this.addNamespace( definition );
		await this.save();
	}
}

module.exports = {
	CHECKBOX_DEFAULTS,
	CHECKBOX_LABELS,
	CHIP_FIELDS,
	ManageNamespacesPage,
	TEXT_FIELDS
};
