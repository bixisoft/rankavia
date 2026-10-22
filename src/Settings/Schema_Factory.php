<?php
/**
 * Rankavia's settings schema.
 *
 * @package Rankavia
 */

declare( strict_types=1 );

namespace Bixisoft\Rankavia\Settings;

use Bixisoft\Rankavia\Vendor\Core\Settings\Schema;
use Bixisoft\Rankavia\Crawlers\Bots;
use Bixisoft\Rankavia\Templates\Contexts;

/**
 * Declares every settings group and field. Later features add groups here.
 */
final class Schema_Factory {

	/**
	 * Build the schema. Calls translation functions, so run it on or after init.
	 *
	 * @return Schema
	 */
	public static function create(): Schema {
		$title_keys           = array();
		$title_defaults       = array();
		$description_defaults = array();
		foreach ( Contexts::for_site() as $context ) {
			$title_keys[ $context['key'] ]           = $context['label'];
			$title_defaults[ $context['key'] ]       = $context['title'];
			$description_defaults[ $context['key'] ] = $context['description'];
		}

		$type_choices     = self::choices_of( get_post_types( array( 'public' => true ), 'objects' ), array( 'attachment' ) );
		$taxonomy_choices = self::choices_of( get_taxonomies( array( 'public' => true ), 'objects' ), array( 'post_format' ) );

		$bot_keys = array();
		foreach ( Bots::all() as $bot ) {
			$bot_keys[ $bot['id'] ] = '' === $bot['note']
				? $bot['label']
				: array(
					'label' => $bot['label'],
					'note'  => $bot['note'],
				);
		}

		$signal_choices = array(
			''    => __( 'Not set', 'rankavia' ),
			'yes' => __( 'Yes', 'rankavia' ),
			'no'  => __( 'No', 'rankavia' ),
		);

		return new Schema(
			array(
				'general'           => array(
					'label'       => __( 'General', 'rankavia' ),
					'description' => __( 'How the site is named and described.', 'rankavia' ),
					'fields'      => array(
						'separator'         => array(
							'type'        => 'select',
							'label'       => __( 'Title separator', 'rankavia' ),
							'description' => __( 'Placed between the page title and the site name.', 'rankavia' ),
							'default'     => '–',
							'choices'     => array(
								'–' => '–',
								'|' => '|',
								'·' => '·',
								'•' => '•',
								'»' => '»',
								'~' => '~',
								'/' => '/',
							),
						),
						'site_description'  => array(
							'type'        => 'text',
							'label'       => __( 'Site description', 'rankavia' ),
							'description' => __( 'Used as the meta description of your home page. Leave empty to use the tagline.', 'rankavia' ),
							'default'     => '',
							'max_length'  => 320,
						),
						'site_type'         => array(
							'type'        => 'select',
							'label'       => __( 'Site represents', 'rankavia' ),
							'description' => __( 'Whether the site belongs to an organization or to a person.', 'rankavia' ),
							'default'     => 'organization',
							'choices'     => array(
								'organization' => __( 'An organization', 'rankavia' ),
								'person'       => __( 'A person', 'rankavia' ),
							),
						),
						'organization_name' => array(
							'type'        => 'string',
							'label'       => __( 'Organization name', 'rankavia' ),
							'description' => __( 'The name of the business or organization behind the site.', 'rankavia' ),
							'default'     => '',
							'max_length'  => 120,
						),
						'organization_logo' => array(
							'type'        => 'image',
							'label'       => __( 'Organization logo', 'rankavia' ),
							'description' => __( 'A square logo of at least 112 by 112 pixels.', 'rankavia' ),
							'default'     => 0,
						),
						'person_name'       => array(
							'type'        => 'string',
							'label'       => __( 'Person name', 'rankavia' ),
							'description' => __( 'The name of the person the site represents.', 'rankavia' ),
							'default'     => '',
							'max_length'  => 120,
						),
						'social_profiles'   => array(
							'type'        => 'list',
							'label'       => __( 'Social profiles', 'rankavia' ),
							'description' => __( 'Full URLs of your profiles on other sites, one per entry.', 'rankavia' ),
							'default'     => array(),
							'item_format' => 'url',
							'placeholder' => 'https://',
						),
					),
				),
				'titles'            => array(
					'label'       => __( 'Titles', 'rankavia' ),
					/* translators: %title% and %sitename% are literal template variables, not placeholders. */
					'description' => __( 'Templates for the title and meta description of each kind of page. Use variables such as %title% and %sitename%.', 'rankavia' ),
					'ui'          => array(
						'pair' => array( 'title_templates', 'description_templates' ),
					),
					'fields'      => array(
						'title_templates'       => array(
							'type'        => 'map',
							'label'       => __( 'Title templates', 'rankavia' ),
							'description' => __( 'The title shown in browser tabs and search results.', 'rankavia' ),
							'format'      => 'template',
							'keys'        => $title_keys,
							'default'     => $title_defaults,
						),
						'description_templates' => array(
							'type'        => 'map',
							'label'       => __( 'Description templates', 'rankavia' ),
							'description' => __( 'The meta description shown under the title in search results.', 'rankavia' ),
							'format'      => 'template',
							'keys'        => $title_keys,
							'default'     => $description_defaults,
						),
						'title_append_page'     => array(
							'type'        => 'boolean',
							'label'       => __( 'Add the page number to paginated titles', 'rankavia' ),
							'description' => __( 'Appends "Page 2 of 5" to the title of page 2 and beyond, unless the template already uses it.', 'rankavia' ),
							'default'     => true,
						),
					),
				),
				'search_appearance' => array(
					'label'       => __( 'Indexing', 'rankavia' ),
					'description' => __( 'Which pages search engines should keep out of their results.', 'rankavia' ),
					'fields'      => array(
						'noindex_search'      => array(
							'type'        => 'boolean',
							'label'       => __( 'Hide internal search results', 'rankavia' ),
							'description' => __( 'Adds noindex to your site search result pages.', 'rankavia' ),
							'default'     => true,
						),
						'noindex_author'      => array(
							'type'        => 'boolean',
							'label'       => __( 'Hide author archives', 'rankavia' ),
							'description' => __( 'Adds noindex to author pages, useful on single-author sites where they duplicate the blog.', 'rankavia' ),
							'default'     => false,
						),
						'noindex_date'        => array(
							'type'        => 'boolean',
							'label'       => __( 'Hide date archives', 'rankavia' ),
							'description' => __( 'Adds noindex to year, month and day archives.', 'rankavia' ),
							'default'     => false,
						),
						'noindex_paginated'   => array(
							'type'        => 'boolean',
							'label'       => __( 'Hide paginated pages', 'rankavia' ),
							'description' => __( 'Adds noindex to page 2 and beyond of archives and the blog.', 'rankavia' ),
							'default'     => false,
						),
						'attachment_redirect' => array(
							'type'        => 'boolean',
							'label'       => __( 'Redirect attachment pages', 'rankavia' ),
							'description' => __( 'Sends visitors of media attachment pages straight to the file with a permanent redirect.', 'rankavia' ),
							'default'     => true,
						),
					),
				),
				'sitemaps'          => array(
					'label'       => __( 'Sitemaps', 'rankavia' ),
					'description' => __( 'Shape the XML sitemap that WordPress serves at /wp-sitemap.xml.', 'rankavia' ),
					'ui'          => array(
						'preview' => 'sitemap',
					),
					'fields'      => array(
						'sitemaps_enabled'   => array(
							'type'        => 'boolean',
							'label'       => __( 'XML sitemaps', 'rankavia' ),
							'description' => __( 'Serve a sitemap for search engines. WordPress already turns its sitemaps off when "Discourage search engines from indexing this site" is on in Settings → Reading.', 'rankavia' ),
							'default'     => true,
						),
						'sitemap_post_types' => array(
							'type'        => 'multiselect',
							'label'       => __( 'Content types', 'rankavia' ),
							'description' => __( 'The kinds of content listed in the sitemap. Posts marked noindex are always left out.', 'rankavia' ),
							'default'     => array_keys( $type_choices ),
							'choices'     => $type_choices,
						),
						'sitemap_taxonomies' => array(
							'type'        => 'multiselect',
							'label'       => __( 'Taxonomies', 'rankavia' ),
							'description' => __( 'The category, tag and other archive pages listed in the sitemap.', 'rankavia' ),
							'default'     => array_keys( $taxonomy_choices ),
							'choices'     => $taxonomy_choices,
						),
						'sitemap_users'      => array(
							'type'        => 'boolean',
							'label'       => __( 'Author pages', 'rankavia' ),
							'description' => __( 'List author pages in the sitemap. They are left out whenever author archives are hidden under Indexing.', 'rankavia' ),
							'default'     => true,
						),
					),
				),
				'ai_crawlers'       => array(
					'label'       => __( 'AI crawlers', 'rankavia' ),
					'description' => __( 'Choose which AI crawlers may read your site. These are requests written to robots.txt: well-behaved crawlers follow them, but none are required to.', 'rankavia' ),
					'ui'          => array(
						'presets' => self::presets(),
					),
					'fields'      => array(
						'ai_crawler_policy'       => array(
							'type'        => 'map',
							'label'       => __( 'Crawler rules', 'rankavia' ),
							'description' => __( '"No rule" writes nothing, so the crawler follows your general robots.txt rules. Blocking Bingbot also removes the site from Bing search and Copilot.', 'rankavia' ),
							'default'     => Bots::preset_allow_all(),
							'keys'        => $bot_keys,
							'choices'     => array(
								''                 => __( 'No rule (allowed)', 'rankavia' ),
								Bots::POLICY_ALLOW => __( 'Allow', 'rankavia' ),
								Bots::POLICY_BLOCK => __( 'Block', 'rankavia' ),
							),
						),
						'content_signal_search'   => array(
							'type'        => 'select',
							'label'       => __( 'Content signal: search', 'rankavia' ),
							'description' => __( 'Whether your content may be used to build a search index and show results. A voluntary convention (contentsignals.org); leave it unset to say nothing.', 'rankavia' ),
							'default'     => '',
							'choices'     => $signal_choices,
						),
						'content_signal_ai_input' => array(
							'type'        => 'select',
							'label'       => __( 'Content signal: AI input', 'rankavia' ),
							'description' => __( 'Whether your content may be given to an AI tool as input for an answer, for example by retrieval. Voluntary; crawlers may ignore it.', 'rankavia' ),
							'default'     => '',
							'choices'     => $signal_choices,
						),
						'content_signal_ai_train' => array(
							'type'        => 'select',
							'label'       => __( 'Content signal: AI training', 'rankavia' ),
							'description' => __( 'Whether your content may be used to train or fine-tune AI models. Voluntary; crawlers may ignore it.', 'rankavia' ),
							'default'     => '',
							'choices'     => $signal_choices,
						),
					),
				),
				'robots_txt'        => array(
					'label'       => __( 'robots.txt', 'rankavia' ),
					'description' => __( 'Add your own rules to the robots.txt that WordPress serves. Rankavia never writes a file to your site; a robots.txt file in your site root would take precedence over these settings.', 'rankavia' ),
					'ui'          => array(
						'preview' => 'robots',
					),
					'fields'      => array(
						'robots_txt_custom' => array(
							'type'              => 'text',
							'label'             => __( 'Custom rules', 'rankavia' ),
							'description'       => __( 'Added verbatim after the generated rules, one per line, for example Disallow: /private/. Lines starting with # are comments.', 'rankavia' ),
							'default'           => '',
							'max_length'        => 10000,
							'placeholder'       => 'Disallow: /private/',
							'validate_callback' => array( self::class, 'validate_robots_custom' ),
						),
					),
				),
				'llms_txt'          => array(
					'label'       => __( 'llms.txt', 'rankavia' ),
					'description' => __( 'A plain-text index of your content for AI tools. It is a courtesy, not a ranking signal.', 'rankavia' ),
					'ui'          => array(
						'preview' => 'llms',
					),
					'fields'      => array(
						'llms_enabled'      => array(
							'type'        => 'boolean',
							'label'       => __( 'Serve llms.txt', 'rankavia' ),
							'description' => __( 'A courtesy index of your content for AI tools, served at /llms.txt. It is not a ranking signal; some crawlers read it and none are required to.', 'rankavia' ),
							'default'     => false,
						),
						'llms_intro'        => array(
							'type'        => 'text',
							'label'       => __( 'Introduction', 'rankavia' ),
							'description' => __( 'A short paragraph shown under the site summary. Leave empty to skip it.', 'rankavia' ),
							'default'     => '',
							'max_length'  => 1000,
						),
						'llms_post_types'   => array(
							'type'        => 'multiselect',
							'label'       => __( 'Content to list', 'rankavia' ),
							'description' => __( 'The kinds of content included in the file. Posts marked noindex and password-protected posts are left out.', 'rankavia' ),
							'default'     => array_values( array_intersect( array( 'post', 'page' ), array_keys( $type_choices ) ) ),
							'choices'     => $type_choices,
						),
						'llms_max_per_type' => array(
							'type'        => 'integer',
							'label'       => __( 'Most entries per content type', 'rankavia' ),
							'description' => __( 'The most recently modified entries are listed first.', 'rankavia' ),
							'default'     => 50,
							'min'         => 1,
							'max'         => 500,
						),
					),
				),
				'social'            => array(
					'label'       => __( 'Social', 'rankavia' ),
					'description' => __( 'How your pages look when shared on social networks.', 'rankavia' ),
					'fields'      => array(
						'og_enabled'       => array(
							'type'        => 'boolean',
							'label'       => __( 'Open Graph tags', 'rankavia' ),
							'description' => __( 'Output Open Graph tags used by Facebook, LinkedIn and many messaging apps.', 'rankavia' ),
							'default'     => true,
						),
						'twitter_enabled'  => array(
							'type'        => 'boolean',
							'label'       => __( 'Twitter card tags', 'rankavia' ),
							'description' => __( 'Output Twitter card tags for link previews on X.', 'rankavia' ),
							'default'     => true,
						),
						'og_default_image' => array(
							'type'        => 'image',
							'label'       => __( 'Default social image', 'rankavia' ),
							'description' => __( 'Used when a page has no featured image.', 'rankavia' ),
							'default'     => 0,
						),
						'twitter_site'     => array(
							'type'              => 'string',
							'label'             => __( 'Twitter username', 'rankavia' ),
							'description'       => __( 'Your site\'s handle, without the @.', 'rankavia' ),
							'default'           => '',
							'max_length'        => 16,
							'placeholder'       => 'username',
							'sanitize_callback' => array( self::class, 'strip_at_sign' ),
						),
						'facebook_app_id'  => array(
							'type'              => 'string',
							'label'             => __( 'Facebook app ID', 'rankavia' ),
							'description'       => __( 'Optional. Digits only.', 'rankavia' ),
							'default'           => '',
							'max_length'        => 32,
							'sanitize_callback' => array( self::class, 'digits_only' ),
						),
					),
				),
				'advanced'          => array(
					'label'       => __( 'Advanced', 'rankavia' ),
					'description' => __( 'Remove tags WordPress adds to the head of every page that most sites do not need.', 'rankavia' ),
					'fields'      => array(
						'remove_shortlink'    => array(
							'type'        => 'boolean',
							'label'       => __( 'Remove shortlink', 'rankavia' ),
							'description' => __( 'Removes the rel="shortlink" tag and header.', 'rankavia' ),
							'default'     => true,
						),
						'remove_rsd_link'     => array(
							'type'        => 'boolean',
							'label'       => __( 'Remove RSD link', 'rankavia' ),
							'description' => __( 'Removes the Really Simple Discovery link used by old blog editors.', 'rankavia' ),
							'default'     => true,
						),
						'remove_wlw_manifest' => array(
							'type'        => 'boolean',
							'label'       => __( 'Remove Windows Live Writer link', 'rankavia' ),
							'description' => __( 'Removes the wlwmanifest link for a discontinued desktop editor.', 'rankavia' ),
							'default'     => true,
						),
						'remove_generator'    => array(
							'type'        => 'boolean',
							'label'       => __( 'Remove WordPress version tag', 'rankavia' ),
							'description' => __( 'Removes the generator meta tag that announces your WordPress version.', 'rankavia' ),
							'default'     => true,
						),
						'remove_rest_link'    => array(
							'type'        => 'boolean',
							'label'       => __( 'Remove REST API link', 'rankavia' ),
							'description' => __( 'Removes the REST API discovery link from the head. The API itself keeps working; some integrations rely on the link.', 'rankavia' ),
							'default'     => false,
						),
					),
				),
			)
		);
	}

