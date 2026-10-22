<?php
/**
 * Accessible breadcrumb HTML and matching structured data.
 *
 * @package Rankavia
 */

declare( strict_types=1 );

namespace Bixisoft\Rankavia\Breadcrumbs;

/** Renders only the items supplied by the trail builder. */
final class Renderer {
	/**
	 * Render the visible trail and optional matching JSON-LD.
	 *
	 * @param array<int,array{label:string,url:string}> $items Visible trail.
	 * @param bool                                      $structured Whether to append JSON-LD beside the visible trail.
	 * @return string
	 */
	public static function render( array $items, bool $structured = true ): string {
		if ( empty( $items ) ) {
			return '';
		}
		$html = '<nav class="rankavia-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumbs', 'rankavia' ) . '"><ol>';
		$last = count( $items ) - 1;
		foreach ( $items as $index => $item ) {
			$html .= '<li>';
			if ( $index === $last || '' === $item['url'] ) {
				$html .= '<span' . ( $index === $last ? ' aria-current="page"' : '' ) . '>' . esc_html( $item['label'] ) . '</span>';
			} else {
				$html .= '<a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['label'] ) . '</a>';
			}
			$html .= '</li>';
		}
		$html .= '</ol></nav>';

		if ( ! $structured || count( $items ) < 2 ) {
			return $html;
		}
		$list = array();
		foreach ( $items as $index => $item ) {
			if ( '' === $item['url'] || ! wp_http_validate_url( $item['url'] ) ) {
				return $html;
			}
			$list[] = array(
				'@type'    => 'ListItem',
				'position' => $index + 1,
				'name'     => $item['label'],
				'item'     => $item['url'],
			);
		}
		$json = wp_json_encode(
			array(
				'@context'        => 'https://schema.org',
				'@type'           => 'BreadcrumbList',
				'itemListElement' => $list,
			),
			JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES
		);
		return is_string( $json ) ? $html . '<script type="application/ld+json">' . $json . '</script>' : $html;
	}
}
