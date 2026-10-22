import { useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Notice } from '@wordpress/components';
import { Card, Page, useSettings } from '@bixisoft/admin-ui';

const today = () => new Date().toISOString().slice( 0, 10 );

/**
 * Export, import and reset of all settings.
 *
 * @param {Object} props
 * @param {Object} props.screen Registered screen: id, title, url, groups[].
 * @param {Object} props.config window.rankaviaAdmin with defaults.
 */
export function Tools( { screen, config } ) {
	const { saving, exportSettings, importSettings, reset } = useSettings( {
		restNamespace: config.restNamespace,
	} );
	const fileInput = useRef( null );
	const [ file, setFile ] = useState( null );
	const [ problem, setProblem ] = useState( null );

	const onExport = async () => {
		const payload = await exportSettings();
		if ( ! payload ) {
			return;
		}
		const blob = new Blob( [ JSON.stringify( payload, null, 2 ) ], {
			type: 'application/json',
		} );
		const url = URL.createObjectURL( blob );
		const link = document.createElement( 'a' );
		link.href = url;
		link.download = `rankavia-settings-${ today() }.json`;
		document.body.appendChild( link );
		link.click();
		link.remove();
		URL.revokeObjectURL( url );
	};

	const onImport = async () => {
		setProblem( null );
		if ( ! file ) {
			return;
		}
		let payload;
		try {
			payload = JSON.parse( await file.text() );
		} catch ( e ) {
			setProblem( {
				message: __(
					'The selected file is not valid JSON.',
					'rankavia'
				),
				list: [],
			} );
			return;
		}
		if (
			! payload ||
			typeof payload !== 'object' ||
			Array.isArray( payload )
		) {
			setProblem( {
				message: __(
					'The selected file does not contain Rankavia settings.',
					'rankavia'
				),
				list: [],
			} );
			return;
		}
		// eslint-disable-next-line no-alert
		const confirmed = window.confirm(
			__(
				'Importing replaces your current settings with the values in this file. Continue?',
				'rankavia'
			)
		);
		if ( ! confirmed ) {
			return;
		}
		const result = await importSettings( payload );
		if ( result.ok ) {
			setFile( null );
			if ( fileInput.current ) {
				fileInput.current.value = '';
			}
			return;
		}
		const list = Object.entries( result.errors ).map(
			( [ key, message ] ) => `${ key }: ${ message }`
		);
		if ( list.length ) {
			setProblem( {
				message: __(
					'The file was not imported because some values are not valid.',
					'rankavia'
				),
				list,
			} );
		}
	};

	return (
		<Page title={ screen.title }>
			<Card
				title={ __( 'Export settings', 'rankavia' ) }
				description={ __(
					'Download all Rankavia settings as a JSON file to back them up or move them to another site.',
					'rankavia'
				) }
			>
				<Button variant="secondary" onClick={ onExport }>
					{ __( 'Export settings', 'rankavia' ) }
				</Button>
			</Card>

			<Card
				title={ __( 'Import settings', 'rankavia' ) }
				description={ __(
					'Restore settings from a JSON file exported by Rankavia. Settings not in the file are left as they are.',
					'rankavia'
				) }
			>
				<div className="bx-import">
					<label htmlFor="bx-import-file" className="bx-field__label">
						{ __( 'Settings file', 'rankavia' ) }
					</label>
					<input
						ref={ fileInput }
						id="bx-import-file"
						type="file"
						accept="application/json,.json"
						onChange={ ( event ) => {
							setProblem( null );
							setFile( event.target.files?.[ 0 ] ?? null );
						} }
					/>
					{ problem && (
						<Notice status="error" isDismissible={ false }>
							{ problem.message }
							{ problem.list.length > 0 && (
								<ul className="bx-import__errors">
									{ problem.list.map( ( line ) => (
										<li key={ line }>{ line }</li>
									) ) }
								</ul>
							) }
						</Notice>
					) }
					<div>
						<Button
							variant="secondary"
							onClick={ onImport }
							disabled={ ! file || saving }
							isBusy={ saving }
						>
							{ __( 'Import settings', 'rankavia' ) }
						</Button>
					</div>
				</div>
			</Card>

			<Card
				title={ __( 'Reset all settings', 'rankavia' ) }
				description={ __(
					'Restore every Rankavia setting to its default value. This cannot be undone.',
					'rankavia'
				) }
			>
				<Button
					variant="secondary"
					isDestructive
					onClick={ () => reset() }
					disabled={ saving }
				>
					{ __( 'Reset all settings', 'rankavia' ) }
				</Button>
			</Card>
		</Page>
	);
}
