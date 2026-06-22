/*!
 * NamespaceManager dynamic management interface.
 *
 * Renders the namespace definitions provided via mw.config as an OOUI-based
 * editor, replacing the raw JSON textarea on Special:ManageNamespaces. The
 * raw JSON field is kept as an advanced fallback and is used as the field that
 * is actually submitted with the form: every change in the dynamic interface
 * is serialized back into it.
 */
( function () {
	'use strict';

	// Human-readable labels for the known namespace properties. Unknown keys
	// fall back to the raw key name so the interface still renders for them.
	var propertyLabels = {
		id: 'Namespace ID',
		name: 'Name',
		talkname: 'Talk page name',
		content: 'Content namespace',
		visualeditor: 'Enable VisualEditor',
		searchdefault: 'Searched by default',
		talksearchdefault: 'Talk searched by default',
		subpages: 'Subpages enabled',
		talksubpages: 'Talk subpages enabled',
		includable: 'Includable',
		talkincludable: 'Talk includable',
		aliases: 'Aliases',
		talkaliases: 'Talk aliases',
		editpermissions: 'Edit permissions',
		talkeditpermissions: 'Talk edit permissions'
	};

	// Default schema used when there are no existing namespaces to derive the
	// shape from. Mirrors the documented sample configuration.
	var defaultSchema = {
		id: 3000,
		name: '',
		talkname: '',
		content: false,
		visualeditor: false,
		searchdefault: false,
		talksearchdefault: false,
		subpages: false,
		talksubpages: false,
		includable: true,
		talkincludable: true,
		aliases: [],
		talkaliases: [],
		editpermissions: [],
		talkeditpermissions: []
	};

	var namespaces = [];
	// Parallel array of { key: widget } maps, one entry per rendered namespace.
	var panelWidgets = [];
	var $app, $jsonField, $rawField;

	function labelFor( key ) {
		return Object.prototype.hasOwnProperty.call( propertyLabels, key ) ?
			propertyLabels[ key ] : key;
	}

	/**
	 * Build an OOUI widget appropriate for the type of the given value.
	 *
	 * @param {Mixed} value
	 * @return {OO.ui.Widget}
	 */
	function buildWidget( value ) {
		if ( typeof value === 'boolean' ) {
			return new OO.ui.ToggleSwitchWidget( { value: value } );
		}
		if ( typeof value === 'number' ) {
			return new OO.ui.NumberInputWidget( { value: value } );
		}
		if ( Array.isArray( value ) ) {
			return new OO.ui.TagMultiselectWidget( {
				allowArbitrary: true,
				selected: value.map( String )
			} );
		}
		return new OO.ui.TextInputWidget( {
			value: value === null || value === undefined ? '' : String( value )
		} );
	}

	/**
	 * Read the current value out of a widget, coercing it back to the type that
	 * matches how it was originally rendered.
	 *
	 * @param {OO.ui.Widget} widget
	 * @param {Mixed} original Original value, used to infer the target type
	 * @return {Mixed}
	 */
	function readWidget( widget, original ) {
		if ( typeof original === 'number' ) {
			return Number( widget.getValue() );
		}
		// Booleans (ToggleSwitch), arrays (TagMultiselect) and strings
		// (TextInput) are all returned directly by getValue().
		return widget.getValue();
	}

	/**
	 * Copy the current widget values back into the in-memory namespace model.
	 */
	function collect() {
		panelWidgets.forEach( function ( widgets, index ) {
			var ns = namespaces[ index ];
			Object.keys( widgets ).forEach( function ( key ) {
				ns[ key ] = readWidget( widgets[ key ], ns[ key ] );
			} );
		} );
	}

	/**
	 * Serialize the model into the raw JSON field that is submitted with the
	 * form.
	 */
	function sync() {
		collect();
		$jsonField.val( JSON.stringify( namespaces, null, 4 ) );
	}

	/**
	 * Compute the next available even namespace id within the allowed range.
	 *
	 * @return {number}
	 */
	function nextId() {
		var max = 2998;
		namespaces.forEach( function ( ns ) {
			if ( typeof ns.id === 'number' && ns.id > max ) {
				max = ns.id;
			}
		} );
		var candidate = max + 2;
		if ( candidate < 3000 ) {
			candidate = 3000;
		}
		if ( candidate > 4998 ) {
			candidate = 4998;
		}
		return candidate;
	}

	/**
	 * Create a blank namespace definition based on the schema already in use by
	 * the existing namespaces (or the default schema when there are none).
	 *
	 * @return {Object}
	 */
	function makeNamespace() {
		var template = namespaces.length ? namespaces[ 0 ] : defaultSchema;
		var ns = {};
		Object.keys( template ).forEach( function ( key ) {
			var value = template[ key ];
			if ( typeof value === 'boolean' ) {
				ns[ key ] = false;
			} else if ( typeof value === 'number' ) {
				ns[ key ] = 0;
			} else if ( Array.isArray( value ) ) {
				ns[ key ] = [];
			} else {
				ns[ key ] = '';
			}
		} );
		ns.id = nextId();
		return ns;
	}

	/**
	 * Render a single namespace definition as an OOUI panel.
	 *
	 * @param {Object} ns
	 * @param {number} index
	 * @return {jQuery}
	 */
	function renderNamespace( ns, index ) {
		var widgets = {};

		var deleteButton = new OO.ui.ButtonWidget( {
			label: 'Delete namespace',
			icon: 'trash',
			flags: [ 'destructive' ]
		} );
		deleteButton.on( 'click', function () {
			removeNamespace( index );
		} );

		var titleText = String( ns.name || '' ).trim();
		var fieldset = new OO.ui.FieldsetLayout( {
			label: titleText !== '' ?
				titleText + ' (' + ns.id + ')' :
				'New namespace (' + ns.id + ')'
		} );

		Object.keys( ns ).forEach( function ( key ) {
			var widget = buildWidget( ns[ key ] );
			widgets[ key ] = widget;
			widget.connect( null, { change: sync } );
			fieldset.addItems( [
				new OO.ui.FieldLayout( widget, {
					label: labelFor( key ),
					align: 'left'
				} )
			] );
		} );

		fieldset.addItems( [
			new OO.ui.FieldLayout( deleteButton, { align: 'top' } )
		] );

		panelWidgets[ index ] = widgets;

		return new OO.ui.PanelLayout( {
			expanded: false,
			framed: true,
			padded: true,
			classes: [ 'namespacemanager-namespace' ],
			content: [ fieldset ]
		} ).$element;
	}

	/**
	 * Rebuild the whole interface from the current model.
	 */
	function render() {
		panelWidgets = [];
		$app.empty();

		namespaces.forEach( function ( ns, index ) {
			$app.append( renderNamespace( ns, index ) );
		} );

		var addButton = new OO.ui.ButtonWidget( {
			label: 'Add namespace',
			icon: 'add',
			flags: [ 'progressive' ]
		} );
		addButton.on( 'click', addNamespace );

		var rawToggle = new OO.ui.ButtonWidget( {
			label: 'Toggle raw JSON editor',
			icon: 'code'
		} );
		rawToggle.on( 'click', function () {
			$rawField.toggle();
		} );

		$app.append(
			$( '<div>' )
				.addClass( 'namespacemanager-actions' )
				.append( addButton.$element, rawToggle.$element )
		);

		sync();
	}

	function addNamespace() {
		collect();
		namespaces.push( makeNamespace() );
		render();
	}

	function removeNamespace( index ) {
		collect();
		namespaces.splice( index, 1 );
		render();
	}

	$( function () {
		$app = $( '#namespacemanager-app' );
		if ( !$app.length ) {
			return;
		}

		$jsonField = $( '[name="namespaceJsonContents"]' );
		$rawField = $( '.namespacemanager-rawjson' );

		var data = mw.config.get( 'wgNamespaceManagerData' );
		if ( Array.isArray( data ) ) {
			namespaces = data;
		} else if ( data && typeof data === 'object' ) {
			// Tolerate an object map by taking its values.
			namespaces = Object.keys( data ).map( function ( key ) {
				return data[ key ];
			} );
		} else {
			namespaces = [];
		}

		render();
	} );
}() );
