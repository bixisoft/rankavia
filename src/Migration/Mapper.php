<?php
/**
 * Conservative per-post metadata mapping from supported SEO plugins.
 *
 * @package Rankavia
 */

declare( strict_types=1 );

namespace Bixisoft\Rankavia\Migration;

/** Pure mapping only; callers own database reads, conflict checks, and writes. */
final class Mapper {
	private const MAX_VALUE = 65536;
	private const TOKENS    = array( 'title', 'sep', 'sitename', 'excerpt', 'category', 'date', 'modified', 'author' );

	/** Map supported SEO metadata to Rankavia fields.
	 *
	 * @param string               $provider yoast or rankmath.
	 * @param array<string,string> $source   Raw, single-valued postmeta.
	 * @return array{values:array<string,string>,warnings:array<string>}
	 */
	public static function map( string $provider, array $source ): array {
		$result   = array(
			'values'   => array(),
			'warnings' => array(),
		);
		$provider = strtolower( str_replace( array( '-', '_' ), '', $provider ) );
		if ( ! in_array( $provider, array( 'yoast', 'rankmath' ), true ) ) {
			$result['warnings'][] = 'Unsupported source provider.';
			return $result;
		}
		$prefix      = 'yoast' === $provider ? '_yoast_wpseo_' : 'rank_math_';
		$fields      = 'yoast' === $provider
			? array(
				'title'                 => 'title',
				'metadesc'              => 'description',
				'canonical'             => 'canonical',
				'focuskw'               => 'focus_keyword',
				'opengraph-title'       => 'og_title',
				'opengraph-description' => 'og_description',
				'opengraph-image-id'    => 'og_image',
			)
			: array(
				'title'                => 'title',
				'description'          => 'description',
				'canonical_url'        => 'canonical',
				'focus_keyword'        => 'focus_keyword',
				'facebook_title'       => 'og_title',
				'facebook_description' => 'og_description',
				'facebook_image_id'    => 'og_image',
			);
		$known       = array_keys( $fields );
		$known       = array_merge( $known, 'yoast' === $provider ? array( 'meta-robots-noindex', 'meta-robots-nofollow' ) : array( 'robots' ) );
		$unsupported = false;
		foreach ( $source as $key => $raw ) {
			if ( ! is_string( $key ) || 0 !== strpos( $key, $prefix ) ) {
				continue;
			}
			$name = substr( $key, strlen( $prefix ) );
			if ( ! in_array( $name, $known, true ) ) {
				$unsupported = true;
				continue;
			}
			if ( ! is_string( $raw ) || strlen( $raw ) > self::MAX_VALUE ) {
				$result['warnings'][] = $key . ': invalid or oversized source value.';
				continue;
			}
			if ( '' === trim( $raw ) ) {
				$result['warnings'][] = $key . ': empty value skipped.';
				continue;
			}
			if ( 'robots' === $name ) {
				self::map_robots( $raw, $result );
				continue;
			}
			if ( 'meta-robots-noindex' === $name || 'meta-robots-nofollow' === $name ) {
				$target = 'meta-robots-noindex' === $name ? '_rankavia_noindex' : '_rankavia_nofollow';
				$valid  = 'meta-robots-noindex' === $name ? array(
					'1' => '1',
					'2' => '0',
				) : array(
					'1' => '1',
					'0' => '0',
				);
				if ( isset( $valid[ $raw ] ) ) {
					$result['values'][ $target ] = $valid[ $raw ];
				} elseif ( '0' !== $raw || 'meta-robots-noindex' !== $name ) {
					$result['warnings'][] = $key . ': invalid robots value.';
				}
				continue;
			}
			$target = '_rankavia_' . $fields[ $name ];
			if ( 'canonical' === $fields[ $name ] ) {
				$parts = wp_parse_url( $raw );
				if ( ! is_array( $parts ) || ! isset( $parts['scheme'], $parts['host'] ) || ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || preg_match( '/\s/', $raw ) ) {
					$result['warnings'][] = $key . ': unsafe or invalid URL.';
					continue;
				}
				$result['values'][ $target ] = $raw;
				continue;
			}
			if ( 'og_image' === $fields[ $name ] ) {
				if ( ! ctype_digit( $raw ) || 0 === (int) $raw ) {
					$result['warnings'][] = $key . ': expected a local attachment ID.';
					continue;
				}
				$result['values'][ $target ] = $raw;
				continue;
			}
			if ( 'focus_keyword' === $fields[ $name ] && 'rankmath' === $provider && false !== strpos( $raw, ',' ) ) {
				$raw                  = trim( explode( ',', $raw, 2 )[0] );
				$result['warnings'][] = $key . ': only the first focus keyword was mapped.';
			}
			if ( 'yoast' === $provider ) {
				$raw = preg_replace( '/%%([a-zA-Z_]+)%%/', '%$1%', $raw );
			}
			if ( preg_match_all( '/%([^%]+)%/', $raw, $matches ) ) {
				$unknown = array_diff( $matches[1], self::TOKENS );
				if ( array() !== $unknown ) {
					$result['warnings'][] = $key . ': unsupported template token.';
					continue;
				}
			}
			if ( false !== strpos( $raw, '%%' ) ) {
				$result['warnings'][] = $key . ': malformed template token.';
				continue;
			}
			// WordPress removes percent-encoded octets: protect approved %date%
			// and %category% tokens from being mistaken for %da and %ca.
			$placeholders = array();
			$protected    = (string) preg_replace_callback(
				'/%([A-Za-z0-9_]+)%/',
				static function ( array $token ) use ( &$placeholders, $raw ): string {
					$marker                  = 'RANKAVIATOKEN' . hash( 'sha256', $raw ) . 'X' . count( $placeholders ) . 'X';
					$placeholders[ $marker ] = $token[0];
					return $marker;
				},
				$raw
			);
			$clean        = strtr( trim( sanitize_text_field( $protected ) ), $placeholders );
			if ( '' === $clean ) {
				$result['warnings'][] = $key . ': empty sanitized value.';
				continue;
			}
			$result['values'][ $target ] = $clean;
		}
		if ( $unsupported ) {
			$result['warnings'][] = 'Unsupported source metadata was not mapped.';
		}
		return $result;
	}

