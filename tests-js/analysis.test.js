import { describe, expect, it } from 'vitest';
import {
	analyse,
	countWords,
	excerptFrom,
	normalise,
	parseContent,
	scoreOf,
} from '../src-js/editor/analysis';

const filler = ( count ) => Array( count ).fill( 'lorem' ).join( ' ' );

/**
 * Text of `total` words in which the first `count` words are the keyword "widget".
 *
 * @param {number} count Occurrences of the keyword.
 * @param {number} total Words in the text.
 */
const textWithDensity = ( count, total ) =>
	[
		...Array( count ).fill( 'widget' ),
		...Array( total - count ).fill( 'lorem' ),
	].join( ' ' );

const chars = ( count ) => 'a'.repeat( count );

// Passes every scored check.
const GOOD = {
	keyword: 'widget',
	title: 'The best widget guide for beginners today',
	description:
		'Learn how to pick the right widget for your project with a clear, practical, step by step guide.',
	slug: 'best-widget-guide',
	contentText: `widget ${ filler( 19 ) }\n${ filler(
		100
	) } widget widget widget widget widget\n${ filler( 275 ) }`,
	headings: [ 'Choosing a widget', 'Setup' ],
	images: [ { src: '/a.png', alt: 'A widget on a desk' } ],
	links: [
		{ href: '/other', internal: true },
		{ href: 'https://example.org/', internal: false },
	],
};

const run = ( overrides = {} ) => analyse( { ...GOOD, ...overrides } );
const find = ( result, id ) => result.checks.find( ( item ) => item.id === id );
const status = ( overrides, id ) => find( run( overrides ), id ).status;

describe( 'analyse without a keyword', () => {
	it( 'returns one info row and no score', () => {
		const result = run( { keyword: '   ' } );
		expect( result.score ).toBeNull();
		expect( result.checks ).toHaveLength( 1 );
		expect( result.checks[ 0 ] ).toMatchObject( {
			id: 'keyword-set',
			status: 'info',
			detail: 'Add a focus keyword to see the analysis',
		} );
	} );

	it( 'skips every other check', () => {
		const ids = run( { keyword: '' } ).checks.map( ( item ) => item.id );
		expect( ids ).toEqual( [ 'keyword-set' ] );
	} );
} );

