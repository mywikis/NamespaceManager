<template>
	<div class="namespacemanager-app">
		<cdx-message
			v-if="message"
			:type="message.type"
			:auto-dismiss="false">
			<p>{{ message.text }}</p>
			<ul v-if="message.details && message.details.length">
				<li v-for="detail in message.details" :key="detail">
					{{ detail }}
				</li>
			</ul>
		</cdx-message>

		<div v-if="loading" class="namespacemanager-loading">
			{{ $i18n( 'namespacemanager-loading' ).text() }}
		</div>
		<cdx-message v-else-if="namespaces.length === 0" type="notice">
			{{ $i18n( 'namespacemanager-empty' ).text() }}
		</cdx-message>

		<section
			v-for="( namespace, index ) in namespaces"
			:key="namespace.key"
			class="namespacemanager-block"
			:aria-labelledby="'namespacemanager-heading-' + namespace.key"
		>
			<div class="namespacemanager-block__heading">
				<h2 :id="'namespacemanager-heading-' + namespace.key">
					{{ namespace.name || $i18n( 'namespacemanager-new-namespace' ).text() }}
				</h2>
				<cdx-button
					action="destructive"
					weight="quiet"
					:disabled="editingDisabled"
					:aria-label="$i18n( 'namespacemanager-remove' ).text()"
					@click="removeNamespace( index )"
				>
					<cdx-icon :icon="icons.cdxIconTrash"></cdx-icon>
					{{ $i18n( 'namespacemanager-remove' ).text() }}
				</cdx-button>
			</div>

			<div class="namespacemanager-grid">
				<cdx-field
					:status="fieldStatus( index, 'id' )"
					:messages="fieldMessages( index, 'id' )"
					:disabled="editingDisabled"
					:class="{ 'namespacemanager-field--error': fieldError( index, 'id' ) }"
				>
					<template #label>
						{{ $i18n( 'namespacemanager-id' ).text() }}
					</template>
					<cdx-text-input
						v-model="namespace.id"
						input-type="number"
						:min="3000"
						:max="4998"
						:step="2"
						:status="fieldStatus( index, 'id' )"
						@update:model-value="onBasicFieldChange( index, 'id' )"
					></cdx-text-input>
				</cdx-field>
				<cdx-field
					:status="fieldStatus( index, 'name' )"
					:messages="fieldMessages( index, 'name' )"
					:disabled="editingDisabled"
					:class="{ 'namespacemanager-field--error': fieldError( index, 'name' ) }"
				>
					<template #label>
						{{ $i18n( 'namespacemanager-name' ).text() }}
					</template>
					<cdx-text-input
						v-model="namespace.name"
						:status="fieldStatus( index, 'name' )"
						@update:model-value="onBasicFieldChange( index, 'name' )"
					></cdx-text-input>
				</cdx-field>
				<cdx-field
					:status="fieldStatus( index, 'talkname' )"
					:messages="fieldMessages( index, 'talkname' )"
					:disabled="editingDisabled"
					:class="{ 'namespacemanager-field--error': fieldError( index, 'talkname' ) }"
				>
					<template #label>
						{{ $i18n( 'namespacemanager-talkname' ).text() }}
					</template>
					<cdx-text-input
						v-model="namespace.talkname"
						:status="fieldStatus( index, 'talkname' )"
					></cdx-text-input>
					<template #help-text>
						{{ fieldError( index, 'talkname' ) ||
							$i18n( 'namespacemanager-talkname-help' ).text() }}
					</template>
				</cdx-field>
			</div>

			<div class="namespacemanager-grid namespacemanager-grid--checks">
				<cdx-checkbox v-model="namespace.content" :disabled="editingDisabled">
					{{ $i18n( 'namespacemanager-content' ).text() }}
				</cdx-checkbox>
				<cdx-checkbox v-model="namespace.visualeditor" :disabled="editingDisabled">
					{{ $i18n( 'namespacemanager-visualeditor' ).text() }}
				</cdx-checkbox>
				<cdx-checkbox v-model="namespace.searchdefault" :disabled="editingDisabled">
					{{ $i18n( 'namespacemanager-searchdefault' ).text() }}
				</cdx-checkbox>
				<cdx-checkbox v-model="namespace.talksearchdefault" :disabled="editingDisabled">
					{{ $i18n( 'namespacemanager-talksearchdefault' ).text() }}
				</cdx-checkbox>
				<cdx-checkbox v-model="namespace.subpages" :disabled="editingDisabled">
					{{ $i18n( 'namespacemanager-subpages' ).text() }}
				</cdx-checkbox>
				<cdx-checkbox v-model="namespace.talksubpages" :disabled="editingDisabled">
					{{ $i18n( 'namespacemanager-talksubpages' ).text() }}
				</cdx-checkbox>
				<cdx-checkbox v-model="namespace.includable" :disabled="editingDisabled">
					{{ $i18n( 'namespacemanager-includable' ).text() }}
				</cdx-checkbox>
				<cdx-checkbox v-model="namespace.talkincludable" :disabled="editingDisabled">
					{{ $i18n( 'namespacemanager-talkincludable' ).text() }}
				</cdx-checkbox>
			</div>

			<div class="namespacemanager-grid">
				<cdx-field
					:status="fieldStatus( index, 'aliases' )"
					:messages="fieldMessages( index, 'aliases' )"
					:disabled="editingDisabled"
					:class="{ 'namespacemanager-field--error': fieldError( index, 'aliases' ) }"
				>
					<template #label>
						{{ $i18n( 'namespacemanager-aliases' ).text() }}
					</template>
					<cdx-chip-input
						v-model:input-chips="namespace.aliases"
						:chip-aria-description="$i18n( 'namespacemanager-chip-description' ).text()"
						:status="fieldStatus( index, 'aliases' )"
					></cdx-chip-input>
				</cdx-field>
				<cdx-field
					:status="fieldStatus( index, 'talkaliases' )"
					:messages="fieldMessages( index, 'talkaliases' )"
					:disabled="editingDisabled"
					:class="{ 'namespacemanager-field--error': fieldError( index, 'talkaliases' ) }"
				>
					<template #label>
						{{ $i18n( 'namespacemanager-talkaliases' ).text() }}
					</template>
					<cdx-chip-input
						v-model:input-chips="namespace.talkaliases"
						:chip-aria-description="$i18n( 'namespacemanager-chip-description' ).text()"
						:status="fieldStatus( index, 'talkaliases' )"
					></cdx-chip-input>
				</cdx-field>
				<cdx-field
					:status="fieldStatus( index, 'editpermissions' )"
					:messages="fieldMessages( index, 'editpermissions' )"
					:disabled="editingDisabled"
					:class="{
						'namespacemanager-field--error': fieldError( index, 'editpermissions' )
					}"
				>
					<template #label>
						{{ $i18n( 'namespacemanager-editpermissions' ).text() }}
					</template>
					<cdx-chip-input
						v-model:input-chips="namespace.editpermissions"
						:chip-aria-description="$i18n( 'namespacemanager-chip-description' ).text()"
						:status="fieldStatus( index, 'editpermissions' )"
					></cdx-chip-input>
				</cdx-field>
				<cdx-field
					:status="fieldStatus( index, 'talkeditpermissions' )"
					:messages="fieldMessages( index, 'talkeditpermissions' )"
					:disabled="editingDisabled"
					:class="{
						'namespacemanager-field--error':
							fieldError( index, 'talkeditpermissions' )
					}"
				>
					<template #label>
						{{ $i18n( 'namespacemanager-talkeditpermissions' ).text() }}
					</template>
					<cdx-chip-input
						v-model:input-chips="namespace.talkeditpermissions"
						:chip-aria-description="$i18n( 'namespacemanager-chip-description' ).text()"
						:status="fieldStatus( index, 'talkeditpermissions' )"
					></cdx-chip-input>
				</cdx-field>
			</div>
		</section>

		<div class="namespacemanager-actions">
			<cdx-button
				action="progressive"
				:disabled="editingDisabled"
				@click="addNamespace"
			>
				<cdx-icon :icon="icons.cdxIconAdd"></cdx-icon>
				{{ $i18n( 'namespacemanager-add' ).text() }}
			</cdx-button>
			<cdx-button
				action="progressive"
				weight="primary"
				:disabled="editingDisabled"
				@click="save"
			>
				{{ saveButtonLabel }}
			</cdx-button>
		</div>
	</div>
