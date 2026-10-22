import { useCallback, useEffect, useState } from '@wordpress/element';

const readHash = () =>
	decodeURIComponent( window.location.hash.replace( /^#/, '' ) );

/**
 * Tab id kept in sync with location.hash. Returns [ tab, setTab ].
 *
 * @param {string} defaultId Tab used when the hash is empty.
 */
export function useHashTab( defaultId ) {
	const [ tab, setTabState ] = useState( () => readHash() || defaultId );

	useEffect( () => {
		const onHashChange = () => setTabState( readHash() || defaultId );
		window.addEventListener( 'hashchange', onHashChange );
		return () => window.removeEventListener( 'hashchange', onHashChange );
	}, [ defaultId ] );

	const setTab = useCallback( ( id ) => {
		setTabState( id );
		// replaceState avoids a scroll jump and a history entry per tab click.
		window.history.replaceState(
			null,
			'',
			`#${ encodeURIComponent( id ) }`
		);
	}, [] );

	return [ tab || defaultId, setTab ];
}
