import { useEffect } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Button, Notice, Spinner } from '@wordpress/components';
import {
	Page,
	SettingsForm,
	useHashTab,
	useSettings,
} from '@bixisoft/admin-ui';

/**
 * Generic settings screen: renders the schema groups listed in screen.groups, as hash tabs
 * when there is more than one, with a sticky save bar.
 *
 * @param {Object} props
 * @param {Object} props.screen Registered screen: id, title, url, groups[].
 * @param {Object} props.config window.rankaviaAdmin with defaults.
 */
export function SettingsScreen( { screen, config } ) {
	const {
		schema,
		values,
		saved,
		dirty,
		loading,
		saving,
		errors,
		loadError,
		setValue,
		save,
		discard,
	} = useSettings( {
		restNamespace: config.restNamespace,
	} );

	const wanted = screen.groups ?? [];
	const groups = wanted
		.map( ( id ) => schema.groups.find( ( group ) => group.id === id ) )
		.filter( Boolean );
	const [ tab, setTab ] = useHashTab( wanted[ 0 ] );
	const active = groups.find( ( group ) => group.id === tab ) ?? groups[ 0 ];

	const errorCount = ( group ) =>
		group.fields.filter( ( field ) => errors[ field.key ] ).length;

	// Warn before leaving with unsaved changes.
	useEffect( () => {
		if ( ! dirty ) {
			return undefined;
		}
		const onBeforeUnload = ( event ) => {
			event.preventDefault();
			event.returnValue = '';
		};
		window.addEventListener( 'beforeunload', onBeforeUnload );
		return () =>
			window.removeEventListener( 'beforeunload', onBeforeUnload );
	}, [ dirty ] );

	const onSave = async () => {
		const result = await save();
		if ( ! result.ok ) {
			// Jump to the first tab that holds an error so it is not hidden.
			const failing = groups.find( ( group ) =>
				group.fields.some( ( field ) => result.errors[ field.key ] )
			);
			if ( failing ) {
				setTab( failing.id );
			}
		}
	};

	if ( loading ) {
		return (
			<Page title={ screen.title }>
				<Spinner />
			</Page>
		);
	}

	if ( loadError ) {
		return (
			<Page title={ screen.title }>
				<Notice status="error" isDismissible={ false }>
					{ loadError }
				</Notice>
			</Page>
		);
	}

	return (
		<Page title={ screen.title }>
			{ groups.length > 1 && (
				<nav
					className="bx-tabs"
					aria-label={ __( 'Settings sections', 'rankavia' ) }
				>
					<ul>
						{ groups.map( ( group ) => {
							const count = errorCount( group );
							const current = group.id === active?.id;
							return (
								<li key={ group.id }>
									<a
										href={ `#${ group.id }` }
										className={
											current ? 'is-current' : undefined
										}
										aria-current={
											current ? 'true' : undefined
										}
										onClick={ ( event ) => {
											event.preventDefault();
											setTab( group.id );
										} }
									>
										{ group.label }
										{ count > 0 && (
											<span className="bx-tabs__errors">
												{ sprintf(
													/* translators: %d: number of fields with errors. */
													_n(
														'%d error',
														'%d errors',
														count,
														'rankavia'
													),
													count
												) }
											</span>
										) }
									</a>
								</li>
							);
						} ) }
					</ul>
				</nav>
			) }

			{ active && (
				<SettingsForm
					group={ active }
					values={ values }
					errors={ errors }
					onChange={ setValue }
					savedKey={ JSON.stringify( saved ) }
				/>
			) }

			{ ! active && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'There are no settings on this screen.',
						'rankavia'
					) }
				</Notice>
			) }

			<div
				className="bx-savebar"
				role="region"
				aria-label={ __( 'Save settings', 'rankavia' ) }
			>
				<span
					className={ `bx-savebar__status${
						dirty ? ' is-dirty' : ''
					}` }
					role="status"
				>
					{ dirty ? __( 'Unsaved changes', 'rankavia' ) : '' }
				</span>
				<Button
					variant="link"
					onClick={ discard }
					disabled={ ! dirty || saving }
				>
					{ __( 'Discard', 'rankavia' ) }
				</Button>
				<Button
					variant="primary"
					onClick={ onSave }
					isBusy={ saving }
					disabled={ ! dirty || saving }
				>
					{ saving
						? __( 'Saving…', 'rankavia' )
						: __( 'Save', 'rankavia' ) }
				</Button>
			</div>
		</Page>
	);
}
