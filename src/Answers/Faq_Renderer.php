<?php
/**
 * Visible, authored FAQ and matching structured data.
 *
 * @package Rankavia
 */

declare( strict_types=1 );

namespace Bixisoft\Rankavia\Answers;

/** Keep visible answers and schema sourced from the same validated entries. */
final class Faq_Renderer {
	/**
	 * Normalize authored entries.
	 *
	 * @param mixed $raw Block items.
	 * @return array<int,array{question:string,answer:string}>
	 */
	public static function normalize( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$items = array();
		foreach ( array_slice( $raw, 0, 10 ) as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['question'], $row['answer'] ) || ! is_string( $row['question'] ) || ! is_string( $row['answer'] ) ) {
				continue;
			}
			if ( strlen( $row['question'] ) > 500 || strlen( $row['answer'] ) > 5000 ) {
				continue;
			}
			$question = trim( sanitize_text_field( $row['question'] ) );
			$answer   = trim( wp_kses_post( $row['answer'] ) );
			if ( '' !== $question && '' !== trim( wp_strip_all_tags( $answer ) ) ) {
				$items[] = array(
					'question' => $question,
					'answer'   => $answer,
				);
			}
		}
		return $items;
	}

	/**
	 * Render the visible list and optional matching schema.
	 *
	 * @param array<int,array{question:string,answer:string}> $items Valid entries.
	 * @param bool                                            $schema Whether schema is allowed.
	 * @return string
	 */
	public static function render( array $items, bool $schema ): string {
		if ( array() === $items ) {
			return '';
		}
		$html  = '<section class="rankavia-faq" aria-label="' . esc_attr__( 'Frequently asked questions', 'rankavia' ) . '"><dl>';
		$graph = array();
		foreach ( $items as $item ) {
			$html   .= '<dt>' . esc_html( $item['question'] ) . '</dt><dd>' . $item['answer'] . '</dd>';
			$graph[] = array(
				'@type'          => 'Question',
				'name'           => $item['question'],
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => html_entity_decode( wp_strip_all_tags( $item['answer'] ), ENT_QUOTES, 'UTF-8' ),
				),
			);
		}
		$html .= '</dl></section>';
		if ( $schema ) {
			$json = wp_json_encode(
				array(
					'@context'   => 'https://schema.org',
					'@type'      => 'FAQPage',
					'mainEntity' => $graph,
				),
				JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES
			);
			if ( is_string( $json ) && '' !== $json ) {
				$html .= '<script type="application/ld+json">' . $json . '</script>';
			}
		}
		return $html;
	}
}
