<?php
/**
 * Builds the llms.txt text (llmstxt.org format) from plain arrays.
 *
 * @package Rankavia
 */

declare( strict_types=1 );

namespace Bixisoft\Rankavia\Llms;

/**
 * Pure: arrays in, one string out. No WordPress functions.
 */
final class Llms_Builder {

	/**
	 * Build the file.
	 *
	 * @param array<string, mixed>             $site     Keys name, summary and intro.
	 * @param array<int, array<string, mixed>> $sections Sections: a title and items, each with title, url and summary.
	 * @return string
	 */
	public static function build( array $site, array $sections ): string {
		$name    = self::plain( $site['name'] ?? '' );
		$summary = self::plain( $site['summary'] ?? '' );
		$intro   = self::plain( $site['intro'] ?? '' );

		$blocks = array( '# ' . $name );
		if ( '' !== $summary ) {
			$blocks[] = '> ' . $summary;
		}
		if ( '' !== $intro ) {
			$blocks[] = $intro;
		}

		foreach ( $sections as $section ) {
			$lines = array();
			$items = isset( $section['items'] ) && is_array( $section['items'] ) ? $section['items'] : array();
			foreach ( $items as $item ) {
				$line = is_array( $item ) ? self::item_line( $item ) : '';
				if ( '' !== $line ) {
					$lines[] = $line;
				}
			}

			$title = self::plain( $section['title'] ?? '' );
			if ( '' === $title || array() === $lines ) {
				continue;
			}
			$blocks[] = '## ' . $title . "\n\n" . implode( "\n", $lines );
		}

		return implode( "\n\n", $blocks ) . "\n";
	}

	/**
	 * One list line, or an empty string when the item has no title or no URL.
	 *
	 * @param array<string, mixed> $item Keys title, url and summary.
	 * @return string
	 */
	private static function item_line( array $item ): string {
		$title = self::plain( $item['title'] ?? '' );
		$url   = self::url( self::plain( $item['url'] ?? '' ) );
		if ( '' === $title || '' === $url ) {
			return '';
		}

		$line    = '- [' . str_replace( array( '[', ']' ), array( '\\[', '\\]' ), $title ) . '](' . $url . ')';
		$summary = self::plain( $item['summary'] ?? '' );
		return '' === $summary ? $line : $line . ': ' . $summary;
	}

	/**
	 * Plain single-line text: tags stripped, entities decoded, whitespace and newlines collapsed to single spaces.
	 *
	 * @param mixed $value Raw text or markup.
	 * @return string
	 */
	private static function plain( $value ): string {
		$text = is_scalar( $value ) ? (string) $value : '';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- The builder must stay free of WordPress functions so it is unit-testable.
		$text = html_entity_decode( strip_tags( $text ), ENT_QUOTES, 'UTF-8' );
		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}

	/**
	 * A URL that is safe inside a Markdown link: no whitespace, parentheses encoded.
	 *
	 * @param string $url Absolute URL.
	 * @return string
	 */
	private static function url( string $url ): string {
		return (string) preg_replace_callback(
			'/[\s()<>]/u',
			static function ( array $match ): string {
				return rawurlencode( $match[0] );
			},
			trim( $url )
		);
	}
}
