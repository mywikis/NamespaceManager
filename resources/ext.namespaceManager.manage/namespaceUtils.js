function nextAvailableId( namespaces ) {
	const used = new Set( namespaces.map( ( item ) => Number( item.id ) ) );
	for ( let id = 3000; id <= 4998; id += 2 ) {
		if ( !used.has( id ) ) {
			return id;
		}
	}
	return null;
}

function validateBasic( namespaces ) {
	const errors = {};
	const ids = new Set();
	namespaces.forEach( ( namespace, index ) => {
		const id = Number( namespace.id );
		if ( !Number.isInteger( id ) || id < 3000 || id > 4998 || id % 2 !== 0 ||
			ids.has( id )
		) {
			errors[ `${ index }:id` ] = 'invalid-id';
		}
		ids.add( id );
		if ( typeof namespace.name !== 'string' ||
			!namespace.name.trim() ||
			namespace.name.includes( ':' )
		) {
			errors[ `${ index }:name` ] = 'invalid-name';
		}
	} );
	return errors;
}

function updateBasicFieldError( errors, namespaces, index, field ) {
	const key = `${ index }:${ field }`;
	const updated = Object.assign( {}, errors );
	if ( field === 'id' ) {
		Object.keys( updated ).forEach( ( errorKey ) => {
			if ( errorKey.endsWith( ':id' ) ) {
				delete updated[ errorKey ];
			}
		} );
	} else {
		delete updated[ key ];
	}

	const currentErrors = validateBasic( namespaces );
	Object.keys( currentErrors ).forEach( ( errorKey ) => {
		if ( errorKey === key || ( field === 'id' && errorKey.endsWith( ':id' ) ) ) {
			updated[ errorKey ] = currentErrors[ errorKey ];
		}
	} );
	return updated;
}

module.exports = {
	nextAvailableId,
	updateBasicFieldError,
	validateBasic
};
