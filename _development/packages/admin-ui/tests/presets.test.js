import { describe, expect, it } from 'vitest';
import { applyPreset } from '../src/apply-preset';

describe( 'applyPreset', () => {
	it( 'returns [ key, value ] pairs for flat values', () => {
		expect(
			applyPreset( { values: { a: 'allow', b: 'block', c: true } } )
		).toEqual( [
			[ 'a', 'allow' ],
			[ 'b', 'block' ],
			[ 'c', true ],
		] );
	} );

	it( 'keeps a nested map value as one value', () => {
		const map = { gptbot: 'block', ccbot: 'block' };
		expect( applyPreset( { values: { ai_bots: map } } ) ).toEqual( [
			[ 'ai_bots', map ],
		] );
	} );

	it( 'skips inherited properties', () => {
		const values = Object.create( { inherited: 'x' } );
		values.own = 'y';
		expect( applyPreset( { values } ) ).toEqual( [ [ 'own', 'y' ] ] );
	} );

	it( 'returns an empty array for empty or missing values', () => {
		expect( applyPreset( { values: {} } ) ).toEqual( [] );
		expect( applyPreset( {} ) ).toEqual( [] );
		expect( applyPreset( { values: null } ) ).toEqual( [] );
		expect( applyPreset( undefined ) ).toEqual( [] );
	} );
} );
