<?php
/**
 * Public HTML sitemap shortcode.
 *
 * @package Rankavia
 */

declare( strict_types=1 );

namespace Bixisoft\Rankavia\Modules\Sitemaps;

use Bixisoft\Rankavia\Vendor\Core\Container;
use Bixisoft\Rankavia\Vendor\Core\Contracts\Module;
use Bixisoft\Rankavia\Vendor\Core\Settings\Settings;
use Bixisoft\Rankavia\Modules\Meta\Meta_Module;
use Bixisoft\Rankavia\Sitemaps\Html_Renderer;
use WP_Post;
use WP_Query;

/** Uses WordPress queries and the existing XML sitemap policy hooks. */
final class Html_Sitemap_Module implements Module {
	public const MAX_ITEMS = 100;

	/**
	 * Shared services.
	 *
	 * @var Container
	 */
	private Container $container;

	/** Get module id. @return string */
	public function id(): string {
		return 'html-sitemap';
	}

	/**
	 * Keep shared services.
	 *
	 * @param Container $container Shared container.
	 * @return void
	 */
	public function register( Container $container ): void {
		$this->container = $container;
	}

	/**
	 * Register the public shortcode.
	 *
	 * @param Container $container Shared container.
	 * @return void
	 */
	public function boot( Container $container ): void {
		$this->container = $container;
		add_shortcode( 'rankavia_sitemap', array( $this, 'shortcode' ) );
	}

	/**
	 * Render the bounded public sitemap.
	 *
	 * @param mixed $attributes Unused shortcode attributes.
	 * @return string
	 */
	public function shortcode( $attributes = array() ): string {
		if ( is_admin() || is_feed() || '0' === (string) get_option( 'blog_public', '1' ) ) {
			return '';
		}
		$settings = $this->container->get( 'settings' );
		if ( ! $settings instanceof Settings || ! (bool) $settings->get( 'sitemaps_enabled' ) ) {
			return '';
		}
		$selected = $settings->get( 'sitemap_post_types' );
		$selected = is_array( $selected ) ? $selected : array();
		$posts    = array();
		foreach ( array( 'post', 'page' ) as $type ) {
			$object = get_post_type_object( $type );
			if ( ! in_array( $type, $selected, true ) || ! is_object( $object ) || empty( $object->public ) ) {
				continue;
			}
			$args      = array(
				'post_type'           => $type,
				'post_status'         => 'publish',
				'has_password'        => false,
				'posts_per_page'      => self::MAX_ITEMS + 1,
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
				'orderby'             => array(
					'date' => 'DESC',
					'ID'   => 'DESC',
				),
				'meta_query'          => Sitemaps_Module::noindex_meta_query(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Public sitemap must exclude noindex posts.
			);
			$filtered  = apply_filters( 'wp_sitemaps_posts_query_args', $args, $type ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Reuse core sitemap query policy filters.
			$filtered  = is_array( $filtered ) ? $filtered : array();
			$peer_meta = isset( $filtered['meta_query'] ) && is_array( $filtered['meta_query'] ) ? $filtered['meta_query'] : array();
			$args      = array_merge( $filtered, $args );
			// Reassert privacy and the bound after third-party filters; retain stricter meta exclusions.
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Existing peer conditions are ANDed with Rankavia noindex policy.
			$args['meta_query'] = array() !== $peer_meta && Sitemaps_Module::noindex_meta_query() !== $peer_meta
				? array(
					'relation' => 'AND',
					$peer_meta,
					Sitemaps_Module::noindex_meta_query(),
				)
				: Sitemaps_Module::noindex_meta_query();
			$query              = new WP_Query( $args );
			foreach ( array_slice( $query->posts, 0, self::MAX_ITEMS + 1 ) as $post ) {
				if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status || '' !== $post->post_password || $type !== $post->post_type || ! empty( get_post_meta( $post->ID, Meta_Module::META_NOINDEX, true ) ) ) {
					continue;
				}
				$url = get_permalink( $post );
				if ( ! is_string( $url ) || '' === $url ) {
					continue;
				}
				$posts[] = array(
					'post' => $post,
					'url'  => $url,
				);
			}
		}
		usort(
			$posts,
			static function ( array $a, array $b ): int {
				$dates = strcmp( $b['post']->post_date_gmt, $a['post']->post_date_gmt );
				return 0 !== $dates ? $dates : $b['post']->ID <=> $a['post']->ID;
			}
		);
		$items = array();
		foreach ( array_slice( $posts, 0, self::MAX_ITEMS ) as $entry ) {
			$items[] = array(
				'title' => (string) get_the_title( $entry['post'] ),
				'url'   => $entry['url'],
			);
		}
		return Html_Renderer::render( $items );
	}
}
