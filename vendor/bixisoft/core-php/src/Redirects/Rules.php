<?php
/**
 * Exact-path redirect validation.
 *
 * @package Bixisoft\Rankavia\Vendor\Core
 */

declare( strict_types=1 );

namespace Bixisoft\Rankavia\Vendor\Core\Redirects;

/**
 * Keeps redirects local, bounded and free of ambiguous path matching.
 *
 * Existing rules are reached through a lookup callback (source path => rule or null), so validation
 * works the same over an indexed table of any size as over a small in-memory list.
 */
final class Rules {
	/** Longest accepted source path. */
	public const SOURCE_MAX = 200;

	/** Longest accepted destination URL. */
	public const TARGET_MAX = 2048;

	/** Longest redirect chain followed when looking for loops. */
	public const CHAIN_MAX = 10;

	/** Accepted HTTP statuses. */
	public const STATUSES = array( 301, 302, 410 );

	/**
	 * Validate one rule against the site's home URL and the existing rules.
	 *
	 * @param string                                                                 $source Exact source path.
	 * @param string                                                                 $target Same-origin destination URL or path; ignored for 410.
	 * @param int                                                                    $status HTTP status.
	 * @param string                                                                 $home   Site home URL.
	 * @param callable(string): (array{source:string,target:string,status:int}|null) $lookup Existing rule for a source path.
	 * @return array{source:string,target:string,status:int}|null Null when invalid, duplicate or looping.
	 */
	public static function create( string $source, string $target, int $status, string $home, callable $lookup ): ?array {
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			return null;
		}
		$source = trim( $source );
		$target = trim( $target );
		if ( strlen( $source ) > self::SOURCE_MAX || ! self::safe_path( $source, $home ) ) {
			return null;
		}
		if ( null !== $lookup( $source ) ) {
			return null;
		}
		if ( 410 === $status ) {
			$target = '';
		} else {
			$target = self::target_url( $target, $home );
			if ( '' === $target || strlen( $target ) > self::TARGET_MAX ) {
				return null;
			}
			$target_path = (string) wp_parse_url( $target, PHP_URL_PATH );
			if ( $source === $target_path || self::cycles( $source, $target_path, $lookup ) ) {
				return null;
			}
		}
		return array(
			'source' => $source,
			'target' => $target,
			'status' => $status,
		);
	}

	/**
	 * Reject ambiguous or protected request paths.
	 *
	 * @param string $path Request/source path.
	 * @param string $home Site home URL.
	 * @return bool
	 */
	public static function safe_path( string $path, string $home ): bool {
		$base = (string) wp_parse_url( $home, PHP_URL_PATH );
		$base = '/' . trim( $base, '/' );
		if ( '/' === $base ) {
			$base = '';
		}
		if ( '' === $path || '/' !== $path[0] || 0 === strpos( $path, '//' ) || preg_match( '/[%?#\\\\\x00-\x1F]/', $path ) || false !== strpos( $path, '/./' ) || false !== strpos( $path, '/../' ) || '.' === basename( $path ) || '..' === basename( $path ) ) {
			return false;
		}
		if ( '' !== $base && 0 !== strpos( $path, $base . '/' ) && $path !== $base ) {
			return false;
		}
		$relative = strtolower( ltrim( substr( $path, strlen( $base ) ), '/' ) );
		foreach ( array( 'wp-admin', 'wp-json', 'wp-login.php', 'wp-register.php', 'xmlrpc.php', 'cart', 'checkout', 'my-account', 'wc-api' ) as $reserved ) {
			if ( $relative === $reserved || 0 === strpos( $relative, $reserved . '/' ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * The path part of a stored destination URL.
	 *
	 * @param string $target Destination URL.
	 * @return string
	 */
	public static function target_path( string $target ): string {
		return (string) wp_parse_url( $target, PHP_URL_PATH );
	}

	/**
	 * Resolve a local destination while enforcing the home origin.
	 *
	 * @param string $target Entered destination.
	 * @param string $home Site home URL.
	 * @return string Absolute same-origin URL, or '' when not allowed.
	 */
	public static function target_url( string $target, string $home ): string {
		if ( 0 === strpos( $target, '/' ) ) {
			if ( ! self::safe_path( $target, $home ) ) {
				return '';
			}
			$origin = wp_parse_url( $home );
			if ( ! is_array( $origin ) ) {
				return '';
			}
			return ( $origin['scheme'] ?? '' ) . '://' . ( $origin['host'] ?? '' ) . ( isset( $origin['port'] ) ? ':' . $origin['port'] : '' ) . $target;
		}
		$parts = wp_parse_url( $target );
		$site  = wp_parse_url( $home );
		if ( ! is_array( $parts ) || ! is_array( $site ) || ! in_array( $parts['scheme'] ?? '', array( 'http', 'https' ), true ) || strtolower( (string) ( $parts['scheme'] ?? '' ) ) !== strtolower( (string) ( $site['scheme'] ?? '' ) ) || strtolower( (string) ( $parts['host'] ?? '' ) ) !== strtolower( (string) ( $site['host'] ?? '' ) ) || ( $parts['port'] ?? null ) !== ( $site['port'] ?? null ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['query'] ) || isset( $parts['fragment'] ) || ! self::safe_path( (string) ( $parts['path'] ?? '/' ), $home ) ) {
			return '';
		}
		return $target;
	}

	/**
	 * Check a legacy rule list (the old option format) and return it in creation order, or null when any
	 * entry is malformed, duplicated or looping. Used once, to migrate stored rules.
	 *
	 * @param mixed  $stored Option value.
	 * @param string $home   Site home URL.
	 * @return array<int,array{source:string,target:string,status:int}>|null
	 */
	public static function legacy( $stored, string $home ): ?array {
		if ( ! is_array( $stored ) ) {
			return null;
		}
		$valid = array();
		foreach ( $stored as $rule ) {
			if ( ! is_array( $rule ) || ! isset( $rule['source'], $rule['target'], $rule['status'] ) || ! is_string( $rule['source'] ) || ! is_string( $rule['target'] ) || ! is_int( $rule['status'] ) ) {
				return null;
			}
			$checked = self::create(
				$rule['source'],
				$rule['target'],
				$rule['status'],
				$home,
				static function ( string $source ) use ( $valid ): ?array {
					return $valid[ $source ] ?? null;
				}
			);
			if ( null === $checked || $checked !== $rule ) {
				return null;
			}
			$valid[ $checked['source'] ] = $checked;
		}
		return array_values( $valid );
	}

	/**
	 * Reject loops and chains longer than CHAIN_MAX.
	 *
	 * @param string                                                                 $source New source.
	 * @param string                                                                 $next   New target path.
	 * @param callable(string): (array{source:string,target:string,status:int}|null) $lookup Existing rule for a source path.
	 * @return bool
	 */
	private static function cycles( string $source, string $next, callable $lookup ): bool {
		$seen = array( $source => true );
		for ( $depth = 0; $depth < self::CHAIN_MAX; ++$depth ) {
			if ( isset( $seen[ $next ] ) ) {
				return true;
			}
			$seen[ $next ] = true;
			$rule          = $lookup( $next );
			if ( null === $rule || ! in_array( (int) $rule['status'], array( 301, 302 ), true ) ) {
				return false;
			}
			$next = self::target_path( (string) $rule['target'] );
		}
		return true;
	}
}
