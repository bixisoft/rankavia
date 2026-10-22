import { Card } from './card';
import { Field } from './field';
import { FilePreview } from './file-preview';
import { PresetBar } from './presets';
import { TemplatePairs } from './template-pairs';
import { useRankaviaConfig } from './use-config';

/**
 * One schema group (as emitted by Schema::describe()) as a Card of fields.
 * A group with ui.pair naming two map fields renders them as template pairs with SERP previews.
 *
 * @param {Object}   props
 * @param {Object}   props.group      Group: id, label, description, ui, fields[].
 * @param {Object}   props.values     key => value.
 * @param {Object}   [props.errors]   key => message.
 * @param {Function} props.onChange   Called with ( key, value ).
 * @param {string}   [props.savedKey] Changes whenever the saved settings change; refreshes ui.preview.
 */
export function SettingsForm( { group, values, errors, onChange, savedKey } ) {
	const config = useRankaviaConfig();
	const fields = group.fields ?? [];
	const variables = Array.isArray( config.templateVariables )
		? config.templateVariables
		: [];
	const separator = values?.separator || '–';

	const pair = Array.isArray( group.ui?.pair ) ? group.ui.pair : [];
	const titleField = fields.find(
		( field ) => field.key === pair[ 0 ] && field.type === 'map'
	);
	const descriptionField = fields.find(
		( field ) => field.key === pair[ 1 ] && field.type === 'map'
	);
	const paired = !! ( titleField && descriptionField );
	const rest = paired
		? fields.filter(
				( field ) => field !== titleField && field !== descriptionField
		  )
		: fields;

	const presets = Array.isArray( group.ui?.presets ) ? group.ui.presets : [];
	const presetBar =
		presets.length > 0 ? (
			<PresetBar presets={ presets } onApply={ onChange } />
		) : null;
	const preview = group.ui?.preview ? (
		<FilePreview
			name={ group.ui.preview }
			restNamespace={ config.restNamespace }
			savedKey={ savedKey }
		/>
	) : null;

	const form = (
		<Card
			title={ paired ? undefined : group.label }
			description={ paired ? undefined : group.description }
		>
			<div className="bx-form">
				{ rest.map( ( field ) => (
					<Field
						key={ field.key }
						field={ field }
						value={ values?.[ field.key ] }
						error={ errors?.[ field.key ] }
						onChange={ ( value ) => onChange( field.key, value ) }
						kind={
							field.key.includes( 'description' )
								? 'description'
								: 'title'
						}
						variables={ variables }
						separator={ separator }
					/>
				) ) }
			</div>
		</Card>
	);

	if ( ! paired ) {
		return (
			<>
				{ presetBar }
				{ form }
				{ preview }
			</>
		);
	}

	return (
		<div className="bx-pairs-group">
			<header className="bx-pairs__intro">
				<h2 className="bx-pairs__title">{ group.label }</h2>
				{ group.description && (
					<p className="bx-pairs__description">
						{ group.description }
					</p>
				) }
			</header>
			{ presetBar }
			<TemplatePairs
				titleField={ titleField }
				descriptionField={ descriptionField }
				values={ values }
				errors={ errors }
				onChange={ onChange }
				variables={ variables }
				separator={ separator }
				siteName={ config.siteName }
				homeUrl={ config.homeUrl }
			/>
			{ rest.length > 0 && form }
			{ preview }
		</div>
	);
}
