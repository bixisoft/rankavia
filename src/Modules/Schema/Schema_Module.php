<?php
/**
 * JSON-LD head integration.
 *
 * @package Rankavia
 */

declare( strict_types=1 );

namespace Bixisoft\Rankavia\Modules\Schema;

use Bixisoft\Rankavia\Vendor\Core\Container;
use Bixisoft\Rankavia\Vendor\Core\Contracts\Module;
use Bixisoft\Rankavia\Vendor\Core\Html\Head;
use Bixisoft\Rankavia\Vendor\Core\Settings\Settings;
use Bixisoft\Rankavia\Schema\Graph_Builder;

/**
 * Adds one graph to the existing head collector on indexable public pages.
 */
final class Schema_Module implements Module {

	/**
	 * Shared plugin services.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Return the module identifier.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'schema';
	}

	/**
	 * Keep the shared services.
	 *
	 * @param Container $container Shared services.
	 * @return void
	 */
	public function register( Container $container ): void {
		$this->container = $container;
	}

	/**
	 * Register the head filter.
	 *
	 * @param Container $container Shared services.
	 * @return void
	 */
	public function boot( Container $container ): void {
		$this->container = $container;
		add_filter( 'rankavia_head', array( $this, 'add_graph' ) );
	}

	/**
	 * Add an escaped graph to the head collector.
	 *
	 * @param Head $head Collected tags.
	 * @return Head
	 */
	public function add_graph( Head $head ): Head {
		if ( ! $this->should_render() || $head->has( 'raw:rankavia-schema' ) ) {
			return $head;
		}

		$settings = $this->container->get( 'settings' );
		if ( ! $settings instanceof Settings ) {
			return $head;
		}
		$home        = home_url( '/' );
		$page        = $this->page_url();
		$logo_id     = (int) $settings->get( 'organization_logo' );
		$logo        = $logo_id > 0 ? wp_get_attachment_image_url( $logo_id, 'full' ) : '';
		$description = $settings->get( 'site_description' );
		$graph       = ( new Graph_Builder() )->build(
			array(
				'home_url'          => is_string( $home ) ? $home : '',
				'page_url'          => $page,
				'site_name'         => (string) get_bloginfo( 'name' ),
				'site_description'  => (string) ( $description ? $description : get_bloginfo( 'description' ) ),
				'site_type'         => $settings->get( 'site_type' ),
				'organization_name' => $settings->get( 'organization_name' ),
				'organization_logo' => is_string( $logo ) ? $logo : '',
				'social_profiles'   => $settings->get( 'social_profiles' ),
				'page_title'        => wp_get_document_title(),
			)
		);
		if ( empty( $graph ) ) {
			return $head;
		}
		$json = wp_json_encode( $graph, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES );
		if ( ! is_string( $json ) || '' === $json ) {
			return $head;
		}
		$head->raw( 'rankavia-schema', '<script type="application/ld+json">' . $json . '</script>' );
		return $head;
	}

	/**
	 * Use only URLs that Meta also treats as canonical.
	 *
	 * @return string
	 */
	private function page_url(): string {
		if ( is_singular() ) {
			$id       = (int) get_queried_object_id();
			$override = $id > 0 ? get_post_meta( $id, '_rankavia_canonical', true ) : '';
			if ( is_string( $override ) && '' !== trim( $override ) ) {
				$valid = wp_http_validate_url( $override );
				if ( is_string( $valid ) && '' !== $valid ) {
					return $valid;
				}
			}
			$url = wp_get_canonical_url();
			return is_string( $url ) ? $url : '';
		}
		if ( is_front_page() ) {
			return (string) home_url( '/' );
		}
		if ( is_home() ) {
			$id = (int) get_option( 'page_for_posts' );
			return $id > 0 ? (string) get_permalink( $id ) : (string) home_url( '/' );
		}
		return '';
	}

	/**
	 * Determine whether this public request permits graph output.
	 *
	 * @return bool
	 */
	private function should_render(): bool {
		if ( is_admin() || is_feed() || is_404() || is_search() || is_preview() || is_paged() || '0' === (string) get_option( 'blog_public', '1' ) ) {
			return false;
		}
		if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' ) ) {
			return false;
		}
		$robots = apply_filters( 'wp_robots', array() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Invoke the core robots filter chain to respect noindex from every plugin.
		return ! ( is_array( $robots ) && ! empty( $robots['noindex'] ) );
	}
}