	/**
	 * Validate the custom robots.txt rules: every line must be blank, a comment or "Field: value" with a known field.
	 *
	 * @param mixed $value Sanitised rules.
	 * @return string|true The message naming the first bad line, or true when valid.
	 */
	public static function validate_robots_custom( $value ) {
		$fields = array( 'user-agent', 'allow', 'disallow', 'sitemap', 'crawl-delay', 'host', 'clean-param', 'content-signal' );
		$lines  = preg_split( '/\R/', is_string( $value ) ? $value : '' );

		foreach ( is_array( $lines ) ? $lines : array() as $index => $line ) {
			$line = trim( $line );
			if ( '' === $line || 0 === strpos( $line, '#' ) ) {
				continue;
			}

			if ( 1 === preg_match( '/^([A-Za-z][A-Za-z-]*)[ \t]*:/', $line, $match ) && in_array( strtolower( $match[1] ), $fields, true ) ) {
				continue;
			}

			$shown = 1 === preg_match( '/^.{1,80}/us', $line, $cut ) ? $cut[0] : $line;
			return sprintf(
				/* translators: 1: line number, 2: the text of that line. */
				__( 'Line %1$d is not a valid robots.txt rule: "%2$s". Use "Field: value" with user-agent, allow, disallow, sitemap, crawl-delay, host, clean-param or content-signal, or start the line with # for a comment.', 'rankavia' ),
				$index + 1,
				$shown === $line ? $line : $shown . '…'
			);
		}

		return true;
	}

