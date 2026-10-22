import { __, _n, sprintf } from '@wordpress/i18n';

export const EXCERPT_LENGTH = 155;

export const DEFAULT_THRESHOLDS = {
	titleMin: 30,
	titleMax: 60,
	descriptionMin: 70,
	descriptionMax: 160,
};

const BLOCK_CLOSE =
	/<\/(?:p|div|h[1-6]|li|ul|ol|blockquote|tr|table|figure|figcaption|pre|section|article)>|<br\s*\/?>/gi;

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
 * Collapses whitespace runs and trims.
 *
 * @param {string} text Any text.
 * @return {string} Single-spaced text.
 */
const squash = ( text ) =>
	String( text ?? '' )
		.replace( /\s+/g, ' ' )
		.trim();

/**
 * Lowercases and reduces text to words separated by single spaces, so a slug, a title and a
 * keyword compare on the same footing.
 *
 * @param {string} text Any text.
 * @return {string} Lowercase words.
 */
export const normalise = ( text ) =>
	decodeEntities( String( text ?? '' ) )
		.toLowerCase()
		.replace( /[^\p{L}\p{N}]+/gu, ' ' )
		.trim();

const wordsOf = ( text ) => {
	const normal = normalise( text );
	return normal ? normal.split( ' ' ) : [];
};

/**
 * Number of words in a text.
 *
 * @param {string} text Plain text.
 * @return {number} Word count.
 */
export const countWords = ( text ) => wordsOf( text ).length;

/**
 * Plain-text excerpt with the same rules as the PHP side (Variables::plain_excerpt):
 * whitespace collapsed, cut to 155 characters, trimmed, with an ellipsis when cut.
 *
 * @param {string} text Plain text.
 * @return {string} Excerpt.
 */
export function excerptFrom( text ) {
	const plain = squash( text );
	const chars = Array.from( plain );
	if ( chars.length <= EXCERPT_LENGTH ) {
		return plain;
	}
	return chars.slice( 0, EXCERPT_LENGTH ).join( '' ).trim() + '…';
}

const isInternal = ( href, homeUrl ) =>
	( href.startsWith( '/' ) && ! href.startsWith( '//' ) ) ||
	( !! homeUrl && href.startsWith( homeUrl ) );

