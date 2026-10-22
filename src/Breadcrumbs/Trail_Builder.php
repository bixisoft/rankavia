<?php
/**
 * Pure breadcrumb item construction.
 *
 * @package Rankavia
 */

declare( strict_types=1 );

namespace Bixisoft\Rankavia\Breadcrumbs;

/** Keeps one ordered trail for both visible links and structured data. */
final class Trail_Builder {
	/**
	 * Normalize an ordered breadcrumb trail.
	 *
	 * @param array{label:string,url:string}            $home Home item.
	 * @param array<int,array{label:string,url:string}> $ancestors Public parent items, oldest first.
	 * @param array{label:string,url:string}            $current Current item.
	 * @return array<int,array{label:string,url:string}>
	 */
	public static function build( array $home, array $ancestors, array $current ): array {
		$items = array();
		foreach ( array_merge( array( $home ), array_slice( $ancestors, 0, 20 ), array( $current ) ) as $item ) {
			if ( '' === trim( $item['label'] ) ) {
				continue;
			}
			if ( ! empty( $items ) && $items[ count( $items ) - 1 ]['url'] === $item['url'] && '' !== $item['url'] ) {
				$items[ count( $items ) - 1 ] = $item;
				continue;
			}
			$items[] = $item;
		}
		return $items;
	}
}