	/**
	 * The three presets for the AI crawler form, each with the full set of values it applies.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function presets(): array {
		return array(
			array(
				'id'          => 'allow-all',
				'label'       => __( 'Allow all', 'rankavia' ),
				'description' => __( 'No AI-specific rules. Every crawler may read the site.', 'rankavia' ),
				'values'      => array(
					'ai_crawler_policy'       => Bots::preset_allow_all(),
					'content_signal_search'   => '',
					'content_signal_ai_input' => '',
					'content_signal_ai_train' => '',
				),
			),
			array(
				'id'          => 'search-not-training',
				'label'       => __( 'Search yes, training no', 'rankavia' ),
				'description' => __( 'Allow the crawlers that send visitors from AI search; block the ones that only collect training data.', 'rankavia' ),
				'values'      => array(
					'ai_crawler_policy'       => Bots::preset_search_not_training(),
					'content_signal_search'   => 'yes',
					'content_signal_ai_input' => 'yes',
					'content_signal_ai_train' => 'no',
				),
			),
			array(
				'id'          => 'block-all',
				'label'       => __( 'Block all AI crawlers', 'rankavia' ),
				'description' => __( 'Block every AI crawler in the list. Bingbot is left alone because blocking it removes the site from Bing search.', 'rankavia' ),
				'values'      => array(
					'ai_crawler_policy'       => Bots::preset_block_all(),
					'content_signal_search'   => 'yes',
					'content_signal_ai_input' => 'no',
					'content_signal_ai_train' => 'no',
				),
			),
		);
	}

	/**
	 * Name to plural label for post type or taxonomy objects, skipping some names.
	 *
	 * @param mixed              $objects Objects keyed by name, as returned by get_post_types() or get_taxonomies().
	 * @param array<int, string> $skip    Names to leave out.
	 * @return array<string, string>
	 */
	private static function choices_of( $objects, array $skip ): array {
		$choices = array();
		foreach ( is_array( $objects ) ? $objects : array() as $name => $object ) {
			$name = (string) $name;
			if ( in_array( $name, $skip, true ) ) {
				continue;
			}
			$label            = is_object( $object ) && isset( $object->labels->name ) ? (string) $object->labels->name : '';
			$choices[ $name ] = '' !== $label ? $label : $name;
		}
		return $choices;
	}

	/**
	 * Strip leading at signs from a handle.
	 *
	 * @param mixed $value Sanitised string.
	 * @return string
	 */
	public static function strip_at_sign( $value ): string {
		return ltrim( is_string( $value ) ? $value : '', '@' );
	}

	/**
	 * Keep digits only.
	 *
	 * @param mixed $value Sanitised string.
	 * @return string
	 */
	public static function digits_only( $value ): string {
		return (string) preg_replace( '/\D+/', '', is_string( $value ) ? $value : '' );
	}
}
