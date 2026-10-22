import { useCallback, useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import { Button, Notice, Spinner } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { Card } from './card';

const NOTE_STATUSES = [ 'info', 'warning', 'error' ];

/**
 * Live view of a generated file (robots.txt, llms.txt, sitemap) as the site serves it now.
 * Refetches when savedKey changes, so it reflects saved settings only.
 *
 * @param {Object} props
 * @param {string} props.name          File name: robots, llms or sitemap.
 * @param {string} props.restNamespace REST namespace, for example "rankavia/v1".
 * @param {string} props.savedKey      Changes whenever the saved settings change.
 */
export function FilePreview( {
	name,
	restNamespace = 'rankavia/v1',
	savedKey,
} ) {
	const { createSuccessNotice } = useDispatch( noticesStore );
	const [ file, setFile ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		let current = true;
		setLoading( true );
		setError( null );
		apiFetch( { path: `/${ restNamespace }/files/${ name }` } )
			.then( ( data ) => {
				if ( current ) {
					setFile( data );
					setLoading( false );
				}
			} )
			.catch( ( err ) => {
				if ( current ) {
					setError(
						err?.message ||
							__( 'The preview could not be loaded.', 'rankavia' )
					);
					setLoading( false );
				}
			} );
		return () => {
			current = false;
		};
	}, [ name, restNamespace, savedKey ] );

	const content = typeof file?.content === 'string' ? file.content : '';
	const notes = Array.isArray( file?.notes ) ? file.notes : [];

	const copy = useCallback( async () => {
		try {
			await window.navigator.clipboard.writeText( content );
			createSuccessNotice( __( 'Copied to the clipboard.', 'rankavia' ), {
				type: 'snackbar',
			} );
		} catch ( e ) {
			setError( __( 'The content could not be copied.', 'rankavia' ) );
		}
	}, [ content, createSuccessNotice ] );

	return (
		<Card title={ __( 'Preview', 'rankavia' ) }>
			<div className="bx-file-preview">
				{ loading && <Spinner /> }

				{ error && (
					<Notice status="error" isDismissible={ false }>
						{ error }
					</Notice>
				) }

				{ ! loading &&
					file &&
					notes.map( ( note, index ) => (
						<Notice
							key={ index }
							status={
								NOTE_STATUSES.includes( note.status )
									? note.status
									: 'info'
							}
							isDismissible={ false }
						>
							{ note.message }
						</Notice>
					) ) }

				{ ! loading && file && (
					<p className="bx-file-preview__meta">
						{ file.url && (
							<a
								href={ file.url }
								target="_blank"
								rel="noopener noreferrer"
							>
								{ sprintf(
									/* translators: %s: file URL. */
									__( 'Open %s', 'rankavia' ),
									file.url
								) }
								<span className="screen-reader-text">
									{ ' ' +
										__(
											'(opens in a new tab)',
											'rankavia'
										) }
								</span>
								<span aria-hidden="true">{ ' ↗' }</span>
							</a>
						) }
						<span
							className={ `bx-file-preview__status${
								file.active ? ' is-active' : ''
							}` }
						>
							<span aria-hidden="true">
								{ file.active ? '✓ ' : '✕ ' }
							</span>
							{ file.active
								? __( 'Active', 'rankavia' )
								: __( 'Not active', 'rankavia' ) }
						</span>
					</p>
				) }

				{ ! loading && file && content !== '' && (
					<>
						<pre className="bx-file-preview__content">
							{ content }
						</pre>
						<Button variant="secondary" onClick={ copy }>
							{ __( 'Copy', 'rankavia' ) }
						</Button>
					</>
				) }

				<p className="bx-field__help">
					{ __( 'The preview reflects saved settings.', 'rankavia' ) }
				</p>
			</div>
		</Card>
	);
}
