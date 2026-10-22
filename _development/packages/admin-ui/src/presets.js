import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { Card } from './card';
import { applyPreset } from './apply-preset';

export { applyPreset };

/**
 * A card of preset buttons. Applying only edits the form; nothing is saved until Save.
 *
 * @param {Object}   props
 * @param {Array}    props.presets Presets: id, label, description, values.
 * @param {Function} props.onApply Called with ( key, value ) for each pair of the chosen preset.
 */
export function PresetBar( { presets, onApply } ) {
	const list = Array.isArray( presets ) ? presets : [];
	if ( list.length === 0 ) {
		return null;
	}

	return (
		<Card title={ __( 'Presets', 'rankavia' ) }>
			<ul className="bx-presets">
				{ list.map( ( preset ) => (
					<li key={ preset.id } className="bx-presets__item">
						<Button
							variant="secondary"
							onClick={ () =>
								applyPreset( preset ).forEach(
									( [ key, value ] ) => onApply( key, value )
								)
							}
						>
							{ preset.label }
						</Button>
						{ preset.description && (
							<p className="bx-presets__description">
								{ preset.description }
							</p>
						) }
					</li>
				) ) }
			</ul>
		</Card>
	);
}
