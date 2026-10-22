import { ScoreRing } from './score-ring';
import { gradeFor } from './grade';

/**
 * Stat tile: sentence-case label, the score as the value, a grade letter + word so colour is never alone.
 *
 * @param {Object} props
 * @param {string} props.label  Tile label.
 * @param {number} props.score  Score 0-100, or null.
 * @param {Object} props.counts Pass, warn and fail counts.
 */
export function StatTile( { label, score, counts } ) {
	const grade = gradeFor( score );
	return (
		<div className={ `bx-tile bx-tile--${ grade.status }` }>
			<ScoreRing
				score={ score }
				size={ 72 }
				stroke={ 7 }
				label={ label }
			/>
			<div className="bx-tile__body">
				<div className="bx-tile__label">{ label }</div>
				<div className="bx-tile__grade">
					<span className="bx-tile__letter">{ grade.letter }</span>
					<span className="bx-tile__word">{ grade.label }</span>
				</div>
				{ counts && (
					<div className="bx-tile__counts">
						{ counts.passed } ✓ · { counts.warned } ! ·{ ' ' }
						{ counts.failed } ✕
					</div>
				) }
			</div>
		</div>
	);
}
