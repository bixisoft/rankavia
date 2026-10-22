<?php
/**
 * Front-end head output: description, canonical, Open Graph, Twitter cards, robots.
 *
 * @package Rankavia
 */

declare( strict_types=1 );

namespace Bixisoft\Rankavia\Modules\Meta;

use Bixisoft\Rankavia\Vendor\Core\Container;
use Bixisoft\Rankavia\Vendor\Core\Contracts\Module;
use Bixisoft\Rankavia\Vendor\Core\Html\Head;
use Bixisoft\Rankavia\Vendor\Core\Settings\Settings;
use Bixisoft\Rankavia\Vendor\Core\Text\Template;
use Bixisoft\Rankavia\Templates\Contexts;
use Bixisoft\Rankavia\Templates\Variables;
use RuntimeException;
use WP_Term;

/**
 * Builds the SEO head tags for the current request. Not final so tests can stub the redirect exit.
 */
class Meta_Module implements Module {

	/**
	 * Post meta key of the per-post title template.
	 */
	public const META_TITLE = '_rankavia_title';

	/**
	 * Post meta key of the per-post description template.
	 */
	public const META_DESCRIPTION = '_rankavia_description';

	/**
	 * Post meta key of the focus keyword (editor analysis only).
	 */
	public const META_FOCUS_KEYWORD = '_rankavia_focus_keyword';

	/**
	 * Post meta key of the canonical URL override.
	 */
	public const META_CANONICAL = '_rankavia_canonical';

	/**
	 * Post meta key of the noindex flag.
	 */
	public const META_NOINDEX = '_rankavia_noindex';

	/**
	 * Post meta key of the nofollow flag.
	 */
	public const META_NOFOLLOW = '_rankavia_nofollow';

	/**
	 * Post meta key of the social title template.
	 */
	public const META_OG_TITLE = '_rankavia_og_title';

	/**
	 * Post meta key of the social description template.
	 */
	public const META_OG_DESCRIPTION = '_rankavia_og_description';

	/**
	 * Post meta key of the social image attachment id.
	 */
	public const META_OG_IMAGE = '_rankavia_og_image';

	/**
	 * Longest meta description, in characters.
	 */
	public const DESCRIPTION_MAX = 320;

	/**
	 * Shared container.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Module id.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'meta';
	}

	/**
	 * Register the head collector.
	 *
	 * @param Container $container Shared container.
	 * @return void
	 */
	public function register( Container $container ): void {
		$this->container = $container;
		$container->singleton(
			'head',
			static function (): Head {
				return new Head();
			}
		);
	}

	/**
	 * Add hooks.
	 *
	 * @param Container $container Shared container.
	 * @return void
	 */
	public function boot( Container $container ): void {
		$this->container = $container;

		add_filter( 'document_title_separator', array( $this, 'title_separator' ) );
		add_filter( 'pre_get_document_title', array( $this, 'document_title' ) );
		add_filter( 'wp_robots', array( $this, 'robots' ) );
		add_action( 'wp_head', array( $this, 'output_head' ), 1 );
		add_action( 'template_redirect', array( $this, 'redirect_attachment' ) );

		// Core's canonical is replaced by ours so there is exactly one.
		remove_action( 'wp_head', 'rel_canonical' );

		// Settings labels are translated, so the settings are first read on init rather than at plugins_loaded.
		add_action( 'init', array( $this, 'cleanup_head' ) );

		// After custom post types are registered, so every public type gets the override fields.
		add_action( 'init', array( $this, 'register_post_meta' ), 20 );
	}

	/**
	 * Remove the optional core head tags that the advanced settings switch off.
	 *
	 * @return void
	 */
	public function cleanup_head(): void {
		$settings = $this->settings();

		if ( (bool) $settings->get( 'remove_shortlink' ) ) {
			remove_action( 'wp_head', 'wp_shortlink_wp_head', 10 );
			remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
		}
		if ( (bool) $settings->get( 'remove_rsd_link' ) ) {
			remove_action( 'wp_head', 'rsd_link' );
		}
		if ( (bool) $settings->get( 'remove_wlw_manifest' ) ) {
			remove_action( 'wp_head', 'wlwmanifest_link' );
		}
		if ( (bool) $settings->get( 'remove_generator' ) ) {
			remove_action( 'wp_head', 'wp_generator' );
		}
		if ( (bool) $settings->get( 'remove_rest_link' ) ) {
			remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
			remove_action( 'template_redirect', 'rest_output_link_header', 11 );
		}
	}

