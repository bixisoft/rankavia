import { __ } from '@wordpress/i18n';

const hostOf = ( url ) => {
	try {
		return new URL( url ).host;
	} catch ( e ) {
		return url || '';
	}
};

/**
 * A search result look-alike. Presentational only.
 *
 * @param {Object} props
 * @param {string} props.title       Rendered title.
 * @param {string} props.description Rendered description.
 * @param {string} props.url         Page URL (the home URL for previews).
 * @param {string} props.siteName    Site name.
 */
export function SerpPreview( { title, description, url, siteName } ) {
	const initial = ( siteName || hostOf( url ) || '?' )
		.trim()
		.charAt( 0 )
		.toUpperCase();
	return (
		<div
			className="bx-serp"
			role="group"
			aria-label={ __( 'Search result preview', 'rankavia' ) }
		>
			<div className="bx-serp__site">
				<span className="bx-serp__favicon" aria-hidden="true">
					{ initial }
				</span>
				<span className="bx-serp__source">
					<span className="bx-serp__name">{ siteName }</span>
					<span className="bx-serp__url">{ url }</span>
				</span>
			</div>
			<div className="bx-serp__title">
				{ title || __( '(no title)', 'rankavia' ) }
			</div>
			<div className="bx-serp__description">{ description }</div>
		</div>
	);
}
