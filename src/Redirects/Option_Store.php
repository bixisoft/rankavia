<?php
/**
 * Atomic persistence for the redirect rule option.
 *
 * @package Rankavia
 */

declare( strict_types=1 );

namespace Bixisoft\Rankavia\Redirects;

/** Uses WordPress's option row with a conditional value update. */
final class Option_Store {
	public const KEY = 'rankavia_redirect_rules';

	/**
	 * Read the authoritative row without a potentially stale object-cache value.
	 *
	 * @return array{raw:string|null,rules:mixed}
	 */
	public static function read(): array {
		global $wpdb;
		$sql = $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", self::KEY );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Prepared above; CAS requires an uncached authoritative row.
		$raw = $wpdb->get_var( $sql );
		return array(
			'raw'   => is_string( $raw ) ? $raw : null,
			'rules' => is_string( $raw ) ? maybe_unserialize( $raw ) : array(),
		);
	}

	/**
	 * Write only if the database value is still the one that was read.
	 *
	 * @param string|null                                              $expected Raw value read before validation.
	 * @param array<int,array{source:string,target:string,status:int}> $rules New rule set.
	 * @return bool True when saved or unchanged; false on a conflict or write failure.
	 */
	public static function compare_and_swap( ?string $expected, array $rules ): bool {
		if ( null === $expected ) {
			return add_option( self::KEY, $rules, '', false );
		}
		$next = maybe_serialize( $rules );
		if ( $next === $expected ) {
			return true;
		}
		global $wpdb;
		$sql = $wpdb->prepare(
			"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND BINARY option_value = BINARY %s",
			$next,
			self::KEY,
			$expected
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared -- Prepared above; update_option() cannot compare the old value atomically.
		$changed = $wpdb->query( $sql );
		if ( 1 !== $changed ) {
			return false;
		}
		wp_cache_delete( self::KEY, 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		return true;
	}
}
