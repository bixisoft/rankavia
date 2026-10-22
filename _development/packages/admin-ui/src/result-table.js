import { __ } from '@wordpress/i18n';

const STATUS = () => ( {
	pass: { icon: '✓', label: __( 'Pass', 'rankavia' ) },
	warn: { icon: '!', label: __( 'Warning', 'rankavia' ) },
	fail: { icon: '✕', label: __( 'Fail', 'rankavia' ) },
	info: { icon: 'i', label: __( 'Info', 'rankavia' ) },
} );

const ORDER = { fail: 0, warn: 1, info: 2, pass: 3 };

/**
 * The table view of every check: status (icon + word), check, what was found. Failures first.
 *
 * @param {Object} props
 * @param {Array}  props.results Check results.
 */
export function ResultTable( { results } ) {
	const status = STATUS();
	const rows = [ ...( results ?? [] ) ].sort(
		( a, b ) => ORDER[ a.status ] - ORDER[ b.status ]
	);

	return (
		<table className="bx-results widefat striped">
			<thead>
				<tr>
					<th scope="col">{ __( 'Status', 'rankavia' ) }</th>
					<th scope="col">{ __( 'Check', 'rankavia' ) }</th>
					<th scope="col">{ __( 'Result', 'rankavia' ) }</th>
				</tr>
			</thead>
			<tbody>
				{ rows.map( ( row ) => (
					<tr
						key={ row.id }
						className={ `bx-results__row bx-results__row--${ row.status }` }
					>
						<td className="bx-results__status">
							<span
								className={ `bx-status bx-status--${ row.status }` }
								aria-hidden="true"
							>
								{ status[ row.status ]?.icon }
							</span>
							<span className="bx-status__word">
								{ status[ row.status ]?.label }
							</span>
						</td>
						<td>
							<strong>{ row.title }</strong>
							<div className="bx-results__category">
								{ row.category?.toUpperCase() } ·{ ' ' }
								{ row.severity }
							</div>
						</td>
						<td>
							{ row.message }
							{ row.status !== 'pass' && row.description && (
								<div className="bx-results__hint">
									{ row.description }
								</div>
							) }
						</td>
					</tr>
				) ) }
			</tbody>
		</table>
	);
}
