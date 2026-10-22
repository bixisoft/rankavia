import { useCallback, useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Notice, Spinner } from '@wordpress/components';
import { Page, ResultTable, ScoreGrid } from '@bixisoft/admin-ui';

export function Dashboard( { config } ) {
	const [ report, setReport ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ loading, setLoading ] = useState( true );

	const load = useCallback(
		( refresh = false ) => {
			setLoading( true );
			setError( null );
			apiFetch( {
				path: `/${ config.restNamespace }/score${
					refresh ? '?refresh=1' : ''
				}`,
			} )
				.then( ( data ) => {
					setReport( data );
					setLoading( false );
				} )
				.catch( ( err ) => {
					setError(
						err?.message || __( 'The request failed.', 'rankavia' )
					);
					setLoading( false );
				} );
		},
		[ config.restNamespace ]
	);

	useEffect( () => {
		load();
	}, [ load ] );

	return (
		<Page
			title={ __( 'Visibility Score', 'rankavia' ) }
			actions={
				<Button
					variant="secondary"
					onClick={ () => load( true ) }
					disabled={ loading }
				>
					{ __( 'Rescan', 'rankavia' ) }
				</Button>
			}
		>
			<p className="bx-toolbar__meta">
				{ report
					? sprintf(
							/* translators: 1: URL, 2: date/time. */
							__( 'Scanned %1$s at %2$s', 'rankavia' ),
							report.url,
							new Date( report.generatedAt ).toLocaleString()
					  )
					: __( 'Scanning your home page…', 'rankavia' ) }
			</p>

			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			{ loading && ! report && <Spinner /> }

			{ report && (
				<>
					<ScoreGrid scores={ report.scores } />
					<h3>{ __( 'Checks', 'rankavia' ) }</h3>
					<ResultTable results={ report.results } />
				</>
			) }
		</Page>
	);
}
