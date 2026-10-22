const NAMED_ENTITIES = {
	amp: '&',
	lt: '<',
	gt: '>',
	quot: '"',
	apos: "'",
	nbsp: ' ',
};

const decodeEntities = ( text ) =>
	text.replace( /&(#x[0-9a-f]+|#\d+|[a-z]+);/gi, ( match, body ) => {
		if ( body[ 0 ] === '#' ) {
			const code =
				body[ 1 ].toLowerCase() === 'x'
					? parseInt( body.slice( 2 ), 16 )
					: parseInt( body.slice( 1 ), 10 );
			try {
				return String.fromCodePoint( code );
			} catch ( e ) {
				return match;
			}
		}
		const named = NAMED_ENTITIES[ body.toLowerCase() ];
		return named === undefined ? match : named;
	} );

/**
 * Names of the variables used in a template, in order of first use, unique.
 *
 * @param {string} template Template text with %name% variables.
 * @return {string[]} Variable names without the percent signs.
 */
export function variablesIn( template ) {
	const names = [];
	for ( const match of String( template ?? '' ).matchAll(
		/%([a-z0-9_]+)%/gi
	) ) {
		if ( ! names.includes( match[ 1 ] ) ) {
			names.push( match[ 1 ] );
		}
	}
	return names;
}

/**
 * Renders a template with the same rules as the PHP engine (Bixisoft\Core\Text\Template).
 *
 * @param {string} template  Template text with %name% variables.
 * @param {Object} values    name => value. `sep` always resolves to the separator argument.
 * @param {string} separator The separator setting, matched literally.
 * @return {string} Plain text.
 */
export function renderTemplate( template, values, separator = '–' ) {
	const map = { ...( values ?? {} ), sep: separator };

	// 1. Replace variables; unknown names become empty.
	let text = String( template ?? '' ).replace(
		/%([a-z0-9_]+)%/gi,
		( match, name ) =>
			Object.prototype.hasOwnProperty.call( map, name )
				? String( map[ name ] ?? '' )
				: ''
	);

	// 2. Collapse whitespace and trim.
	text = text.replace( /\s+/g, ' ' ).trim();

	// 3. Drop empty separator segments. The text is already collapsed and trimmed, so a
	// separator standing on its own is always " sep " once the ends are padded; a plain
	// string split keeps this free of regex lookbehind, which older Safari lacks.
	if ( separator ) {
		text = ` ${ text } `
			.split( ` ${ separator } ` )
			.map( ( segment ) => segment.trim() )
			.filter( Boolean )
			.join( ` ${ separator } ` );
	}

	// 4. Strip tags, decode entities.
	return decodeEntities( text.replace( /<[^>]*>/g, '' ) );
}
