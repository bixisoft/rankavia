<?php
/**
 * Exact-path redirect validation and lookup.
 *
 * @package Rankavia
 */

declare( strict_types=1 );

namespace Bixisoft\Rankavia\Redirects;

/** Keeps redirects local, bounded and free of ambiguous path matching. */
final class Rules {
	public const LIMIT = 100;

	/**
	 * Validate one rule against the site's home URL and existing rules.
	 *
	 * @param string                         $source Exact source path.
	 * @param string                         $target Same-origin destination URL or path.
	 * @param int                            $status HTTP status.
	 * @param string                         $home Site home URL.
	 * @param array<int,array<string,mixed>> $rules Existing rules.
	 * @return array{source:string,target:string,status:int}|null
	 */
	public static function create( string $source, string $target, int $status, string $home, array $rules ): ?array {
		if ( self::LIMIT <= count( $rules ) || ! in_array( $status, array( 301, 302, 410 ), true ) ) {
			return null;
		}
		$source = trim( $source );
		$target = trim( $target );
		if ( ! self::safe_path( $source, $home ) || strlen( $source ) > 200 ) {
			return null;
		}
		foreach ( $rules as $rule ) {
			if ( ( $rule['source'] ?? '' ) === $source ) {
				return null;
			}
		}
		if ( 410 === $status ) {
			$target = '';
		} else {
			$target = self::target_url( $target, $home );
			if ( '' === $target || strlen( $target ) > 2048 ) {
				return null;
			}
			$target_path = (string) wp_parse_url( $target, PHP_URL_PATH );
			if ( $source === $target_path || self::cycles( $source, $target_path, $rules ) ) {
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
	 * Resolve a local destination while enforcing the home origin.
	 *
	 * @param string $target Entered destination.
	 * @param string $home Site home URL.
	 * @return string
	 */
	private static function target_url( string $target, string $home ): string {
		if ( 0 === strpos( $target, '/' ) ) {
			if ( ! self::safe_path( $target, $home ) ) {
				return '';
			}
			$origin = wp_parse_url( $home );
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
	 * Recheck persisted data before either display or execution; fail closed on corruption.
	 *
	 * @param mixed  $stored Option value.
	 * @param string $home Site home URL.
	 * @return array<int,array{source:string,target:string,status:int}>|null
	 */
	public static function stored( $stored, string $home ): ?array {
		if ( ! is_array( $stored ) || count( $stored ) > self::LIMIT ) {
			return null;
		}
		$valid = array();
		foreach ( $stored as $rule ) {
			if ( ! is_array( $rule ) || ! isset( $rule['source'], $rule['target'], $rule['status'] ) || ! is_string( $rule['source'] ) || ! is_string( $rule['target'] ) || ! is_int( $rule['status'] ) ) {
				return null;
			}
			$checked = self::create( $rule['source'], $rule['target'], $rule['status'], $home, $valid );
			if ( null === $checked || $checked !== $rule ) {
				return null;
			}
			$valid[] = $checked;
		}
		return $valid;
	}

	/**
	 * Reject loops and chains that exceed the traversal bound.
	 *
	 * @param string                         $source New source.
	 * @param string                         $next New target path.
	 * @param array<int,array<string,mixed>> $rules Existing rules.
	 * @return bool
	 */
	private static function cycles( string $source, string $next, array $rules ): bool {
		$seen = array( $source => true );
		for ( $depth = 0; $depth < 10; ++$depth ) {
			if ( isset( $seen[ $next ] ) ) {
				return true;
			}
			$seen[ $next ] = true;
			$follow        = null;
			foreach ( $rules as $rule ) {
				if ( ( $rule['source'] ?? '' ) === $next && in_array( (int) ( $rule['status'] ?? 0 ), array( 301, 302 ), true ) ) {
					$follow = (string) wp_parse_url( (string) $rule['target'], PHP_URL_PATH );
					break;
				}
			}
			if ( null === $follow ) {
				return false;
			}
			$next = $follow;
		}
		return true;
	}
}