const isNavigable = ( href ) =>
	href !== '' && ! /^(#|mailto:|tel:|javascript:)/i.test( href );

const attributeOf = ( tag, name ) => {
	const match = new RegExp(
		`\\s${ name }\\s*=\\s*(?:"([^"]*)"|'([^']*)'|([^\\s"'>]+))`,
		'i'
	).exec( tag );
	return match
		? decodeEntities( match[ 1 ] ?? match[ 2 ] ?? match[ 3 ] )
		: '';
};

const stripMarkup = ( html ) =>
	decodeEntities(
		html
			.replace( /<!--[\s\S]*?-->/g, '' )
			.replace( /<(script|style)\b[\s\S]*?<\/\1>/gi, '' )
			.replace( BLOCK_CLOSE, '$&\n' )
			.replace( /<[^>]*>/g, '' )
	);

const textLines = ( text ) =>
	text.split( '\n' ).map( squash ).filter( Boolean ).join( '\n' );

const parseWithRegex = ( html ) => {
	const headings = [];
	for ( const match of html.matchAll(
		/<h([2-4])\b[^>]*>([\s\S]*?)<\/h\1>/gi
	) ) {
		const text = squash( stripMarkup( match[ 2 ] ) );
		if ( text ) {
			headings.push( text );
		}
	}
	const images = [];
	for ( const match of html.matchAll( /<img\b[^>]*>/gi ) ) {
		images.push( {
			src: attributeOf( match[ 0 ], 'src' ),
			alt: attributeOf( match[ 0 ], 'alt' ),
		} );
	}
	const links = [];
	for ( const match of html.matchAll( /<a\b[^>]*>/gi ) ) {
		links.push( attributeOf( match[ 0 ], 'href' ).trim() );
	}
	return { text: textLines( stripMarkup( html ) ), headings, images, links };
};

const parseWithDom = ( html ) => {
	const doc = new window.DOMParser().parseFromString(
		html.replace( BLOCK_CLOSE, '$&\n' ),
		'text/html'
	);
	doc.querySelectorAll( 'script, style' ).forEach( ( node ) =>
		node.remove()
	);
	return {
		text: textLines( doc.body?.textContent ?? '' ),
		headings: Array.from( doc.querySelectorAll( 'h2, h3, h4' ) )
			.map( ( node ) => squash( node.textContent ) )
			.filter( Boolean ),
		images: Array.from( doc.querySelectorAll( 'img' ) ).map( ( node ) => ( {
			src: node.getAttribute( 'src' ) ?? '',
			alt: node.getAttribute( 'alt' ) ?? '',
		} ) ),
		links: Array.from( doc.querySelectorAll( 'a[href]' ) ).map( ( node ) =>
			( node.getAttribute( 'href' ) ?? '' ).trim()
		),
	};
};

/**
 * Derives what the analysis needs from the serialized post content. Uses DOMParser in the
 * browser and a regex path where it is missing (tests, workers).
 *
 * @param {string}  html     Serialized blocks.
 * @param {string}  homeUrl  Site home URL; links starting with it, or with "/", are internal.
 * @param {boolean} [useDom] Force the DOM path on or off; defaults to whether DOMParser exists.
 * @return {{text: string, headings: string[], images: Array<{src: string, alt: string}>, links: Array<{href: string, internal: boolean}>}} Parsed content. `text` has one block per line.
 */
export function parseContent(
	html,
	homeUrl = '',
	useDom = typeof window !== 'undefined' &&
		typeof window.DOMParser === 'function'
) {
	const source = String( html ?? '' );
	const parsed = useDom ? parseWithDom( source ) : parseWithRegex( source );
	return {
		text: parsed.text,
		headings: parsed.headings,
		images: parsed.images,
		links: parsed.links.filter( isNavigable ).map( ( href ) => ( {
			href,
			internal: isInternal( href, homeUrl ),
		} ) ),
	};
}

const containsPhrase = ( haystack, phrase ) =>
	phrase !== '' && ` ${ normalise( haystack ) } `.includes( ` ${ phrase } ` );

const occurrencesOf = ( words, phraseWords ) => {
	let count = 0;
	for ( let i = 0; i + phraseWords.length <= words.length; i++ ) {
		if ( phraseWords.every( ( word, j ) => words[ i + j ] === word ) ) {
			count++;
			i += phraseWords.length - 1;
		}
	}
	return count;
};

const check = ( id, status, title, detail ) => ( {
	id,
	status,
	title,
	detail,
} );

/**
 * Pass or fail by a condition; the details are the two messages.
 *
 * @param {string}  id      Check id.
 * @param {boolean} ok      Whether the check passed.
 * @param {string}  miss    Status when it did not pass (warn or fail).
 * @param {string}  title   Check title.
 * @param {string}  passed  Detail when it passed.
 * @param {string}  missing Detail when it did not.
 */
const binary = ( id, ok, miss, title, passed, missing ) =>
	check( id, ok ? 'pass' : miss, title, ok ? passed : missing );

/**
 * Score over the checks that ran: pass = 1, warn = 0.5, fail = 0, info is not scored.
 *
 * @param {Array} checks Check results.
 * @return {number|null} 0-100, or null when no check was scored.
 */
export function scoreOf( checks ) {
	const weights = { pass: 1, warn: 0.5, fail: 0 };
	const scored = checks.filter( ( item ) => item.status in weights );
	if ( ! scored.length ) {
		return null;
	}
	const total = scored.reduce(
		( sum, item ) => sum + weights[ item.status ],
		0
	);
	return Math.round( ( total / scored.length ) * 100 );
}

const rangeDetail = ( count, min, max ) =>
	sprintf(
		/* translators: 1: character count, 2: recommended minimum, 3: recommended maximum. */
		__( '%1$d characters (aim for %2$d to %3$d).', 'rankavia' ),
		count,
		min,
		max
	);

/**
 * Runs the focus keyword analysis.
 *
 * @param {Object}   input
 * @param {string}   input.keyword      Focus keyword.
 * @param {string}   input.title        Rendered SEO title.
 * @param {string}   input.description  Rendered meta description.
 * @param {string}   input.slug         Post slug.
 * @param {string}   input.contentText  Plain text, one block per line.
 * @param {string[]} input.headings     Text of the H2-H4 headings.
 * @param {Array}    input.images       [{ src, alt }].
 * @param {Array}    input.links        [{ href, internal }].
 * @param {Object}   [input.thresholds] titleMin, titleMax, descriptionMin, descriptionMax.
 * @return {{score: (number|null), checks: Array}} Score and checks.
 */
export function analyse( {
	keyword = '',
	title = '',
	description = '',
	slug = '',
	contentText = '',
	headings = [],
	images = [],
	links = [],
	thresholds = {},
} ) {
	const limits = { ...DEFAULT_THRESHOLDS, ...thresholds };
	const phrase = normalise( keyword );
	const keywordTitle = __( 'Focus keyword', 'rankavia' );

	// Without a keyword nothing else is meaningful: one info row, no score.
	if ( ! phrase ) {
		return {
			score: null,
			checks: [
				check(
					'keyword-set',
					'info',
					keywordTitle,
					__( 'Add a focus keyword to see the analysis', 'rankavia' )
				),
			],
		};
	}

	const lines = String( contentText ?? '' )
		.split( '\n' )
		.map( squash )
		.filter( Boolean );
	const words = wordsOf( contentText );
	// Intro: the first paragraph, or the first 10% of the words.
	const introWords = words.slice( 0, Math.ceil( words.length * 0.1 ) );
	const inIntro =
		containsPhrase( lines[ 0 ] ?? '', phrase ) ||
		containsPhrase( introWords.join( ' ' ), phrase );

	// Density: occurrences per 100 words, rounded to two places so the boundaries are stable.
	const occurrences = occurrencesOf( words, phrase.split( ' ' ) );
	const density = words.length
		? Math.round( ( occurrences * 10000 ) / words.length ) / 100
		: 0;
	let densityStatus = 'pass';
	if ( density > 3 ) {
		densityStatus = 'fail';
	} else if ( density < 0.5 || density > 2.5 ) {
		densityStatus = 'warn';
	}
	let densityAdvice = __( 'This is in the recommended range.', 'rankavia' );
	if ( densityStatus === 'fail' ) {
		densityAdvice = __(
			'The keyword is overused and reads as keyword stuffing.',
			'rankavia'
		);
	} else if ( density < 0.5 ) {
		densityAdvice = __(
			'Use the keyword more often (aim for 0.5% to 2.5%).',
			'rankavia'
		);
	} else if ( density > 2.5 ) {
		densityAdvice = __(
			'Use the keyword a little less often (aim for 0.5% to 2.5%).',
			'rankavia'
		);
	}

	const titleLength = Array.from( squash( title ) ).length;
	const descriptionLength = Array.from( squash( description ) ).length;

	let contentStatus = 'fail';
	if ( words.length >= 300 ) {
		contentStatus = 'pass';
	} else if ( words.length >= 100 ) {
		contentStatus = 'warn';
	}

	const pictures = images ?? [];
	const missingAlt = pictures.filter(
		( image ) => ! squash( image?.alt )
	).length;
	let altStatus = 'info';
	let altDetail = __( 'There are no images in the content.', 'rankavia' );
	if ( pictures.length && missingAlt === 0 ) {
		altStatus = 'pass';
		altDetail = __( 'Every image has alt text.', 'rankavia' );
	} else if ( pictures.length ) {
		altStatus = 'warn';
		altDetail = sprintf(
			/* translators: 1: number of images without alt text, 2: total number of images. */
			_n(
				'%1$d of %2$d image has no alt text.',
				'%1$d of %2$d images have no alt text.',
				missingAlt,
				'rankavia'
			),
			missingAlt,
			pictures.length
		);
	}

	const anchors = links ?? [];
	const internal = anchors.filter( ( link ) => link.internal ).length;
	const external = anchors.length - internal;

	const checks = [
		check(
			'keyword-set',
			'info',
			keywordTitle,
			sprintf(
				/* translators: %s: the focus keyword. */
				__( 'Analysing for "%s".', 'rankavia' ),
				squash( keyword )
			)
		),
		binary(
			'keyword-in-title',
			containsPhrase( title, phrase ),
			'fail',
			__( 'Keyword in the SEO title', 'rankavia' ),
			__( 'The SEO title contains the keyword.', 'rankavia' ),
			__( 'Add the keyword to the SEO title.', 'rankavia' )
		),
		binary(
			'keyword-in-description',
			containsPhrase( description, phrase ),
			'fail',
			__( 'Keyword in the meta description', 'rankavia' ),
			__( 'The meta description contains the keyword.', 'rankavia' ),
			__( 'Add the keyword to the meta description.', 'rankavia' )
		),
		binary(
			'keyword-in-slug',
			containsPhrase( slug, phrase ),
			'warn',
			__( 'Keyword in the URL slug', 'rankavia' ),
			__( 'The slug contains the keyword.', 'rankavia' ),
			__( 'Consider adding the keyword to the slug.', 'rankavia' )
		),
		binary(
			'keyword-in-intro',
			inIntro,
			'warn',
			__( 'Keyword in the introduction', 'rankavia' ),
			__(
				'The keyword appears at the start of the content.',
				'rankavia'
			),
			__( 'Use the keyword in the first paragraph.', 'rankavia' )
		),
		binary(
			'keyword-in-headings',
			( headings ?? [] ).some( ( heading ) =>
				containsPhrase( heading, phrase )
			),
			'warn',
			__( 'Keyword in a subheading', 'rankavia' ),
			__( 'A subheading (H2 to H4) contains the keyword.', 'rankavia' ),
			__(
				'Use the keyword in at least one H2, H3 or H4 subheading.',
				'rankavia'
			)
		),
		check(
			'keyword-density',
			densityStatus,
			__( 'Keyword density', 'rankavia' ),
			sprintf(
				/* translators: 1: density as a percentage, 2: number of occurrences, 3: advice. */
				__( '%1$s%% (%2$d times). %3$s', 'rankavia' ),
				density,
				occurrences,
				densityAdvice
			)
		),
		binary(
			'title-length',
			titleLength >= limits.titleMin && titleLength <= limits.titleMax,
			'warn',
			__( 'SEO title length', 'rankavia' ),
			rangeDetail( titleLength, limits.titleMin, limits.titleMax ),
			rangeDetail( titleLength, limits.titleMin, limits.titleMax )
		),
		binary(
			'description-length',
			descriptionLength >= limits.descriptionMin &&
				descriptionLength <= limits.descriptionMax,
			'warn',
			__( 'Meta description length', 'rankavia' ),
			rangeDetail(
				descriptionLength,
				limits.descriptionMin,
				limits.descriptionMax
			),
			rangeDetail(
				descriptionLength,
				limits.descriptionMin,
				limits.descriptionMax
			)
		),
		check(
			'content-length',
			contentStatus,
			__( 'Content length', 'rankavia' ),
			sprintf(
				/* translators: %d: number of words. */
				_n(
					'%d word (aim for 300 or more).',
					'%d words (aim for 300 or more).',
					words.length,
					'rankavia'
				),
				words.length
			)
		),
		check(
			'images-alt',
			altStatus,
			__( 'Image alt text', 'rankavia' ),
			altDetail
		),
		binary(
			'links-internal',
			internal > 0,
			'warn',
			__( 'Internal links', 'rankavia' ),
			sprintf(
				/* translators: %d: number of internal links. */
				_n(
					'%d internal link.',
					'%d internal links.',
					internal,
					'rankavia'
				),
				internal
			),
			__( 'Link to at least one other page on your site.', 'rankavia' )
		),
		check(
			'links-external',
			'info',
			__( 'External links', 'rankavia' ),
			sprintf(
				/* translators: %d: number of external links. */
				_n(
					'%d external link.',
					'%d external links.',
					external,
					'rankavia'
				),
				external
			)
		),
	];

	return { score: scoreOf( checks ), checks };
}
