import { useEffect, useRef } from '@wordpress/element';
import {
	DropdownMenu,
	MenuGroup,
	MenuItem,
	TextControl,
} from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { renderTemplate } from './template';

const LONG_AT = { title: 60, description: 160 };

/**
 * name => sample map from the variable table shipped by PHP.
 *
 * @param {Array} variables [{ name, label, sample }].
 */
export const samplesOf = ( variables ) =>
	Object.fromEntries(
		( Array.isArray( variables ) ? variables : [] ).map( ( variable ) => [
			variable.name,
			variable.sample ?? '',
		] )
	);

/**
 * Template string input with a variable picker and a live preview of the rendered sample.
 *
 * @param {Object}   props
 * @param {string}   props.id            Element id.
 * @param {string}   props.label         Visible label.
 * @param {string}   props.value         Template text.
 * @param {Function} props.onChange      Called with the new template.
 * @param {Array}    [props.variables]   [{ name, label, sample }].
 * @param {string}   [props.separator]   Value of %sep% in the preview.
 * @param {string}   [props.kind]        'title' or 'description'; sets the length hint.
 * @param {string}   [props.describedBy] Space separated ids of help and error text.
 * @param {boolean}  [props.invalid]     Whether the field has an error.
 * @param {string}   [props.error]       Validation message to show under the field.
 */
export function TemplateField( {
	id,
	label,
	value,
	onChange,
	variables = [],
	separator = '–',
	kind = 'title',
	describedBy,
	invalid,
	error,
} ) {
	const wrapper = useRef( null );
	const caret = useRef( null );
	const previewId = `${ id }-preview`;
	const errorId = `${ id }-error`;
	const text = value ?? '';
	const rendered = renderTemplate( text, samplesOf( variables ), separator );
	const limit = LONG_AT[ kind ] ?? LONG_AT.title;
	const isLong = rendered.length > limit;
	const hasError = !! ( invalid || error );
	const ids =
		[ describedBy, previewId, error ? errorId : null ]
			.filter( Boolean )
			.join( ' ' ) || undefined;

	// Selection events bubble from the input; remembered so the picker can insert at the caret.
	useEffect( () => {
		const node = wrapper.current;
		if ( ! node ) {
			return undefined;
		}
		const remember = ( event ) => {
			const target = event.target;
			if ( target && typeof target.selectionStart === 'number' ) {
				caret.current = {
					start: target.selectionStart,
					end: target.selectionEnd,
				};
			}
		};
		const events = [ 'select', 'keyup', 'click', 'input', 'focusout' ];
		events.forEach( ( name ) => node.addEventListener( name, remember ) );
		return () =>
			events.forEach( ( name ) =>
				node.removeEventListener( name, remember )
			);
	}, [] );

	const insert = ( name, onClose ) => {
		const token = `%${ name }%`;
		const range = caret.current ?? {
			start: text.length,
			end: text.length,
		};
		const start = Math.min( range.start, text.length );
		const end = Math.min( Math.max( range.end, start ), text.length );
		onChange( text.slice( 0, start ) + token + text.slice( end ) );
		const position = start + token.length;
		caret.current = { start: position, end: position };
		onClose();
		window.setTimeout( () => {
			const input = wrapper.current?.querySelector( 'input' );
			if ( input ) {
				input.focus();
				input.setSelectionRange( position, position );
			}
		}, 0 );
	};

	return (
		<div
			className={ `bx-template${ hasError ? ' has-error' : '' }` }
			ref={ wrapper }
		>
			<div className="bx-template__input">
				<TextControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					id={ id }
					type="text"
					label={ label }
					value={ text }
					onChange={ onChange }
					aria-describedby={ ids }
					aria-invalid={ hasError || undefined }
				/>
			</div>
			<div className="bx-template__tools">
				<DropdownMenu
					label={ __( 'Insert variable', 'rankavia' ) }
					text={ __( 'Insert variable', 'rankavia' ) }
					toggleProps={ { variant: 'secondary', size: 'small' } }
					popoverProps={ { placement: 'bottom-start' } }
				>
					{ ( { onClose } ) => (
						<MenuGroup>
							{ variables.map( ( variable ) => (
								<MenuItem
									key={ variable.name }
									info={ `%${ variable.name }%` }
									onClick={ () =>
										insert( variable.name, onClose )
									}
								>
									{ variable.label || variable.name }
								</MenuItem>
							) ) }
						</MenuGroup>
					) }
				</DropdownMenu>
			</div>
			<p
				id={ previewId }
				className={ `bx-template__preview${
					isLong ? ' is-long' : ''
				}` }
			>
				<span className="bx-template__rendered">
					{ rendered || __( '(empty)', 'rankavia' ) }
				</span>
				<span className="bx-template__count">
					{ sprintf(
						/* translators: %d: number of characters. */
						_n(
							'%d character',
							'%d characters',
							rendered.length,
							'rankavia'
						),
						rendered.length
					) }
					{ isLong ? ` ${ __( '(long)', 'rankavia' ) }` : '' }
				</span>
			</p>
			{ error && (
				<p id={ errorId } className="bx-field__error" role="alert">
					<span aria-hidden="true">{ '⚠ ' }</span>
					{ __( 'Error:', 'rankavia' ) } { error }
				</p>
			) }
		</div>
	);
}
