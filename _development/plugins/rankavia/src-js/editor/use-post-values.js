import { useEffect, useMemo, useState } from '@wordpress/element';
import { useRegistry, useSelect } from '@wordpress/data';
import { dateI18n, getSettings } from '@wordpress/date';
import { excerptFrom, parseContent } from './analysis';
import { getEditorConfig } from './serp';

const CONTENT_DELAY = 600;

const stripTags = ( text ) => String( text ?? '' ).replace( /<[^>]*>/g, ' ' );

/**
 * The parsed post content (text, headings, images, links). Serializing the blocks is not
 * cheap, so the editor store is read after a pause in the changes rather than on every
 * keystroke.
 *
 * @param {string} homeUrl Site home URL, to tell internal links from external ones.
 * @return {{text: string, headings: string[], images: Array, links: Array}} Parsed content.
 */
export function usePostContent( homeUrl ) {
	const registry = useRegistry();
	const [ html, setHtml ] = useState( '' );

	useEffect( () => {
		let timer = null;
		const read = () =>
			setHtml(
				registry.select( 'core/editor' ).getEditedPostContent() ?? ''
			);
		const schedule = () => {
			window.clearTimeout( timer );
			timer = window.setTimeout( read, CONTENT_DELAY );
		};
		read();
		const unsubscribe = registry.subscribe( schedule );
		return () => {
			window.clearTimeout( timer );
			unsubscribe();
		};
	}, [ registry ] );

	return useMemo( () => parseContent( html, homeUrl ), [ html, homeUrl ] );
}

/**
 * Slug and permalink of the post being edited.
 *
 * @return {{slug: string, permalink: string}} Slug (generated one for drafts) and permalink.
 */
export function usePostLink() {
	return useSelect( ( select ) => {
		const editor = select( 'core/editor' );
		return {
			slug:
				editor.getEditedPostAttribute( 'slug' ) ||
				editor.getEditedPostAttribute( 'generated_slug' ) ||
				'',
			permalink: editor.getPermalink() || '',
		};
	}, [] );
}

/**
 * Values for the template variables of the post being edited, read from the editor store.
 * Mirrors Variables::for_request() on the PHP side for a singular view.
 *
 * @param {string} contentText Plain text of the content, for the automatic excerpt.
 * @return {Object} name => value (the separator comes from the config).
 */
export function usePostValues( contentText = '' ) {
	const config = getEditorConfig();
	const data = useSelect( ( select ) => {
		const editor = select( 'core/editor' );
		const core = select( 'core' );
		const type = editor.getCurrentPostType();
		const authorId = editor.getEditedPostAttribute( 'author' );
		const categoryIds =
			type === 'post'
				? editor.getEditedPostAttribute( 'categories' ) ?? []
				: [];
		const categories = categoryIds.length
			? core.getEntityRecords( 'taxonomy', 'category', {
					include: categoryIds,
					per_page: categoryIds.length,
			  } )
			: null;
		const postType = type ? core.getPostType( type ) : null;
		const author = authorId
			? core.getEntityRecord( 'root', 'user', authorId )
			: null;
		return {
			id: editor.getCurrentPostId(),
			title: editor.getEditedPostAttribute( 'title' ),
			excerpt: editor.getEditedPostAttribute( 'excerpt' ),
			date: editor.getEditedPostAttribute( 'date' ),
			modified: editor.getEditedPostAttribute( 'modified' ),
			// Core-data has the site name and tagline for every role in the REST index.
			siteName: core.getEntityRecord( 'root', '__unstableBase' )?.name,
			tagline: core.getEntityRecord( 'root', '__unstableBase' )
				?.description,
			category: [ ...( categories ?? [] ) ].sort( ( a, b ) =>
				String( a.name ).localeCompare( String( b.name ) )
			)[ 0 ]?.name,
			author: author?.name ?? core.getCurrentUser()?.name,
			singular: postType?.labels?.singular_name,
			plural: postType?.labels?.name,
		};
	}, [] );

	const format = getSettings().formats?.date || 'F j, Y';
	const now = new Date();
	const manual = stripTags( data.excerpt ).trim();

	const values = {
		title: data.title ?? '',
		sitename: data.siteName || config.siteName,
		tagline: data.tagline ?? '',
		sep: config.separator,
		excerpt: excerptFrom( manual || contentText ),
		category: data.category ?? '',
		term: '',
		term_description: '',
		author: data.author ?? '',
		date: dateI18n( format, data.date || now ),
		modified: dateI18n( format, data.modified || data.date || now ),
		post_type: data.singular ?? '',
		post_type_plural: data.plural ?? '',
		page: '',
		pagenumber: '',
		search: '',
		currentyear: String( now.getFullYear() ),
		currentdate: dateI18n( format, now ),
		id: data.id ? String( data.id ) : '',
	};

	// A stable object while nothing changed, so memoised consumers do not recompute.
	const key = JSON.stringify( values );
	// eslint-disable-next-line react-hooks/exhaustive-deps
	return useMemo( () => values, [ key ] );
}
