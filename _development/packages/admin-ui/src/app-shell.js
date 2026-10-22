import { useDispatch, useSelect } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import { SnackbarList } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Header with brand and version, a nav of screens, the content area and a snackbar list
 * fed by the core/notices store.
 *
 * @param {Object} props
 * @param {string} props.brand         Brand name.
 * @param {string} props.version       Plugin version.
 * @param {Array}  props.screens       Screens: id, title, url.
 * @param {string} props.currentScreen Id of the current screen.
 * @param {*}      props.children      Screen content.
 */
export function AppShell( {
	brand,
	version,
	screens = [],
	currentScreen,
	children,
} ) {
	const notices = useSelect(
		( select ) =>
			select( noticesStore )
				.getNotices()
				.filter( ( notice ) => notice.type === 'snackbar' ),
		[]
	);
	const { removeNotice } = useDispatch( noticesStore );

	return (
		<div className="bx-app">
			<header className="bx-shell__header">
				<div className="bx-shell__brand">
					<span className="bx-shell__mark" aria-hidden="true">
						{ ( brand || '' ).charAt( 0 ) }
					</span>
					<span className="bx-shell__name">{ brand }</span>
					{ version && (
						<span className="bx-shell__version">
							{ sprintf(
								/* translators: %s: plugin version. */
								__( 'Version %s', 'rankavia' ),
								version
							) }
						</span>
					) }
				</div>
				{ screens.length > 0 && (
					<nav
						className="bx-shell__nav"
						aria-label={ __( 'Rankavia sections', 'rankavia' ) }
					>
						<ul>
							{ screens.map( ( screen ) => (
								<li key={ screen.id }>
									<a
										href={ screen.url }
										className={
											screen.id === currentScreen
												? 'is-current'
												: undefined
										}
										aria-current={
											screen.id === currentScreen
												? 'page'
												: undefined
										}
									>
										{ screen.title }
									</a>
								</li>
							) ) }
						</ul>
					</nav>
				) }
			</header>
			<main className="bx-shell__content">{ children }</main>
			<div className="bx-snackbars">
				<SnackbarList notices={ notices } onRemove={ removeNotice } />
			</div>
		</div>
	);
}
