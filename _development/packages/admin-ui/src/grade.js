import { __ } from '@wordpress/i18n';

/**
 * Grade bands mirror the public scanners users compare against (90+ A+, 80 A, 70 B, 60 C, below D).
 * Each band carries a status role so colour is never the only signal: the letter and label travel with it.
 */
export const GRADES = [
	{ min: 90, letter: 'A+', status: 'good' },
	{ min: 80, letter: 'A', status: 'good' },
	{ min: 70, letter: 'B', status: 'warning' },
	{ min: 60, letter: 'C', status: 'serious' },
	{ min: 0, letter: 'D', status: 'critical' },
];

export function gradeFor( score ) {
	if ( score === null || score === undefined || Number.isNaN( score ) ) {
		return {
			letter: '–',
			status: 'none',
			label: __( 'Not scored', 'rankavia' ),
		};
	}
	const band =
		GRADES.find( ( g ) => score >= g.min ) ?? GRADES[ GRADES.length - 1 ];
	const labels = {
		good:
			score >= 90
				? __( 'Excellent', 'rankavia' )
				: __( 'Good', 'rankavia' ),
		warning: __( 'Fair', 'rankavia' ),
		serious: __( 'Needs work', 'rankavia' ),
		critical: __( 'Critical', 'rankavia' ),
	};
	return {
		letter: band.letter,
		status: band.status,
		label: labels[ band.status ],
	};
}