	/**
	 * Send attachment pages to the file itself with a permanent redirect.
	 *
	 * @return void
	 */
	public function redirect_attachment(): void {
		if ( ! (bool) $this->settings()->get( 'attachment_redirect' ) || ! is_attachment() ) {
			return;
		}

		$url = wp_get_attachment_url( (int) get_queried_object_id() );
		if ( ! is_string( $url ) || '' === $url ) {
			return;
		}

		wp_safe_redirect( $url, 301 );
		$this->terminate();
	}

	/**
	 * End the request after a redirect. Separate so tests can stub it.
	 *
	 * @return void
	 */
	protected function terminate(): void {
		exit;
	}

	/**
	 * Title separator from settings.
	 *
	 * @param string $separator Core default.
	 * @return string
	 */
	public function title_separator( string $separator ): string {
		$custom = (string) $this->settings()->get( 'separator' );
		return '' !== trim( $custom ) ? $custom : $separator;
	}

	/**
	 * Robots directives: noindex search, author, date and paginated pages when enabled, and the per-post flags on singular views.
	 *
	 * @param array<string, bool|string> $robots Directives from core.
	 * @return array<string, bool|string>
	 */
	public function robots( array $robots ): array {
		$settings = $this->settings();

		if (
			( (bool) $settings->get( 'noindex_search' ) && is_search() )
			|| ( (bool) $settings->get( 'noindex_author' ) && is_author() )
			|| ( (bool) $settings->get( 'noindex_date' ) && is_date() )
			|| ( (bool) $settings->get( 'noindex_paginated' ) && is_paged() )
		) {
			unset( $robots['index'] );
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}

		if ( is_singular() ) {
			if ( $this->post_flag( self::META_NOINDEX ) ) {
				unset( $robots['index'] );
				$robots['noindex'] = true;
			}
			if ( $this->post_flag( self::META_NOFOLLOW ) ) {
				unset( $robots['follow'] );
				$robots['nofollow'] = true;
			}
		}
		return $robots;
	}

	/**
	 * Print the head tags.
	 *
	 * @return void
	 */
	public function output_head(): void {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Every tag is escaped by Head when it is added.
		echo $this->build_head()->render();
	}

	/**
	 * Build the head tags for the current request.
	 *
	 * @return Head
	 */
	public function build_head(): Head {
		$head = $this->container->get( 'head' );
		if ( ! $head instanceof Head ) {
			$head = new Head();
		}

		$title       = wp_get_document_title();
		$description = $this->description();
		$canonical   = $this->canonical_url();
		$image       = $this->image_url();

		$social_title       = $this->social_text( self::META_OG_TITLE, $title, false );
		$social_description = $this->social_text( self::META_OG_DESCRIPTION, $description, true );

		$settings = $this->settings();

		$head
			->meta( 'description', $description )
			->link( 'canonical', $canonical );

		if ( (bool) $settings->get( 'og_enabled' ) ) {
			$head
				->meta( 'og:type', is_singular() ? 'article' : 'website', 'property' )
				->meta( 'og:title', $social_title, 'property' )
				->meta( 'og:description', $social_description, 'property' )
				->meta( 'og:url', $canonical, 'property' )
				->meta( 'og:site_name', (string) get_bloginfo( 'name' ), 'property' )
				->meta( 'og:locale', (string) get_locale(), 'property' )
				->meta( 'og:image', $image, 'property' )
				->meta( 'fb:app_id', (string) $settings->get( 'facebook_app_id' ), 'property' );
		}

		if ( (bool) $settings->get( 'twitter_enabled' ) ) {
			$handle = ltrim( (string) $settings->get( 'twitter_site' ), '@' );
			$head
				->meta( 'twitter:card', '' !== $image ? 'summary_large_image' : 'summary' )
				->meta( 'twitter:site', '' !== $handle ? '@' . $handle : '' )
				->meta( 'twitter:title', $social_title )
				->meta( 'twitter:description', $social_description )
				->meta( 'twitter:image', $image );
		}

		/**
		 * Filters the head tags before output.
		 *
		 * @param Head $head The collected tags.
		 */
		$filtered = apply_filters( 'rankavia_head', $head );

		return $filtered instanceof Head ? $filtered : $head;
	}

	/**
	 * Document title for the current request, used by core through pre_get_document_title.
	 *
	 * @param mixed $title Title from earlier filters, returned unchanged when there is nothing to render.
	 * @return mixed
	 */
	public function document_title( $title ) {
		$rendered = $this->title();
		return '' !== $rendered ? $rendered : $title;
	}

