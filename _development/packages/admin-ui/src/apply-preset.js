/**
 * The [ key, value ] pairs a preset applies. Values are kept whole, so a map value stays one value.
 * Pure, so it can be tested without React.
 *
 * @param {Object} preset Preset: id, label, description, values{ key: value }.
 * @return {Array} Array of [ key, value ].
 */
export function applyPreset( preset ) {
	const values = preset?.values;
	if ( ! values || typeof values !== 'object' || Array.isArray( values ) ) {
		return [];
	}
	return Object.keys( values )
		.filter( ( key ) =>
			Object.prototype.hasOwnProperty.call( values, key )
		)
		.map( ( key ) => [ key, values[ key ] ] );
}