describe( 'analyse check ids', () => {
	it( 'emits every id once when a keyword is set', () => {
		const ids = run().checks.map( ( item ) => item.id );
		expect( ids ).toEqual( [
			'keyword-set',
			'keyword-in-title',
			'keyword-in-description',
			'keyword-in-slug',
			'keyword-in-intro',
			'keyword-in-headings',
			'keyword-density',
			'title-length',
			'description-length',
			'content-length',
			'images-alt',
			'links-internal',
			'links-external',
		] );
	} );

	it( 'keyword-set is info', () => {
		expect( status( {}, 'keyword-set' ) ).toBe( 'info' );
	} );

	it( 'keyword-in-title passes or fails', () => {
		expect( status( {}, 'keyword-in-title' ) ).toBe( 'pass' );
		expect( status( { title: 'Nothing here' }, 'keyword-in-title' ) ).toBe(
			'fail'
		);
	} );

	it( 'matches whole words and ignores case and punctuation', () => {
		expect(
			status( { title: 'WIDGET: the guide' }, 'keyword-in-title' )
		).toBe( 'pass' );
		expect(
			status( { title: 'Widgets galore' }, 'keyword-in-title' )
		).toBe( 'fail' );
	} );

	it( 'keyword-in-description passes or fails', () => {
		expect( status( {}, 'keyword-in-description' ) ).toBe( 'pass' );
		expect(
			status(
				{ description: 'Something else' },
				'keyword-in-description'
			)
		).toBe( 'fail' );
	} );

	it( 'keyword-in-slug passes or warns', () => {
		expect( status( {}, 'keyword-in-slug' ) ).toBe( 'pass' );
		expect( status( { slug: 'my-guide' }, 'keyword-in-slug' ) ).toBe(
			'warn'
		);
		expect(
			status(
				{ keyword: 'blue widget', slug: 'a-blue-widget-guide' },
				'keyword-in-slug'
			)
		).toBe( 'pass' );
	} );

	it( 'keyword-in-intro looks at the first paragraph or the first 10% of words', () => {
		expect( status( {}, 'keyword-in-intro' ) ).toBe( 'pass' );
		// Not in the first paragraph, and the first 10% of 100 words is 10 words.
		expect(
			status(
				{ contentText: `${ filler( 30 ) }\n${ filler( 69 ) } widget` },
				'keyword-in-intro'
			)
		).toBe( 'warn' );
		// First paragraph is long, the keyword sits inside the first 10% of all words.
		expect(
			status(
				{ contentText: `${ filler( 3 ) } widget ${ filler( 96 ) }` },
				'keyword-in-intro'
			)
		).toBe( 'pass' );
	} );

	it( 'keyword-in-headings passes or warns', () => {
		expect( status( {}, 'keyword-in-headings' ) ).toBe( 'pass' );
		expect(
			status( { headings: [ 'Setup' ] }, 'keyword-in-headings' )
		).toBe( 'warn' );
		expect( status( { headings: [] }, 'keyword-in-headings' ) ).toBe(
			'warn'
		);
	} );

	it( 'title-length passes from 30 to 60 characters and warns outside', () => {
		const title = ( length ) => `widget ${ chars( length - 7 ) }`;
		expect( status( { title: title( 29 ) }, 'title-length' ) ).toBe(
			'warn'
		);
		expect( status( { title: title( 30 ) }, 'title-length' ) ).toBe(
			'pass'
		);
		expect( status( { title: title( 60 ) }, 'title-length' ) ).toBe(
			'pass'
		);
		expect( status( { title: title( 61 ) }, 'title-length' ) ).toBe(
			'warn'
		);
	} );

	it( 'title-length uses the thresholds it is given', () => {
		const result = run( {
			title: 'widget ten',
			thresholds: { titleMin: 5, titleMax: 12 },
		} );
		expect( find( result, 'title-length' ).status ).toBe( 'pass' );
	} );

	it( 'description-length passes from 70 to 160 characters and warns outside', () => {
		const text = ( length ) => `widget ${ chars( length - 7 ) }`;
		expect(
			status( { description: text( 69 ) }, 'description-length' )
		).toBe( 'warn' );
		expect(
			status( { description: text( 70 ) }, 'description-length' )
		).toBe( 'pass' );
		expect(
			status( { description: text( 160 ) }, 'description-length' )
		).toBe( 'pass' );
		expect(
			status( { description: text( 161 ) }, 'description-length' )
		).toBe( 'warn' );
	} );

	it( 'content-length passes from 300 words, warns from 100, fails below', () => {
		expect(
			status( { contentText: filler( 300 ) }, 'content-length' )
		).toBe( 'pass' );
		expect(
			status( { contentText: filler( 299 ) }, 'content-length' )
		).toBe( 'warn' );
		expect(
			status( { contentText: filler( 100 ) }, 'content-length' )
		).toBe( 'warn' );
		expect(
			status( { contentText: filler( 99 ) }, 'content-length' )
		).toBe( 'fail' );
	} );

	it( 'images-alt is info without images, pass with all alt, warn otherwise', () => {
		expect( status( { images: [] }, 'images-alt' ) ).toBe( 'info' );
		expect( status( {}, 'images-alt' ) ).toBe( 'pass' );
		expect(
			status(
				{
					images: [
						{ src: 'a.png', alt: 'A' },
						{ src: 'b.png', alt: '  ' },
					],
				},
				'images-alt'
			)
		).toBe( 'warn' );
	} );

	it( 'links-internal passes with an internal link and warns without', () => {
		expect( status( {}, 'links-internal' ) ).toBe( 'pass' );
		expect(
			status(
				{
					links: [
						{ href: 'https://example.org/', internal: false },
					],
				},
				'links-internal'
			)
		).toBe( 'warn' );
		expect( status( { links: [] }, 'links-internal' ) ).toBe( 'warn' );
	} );

	it( 'links-external is info and reports the count', () => {
		const result = run( {
			links: [
				{ href: '/a', internal: true },
				{ href: 'https://x.test/', internal: false },
				{ href: 'https://y.test/', internal: false },
			],
		} );
		const row = find( result, 'links-external' );
		expect( row.status ).toBe( 'info' );
		expect( row.detail ).toContain( '2' );
	} );
} );

