import { useMemo } from '@wordpress/element';

const DEFAULTS = {
	version: '',
	homeUrl: '',
	adminUrl: '',
	restNamespace: 'rankavia/v1',
	siteName: '',
	screens: [],
	currentScreen: 'dashboard',
};

/**
 * The inline config printed by PHP (window.rankaviaAdmin) merged over safe defaults.
 */
export function getRankaviaConfig() {
	const raw = typeof window !== 'undefined' ? window.rankaviaAdmin : null;
	const config = { ...DEFAULTS, ...( raw ?? {} ) };
	if ( ! Array.isArray( config.screens ) ) {
		config.screens = [];
	}
	return config;
}

/**
 * Hook form of getRankaviaConfig(); the config never changes during a page view.
 */
export function useRankaviaConfig() {
	return useMemo( () => getRankaviaConfig(), [] );
}
