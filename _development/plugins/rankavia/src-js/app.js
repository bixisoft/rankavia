import { __ } from '@wordpress/i18n';
import { AppShell, useRankaviaConfig } from '@bixisoft/admin-ui';
import { Dashboard } from './screens/dashboard';
import { SettingsScreen } from './screens/settings-screen';
import { Tools } from './screens/tools';

/**
 * Shell plus the screen picked by PHP. Screens without a custom component but with schema groups
 * render through the generic settings screen, so modules only register a screen from PHP.
 *
 * @param {Object} props
 * @param {string} props.screenId Id of the screen to render.
 */
export function App( { screenId } ) {
	const config = useRankaviaConfig();
	const screen = config.screens.find( ( item ) => item.id === screenId ) ?? {
		id: screenId,
		title: __( 'Rankavia', 'rankavia' ),
		url: '',
		groups: [],
	};

	let content;
	if ( screenId === 'tools' ) {
		content = <Tools screen={ screen } config={ config } />;
	} else if ( screen.groups?.length > 0 ) {
		content = (
			<SettingsScreen
				key={ screen.id }
				screen={ screen }
				config={ config }
			/>
		);
	} else {
		content = <Dashboard config={ config } />;
	}

	return (
		<AppShell
			brand={ __( 'Rankavia', 'rankavia' ) }
			version={ config.version }
			screens={ config.screens }
			currentScreen={ screenId }
		>
			{ content }
		</AppShell>
	);
}
