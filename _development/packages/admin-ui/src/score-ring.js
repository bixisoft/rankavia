import { gradeFor } from './grade';

/**
 * A meter: the filled arc carries severity through the status role, the track is a lighter
 * step of the same colour so state reads across the whole ring. Text stays in ink tokens.
 *
 * @param {Object} props
 * @param {number} props.score  Score 0-100, or null.
 * @param {number} props.size   Diameter in px.
 * @param {number} props.stroke Stroke width in px.
 * @param {string} props.label  Accessible label.
 */
export function ScoreRing( { score, size = 96, stroke = 8, label } ) {
	const grade = gradeFor( score );
	const radius = ( size - stroke ) / 2;
	const circumference = 2 * Math.PI * radius;
	const value =
		score === null || score === undefined
			? 0
			: Math.max( 0, Math.min( 100, score ) );
	const offset = circumference * ( 1 - value / 100 );

	return (
		<svg
			className={ `bx-ring bx-ring--${ grade.status }` }
			width={ size }
			height={ size }
			viewBox={ `0 0 ${ size } ${ size }` }
			role="img"
			aria-label={ `${ label ?? '' } ${ score ?? '' } ${
				grade.label
			}`.trim() }
		>
			<circle
				className="bx-ring__track"
				cx={ size / 2 }
				cy={ size / 2 }
				r={ radius }
				strokeWidth={ stroke }
				fill="none"
			/>
			<circle
				className="bx-ring__fill"
				cx={ size / 2 }
				cy={ size / 2 }
				r={ radius }
				strokeWidth={ stroke }
				fill="none"
				strokeLinecap="round"
				strokeDasharray={ circumference }
				strokeDashoffset={ offset }
				transform={ `rotate(-90 ${ size / 2 } ${ size / 2 })` }
			/>
			<text
				className="bx-ring__value"
				x="50%"
				y="50%"
				dominantBaseline="central"
				textAnchor="middle"
			>
				{ score === null || score === undefined ? '–' : score }
			</text>
		</svg>
	);
}
