<?php
/**
 * Serves /llms.txt, a courtesy index of the site's content for AI tools.
 *
 * @package Rankavia
 */

declare( strict_types=1 );

namespace Bixisoft\Rankavia\Modules\Llms;

use Bixisoft\Rankavia\Vendor\Core\Container;
use Bixisoft\Rankavia\Vendor\Core\Contracts\Module;
use Bixisoft\Rankavia\Vendor\Core\Settings\Settings;
use Bixisoft\Rankavia\Llms\Llms_Builder;
use Bixisoft\Rankavia\Modules\Sitemaps\Sitemaps_Module;
use Bixisoft\Rankavia\Templates\Variables;
use RuntimeException;

/**
 * Adds a rewrite rule for llms.txt and answers it on template_redirect. Not final so tests can stub the exit.
 */
class Llms_Module implements Module {

	/**
	 * Bump when the rewrite rules change; the next request flushes them once.
	 */
	public const REWRITE_VERSION = '1';

	/**
	 * Option holding the rewrite version that was last flushed.
	 */
	public const VERSION_OPTION = 'rankavia_rewrite_version';

	/**
	 * Query variable set by the rewrite rule.
	 */
	public const QUERY_VAR = 'rankavia_llms';

	/**
	 * Transient holding the built file and the hash of its inputs.
	 */
	public const TRANSIENT = 'rankavia_llms_txt';

