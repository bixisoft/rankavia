import { __ } from '@wordpress/i18n';
import { Card } from './card';
import { SerpPreview } from './serp-preview';
import { TemplateField, samplesOf } from './template-field';
import { renderTemplate } from './template';

const asArray = ( value ) => ( Array.isArray( value ) ? value : [] );

const asMap = ( value ) =>
	value && typeof value === 'object' && ! Array.isArray( value ) ? value : {};

/**
 * Title and description template maps shown side by side per key, with a SERP preview under each pair.
 *
 * @param {Object}   props
 * @param {Object}   props.titleField       Map field description (key, label, keys) for titles.
 * @param {Object}   props.descriptionField Map field description for descriptions.
 * @param {Object}   props.values           field key => map.
 * @param {Object}   [props.errors]         field key => message.
 * @param {Function} props.onChange         Called with ( fieldKey, map ).
 * @param {Array}    [props.variables]      [{ name, label, sample }].
 * @param {string}   [props.separator]      Value of %sep%.
 * @param {string}   [props.siteName]       Site name for the preview.
 * @param {string}   [props.homeUrl]        Home URL for the preview.
 */
export function TemplatePairs( {
	titleField,
	descriptionField,
	values,
	errors,
	onChange,
	variables = [],
	separator = '–',
	siteName = '',
	homeUrl = '',
} ) {
	const titles = asMap( values?.[ titleField.key ] );
	const descriptions = asMap( values?.[ descriptionField.key ] );
	const samples = samplesOf( variables );

	return (
		<div className="bx-pairs">
			{ asArray( titleField.keys ).map( ( entry ) => {
				const titleTemplate = titles[ entry.value ] ?? '';
				const descriptionTemplate = descriptions[ entry.value ] ?? '';
				const id = `bx-pair-${ String( entry.value ).replace(
					/[^a-z0-9_-]/gi,
					'-'
				) }`;
				return (
					<Card key={ entry.value } title={ entry.label }>
						<div className="bx-pairs__fields">
							<TemplateField
								id={ `${ id }-title` }
								label={ __( 'Title', 'rankavia' ) }
								value={ titleTemplate }
								onChange={ ( next ) =>
									onChange( titleField.key, {
										...titles,
										[ entry.value ]: next,
									} )
								}
								variables={ variables }
								separator={ separator }
								kind="title"
								error={ errors?.[ titleField.key ] }
							/>
							<TemplateField
								id={ `${ id }-description` }
								label={ __( 'Description', 'rankavia' ) }
								value={ descriptionTemplate }
								onChange={ ( next ) =>
									onChange( descriptionField.key, {
										...descriptions,
										[ entry.value ]: next,
									} )
								}
								variables={ variables }
								separator={ separator }
								kind="description"
								error={ errors?.[ descriptionField.key ] }
							/>
						</div>
						<SerpPreview
							title={ renderTemplate(
								titleTemplate,
								samples,
								separator
							) }
							description={ renderTemplate(
								descriptionTemplate,
								samples,
								separator
							) }
							url={ homeUrl }
							siteName={ siteName }
						/>
					</Card>
				);
			} ) }
		</div>
	);
}
