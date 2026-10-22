import { createRoot } from '@wordpress/element';
import { getRankaviaConfig } from '@bixisoft/admin-ui';
import { App } from './app';
import './style.css';

const root = document.getElementById( 'rankavia-app' );
if ( root ) {
	const config = getRankaviaConfig();
	createRoot( root ).render(
		<App screenId={ config.currentScreen || 'dashboard' } />
	);
}