describe( 'keyword density boundaries', () => {
	const density = ( count ) =>
		find(
			run( { contentText: textWithDensity( count, 1000 ) } ),
			'keyword-density'
		);

	it( 'warns below 0.5%', () => {
		expect( density( 4 ).status ).toBe( 'warn' );
		expect( density( 0 ).status ).toBe( 'warn' );
	} );

	it( 'passes at exactly 0.5%', () => {
		expect( density( 5 ).status ).toBe( 'pass' );
	} );

	it( 'passes at exactly 2.5%', () => {
		expect( density( 25 ).status ).toBe( 'pass' );
	} );

	it( 'warns above 2.5% up to and including 3%', () => {
		expect( density( 26 ).status ).toBe( 'warn' );
		expect( density( 30 ).status ).toBe( 'warn' );
	} );

	it( 'fails above 3%', () => {
		expect( density( 31 ).status ).toBe( 'fail' );
	} );

	it( 'counts a multi-word keyword as a phrase', () => {
		const result = run( {
			keyword: 'blue widget',
			contentText: `${ Array( 10 )
				.fill( 'blue widget' )
				.join( ' ' ) } ${ filler( 980 ) }`,
		} );
		// 10 occurrences in 1000 words.
		expect( find( result, 'keyword-density' ).detail ).toContain( '1%' );
		expect( find( result, 'keyword-density' ).status ).toBe( 'pass' );
	} );

	it( 'does not divide by zero on empty content', () => {
		const result = run( { contentText: '' } );
		expect( find( result, 'keyword-density' ).status ).toBe( 'warn' );
	} );
} );

describe( 'score', () => {
	it( 'is 100 when every scored check passes', () => {
		const result = run();
		const scored = result.checks.filter(
			( item ) => item.status !== 'info'
		);
		expect( scored.every( ( item ) => item.status === 'pass' ) ).toBe(
			true
		);
		expect( result.score ).toBe( 100 );
	} );

	it( 'weights pass 1, warn 0.5, fail 0 and ignores info', () => {
		const make = ( ...states ) => states.map( ( s ) => ( { status: s } ) );
		expect( scoreOf( make( 'pass', 'warn', 'fail', 'info' ) ) ).toBe( 50 );
		expect( scoreOf( make( 'pass', 'pass', 'warn' ) ) ).toBe( 83 );
		expect( scoreOf( make( 'fail', 'fail' ) ) ).toBe( 0 );
		expect( scoreOf( make( 'info' ) ) ).toBeNull();
	} );

	it( 'drops when a check fails', () => {
		// One fail among 11 scored checks (all but the 2 info rows).
		const result = run( {
			title: 'Nothing here but it is long enough ok',
		} );
		expect( find( result, 'keyword-in-title' ).status ).toBe( 'fail' );
		expect( result.score ).toBe( 91 );
	} );
} );

