import { useEntityProp } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { getEditorConfig } from './serp';

/**
 * Setting name => post meta key (registered by Meta_Module::register_post_meta()).
 */
export const META_KEYS = {
	title: '_rankavia_title',
	description: '_rankavia_description',
	focusKeyword: '_rankavia_focus_keyword',
	canonical: '_rankavia_canonical',
	noindex: '_rankavia_noindex',
	nofollow: '_rankavia_nofollow',
	ogTitle: '_rankavia_og_title',
	ogDescription: '_rankavia_og_description',
	ogImage: '_rankavia_og_image',
};

const BOOLEANS = [ 'noindex', 'nofollow' ];

const read = ( meta, name ) => {
	const raw = meta?.[ META_KEYS[ name ] ];
	if ( BOOLEANS.includes( name ) ) {
		return !! raw;
	}
	if ( name === 'ogImage' ) {
		return parseInt( raw, 10 ) || 0;
	}
	return typeof raw === 'string' ? raw : '';
};

const coerce = ( name, value ) => {
	if ( BOOLEANS.includes( name ) ) {
		return !! value;
	}
	if ( name === 'ogImage' ) {
		return parseInt( value, 10 ) || 0;
	}
	return String( value ?? '' );
};

/**
 * Typed access to the nine Rankavia post meta keys of the post being edited, through the
 * editor's entity store so the values save together with the post.
 *
 * @return {{values: Object, set: Function}} `values` keyed by setting name; `set( name, value )` edits one key.
 */
export function usePostSeo() {
	const postType = useSelect(
		( select ) => select( 'core/editor' ).getCurrentPostType(),
		[]
	);
	const [ meta, setMeta ] = useEntityProp(
		'postType',
		postType || getEditorConfig().postType,
		'meta'
	);

	const values = {};
	Object.keys( META_KEYS ).forEach( ( name ) => {
		values[ name ] = read( meta, name );
	} );

	// Entity edits to `meta` are merged, so only the changed key is sent.
	const set = ( name, value ) =>
		setMeta( { [ META_KEYS[ name ] ]: coerce( name, value ) } );

	return { values, set };
}
