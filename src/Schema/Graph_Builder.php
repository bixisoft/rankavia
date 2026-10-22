<?php
/**
 * Small, evidence-based JSON-LD graph.
 *
 * @package Rankavia
 */

declare( strict_types=1 );

namespace Bixisoft\Rankavia\Schema;

/**
 * Builds only entities supported by supplied site and page facts.
 */
final class Graph_Builder {

	/**
	 * Build a graph from explicit site and page facts.
	 *
	 * @param array<string, mixed> $facts Site and current-page facts.
	 * @return array<string, mixed> Empty when a usable site or page URL is absent.
	 */
	public function build( array $facts ): array {
		$home = $this->url( $facts['home_url'] ?? '' );
		$page = $this->url( $facts['page_url'] ?? '' );
		if ( '' === $home || '' === $page ) {
			return array();
		}

		$home    = trailingslashit( $home );
		$site_id = $home . '#website';
		$graph   = array();
		$site    = array(
			'@type' => 'WebSite',
			'@id'   => $site_id,
			'url'   => $home,
		);
		$name    = $this->plain( $facts['site_name'] ?? '' );
		if ( '' !== $name ) {
			$site['name'] = $name;
		}
		$description = $this->plain( $facts['site_description'] ?? '' );
		if ( '' !== $description ) {
			$site['description'] = $description;
		}

		$organization = $this->plain( $facts['organization_name'] ?? '' );
		if ( 'organization' === ( $facts['site_type'] ?? '' ) && '' !== $organization ) {
			$org_id = $home . '#organization';
			$org    = array(
				'@type' => 'Organization',
				'@id'   => $org_id,
				'name'  => $organization,
				'url'   => $home,
			);
			$logo   = $this->url( $facts['organization_logo'] ?? '' );
			if ( '' !== $logo ) {
				$org['logo'] = $logo;
			}
			$profiles = array();
			foreach ( is_array( $facts['social_profiles'] ?? null ) ? $facts['social_profiles'] : array() as $profile ) {
				$url = $this->url( $profile );
				if ( '' !== $url ) {
					$profiles[] = $url;
				}
			}
			if ( ! empty( $profiles ) ) {
				$org['sameAs'] = array_values( array_unique( $profiles ) );
			}
			$graph[]           = $org;
			$site['publisher'] = array( '@id' => $org_id );
		}

		$graph[] = $site;
		$webpage = array(
			'@type'    => 'WebPage',
			'@id'      => $page . '#webpage',
			'url'      => $page,
			'isPartOf' => array( '@id' => $site_id ),
		);
		$title   = $this->plain( $facts['page_title'] ?? '' );
		if ( '' !== $title ) {
			$webpage['name'] = $title;
		}
		$graph[] = $webpage;

		return array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		);
	}

	/**
	 * Normalize a plain-text value.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private function plain( $value ): string {
		return is_string( $value ) ? trim( wp_strip_all_tags( $value, true ) ) : '';
	}

	/**
	 * Accept an absolute HTTP URL.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private function url( $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$value = trim( $value );
		$parts = wp_parse_url( $value );
		if ( ! is_array( $parts ) || ! in_array( strtolower( (string) ( $parts['scheme'] ?? '' ) ), array( 'http', 'https' ), true ) || empty( $parts['host'] ) ) {
			return '';
		}
		return $value;
	}
}