	/**
	 * Register the per-post SEO fields so the REST API and the editor can read and write them.
	 *
	 * @return void
	 */
	public function register_post_meta(): void {
		$types = get_post_types( array( 'public' => true ) );

		$fields = array(
			self::META_TITLE          => array( 'string', '', 'sanitize_meta' ),
			self::META_DESCRIPTION    => array( 'string', '', 'sanitize_meta' ),
			self::META_FOCUS_KEYWORD  => array( 'string', '', 'sanitize_meta' ),
			self::META_CANONICAL      => array( 'string', '', 'sanitize_canonical' ),
			self::META_NOINDEX        => array( 'boolean', false, 'sanitize_flag' ),
			self::META_NOFOLLOW       => array( 'boolean', false, 'sanitize_flag' ),
			self::META_OG_TITLE       => array( 'string', '', 'sanitize_meta' ),
			self::META_OG_DESCRIPTION => array( 'string', '', 'sanitize_meta' ),
			self::META_OG_IMAGE       => array( 'integer', 0, 'sanitize_image_id' ),
		);

		foreach ( array_keys( is_array( $types ) ? $types : array() ) as $type ) {
			foreach ( $fields as $meta_key => $field ) {
				register_post_meta(
					(string) $type,
					$meta_key,
					array(
						'type'              => $field[0],
						'single'            => true,
						'default'           => $field[1],
						'show_in_rest'      => true,
						'auth_callback'     => array( $this, 'can_edit_meta' ),
						'sanitize_callback' => array( $this, $field[2] ),
					)
				);
			}
		}
	}

	/**
	 * Whether the current user may edit the post's SEO meta.
	 *
	 * @param bool   $allowed  Whether the user can add the meta, as decided so far.
	 * @param string $meta_key Meta key.
	 * @param int    $post_id  Post id.
	 * @return bool
	 */
	public function can_edit_meta( $allowed, $meta_key, $post_id ): bool {
		unset( $allowed, $meta_key );
		return (bool) current_user_can( 'edit_post', (int) $post_id );
	}

	/**
	 * Plain text only: tags stripped, whitespace collapsed.
	 *
	 * @param mixed $value Raw meta value.
	 * @return string
	 */
	public function sanitize_meta( $value ): string {
		return trim( (string) wp_strip_all_tags( is_scalar( $value ) ? (string) $value : '', true ) );
	}

	/**
	 * Canonical URL override: a clean absolute URL, or empty.
	 *
	 * @param mixed $value Raw meta value.
	 * @return string
	 */
	public function sanitize_canonical( $value ): string {
		return esc_url_raw( trim( is_scalar( $value ) ? (string) $value : '' ) );
	}

