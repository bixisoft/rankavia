import { describe, expect, it } from 'vitest';
import { renderTemplate, variablesIn } from '../src/template';

const base = { title: 'How to speed up WordPress', sitename: 'Example Site', tagline: '' };

// Shared with the PHP Template_Test: template, value overrides, separator, expected.
const cases = [
	[ 'a', '%title% %sep% %sitename%', {}, '–', 'How to speed up WordPress – Example Site' ],
	[ 'b', '%title% %sep% %sitename%', { title: '' }, '–', 'Example Site' ],
	[ 'c', '%title% %sep% %sitename% %sep% %tagline%', {}, '–', 'How to speed up WordPress – Example Site' ],
	[ 'd', '%unknown% %sep% %sitename%', {}, '–', 'Example Site' ],
	[ 'e', '  %title%    %sep%   %sitename%  ', {}, '–', 'How to speed up WordPress – Example Site' ],
	[ 'f', '%title% | %sitename%', { sitename: '' }, '|', 'How to speed up WordPress' ],
	[ 'g', '%title%', { title: '<b>Bold</b> &amp; more' }, '–', 'Bold & more' ],
	[ 'h', '%title% %sep% %sitename% %page%', { page: '' }, '–', 'How to speed up WordPress – Example Site' ],
	[ 'i', '%sitename% » %title%', { title: '' }, '»', 'Example Site' ],
];

describe( 'renderTemplate', () => {
	it.each( cases )( 'case %s', ( name, template, overrides, separator, expected ) => {
		expect( renderTemplate( template, { ...base, ...overrides }, separator ) ).toBe( expected );
	} );
} );

describe( 'variablesIn', () => {
	it( 'lists unique names in order', () => {
		expect( variablesIn( '%title% and %title% %sep%' ) ).toEqual( [ 'title', 'sep' ] );
	} );
} );