	/** Add Rank Math robots directives to the mapped result.
	 *
	 * @param string                                                    $raw    Serialized source directives.
	 * @param array{values:array<string,string>,warnings:array<string>} $result Mapping result updated by reference.
	 * @return void
	 */
	private static function map_robots( string $raw, array &$result ): void {
		$robots = @unserialize( $raw, array( 'allowed_classes' => false ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- malformed source data is expected.
		if ( ! is_array( $robots ) || array_keys( $robots ) !== range( 0, count( $robots ) - 1 ) ) {
			$result['warnings'][] = 'rank_math_robots: malformed serialized array.';
			return;
		}
		$conflicts = array();
		foreach ( $robots as $item ) {
			if ( ! is_string( $item ) || ! in_array( $item, array( 'index', 'noindex', 'follow', 'nofollow' ), true ) ) {
				$result['warnings'][] = 'rank_math_robots: unsupported directive.';
				continue;
			}
			$target = in_array( $item, array( 'index', 'noindex' ), true ) ? '_rankavia_noindex' : '_rankavia_nofollow';
			$value  = in_array( $item, array( 'noindex', 'nofollow' ), true ) ? '1' : '0';
			if ( isset( $conflicts[ $target ] ) ) {
				continue;
			}
			if ( isset( $result['values'][ $target ] ) && $value !== $result['values'][ $target ] ) {
				unset( $result['values'][ $target ] );
				$conflicts[ $target ] = true;
				$result['warnings'][] = 'rank_math_robots: conflicting directives.';
				continue;
			}
			$result['values'][ $target ] = $value;
		}
	}
}
