( function ( wp ) {
	'use strict';
	var el = wp.element.createElement;
	var TextControl = wp.components.TextControl;
	var Button = wp.components.Button;
	var RichText = wp.blockEditor.RichText;
	var __ = wp.i18n.__;

	wp.blocks.registerBlockType( 'rankavia/faq', {
		apiVersion: 3,
		title: __( 'FAQ', 'rankavia' ),
		icon: 'editor-help',
		category: 'widgets',
		attributes: { items: { type: 'array', default: [] } },
		edit: function ( props ) {
			var items = Array.isArray( props.attributes.items ) ? props.attributes.items.slice( 0, 10 ) : [];
			function change( index, field, value ) {
				var next = items.slice();
				next[ index ] = Object.assign( {}, next[ index ] );
				next[ index ][ field ] = value.slice( 0, field === 'question' ? 500 : 5000 );
				props.setAttributes( { items: next } );
			}
			return el( 'div', wp.blockEditor.useBlockProps( { className: 'rankavia-faq-editor' } ),
				el( 'h3', null, __( 'Frequently asked questions', 'rankavia' ) ),
				items.map( function ( item, index ) {
					return el( 'div', { key: index },
						el( TextControl, { label: __( 'Question', 'rankavia' ), value: item.question || '', onChange: function ( value ) { change( index, 'question', value ); } } ),
						el( RichText, { tagName: 'div', 'aria-label': __( 'Answer', 'rankavia' ), value: item.answer || '', allowedFormats: [ 'core/bold', 'core/italic', 'core/link' ], onChange: function ( value ) { change( index, 'answer', value ); }, placeholder: __( 'Write an answer', 'rankavia' ) } ),
						el( Button, { isDestructive: true, onClick: function () { props.setAttributes( { items: items.filter( function ( _, i ) { return i !== index; } ) } ); } }, __( 'Remove', 'rankavia' ) )
					);
				} ),
				items.length < 10 && el( Button, { variant: 'secondary', onClick: function () { props.setAttributes( { items: items.concat( [ { question: '', answer: '' } ] ) } ); } }, __( 'Add question', 'rankavia' ) )
			);
		},
		save: function () { return null; }
	} );
} )( window.wp );
