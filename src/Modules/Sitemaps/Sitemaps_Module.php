<?php
/**
 * Shapes WordPress' own XML sitemaps (/wp-sitemap.xml) with the Rankavia settings.
 *
 * @package Rankavia
 */

declare( strict_types=1 );

namespace Bixisoft\Rankavia\Modules\Sitemaps;

use Bixisoft\Rankavia\Vendor\Core\Container;
use Bixisoft\Rankavia\Vendor\Core\Contracts\Module;
use Bixisoft\Rankavia\Vendor\Core\Settings\Settings;
use Bixisoft\Rankavia\Modules\Meta\Meta_Module;
use RuntimeException;

/**
 * Extends core's sitemap engine through its filters; there is no second sitemap generator.
 */
final class Sitemaps_Module implements Module {

	/**
	 * Most URLs per sitemap file.
	 */
	public const MAX_URLS = 1000;

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
		return 'sitemaps';
	}

	/**
	 * Keep the container for lazy access to the settings.
	 *
	 * @param Container $container Shared container.
	 * @return void
	 */
	public function register( Container $container ): void {
		$this->container = $container;
	}

	/**
	 * Add the sitemap filters.
	 *
	 * @param Container $container Shared container.
	 * @return void
	 */
	public function boot( Container $container ): void {
		$this->container = $container;

		add_filter( 'wp_sitemaps_enabled', array( $this, 'filter_enabled' ) );
		add_filter( 'wp_sitemaps_post_types', array( $this, 'filter_post_types' ) );
		add_filter( 'wp_sitemaps_taxonomies', array( $this, 'filter_taxonomies' ) );
		add_filter( 'wp_sitemaps_add_provider', array( $this, 'filter_provider' ), 10, 2 );
		add_filter( 'wp_sitemaps_posts_query_args', array( $this, 'filter_posts_query_args' ) );
		add_filter( 'wp_sitemaps_posts_entry', array( $this, 'filter_posts_entry' ), 10, 2 );
		add_filter( 'wp_sitemaps_max_urls', array( $this, 'filter_max_urls' ) );
	}

	/**
	 * Turn sitemaps off when the setting is off; otherwise keep core's answer, which already respects blog_public.
	 *
	 * @param mixed $enabled Core's value.
	 * @return bool
	 */
	public function filter_enabled( $enabled ): bool {
		return (bool) $enabled && (bool) $this->settings()->get( 'sitemaps_enabled' );
	}

	/**
	 * Keep only the chosen post types.
	 *
	 * @param mixed $post_types Post type objects keyed by name.
	 * @return array<string, mixed>
	 */
	public function filter_post_types( $post_types ): array {
		return $this->only( is_array( $post_types ) ? $post_types : array(), $this->selected( 'sitemap_post_types' ) );
	}

	/**
	 * Keep only the chosen taxonomies, and never post formats.
	 *
	 * @param mixed $taxonomies Taxonomy objects keyed by name.
	 * @return array<string, mixed>
	 */
	public function filter_taxonomies( $taxonomies ): array {
		$kept = $this->only( is_array( $taxonomies ) ? $taxonomies : array(), $this->selected( 'sitemap_taxonomies' ) );
		unset( $kept['post_format'] );
		return $kept;
	}

	/**
	 * Drop the users provider when author sitemaps are off or author archives are noindexed.
	 *
	 * @param mixed  $provider Provider object.
	 * @param string $name     Provider name.
	 * @return mixed The provider, or false to remove it.
	 */
	public function filter_provider( $provider, $name ) {
		if ( 'users' !== $name ) {
			return $provider;
		}

		$settings = $this->settings();
		if ( ! (bool) $settings->get( 'sitemap_users' ) || (bool) $settings->get( 'noindex_author' ) ) {
			return false;
		}
		return $provider;
	}

	/**
	 * Leave posts flagged noindex out of the posts sitemaps.
	 *
	 * @param mixed $args Query arguments.
	 * @return array<string, mixed>
	 */
	public function filter_posts_query_args( $args ): array {
		$args = is_array( $args ) ? $args : array();

		$query = self::noindex_meta_query();
		if ( isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) && array() !== $args['meta_query'] ) {
			$query = array(
				'relation' => 'AND',
				$args['meta_query'],
				$query,
			);
		}

		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Noindex posts must stay out of the sitemap.
		$args['meta_query'] = $query;

		return $args;
	}

	/**
	 * Add the last modification time (UTC, W3C format) to a post entry.
	 *
	 * @param mixed $entry Sitemap entry with at least 'loc'.
	 * @param mixed $post  The post.
	 * @return array<string, mixed>
	 */
	public function filter_posts_entry( $entry, $post = null ): array {
		$entry = is_array( $entry ) ? $entry : array();

		$modified = is_object( $post ) && isset( $post->post_modified_gmt ) ? (string) $post->post_modified_gmt : '';
		$time     = '' !== $modified && 0 !== strpos( $modified, '0000' ) ? strtotime( $modified . ' UTC' ) : false;
		if ( false !== $time ) {
			$entry['lastmod'] = gmdate( 'Y-m-d\TH:i:s+00:00', $time );
		}

		return $entry;
	}

	/**
	 * URLs per sitemap file.
	 *
	 * @return int
	 */
	public function filter_max_urls(): int {
		return self::MAX_URLS;
	}

	/**
	 * Meta query matching posts that are not flagged noindex: no value, an empty value or '0'.
	 *
	 * @return array<int|string, mixed>
	 */
	public static function noindex_meta_query(): array {
		return array(
			'relation' => 'OR',
			array(
				'key'     => Meta_Module::META_NOINDEX,
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => Meta_Module::META_NOINDEX,
				'value'   => '',
				'compare' => '=',
			),
			array(
				'key'     => Meta_Module::META_NOINDEX,
				'value'   => '0',
				'compare' => '=',
			),
		);
	}

	/**
	 * Entries of a map whose keys are in a list, in the map's order.
	 *
	 * @param array<mixed>       $items Items keyed by name.
	 * @param array<int, string> $names Names to keep.
	 * @return array<string, mixed>
	 */
	private function only( array $items, array $names ): array {
		$kept = array();
		foreach ( $items as $name => $item ) {
			if ( in_array( (string) $name, $names, true ) ) {
				$kept[ (string) $name ] = $item;
			}
		}
		return $kept;
	}

	/**
	 * A multiselect setting as a list of strings.
	 *
	 * @param string $key Setting key.
	 * @return array<int, string>
	 */
	private function selected( string $key ): array {
		$value = $this->settings()->get( $key );
		return is_array( $value ) ? array_map( 'strval', array_values( $value ) ) : array();
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
