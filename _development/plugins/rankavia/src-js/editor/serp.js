import { renderTemplate } from '@bixisoft/admin-ui';
import { DEFAULT_THRESHOLDS } from './analysis';

const DEFAULTS = {
	version: '',
	siteName: '',
	homeUrl: '',
	separator: '–',
	postType: 'post',
	templates: {
		title: '%title% %sep% %sitename%',
		description: '%excerpt%',
	},
	templateVariables: [],
	scoreThresholds: DEFAULT_THRESHOLDS,
};

/**
 * The inline config printed by PHP (window.rankaviaEditor) merged over safe defaults.
 *
 * @return {Object} Editor config.
 */
export function getEditorConfig() {
	const raw = typeof window !== 'undefined' ? window.rankaviaEditor : null;
	const config = { ...DEFAULTS, ...( raw ?? {} ) };
	config.templates = { ...DEFAULTS.templates, ...( config.templates ?? {} ) };
	config.scoreThresholds = {
		...DEFAULTS.scoreThresholds,
		...( config.scoreThresholds ?? {} ),
	};
	if ( ! Array.isArray( config.templateVariables ) ) {
		config.templateVariables = [];
	}
	return config;
}

/**
 * The variable table with the post's own values as the samples, so the template field's
 * live rendering previews this post rather than the generic examples.
 *
 * @param {Array}  variables [{ name, label, sample }].
 * @param {Object} values    name => value for the current post.
 * @return {Array} Same shape, samples replaced.
 */
export const withPostSamples = ( variables, values ) =>
	variables.map( ( variable ) => ( {
		...variable,
		sample: String( values?.[ variable.name ] ?? '' ),
	} ) );

/**
 * Renders the title, description and social texts for the post: the per-post override when
 * set, else the post type's template, both through the shared template renderer.
 *
 * @param {Object} input
 * @param {Object} input.seo    Typed meta values (usePostSeo().values).
 * @param {Object} input.config Editor config.
 * @param {Object} input.values Variable values for the post (usePostValues()).
 * @return {{title: string, description: string, socialTitle: string, socialDescription: string}} Rendered texts.
 */
export function renderSerp( { seo, config, values } ) {
	const render = ( template ) =>
		renderTemplate( template, values, config.separator );
	const title = render( seo.title || config.templates.title );
	const description = render(
		seo.description || config.templates.description
	);
	return {
		title,
		description,
		socialTitle: seo.ogTitle ? render( seo.ogTitle ) : title,
		socialDescription: seo.ogDescription
			? render( seo.ogDescription )
			: description,
	};
}
