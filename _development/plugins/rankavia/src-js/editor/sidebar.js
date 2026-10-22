import { useMemo } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import {
	Button,
	PanelBody,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { MediaUpload, MediaUploadCheck } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import {
	ScoreRing,
	SerpPreview,
	TemplateField,
	gradeFor,
} from '@bixisoft/admin-ui';
import { analyse } from './analysis';
import { getEditorConfig, renderSerp, withPostSamples } from './serp';
import { usePostSeo } from './use-post-seo';
import { usePostContent, usePostLink, usePostValues } from './use-post-values';

/**
 * Everything the panels share: the typed meta, the variable values, the rendered texts and
 * the analysis. Computed once by the plugin root and passed down.
 *
 * @return {Object} The model.
 */
export function useSeoModel() {
	const config = getEditorConfig();
	const { values: seo, set } = usePostSeo();
	const content = usePostContent( config.homeUrl );
	const values = usePostValues( content.text );
	const { slug, permalink } = usePostLink();

	const variables = useMemo(
		() => withPostSamples( config.templateVariables, values ),
		[ config.templateVariables, values ]
	);
	const rendered = useMemo(
		() => renderSerp( { seo, config, values } ),
		// eslint-disable-next-line react-hooks/exhaustive-deps
		[ seo.title, seo.description, seo.ogTitle, seo.ogDescription, values ]
	);
	const analysis = useMemo(
		() =>
			analyse( {
				keyword: seo.focusKeyword,
				title: rendered.title,
				description: rendered.description,
				slug,
				contentText: content.text,
				headings: content.headings,
				images: content.images,
				links: content.links,
				thresholds: config.scoreThresholds,
			} ),
		[ seo.focusKeyword, rendered, slug, content, config.scoreThresholds ]
	);

	return {
		config,
		seo,
		set,
		values,
		variables,
		rendered,
		analysis,
		slug,
		url: permalink || config.homeUrl,
	};
}

const STATUS = () => ( {
	pass: { icon: '✓', label: __( 'Pass', 'rankavia' ) },
	warn: { icon: '!', label: __( 'Warning', 'rankavia' ) },
	fail: { icon: '✕', label: __( 'Fail', 'rankavia' ) },
	info: { icon: 'i', label: __( 'Info', 'rankavia' ) },
} );

const ORDER = { fail: 0, warn: 1, info: 2, pass: 3 };

/**
 * A template override: the field, plus either the template that applies when it is empty or
 * a "Use template" button that empties it again.
 *
 * @param {Object}   props
 * @param {string}   props.id             Element id.
 * @param {string}   props.label          Visible label.
 * @param {string}   props.kind           'title' or 'description'.
 * @param {string}   props.value          Override text, empty when the template applies.
 * @param {Function} props.onChange       Called with the new text.
 * @param {string}   [props.template]     The template that applies when the value is empty.
 * @param {Object}   props.model          Result of useSeoModel().
 * @param {string}   [props.fallbackNote] Note shown when there is no template to show.
 */
function OverrideField( {
	id,
	label,
	kind,
	value,
	onChange,
	template,
	model,
	fallbackNote,
} ) {
	return (
		<div className="rk-editor__field">
			<TemplateField
				id={ id }
				label={ label }
				value={ value }
				onChange={ onChange }
				variables={ model.variables }
				separator={ model.config.separator }
				kind={ kind }
			/>
			{ value ? (
				<Button
					variant="link"
					className="rk-editor__reset"
					onClick={ () => onChange( '' ) }
				>
					{ __( 'Use template', 'rankavia' ) }
				</Button>
			) : (
				<p className="rk-editor__note">
					{ template ? (
						<>
							{ __( 'Using the template:', 'rankavia' ) }{ ' ' }
							<code>{ template }</code>
						</>
					) : (
						fallbackNote
					) }
				</p>
			) }
		</div>
	);
}

/**
 * Score ring with the grade word, so the score is never colour alone.
 *
 * @param {Object} props
 * @param {number} props.score  Score 0-100.
 * @param {number} [props.size] Ring diameter in px.
 */
function Score( { score, size = 64 } ) {
	const grade = gradeFor( score );
	return (
		<div className="rk-editor__score">
			<ScoreRing
				score={ score }
				size={ size }
				stroke={ 6 }
				label={ __( 'SEO score', 'rankavia' ) }
			/>
			<span className="rk-editor__grade">{ grade.label }</span>
		</div>
	);
}

function Preview( { model } ) {
	return (
		<SerpPreview
			title={ model.rendered.title }
			description={ model.rendered.description }
			url={ model.url }
			siteName={ model.config.siteName }
		/>
	);
}

function CheckList( { checks } ) {
	const status = STATUS();
	const rows = [ ...checks ].sort(
		( a, b ) => ORDER[ a.status ] - ORDER[ b.status ]
	);
	return (
		<ul className="rk-editor__checks">
			{ rows.map( ( row ) => (
				<li
					key={ row.id }
					className={ `rk-editor__check rk-editor__check--${ row.status }` }
				>
					<span className="rk-editor__status">
						<span
							className={ `bx-status bx-status--${ row.status }` }
							aria-hidden="true"
						>
							{ status[ row.status ].icon }
						</span>
						<span className="rk-editor__word">
							{ status[ row.status ].label }
						</span>
					</span>
					<span className="rk-editor__what">
						<strong>{ row.title }</strong>
						<span className="rk-editor__detail">
							{ row.detail }
						</span>
					</span>
				</li>
			) ) }
		</ul>
	);
}

function mediaUrlOf( media ) {
	return (
		media?.media_details?.sizes?.medium?.source_url ||
		media?.source_url ||
		''
	);
}

function SocialImage( { model } ) {
	const { seo, set } = model;
	const featuredId = useSelect(
		( select ) =>
			select( 'core/editor' ).getEditedPostAttribute(
				'featured_media'
			) || 0,
		[]
	);
	const shownId = seo.ogImage || featuredId;
	const media = useSelect(
		( select ) => ( shownId ? select( 'core' ).getMedia( shownId ) : null ),
		[ shownId ]
	);
	const url = mediaUrlOf( media );

	let preview = (
		<p className="rk-editor__note">
			{ __( 'No image selected', 'rankavia' ) }
		</p>
	);
	if ( shownId ) {
		preview = (
			<>
				{ url ? (
					<img className="rk-editor__image" src={ url } alt="" />
				) : null }
				{ ! seo.ogImage && (
					<p className="rk-editor__note">
						{ __( 'Using the featured image', 'rankavia' ) }
					</p>
				) }
			</>
		);
	}

	return (
		<div className="rk-editor__field">
			<span className="bx-field__label" id="rk-og-image-label">
				{ __( 'Social image', 'rankavia' ) }
			</span>
			{ preview }
			<MediaUploadCheck>
				<div className="rk-editor__buttons">
					<MediaUpload
						allowedTypes={ [ 'image' ] }
						value={ seo.ogImage || undefined }
						onSelect={ ( picked ) => set( 'ogImage', picked?.id ) }
						render={ ( { open } ) => (
							<Button
								variant="secondary"
								onClick={ open }
								aria-describedby="rk-og-image-label"
							>
								{ seo.ogImage
									? __( 'Change image', 'rankavia' )
									: __( 'Select image', 'rankavia' ) }
							</Button>
						) }
					/>
					{ seo.ogImage > 0 && (
						<Button
							variant="tertiary"
							isDestructive
							onClick={ () => set( 'ogImage', 0 ) }
						>
							{ __( 'Remove image', 'rankavia' ) }
						</Button>
					) }
				</div>
			</MediaUploadCheck>
		</div>
	);
}

/**
 * The four panels of the Rankavia sidebar.
 *
 * @param {Object} props
 * @param {Object} props.model Result of useSeoModel().
 */
export function SidebarPanels( { model } ) {
	const { config, seo, set, analysis } = model;
	return (
		<div className="bx-app rk-editor">
			<PanelBody title={ __( 'Search preview', 'rankavia' ) }>
				<Preview model={ model } />
				<OverrideField
					id="rk-seo-title"
					label={ __( 'SEO title', 'rankavia' ) }
					kind="title"
					value={ seo.title }
					onChange={ ( next ) => set( 'title', next ) }
					template={ config.templates.title }
					model={ model }
				/>
				<OverrideField
					id="rk-seo-description"
					label={ __( 'Meta description', 'rankavia' ) }
					kind="description"
					value={ seo.description }
					onChange={ ( next ) => set( 'description', next ) }
					template={ config.templates.description }
					model={ model }
				/>
			</PanelBody>

			<PanelBody
				title={ __( 'Focus keyword', 'rankavia' ) }
				initialOpen={ false }
			>
				<TextControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __( 'Focus keyword', 'rankavia' ) }
					help={ __(
						'The word or phrase this page should be found for.',
						'rankavia'
					) }
					value={ seo.focusKeyword }
					onChange={ ( next ) => set( 'focusKeyword', next ) }
				/>
				{ analysis.score !== null && (
					<Score score={ analysis.score } />
				) }
				<div aria-live="polite">
					<CheckList checks={ analysis.checks } />
				</div>
			</PanelBody>

			<PanelBody
				title={ __( 'Advanced', 'rankavia' ) }
				initialOpen={ false }
			>
				<TextControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					type="url"
					label={ __( 'Canonical URL', 'rankavia' ) }
					help={ __(
						'Leave empty to use the page address.',
						'rankavia'
					) }
					placeholder={ model.url }
					value={ seo.canonical }
					onChange={ ( next ) => set( 'canonical', next ) }
				/>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __(
						'Hide from search engines (noindex)',
						'rankavia'
					) }
					checked={ seo.noindex }
					onChange={ ( next ) => set( 'noindex', next ) }
				/>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Do not follow links (nofollow)', 'rankavia' ) }
					checked={ seo.nofollow }
					onChange={ ( next ) => set( 'nofollow', next ) }
				/>
			</PanelBody>

			<PanelBody
				title={ __( 'Social', 'rankavia' ) }
				initialOpen={ false }
			>
				<OverrideField
					id="rk-og-title"
					label={ __( 'Social title', 'rankavia' ) }
					kind="title"
					value={ seo.ogTitle }
					onChange={ ( next ) => set( 'ogTitle', next ) }
					model={ model }
					fallbackNote={ __( 'Using the SEO title.', 'rankavia' ) }
				/>
				<OverrideField
					id="rk-og-description"
					label={ __( 'Social description', 'rankavia' ) }
					kind="description"
					value={ seo.ogDescription }
					onChange={ ( next ) => set( 'ogDescription', next ) }
					model={ model }
					fallbackNote={ __(
						'Using the meta description.',
						'rankavia'
					) }
				/>
				<SocialImage model={ model } />
			</PanelBody>
		</div>
	);
}

/**
 * Body of the "Rankavia" panel in the document sidebar: preview, score, and a way to open
 * the full sidebar.
 *
 * @param {Object} props
 * @param {Object} props.model Result of useSeoModel().
 */
export function SummaryPanel( { model } ) {
	const interfaceStore = useDispatch( 'core/interface' );
	const open = () =>
		interfaceStore?.enableComplementaryArea?.(
			'core',
			'rankavia/rankavia'
		);
	const { analysis } = model;

	return (
		<div className="bx-app rk-editor">
			<Preview model={ model } />
			{ analysis.score !== null ? (
				<Score score={ analysis.score } size={ 48 } />
			) : (
				<p className="rk-editor__note">
					{ __(
						'Add a focus keyword to see the analysis',
						'rankavia'
					) }
				</p>
			) }
			<Button variant="secondary" onClick={ open }>
				{ __( 'Open Rankavia', 'rankavia' ) }
			</Button>
		</div>
	);
}