describe( 'parseContent (regex path)', () => {
	const html = `<!-- wp:paragraph -->
<p>Intro with a <a href="/about/">local link</a> and <a href="https://example.com/post">home link</a>.</p>
<!-- /wp:paragraph -->
<!-- wp:heading -->
<h2 class="wp-block-heading">Why <em>widgets</em> &amp; more</h2>
<!-- /wp:heading -->
<h3>Deep dive</h3><h4>Detail</h4><h5>Ignored</h5><h1>Also ignored</h1>
<p>Second <a href="https://other.test/x">external</a> <a href="#top">anchor</a> <a href="mailto:a@b.test">mail</a></p>
<figure><img src="/a.png" alt="A &quot;widget&quot;" /><img src='/b.png'></figure>
<script>var hidden = 1;</script><style>.x{color:red}</style>`;

	const parsed = parseContent( html, 'https://example.com/', false );

	it( 'derives plain text with one block per line', () => {
		const lines = parsed.text.split( '\n' );
		expect( lines[ 0 ] ).toBe( 'Intro with a local link and home link.' );
		expect( parsed.text ).toContain( 'Why widgets & more' );
		expect( parsed.text ).not.toContain( 'hidden' );
		expect( parsed.text ).not.toContain( 'color' );
		expect( parsed.text ).not.toContain( 'wp:' );
	} );

	it( 'collects H2 to H4 text only', () => {
		expect( parsed.headings ).toEqual( [
			'Why widgets & more',
			'Deep dive',
			'Detail',
		] );
	} );

	it( 'collects image src and alt', () => {
		expect( parsed.images ).toEqual( [
			{ src: '/a.png', alt: 'A "widget"' },
			{ src: '/b.png', alt: '' },
		] );
	} );

	it( 'marks links under the home URL or starting with "/" as internal', () => {
		expect( parsed.links ).toEqual( [
			{ href: '/about/', internal: true },
			{ href: 'https://example.com/post', internal: true },
			{ href: 'https://other.test/x', internal: false },
		] );
	} );

	it( 'treats protocol-relative URLs as external and survives empty input', () => {
		expect(
			parseContent(
				'<a href="//cdn.test/x">x</a>',
				'https://example.com/',
				false
			).links
		).toEqual( [ { href: '//cdn.test/x', internal: false } ] );
		expect( parseContent( '', '', false ) ).toEqual( {
			text: '',
			headings: [],
			images: [],
			links: [],
		} );
		expect( parseContent( null, '', false ).text ).toBe( '' );
	} );

	it( 'feeds the analysis', () => {
		const result = analyse( {
			keyword: 'widgets',
			title: 'x',
			description: 'x',
			slug: 'x',
			contentText: parsed.text,
			headings: parsed.headings,
			images: parsed.images,
			links: parsed.links,
		} );
		expect( find( result, 'keyword-in-headings' ).status ).toBe( 'pass' );
		expect( find( result, 'images-alt' ).status ).toBe( 'warn' );
		expect( find( result, 'links-internal' ).status ).toBe( 'pass' );
		expect( find( result, 'links-external' ).detail ).toContain( '1' );
	} );
} );

describe( 'excerptFrom', () => {
	it( 'collapses whitespace and returns short text unchanged', () => {
		expect( excerptFrom( '  A   short\n\ntext  ' ) ).toBe( 'A short text' );
		expect( excerptFrom( '' ) ).toBe( '' );
	} );

	it( 'keeps exactly 155 characters whole', () => {
		const text = chars( 155 );
		expect( excerptFrom( text ) ).toBe( text );
	} );

	it( 'cuts at 155 characters and adds an ellipsis', () => {
		expect( excerptFrom( chars( 156 ) ) ).toBe( `${ chars( 155 ) }…` );
	} );

	it( 'trims a space left at the cut', () => {
		const text = `${ chars( 154 ) } more words`;
		expect( excerptFrom( text ) ).toBe( `${ chars( 154 ) }…` );
	} );

	it( 'counts characters, not UTF-16 units', () => {
		const text = '😀'.repeat( 156 );
		expect( excerptFrom( text ) ).toBe( `${ '😀'.repeat( 155 ) }…` );
	} );
} );

describe( 'helpers', () => {
	it( 'normalise lowercases and reduces to words', () => {
		expect( normalise( ' Hello,   WORLD-wide &amp; more! ' ) ).toBe(
			'hello world wide more'
		);
	} );

	it( 'countWords counts words', () => {
		expect( countWords( 'one two  three\nfour' ) ).toBe( 4 );
		expect( countWords( '' ) ).toBe( 0 );
	} );
} );
