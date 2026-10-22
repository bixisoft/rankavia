import { useEffect, useRef, useState } from '@wordpress/element';
import {
	Button,
	CheckboxControl,
	FormTokenField,
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { TemplateField } from './template-field';

const asArray = ( value ) => ( Array.isArray( value ) ? value : [] );

const tokenText = ( token ) =>
	typeof token === 'string' ? token : token?.value ?? '';

const isHttpUrl = ( token ) => {
	try {
		const url = new URL( tokenText( token ) );
		return url.protocol === 'http:' || url.protocol === 'https:';
	} catch ( e ) {
		return false;
	}
};

/**
 * Picks a thumbnail URL from wp.media attachment JSON.
 *
 * @param {Object} data Attachment data as returned by toJSON().
 */
const thumbnailOf = ( data ) =>
	data?.sizes?.thumbnail?.url || data?.sizes?.medium?.url || data?.url || '';

/**
 * Image field: stores an attachment id. Uses the WordPress media modal when available,
 * otherwise falls back to a plain attachment id input.
 *
 * @param {Object}   props
 * @param {string}   props.id            Element id.
 * @param {string}   props.label         Visible label.
 * @param {number}   props.value         Attachment id, 0 for none.
 * @param {Function} props.onChange      Called with the new attachment id.
 * @param {string}   [props.describedBy] Space separated ids of help and error text.
 * @param {boolean}  [props.invalid]     Whether the field has an error.
 */
export function ImageField( {
	id,
	label,
	value,
	onChange,
	describedBy,
	invalid,
} ) {
	const hasMedia = typeof window !== 'undefined' && !! window.wp?.media;
	const [ thumb, setThumb ] = useState( null );
	const frame = useRef( null );
	const attachmentId = parseInt( value, 10 ) || 0;

	useEffect( () => {
		if ( ! hasMedia || ! attachmentId ) {
			setThumb( null );
			return undefined;
		}
		let active = true;
		const attachment = window.wp.media.attachment( attachmentId );
		const update = () => {
			if ( active ) {
				setThumb( thumbnailOf( attachment.toJSON() ) || null );
			}
		};
		if ( attachment.get( 'url' ) ) {
			update();
		} else {
			attachment.fetch().then( update );
		}
		return () => {
			active = false;
		};
	}, [ hasMedia, attachmentId ] );

	if ( ! hasMedia ) {
		return (
			<TextControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				id={ id }
				type="number"
				min={ 0 }
				label={ label }
				value={ attachmentId ? String( attachmentId ) : '' }
				onChange={ ( next ) =>
					onChange( Math.max( 0, parseInt( next, 10 ) || 0 ) )
				}
				aria-describedby={ describedBy }
				aria-invalid={ invalid || undefined }
			/>
		);
	}

	const open = () => {
		if ( ! frame.current ) {
			frame.current = window.wp.media( {
				title: __( 'Select an image', 'rankavia' ),
				button: { text: __( 'Use this image', 'rankavia' ) },
				library: { type: 'image' },
				multiple: false,
			} );
			frame.current.on( 'select', () => {
				const selected = frame.current
					.state()
					.get( 'selection' )
					.first();
				if ( selected ) {
					onChange( parseInt( selected.get( 'id' ), 10 ) || 0 );
				}
			} );
		}
		frame.current.open();
	};

	let preview = (
		<span className="bx-image__empty">
			{ __( 'No image selected', 'rankavia' ) }
		</span>
	);
	if ( attachmentId && thumb ) {
		preview = <img className="bx-image__thumb" src={ thumb } alt="" />;
	} else if ( attachmentId ) {
		preview = (
			<span className="bx-image__empty">
				{ __( 'Image selected', 'rankavia' ) }
			</span>
		);
	}

	return (
		<div
			className="bx-image"
			role="group"
			aria-labelledby={ `${ id }-label` }
			aria-describedby={ describedBy }
		>
			<span id={ `${ id }-label` } className="bx-field__label">
				{ label }
			</span>
			<div className="bx-image__row">
				{ preview }
				<Button variant="secondary" onClick={ open }>
					{ attachmentId
						? __( 'Change', 'rankavia' )
						: __( 'Select', 'rankavia' ) }
				</Button>
				{ attachmentId > 0 && (
					<Button
						variant="tertiary"
						isDestructive
						onClick={ () => onChange( 0 ) }
					>
						{ __( 'Remove', 'rankavia' ) }
					</Button>
				) }
			</div>
		</div>
	);
}

/**
 * Renders one schema field (as emitted by Schema::describe()) by its type.
 *
 * @param {Object}   props
 * @param {Object}   props.field       Field description: key, type, label, description, choices, keys, ...
 * @param {*}        props.value       Current value.
 * @param {string}   [props.error]     Validation message from the server.
 * @param {Function} props.onChange    Called with the new value.
 * @param {string}   [props.kind]      For template fields: 'title' or 'description'.
 * @param {Array}    [props.variables] For template fields: [{ name, label, sample }].
 * @param {string}   [props.separator] For template fields: value of %sep% in the preview.
 */
export function Field( {
	field,
	value,
	error,
	onChange,
	kind = 'title',
	variables = [],
	separator = '–',
} ) {
	const id = `bx-field-${ field.key }`;
	const helpId = `${ id }-help`;
	const errorId = `${ id }-error`;
	const hasHelp = !! ( field.description || field.helpUrl );
	const describedBy =
		[ hasHelp ? helpId : null, error ? errorId : null ]
			.filter( Boolean )
			.join( ' ' ) || undefined;
	const invalid = !! error;
	const choices = asArray( field.choices );
	const common = {
		'aria-describedby': describedBy,
		'aria-invalid': invalid || undefined,
	};
	const sizing = {
		__nextHasNoMarginBottom: true,
		__next40pxDefaultSize: true,
	};
	const placeholder = field.placeholder ?? undefined;

	let control;

	switch ( field.type ) {
		case 'boolean':
			control = (
				<ToggleControl
					__nextHasNoMarginBottom
					id={ id }
					label={ field.label }
					checked={ !! value }
					onChange={ ( next ) => onChange( !! next ) }
					{ ...common }
				/>
			);
			break;
		case 'text':
			control = (
				<TextareaControl
					__nextHasNoMarginBottom
					id={ id }
					label={ field.label }
					value={ value ?? '' }
					rows={ 4 }
					placeholder={ placeholder }
					onChange={ onChange }
					{ ...common }
				/>
			);
			break;
		case 'integer':
			control = (
				<TextControl
					{ ...sizing }
					id={ id }
					type="number"
					label={ field.label }
					value={
						value === null || value === undefined
							? ''
							: String( value )
					}
					min={ field.min ?? undefined }
					max={ field.max ?? undefined }
					placeholder={ placeholder }
					onChange={ ( next ) =>
						onChange(
							next === ''
								? field.min ?? 0
								: parseInt( next, 10 ) || 0
						)
					}
					{ ...common }
				/>
			);
			break;
		case 'select':
			control = (
				<SelectControl
					{ ...sizing }
					id={ id }
					label={ field.label }
					value={ value ?? '' }
					options={ choices.map( ( choice ) => ( {
						value: choice.value,
						label: choice.label,
					} ) ) }
					onChange={ onChange }
					{ ...common }
				/>
			);
			break;
		case 'multiselect': {
			const selected = asArray( value );
			control = (
				<fieldset
					className="bx-field__group"
					aria-describedby={ describedBy }
				>
					<legend className="bx-field__label">{ field.label }</legend>
					{ choices.map( ( choice ) => (
						<CheckboxControl
							__nextHasNoMarginBottom
							key={ choice.value }
							label={ choice.label }
							checked={ selected.includes( choice.value ) }
							onChange={ ( checked ) =>
								onChange(
									checked
										? [ ...selected, choice.value ]
										: selected.filter(
												( item ) =>
													item !== choice.value
										  )
								)
							}
						/>
					) ) }
				</fieldset>
			);
			break;
		}
		case 'list':
			control = (
				<div
					className="bx-field__group"
					role="group"
					aria-labelledby={ `${ id }-label` }
					aria-describedby={ describedBy }
				>
					<span id={ `${ id }-label` } className="bx-field__label">
						{ field.label }
					</span>
					<FormTokenField
						{ ...sizing }
						label=""
						value={ asArray( value ) }
						onChange={ ( tokens ) => {
							const items = tokens
								.map( ( token ) => tokenText( token ).trim() )
								.filter( Boolean );
							onChange( [ ...new Set( items ) ] );
						} }
						placeholder={ placeholder }
						__experimentalValidateInput={
							field.itemFormat === 'url' ? isHttpUrl : undefined
						}
						__experimentalShowHowTo={ false }
						tokenizeOnBlur
					/>
				</div>
			);
			break;
		case 'map': {
			const current =
				value && typeof value === 'object' && ! Array.isArray( value )
					? value
					: {};
			const mapChoices = asArray( field.choices );
			control = (
				<fieldset
					className={ `bx-field__group${
						mapChoices.length > 0 ? ' bx-field__group--grid' : ''
					}` }
					aria-describedby={ describedBy }
				>
					<legend className="bx-field__label">{ field.label }</legend>
					{ asArray( field.keys ).map( ( entry ) => {
						if ( mapChoices.length > 0 ) {
							return (
								<SelectControl
									{ ...sizing }
									key={ entry.value }
									id={ `${ id }-${ entry.value }` }
									label={ entry.label }
									help={ entry.note || undefined }
									value={ current[ entry.value ] ?? '' }
									options={ mapChoices.map( ( choice ) => ( {
										value: choice.value,
										label: choice.label,
									} ) ) }
									onChange={ ( next ) =>
										onChange( {
											...current,
											[ entry.value ]: next,
										} )
									}
								/>
							);
						}
						return field.format === 'template' ? (
							<TemplateField
								key={ entry.value }
								id={ `${ id }-${ entry.value }` }
								label={ entry.label }
								value={ current[ entry.value ] ?? '' }
								onChange={ ( next ) =>
									onChange( {
										...current,
										[ entry.value ]: next,
									} )
								}
								variables={ variables }
								separator={ separator }
								kind={ kind }
								invalid={ invalid }
							/>
						) : (
							<TextControl
								{ ...sizing }
								key={ entry.value }
								id={ `${ id }-${ entry.value }` }
								label={ entry.label }
								value={ current[ entry.value ] ?? '' }
								onChange={ ( next ) =>
									onChange( {
										...current,
										[ entry.value ]: next,
									} )
								}
							/>
						);
					} ) }
				</fieldset>
			);
			break;
		}
		case 'image':
			control = (
				<ImageField
					id={ id }
					label={ field.label }
					value={ value }
					onChange={ onChange }
					describedBy={ describedBy }
					invalid={ invalid }
				/>
			);
			break;
		default:
			control =
				field.format === 'template' ? (
					<TemplateField
						id={ id }
						label={ field.label }
						value={ value ?? '' }
						onChange={ onChange }
						variables={ variables }
						separator={ separator }
						kind={ kind }
						describedBy={ describedBy }
						invalid={ invalid }
					/>
				) : (
					<TextControl
						{ ...sizing }
						id={ id }
						type="text"
						label={ field.label }
						value={ value ?? '' }
						placeholder={ placeholder }
						onChange={ onChange }
						{ ...common }
					/>
				);
	}

	return (
		<div
			className={ `bx-field bx-field--${ field.type }${
				invalid ? ' has-error' : ''
			}` }
		>
			{ control }
			{ hasHelp && (
				<p id={ helpId } className="bx-field__help">
					{ field.description }
					{ field.description && field.helpUrl ? ' ' : null }
					{ field.helpUrl && (
						<a
							href={ field.helpUrl }
							target="_blank"
							rel="noopener noreferrer"
						>
							{ __( 'Learn more', 'rankavia' ) }
						</a>
					) }
				</p>
			) }
			{ error && (
				<p id={ errorId } className="bx-field__error" role="alert">
					<span aria-hidden="true">{ '⚠ ' }</span>
					{ __( 'Error:', 'rankavia' ) } { error }
				</p>
			) }
		</div>
	);
}