	/**
	 * Characters of content used as a summary when a post has no excerpt.
	 */
	public const SUMMARY_LENGTH = 155;

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
		return 'llms';
	}

	/**
	 * Expose this module as the 'llms' service so the files preview can read the built file.
	 *
	 * @param Container $container Shared container.
	 * @return void
	 */
	public function register( Container $container ): void {
		$this->container = $container;
		$container->instance( 'llms', $this );
	}

	/**
	 * Add hooks.
	 *
	 * @param Container $container Shared container.
	 * @return void
	 */
	public function boot( Container $container ): void {
		$this->container = $container;

		add_action( 'init', array( $this, 'add_rewrite_rule' ) );
		add_action( 'init', array( $this, 'maybe_flush_rewrite_rules' ), 99 );
		add_filter( 'query_vars', array( $this, 'add_query_var' ) );
		add_action( 'template_redirect', array( $this, 'serve' ) );

		add_action( 'save_post', array( $this, 'flush_cache' ) );
		add_action( 'deleted_post', array( $this, 'flush_cache' ) );
		add_action( 'trashed_post', array( $this, 'flush_cache' ) );
	}

	/**
	 * Route /llms.txt to the query variable.
	 *
	 * @return void
	 */
	public function add_rewrite_rule(): void {
		add_rewrite_rule( '^llms\.txt$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
	}

	/**
	 * Flush the rewrite rules once whenever REWRITE_VERSION changes.
	 *
	 * @return void
	 */
	public function maybe_flush_rewrite_rules(): void {
		if ( get_option( self::VERSION_OPTION ) === self::REWRITE_VERSION ) {
			return;
		}

		flush_rewrite_rules( false );
		update_option( self::VERSION_OPTION, self::REWRITE_VERSION );
	}

	/**
	 * Register the query variable.
	 *
	 * @param mixed $vars Public query variables.
	 * @return array<int, mixed>
	 */
	public function add_query_var( $vars ): array {
		$vars   = is_array( $vars ) ? array_values( $vars ) : array();
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Answer a request for /llms.txt: the file when enabled and the site is public, otherwise WordPress' 404.
	 *
	 * @return void
	 */
	public function serve(): void {
		if ( '' === (string) get_query_var( self::QUERY_VAR ) ) {
			return;
		}

		if ( ! $this->is_enabled() || ! $this->is_public() ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			return;
		}

		status_header( 200 );
		$this->send_headers();
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A text/plain body built from stripped, plain-text data; HTML escaping would corrupt it (core does the same for robots.txt).
		echo $this->content();
		$this->terminate();
	}

	/**
	 * Whether llms.txt is switched on.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return (bool) $this->settings()->get( 'llms_enabled' );
	}

	/**
	 * Whether the site is open to search engines (Settings, Reading).
	 *
	 * @return bool
	 */
	public function is_public(): bool {
		return '0' !== (string) get_option( 'blog_public', '1' );
	}

	/**
	 * The file, from the cache while its inputs are unchanged.
	 *
	 * @return string
	 */
	public function content(): string {
		$site = $this->site();
		$hash = md5( (string) wp_json_encode( array( $this->settings()->group( 'llms_txt' ), $site['name'], $site['summary'] ) ) );

		$cached = get_transient( self::TRANSIENT );
		if ( is_array( $cached ) && isset( $cached['hash'], $cached['content'] ) && $hash === $cached['hash'] ) {
			return (string) $cached['content'];
		}

		$content = Llms_Builder::build( $site, $this->sections() );
		set_transient(
			self::TRANSIENT,
			array(
				'hash'    => $hash,
				'content' => $content,
			),
			12 * HOUR_IN_SECONDS
		);

		return $content;
	}

	/**
	 * Drop the cached file; the next request rebuilds it.
	 *
	 * @return void
	 */
	public function flush_cache(): void {
		delete_transient( self::TRANSIENT );
	}

	/**
	 * Send the response headers. Separate so tests can stub it.
	 *
	 * @return void
	 */
	protected function send_headers(): void {
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Content-Type-Options: nosniff' );
	}

	/**
	 * End the request after the file is sent. Separate so tests can stub it.
	 *
	 * @return void
	 */
	protected function terminate(): void {
		exit;
	}

	/**
	 * Site name, summary and intro.
	 *
	 * @return array{name: string, summary: string, intro: string}
	 */
	private function site(): array {
		$summary = trim( (string) $this->settings()->get( 'site_description' ) );
		if ( '' === $summary ) {
			$summary = trim( (string) get_bloginfo( 'description' ) );
		}

		return array(
			'name'    => (string) get_bloginfo( 'name' ),
			'summary' => $summary,
			'intro'   => (string) $this->settings()->get( 'llms_intro' ),
		);
	}

	/**
	 * One section per chosen post type with its most recently modified public posts.
	 *
	 * @return array<int, array{title: string, items: array<int, array{title: string, url: string, summary: string}>}>
	 */
	private function sections(): array {
		$types = $this->settings()->get( 'llms_post_types' );
		$max   = max( 1, (int) $this->settings()->get( 'llms_max_per_type' ) );

		$sections = array();
		foreach ( is_array( $types ) ? $types : array() as $name ) {
			$type = get_post_type_object( (string) $name );
			if ( ! is_object( $type ) ) {
				continue;
			}

			$posts = get_posts(
				array(
					'post_type'           => (string) $name,
					'post_status'         => 'publish',
					'has_password'        => false,
					'posts_per_page'      => $max,
					'orderby'             => 'modified',
					'order'               => 'DESC',
					'no_found_rows'       => true,
					'ignore_sticky_posts' => true,
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Noindex posts must stay out of the file; the result is cached.
					'meta_query'          => Sitemaps_Module::noindex_meta_query(),
				)
			);

			$items = array();
			foreach ( $posts as $post ) {
				if ( is_object( $post ) ) {
					$items[] = array(
						'title'   => (string) get_the_title( $post ),
						'url'     => (string) get_permalink( $post ),
						'summary' => $this->summary( (string) $post->post_excerpt, (string) $post->post_content ),
					);
				}
			}

			$sections[] = array(
				'title' => isset( $type->labels->name ) ? (string) $type->labels->name : (string) $name,
				'items' => $items,
			);
		}
		return $sections;
	}

	/**
	 * The manual excerpt, else the first characters of the content, as plain text.
	 *
	 * @param string $excerpt The manual excerpt, possibly empty.
	 * @param string $content The post content.
	 * @return string
	 */
	private function summary( string $excerpt, string $content ): string {
		if ( '' !== trim( $excerpt ) ) {
			return Variables::plain_excerpt( $excerpt, 0 );
		}
		return Variables::plain_excerpt( $content, self::SUMMARY_LENGTH );
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
