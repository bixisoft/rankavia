<?php
/**
 * Bounded public HTML sitemap markup.
 *
 * @package Rankavia
 */

declare( strict_types=1 );

namespace Bixisoft\Rankavia\Sitemaps;

/** Renders only prechecked public URL/title pairs. */
final class Html_Renderer {
	/**
	 * Render at most 100 recent entries, with an honest bound in the heading.
	 *
	 * @param array<int,array{title:string,url:string}> $items Public entries.
	 * @return string
	 */
	public static function render( array $items ): string {
		$html  = '<nav class="rankavia-html-sitemap" aria-label="' . esc_attr__( 'HTML sitemap', 'rankavia' ) . '">';
		$html .= '<h2>' . esc_html__( 'Recent pages and posts (up to 100)', 'rankavia' ) . '</h2><ul>';
		foreach ( array_slice( $items, 0, 100 ) as $item ) {
			$html .= '<li><a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['title'] ) . '</a></li>';
		}
		return $html . '</ul></nav>';
	}
}