	/**
	 * Boolean flag.
	 *
	 * @param mixed $value Raw meta value.
	 * @return bool
	 */
	public function sanitize_flag( $value ): bool {
		return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Attachment id, zero when unset.
	 *
	 * @param mixed $value Raw meta value.
	 * @return int
	 */
	public function sanitize_image_id( $value ): int {
		return absint( is_scalar( $value ) ? $value : 0 );
	}

	/**
	 * Rendered title for the current request.
	 *
	 * @return string
	 */
	private function title(): string {
		$context  = Contexts::current();
		$template = $this->post_meta( self::META_TITLE );

		if ( '' === $template ) {
			$stored   = $this->settings()->get( 'title_templates' );
			$template = is_array( $stored ) && isset( $stored[ $context ] ) ? trim( (string) $stored[ $context ] ) : '';
		}
		if ( '' === $template ) {
			$template = Contexts::default_title( $context );
		}

		if (
			(bool) $this->settings()->get( 'title_append_page' )
			&& $this->is_paged_request()
			&& ! array_intersect( array( 'page', 'pagenumber' ), Template::variables_in( $template ) )
		) {
			$template .= ' %page%';
		}

		return $this->template()->render( $template );
	}

	/**
	 * Meta description for the current request.
	 *
	 * @return string
	 */
	private function description(): string {
		$context  = Contexts::current();
		$template = $this->post_meta( self::META_DESCRIPTION );

		if ( '' === $template && 'home' === $context ) {
			$template = trim( (string) $this->settings()->get( 'site_description' ) );
		}
		if ( '' === $template ) {
			$stored   = $this->settings()->get( 'description_templates' );
			$template = is_array( $stored ) && isset( $stored[ $context ] )
				? trim( (string) $stored[ $context ] )
				: Contexts::default_description( $context );
		}

		return $this->cap( $this->template()->render( $template ) );
	}

	/**
	 * Per-post override for the singular view being shown, or empty.
	 *
	 * @param string $meta_key Meta key.
	 * @return string
	 */
	private function post_meta( string $meta_key ): string {
		if ( ! is_singular() ) {
			return '';
		}
		$post_id = (int) get_queried_object_id();
		if ( $post_id < 1 ) {
			return '';
		}
		return trim( (string) get_post_meta( $post_id, $meta_key, true ) );
	}

	/**
	 * Whether a boolean per-post flag is set on the singular view being shown.
	 *
	 * @param string $meta_key Meta key.
	 * @return bool
	 */
	private function post_flag( string $meta_key ): bool {
		return filter_var( $this->post_meta( $meta_key ), FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Social text: the per-post override rendered as a template, else the SEO value.
	 *
	 * @param string $meta_key Meta key of the override.
	 * @param string $fallback Value used when there is no override or it renders empty.
	 * @param bool   $capped   Whether to cap the rendering like a meta description.
	 * @return string
	 */
	private function social_text( string $meta_key, string $fallback, bool $capped ): string {
		$template = $this->post_meta( $meta_key );
		if ( '' === $template ) {
			return $fallback;
		}

		$rendered = $this->template()->render( $template );
		if ( $capped ) {
			$rendered = $this->cap( $rendered );
		}
		return '' !== $rendered ? $rendered : $fallback;
	}

	/**
	 * Template engine bound to this request's variables and the separator setting.
	 *
	 * @return Template
	 */
	private function template(): Template {
		$separator = (string) $this->settings()->get( 'separator' );
		$separator = '' !== trim( $separator ) ? $separator : '–';

		return new Template( Variables::for_request( $separator ), $separator );
	}

	/**
	 * Whether this is page 2 or later of an archive or of a split post.
	 *
	 * @return bool
	 */
	private function is_paged_request(): bool {
		return is_paged() || ( is_singular() && (int) get_query_var( 'page' ) > 1 );
	}

	/**
	 * Cap a description at the maximum length, cutting on a word boundary and ending with an ellipsis.
	 *
	 * @param string $text Plain text.
	 * @return string
	 */
	private function cap( string $text ): string {
		$max = self::DESCRIPTION_MAX;

		if ( 1 !== preg_match( '/^(.{' . ( $max - 1 ) . '})(.)./us', $text, $match ) ) {
			return $text;
		}

		$head = $match[1];
		if ( 1 !== preg_match( '/^\s$/u', $match[2] ) ) {
			$space = strrpos( $head, ' ' );
			if ( false !== $space && $space > 0 ) {
				$head = substr( $head, 0, $space );
			}
		}

		return (string) preg_replace( '/[\s,;:.\-–—]+$/u', '', $head ) . '…';
	}

	/**
	 * Canonical URL for the current request, or empty when none applies.
	 *
	 * @return string
	 */
	private function canonical_url(): string {
		if ( is_singular() ) {
			$override = $this->post_meta( self::META_CANONICAL );
			if ( '' !== $override ) {
				$valid = wp_http_validate_url( $override );
				if ( is_string( $valid ) && '' !== $valid ) {
					return $valid;
				}
			}

			$url = wp_get_canonical_url();
			return is_string( $url ) ? $url : '';
		}

		if ( is_front_page() ) {
			return home_url( '/' );
		}

		if ( is_home() ) {
			$page = (int) get_option( 'page_for_posts' );
			return $page > 0 ? (string) get_permalink( $page ) : home_url( '/' );
		}

		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term instanceof WP_Term ) {
				$link = get_term_link( $term );
				return is_string( $link ) ? $link : '';
			}
		}

		if ( is_post_type_archive() ) {
			$link = get_post_type_archive_link( (string) get_query_var( 'post_type' ) );
			return is_string( $link ) ? $link : '';
		}

		if ( is_author() ) {
			return (string) get_author_posts_url( (int) get_queried_object_id() );
		}

		return '';
	}

	/**
	 * Social image: the per-post override when its attachment exists, else the featured image on singular, else the default from settings.
	 *
	 * @return string
	 */
	private function image_url(): string {
		if ( is_singular() ) {
			$override = (int) $this->post_meta( self::META_OG_IMAGE );
			if ( $override > 0 ) {
				$url = wp_get_attachment_image_url( $override, 'large' );
				if ( is_string( $url ) && '' !== $url ) {
					return $url;
				}
			}

			$url = get_the_post_thumbnail_url( null, 'large' );
			if ( is_string( $url ) && '' !== $url ) {
				return $url;
			}
		}

		$attachment_id = (int) $this->settings()->get( 'og_default_image' );
		if ( $attachment_id > 0 ) {
			$url = wp_get_attachment_image_url( $attachment_id, 'large' );
			if ( is_string( $url ) ) {
				return $url;
			}
		}

		return '';
	}

	/**
	 * Settings service.
	 *
	 * @return Settings
	 * @throws RuntimeException When the service is missing.
	 */
	private function settings(): Settings {
		$settings = $this->container->get( 'settings' );
		if ( ! $settings instanceof Settings ) {
			throw new RuntimeException( 'The settings service is not registered.' );
		}
		return $settings;
	}
}
