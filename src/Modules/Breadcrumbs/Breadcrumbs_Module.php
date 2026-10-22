<?php
/**
 * Front-end breadcrumb shortcode.
 *
 * @package Rankavia
 */

declare( strict_types=1 );

namespace Bixisoft\Rankavia\Modules\Breadcrumbs;

use Bixisoft\Rankavia\Vendor\Core\Container;
use Bixisoft\Rankavia\Vendor\Core\Contracts\Module;
use Bixisoft\Rankavia\Breadcrumbs\Renderer;
use Bixisoft\Rankavia\Breadcrumbs\Trail_Builder;
use WP_Post;

/** Builds the current public request's trail only when the shortcode is rendered. */
final class Breadcrumbs_Module implements Module {
	/** Get the module ID.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'breadcrumbs';
	}

	/** Register services (none needed).
	 *
	 * @param Container $container Shared services.
	 * @return void
	 */
	public function register( Container $container ): void {}

	/** Register the shortcode.
	 *
	 * @param Container $container Shared services.
	 * @return void
	 */
	public function boot( Container $container ): void {
		add_shortcode( 'rankavia_breadcrumbs', array( $this, 'shortcode' ) );
	}

	/** Render the shortcode for a public request.
	 *
	 * @param mixed $attributes Shortcode attributes (unused).
	 * @return string
	 */
	public function shortcode( $attributes = array() ): string {
		if ( is_admin() || is_feed() ) {
			return '';
		}
		$home    = array(
			'label' => __( 'Home', 'rankavia' ),
			'url'   => (string) home_url( '/' ),
		);
		$current = $this->current();
		if ( null === $current ) {
			return '';
		}
		$ancestors = is_singular() ? $this->ancestors( (int) get_queried_object_id() ) : array();
		$trail     = Trail_Builder::build( $home, $ancestors, $current );
		return Renderer::render( $trail );
	}

	/** Describe the current request's breadcrumb item.
	 *
	 * @return array{label:string,url:string}|null
	 */
	private function current(): ?array {
		if ( is_front_page() ) {
			return array(
				'label' => __( 'Home', 'rankavia' ),
				'url'   => (string) home_url( '/' ),
			);
		}
		if ( is_404() ) {
			return array(
				'label' => __( 'Page not found', 'rankavia' ),
				'url'   => '',
			);
		}
		if ( is_search() ) {
			return array(
				'label' => sprintf( /* translators: %s: search query. */ __( 'Search results for %s', 'rankavia' ), get_search_query( false ) ),
				'url'   => '',
			);
		}
		if ( is_home() ) {
			$id      = (int) get_option( 'page_for_posts' );
			$page    = $id > 0 ? get_post( $id ) : null;
			$visible = $this->is_public_post( $page );
			return array(
				'label' => $visible ? (string) get_the_title( $page ) : __( 'Blog', 'rankavia' ),
				'url'   => $visible ? (string) get_permalink( $page ) : (string) get_post_type_archive_link( 'post' ),
			);
		}
		if ( is_singular() ) {
			$post = get_post( (int) get_queried_object_id() );
			if ( ! $this->is_public_post( $post ) ) {
				return null;
			}
			return array(
				'label' => (string) get_the_title( $post ),
				'url'   => (string) get_permalink( $post ),
			);
		}
		if ( is_archive() ) {
			return array(
				'label' => (string) get_the_archive_title(),
				'url'   => '',
			);
		}
		return null;
	}

	/**
	 * Walk published, unprotected ancestors with cycle protection.
	 *
	 * @param int $id Current post ID.
	 * @return array<int,array{label:string,url:string}>
	 */
	private function ancestors( int $id ): array {
		$items = array();
		$seen  = array( $id => true );
		$post  = get_post( $id );
		for ( $depth = 0; $post instanceof WP_Post && $post->post_parent > 0 && $depth < 20; ++$depth ) {
			$parent_id = (int) $post->post_parent;
			if ( isset( $seen[ $parent_id ] ) ) {
				break;
			}
			$seen[ $parent_id ] = true;
			$post               = get_post( $parent_id );
			if ( ! $this->is_public_post( $post ) ) {
				break;
			}
			$items[] = array(
				'label' => (string) get_the_title( $post ),
				'url'   => (string) get_permalink( $post ),
			);
		}
		return array_reverse( $items );
	}

	/** Check that a post can appear in a public trail.
	 *
	 * @param mixed $post Post candidate.
	 * @return bool
	 */
	private function is_public_post( $post ): bool {
		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status || '' !== $post->post_password ) {
			return false;
		}
		$type = get_post_type_object( $post->post_type );
		return null !== $type && ! empty( $type->public );
	}
}
