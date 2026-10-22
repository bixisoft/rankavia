import { __ } from '@wordpress/i18n';
import { StatTile } from './stat-tile';
import { ScoreRing } from './score-ring';
import { gradeFor } from './grade';

const CATEGORY_LABELS = () => ( {
	seo: __( 'Search engines', 'rankavia' ),
	aeo: __( 'Answer engines', 'rankavia' ),
	geo: __( 'Generative engines', 'rankavia' ),
	speed: __( 'Speed', 'rankavia' ),
} );

/**
 * The hero figure (overall) plus one tile per category. Exactly one hero per view.
 *
 * @param {Object} props
 * @param {Object} props.scores Scores by category, with overall.
 */
export function ScoreGrid( { scores } ) {
	const labels = CATEGORY_LABELS();
	const overall = scores?.overall ?? null;
	const grade = gradeFor( overall );

	return (
		<div className="bx-scores">
			<div className={ `bx-hero bx-hero--${ grade.status }` }>
				<ScoreRing
					score={ overall }
					size={ 140 }
					stroke={ 12 }
					label={ __( 'Visibility Score', 'rankavia' ) }
				/>
				<div className="bx-hero__body">
					<div className="bx-hero__label">
						{ __( 'Visibility Score', 'rankavia' ) }
					</div>
					<div className="bx-hero__grade">
						<span className="bx-hero__letter">
							{ grade.letter }
						</span>
						<span className="bx-hero__word">{ grade.label }</span>
					</div>
				</div>
			</div>
			<div className="bx-tiles">
				{ Object.keys( labels ).map( ( key ) => (
					<StatTile
						key={ key }
						label={ labels[ key ] }
						score={ scores?.categories?.[ key ]?.score ?? null }
						counts={ scores?.categories?.[ key ] }
					/>
				) ) }
			</div>
		</div>
	);
}
