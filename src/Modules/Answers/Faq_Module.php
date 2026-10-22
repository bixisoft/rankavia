<?php
/**
 * Authored FAQ block registration.
 *
 * @package Rankavia
 */

declare( strict_types=1 );

namespace Bixisoft\Rankavia\Modules\Answers;

use Bixisoft\Rankavia\Vendor\Core\Container;
use Bixisoft\Rankavia\Vendor\Core\Contracts\Module;
use Bixisoft\Rankavia\Answers\Faq_Renderer;
use WP_Post;

/** Registers one dynamic block and gates public FAQ structured data. */
final class Faq_Module implements Module {
	/** Get module identifier. @return string */
	public function id(): string {
		return 'faq';
	}

	/**
	 * Bind nothing.
	 *
	 * @param Container $container Shared container.
	 * @return void
	 */
	public function register( Container $container ): void {}

	/**
	 * Register the init hook.
	 *
	 * @param Container $container Shared container.
	 * @return void
	 */
	public function boot( Container $container ): void {
		add_action( 'init', array( $this, 'register_block' ) );
	}

	/** Register the block and its editor script. @return void */
	public function register_block(): void {
		wp_register_script( 'rankavia-faq-editor', RANKAVIA_URL . 'assets/faq-editor.js', array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-i18n', 'wp-block-editor' ), RANKAVIA_VERSION, true );
		register_block_type(
			'rankavia/faq',
			array(
				'api_version'     => '3',
				'attributes'      => array(
					'items' => array(
						'type'    => 'array',
						'default' => array(),
					),
				),
				'editor_script'   => 'rankavia-faq-editor',
				'render_callback' => array( $this, 'render' ),
			)
		);
	}

	/**
	 * Render authored entries.
	 *
	 * @param array<string,mixed> $attributes Block attributes.
	 * @return string
	 */
	public function render( array $attributes ): string {
		$items = Faq_Renderer::normalize( $attributes['items'] ?? array() );
		return Faq_Renderer::render( $items, $this->may_emit_schema() );
	}

	/** Check whether this request may include FAQPage schema. @return bool */
	public function may_emit_schema(): bool {
		if ( is_admin() || is_feed() || is_preview() || ! is_singular() || '0' === (string) get_option( 'blog_public', '1' ) ) {
			return false;
		}
		if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' ) ) {
			return false;
		}
		$id   = (int) get_queried_object_id();
		$post = $id > 0 ? get_post( $id ) : null;
		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status || '' !== $post->post_password || (int) get_the_ID() !== $id ) {
			return false;
		}
		$type = get_post_type_object( $post->post_type );
		if ( ! is_object( $type ) || empty( $type->public ) || '1' === (string) get_post_meta( $id, '_rankavia_noindex', true ) ) {
			return false;
		}
		$robots = apply_filters( 'wp_robots', array() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Respect the core robots policy chain.
		return ! ( is_array( $robots ) && ! empty( $robots['noindex'] ) );
	}
}