</template>

<script>
const {
	CdxButton,
	CdxCheckbox,
	CdxChipInput,
	CdxField,
	CdxIcon,
	CdxMessage,
	CdxTextInput
} = require( '../codex.js' );
const icons = require( './icons.json' );
const namespaceUtils = mw.loader.require( 'ext.namespaceManager.utils' );

let nextKey = 1;

// @vue/component
module.exports = {
	name: 'NamespaceManagerApp',
	components: {
		CdxButton,
		CdxCheckbox,
		CdxChipInput,
		CdxField,
		CdxIcon,
		CdxMessage,
		CdxTextInput
	},
	data() {
		return {
			api: new mw.Api(),
			errors: {},
			icons,
			loadFailed: false,
			loading: true,
			message: null,
			namespaces: [],
			saving: false
		};
	},
	computed: {
		editingDisabled() {
			return this.loading || this.saving || this.loadFailed;
		},
		saveButtonLabel() {
			return this.saving ?
				this.$i18n( 'namespacemanager-saving' ).text() :
				this.$i18n( 'namespacemanager-save' ).text();
		}
	},
	methods: {
		normalize( definition ) {
			const normalized = Object.assign( {
				aliases: [],
				content: false,
				editpermissions: [],
				includable: true,
				searchdefault: false,
				subpages: false,
				talkaliases: [],
				talkeditpermissions: [],
				talkincludable: true,
				talkname: '',
				talksearchdefault: false,
				talksubpages: false,
				visualeditor: false
			}, definition, { key: nextKey++ } );
			[
				'aliases',
				'talkaliases',
				'editpermissions',
				'talkeditpermissions'
			].forEach( ( field ) => {
				normalized[ field ] = normalized[ field ].map( ( value ) => ( { value } ) );
			} );
			return normalized;
		},
		load() {
			this.api.post( {
				action: 'namespacemanager',
				formatversion: 2,
				operation: 'get'
			} ).then( ( response ) => {
				this.namespaces = response.namespacemanager.namespaces.map( this.normalize );
				this.loadFailed = false;
			} ).catch( () => {
				this.loadFailed = true;
				this.message = {
					type: 'error',
					text: this.$i18n( 'namespacemanager-load-error' ).text()
				};
			} ).always( () => {
				this.loading = false;
			} );
		},
		nextId() {
			return namespaceUtils.nextAvailableId( this.namespaces );
		},
		addNamespace() {
			const namespace = this.normalize( {
				id: this.nextId(),
				name: ''
			} );
			this.namespaces.push( namespace );
			this.$nextTick( () => {
				const blocks = this.$el.querySelectorAll( '.namespacemanager-block' );
				blocks[ blocks.length - 1 ].querySelector( 'input' ).focus();
			} );
		},
		removeNamespace( index ) {
			this.namespaces.splice( index, 1 );
			this.errors = {};
		},
		payload() {
			return this.namespaces.map( ( namespace ) => {
				const result = Object.assign( {}, namespace, {
					id: Number( namespace.id )
				} );
				[
					'aliases',
					'talkaliases',
					'editpermissions',
					'talkeditpermissions'
				].forEach( ( field ) => {
					result[ field ] = result[ field ].map( ( chip ) => chip.value );
				} );
				delete result.key;
				if ( !result.talkname ) {
					delete result.talkname;
				}
				return result;
			} );
		},
		validate() {
			const errors = namespaceUtils.validateBasic( this.namespaces );
			Object.keys( errors ).forEach( ( key ) => {
				errors[ key ] = this.translateValidationCode( errors[ key ] );
			} );
			this.errors = errors;
			return Object.keys( errors ).length === 0;
		},
		onBasicFieldChange( index, field ) {
			const errors = namespaceUtils.updateBasicFieldError(
				this.errors,
				this.namespaces,
				index,
				field
			);
			Object.keys( errors ).forEach( ( errorKey ) => {
				if ( errors[ errorKey ] === 'invalid-id' ||
					errors[ errorKey ] === 'invalid-name'
				) {
					errors[ errorKey ] = this.translateValidationCode( errors[ errorKey ] );
				}
			} );
			this.errors = errors;
			this.message = Object.keys( errors ).length ?
				this.validationMessage( errors ) :
				null;
		},
		save() {
			this.message = null;
			if ( !this.validate() ) {
				this.message = this.validationMessage( this.errors );
				return;
			}
			this.saving = true;
			this.api.postWithToken( 'csrf', {
				action: 'namespacemanager',
				formatversion: 2,
				operation: 'save',
				namespaces: JSON.stringify( this.payload() )
			} ).then( ( response ) => {
				this.namespaces = response.namespacemanager.namespaces.map( this.normalize );
				this.errors = {};
				this.message = {
					type: 'success',
					text: this.$i18n( 'namespacemanager-save-success' ).text()
				};
			} ).catch( ( code, response ) => {
				const serverErrors = response && response.error && response.error.errors;
				if ( Array.isArray( serverErrors ) ) {
					const errors = {};
					serverErrors.forEach( ( error ) => {
						errors[ `${ error.index }:${ error.field }` ] =
							this.translateValidationCode( error.code );
					} );
					this.errors = errors;
					this.message = this.validationMessage( errors );
				}
				if ( !this.message ) {
					this.message = {
						type: 'error',
						text: this.$i18n( 'namespacemanager-save-error' ).text()
					};
				}
			} ).always( () => {
				this.saving = false;
			} );
		},
		fieldError( index, field ) {
			return this.errors[ `${ index }:${ field }` ] || '';
		},
		fieldStatus( index, field ) {
			return this.fieldError( index, field ) ? 'error' : 'default';
		},
		fieldMessages( index, field ) {
			const error = this.fieldError( index, field );
			return error ? { error } : {};
		},
		fieldLabel( field ) {
			const labels = {
				aliases: 'namespacemanager-aliases',
				content: 'namespacemanager-content',
				editpermissions: 'namespacemanager-editpermissions',
				id: 'namespacemanager-id',
				includable: 'namespacemanager-includable',
				name: 'namespacemanager-name',
				searchdefault: 'namespacemanager-searchdefault',
				talkaliases: 'namespacemanager-talkaliases',
				talkeditpermissions: 'namespacemanager-talkeditpermissions',
				talkincludable: 'namespacemanager-talkincludable',
				talkname: 'namespacemanager-talkname',
				talksearchdefault: 'namespacemanager-talksearchdefault',
				talksubpages: 'namespacemanager-talksubpages',
				subpages: 'namespacemanager-subpages',
				visualeditor: 'namespacemanager-visualeditor'
			};
			return labels[ field ] ?
				this.$i18n( labels[ field ] ).text() :
				field;
		},
		translateValidationCode( code ) {
			if ( code === 'invalid-id' ) {
				return this.$i18n( 'namespacemanager-error-invalid-id' ).text();
			}
			if ( code === 'duplicate-id' ) {
				return this.$i18n( 'namespacemanager-error-duplicate-id' ).text();
			}
			if ( code === 'duplicate-name' ) {
				return this.$i18n( 'namespacemanager-error-duplicate-name' ).text();
			}
			if ( code === 'name-too-long' ) {
				return this.$i18n( 'namespacemanager-error-name-too-long' ).text();
			}
			if ( code === 'invalid-name' ) {
				return this.$i18n( 'namespacemanager-error-invalid-name' ).text();
			}
			return this.$i18n( 'namespacemanager-error-field' ).text();
		},
		validationMessage( errors ) {
			const details = Object.keys( errors ).map( ( key ) => {
				const [ index, field ] = key.split( ':' );
				return this.$i18n(
					'namespacemanager-error-summary-item',
					Number( index ) + 1,
					this.fieldLabel( field ),
					errors[ key ]
				).text();
			} );
			return {
				type: 'error',
				text: this.$i18n( 'namespacemanager-save-error' ).text(),
				details
			};
		}
	},
	mounted() {
		this.load();
	}
};
</script>
