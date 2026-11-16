<?php
/**
 * Private, grouped log of "page not found" requests.
 *
 * @package Bixisoft\Rankavia\Vendor\Core
 */

declare( strict_types=1 );

namespace Bixisoft\Rankavia\Vendor\Core\Redirects;

/**
 * Stores only the requested path, a hit count and first/last seen times. No IP address, query
 * string, cookie, referrer or user agent is kept. Growth is capped; once full, new paths are only
 * counted as dropped. Rows unseen for RETENTION_DAYS are pruned.
 */
class Not_Found_Log {
	/** Unprefixed table name. */
	public const TABLE = 'rankavia_404_log';

	/** Most distinct paths kept. */
	public const MAX_ROWS = 10000;

	/** Days a path is kept after it was last seen. */
	public const RETENTION_DAYS = 30;

	/** Option counting hits on new paths that were not stored because the log was full. */
	public const DROPPED_OPTION = 'rankavia_404_dropped';

	/** Cache key for the row count used by the cap check. */
	private const COUNT_CACHE = 'rankavia_404_rows';

	/**
	 * Fully prefixed table name.
	 *
	 * @return string
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Table definition for dbDelta.
	 *
	 * @param string $charset_collate Charset clause from $wpdb.
	 * @return string
	 */
	public static function schema( string $charset_collate ): string {
		$table = self::table();
		return "CREATE TABLE {$table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  path_hash char(64) NOT NULL,
  path varchar(200) NOT NULL,
  hits bigint(20) unsigned NOT NULL DEFAULT 1,
  first_seen datetime NOT NULL,
  last_seen datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY path_hash (path_hash),
  KEY last_seen (last_seen)
) {$charset_collate};";
	}

	/**
	 * Count one missing request for a path that already passed Rules::safe_path().
	 *
	 * @param string $path Request path, without query string.
	 * @return string 'counted', 'added' or 'dropped'.
	 */
	public function record( string $path ): string {
		global $wpdb;
		$table = self::table();
		$now   = gmdate( 'Y-m-d H:i:s' );
		$hash  = hash( 'sha256', $path );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One indexed counter update.
		$updated = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET hits = hits + 1, last_seen = %s WHERE path_hash = %s", $now, $hash ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
		if ( is_int( $updated ) && $updated > 0 ) {
			return 'counted';
		}
		if ( $this->rows() >= self::MAX_ROWS ) {
			update_option( self::DROPPED_OPTION, $this->dropped() + 1, false );
			return 'dropped';
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Atomic insert on the unique key.
		$inserted = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$table} (path_hash, path, hits, first_seen, last_seen) VALUES (%s, %s, 1, %s, %s)", $hash, $path, $now, $now ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
		if ( 1 === $inserted ) {
			wp_cache_delete( self::COUNT_CACHE, 'rankavia' );
			return 'added';
		}
		return 'counted';
	}

	/**
	 * Distinct paths stored, cached briefly so the cap check costs no query on most requests.
	 *
	 * @return int
	 */
	public function rows(): int {
		$cached = wp_cache_get( self::COUNT_CACHE, 'rankavia' );
		if ( is_int( $cached ) ) {
			return $cached;
		}
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table; cached just above.
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		wp_cache_set( self::COUNT_CACHE, $count, 'rankavia', 300 );
		return $count;
	}

	/**
	 * Hits on new paths that were not stored because the log was full.
	 *
	 * @return int
	 */
	public function dropped(): int {
		return (int) get_option( self::DROPPED_OPTION, 0 );
	}

	/**
	 * One page of paths, most hits first.
	 *
	 * @param int $page     1-based page.
	 * @param int $per_page Rows per page (1–200).
	 * @return array<int,array{path:string,hits:int,first_seen:string,last_seen:string}>
	 */
	public function page( int $page, int $per_page ): array {
		global $wpdb;
		$per_page = max( 1, min( 200, $per_page ) );
		$offset   = ( max( 1, $page ) - 1 ) * $per_page;
		$table    = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, paginated.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT path, hits, first_seen, last_seen FROM {$table} ORDER BY hits DESC, last_seen DESC LIMIT %d OFFSET %d", $per_page, $offset ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
		$out  = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$out[] = array(
				'path'       => (string) $row['path'],
				'hits'       => (int) $row['hits'],
				'first_seen' => (string) $row['first_seen'],
				'last_seen'  => (string) $row['last_seen'],
			);
		}
		return $out;
	}

	/**
	 * Remove one path from the log (after it was fixed or dismissed).
	 *
	 * @param string $path Logged path.
	 * @return bool
	 */
	public function forget( string $path ): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		$deleted = $wpdb->delete( self::table(), array( 'path_hash' => hash( 'sha256', $path ) ), array( '%s' ) );
		wp_cache_delete( self::COUNT_CACHE, 'rankavia' );
		return is_int( $deleted ) && $deleted > 0;
	}

	/**
	 * Empty the log and reset the dropped counter.
	 *
	 * @return void
	 */
	public function clear(): void {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table; no input.
		$wpdb->query( "DELETE FROM {$table}" );
		delete_option( self::DROPPED_OPTION );
		wp_cache_delete( self::COUNT_CACHE, 'rankavia' );
	}

	/**
	 * Delete paths not seen for RETENTION_DAYS.
	 *
	 * @return int Rows removed.
	 */
	public function prune(): int {
		global $wpdb;
		$table  = self::table();
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - self::RETENTION_DAYS * DAY_IN_SECONDS );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Scheduled retention cleanup.
		$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE last_seen < %s", $cutoff ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
		wp_cache_delete( self::COUNT_CACHE, 'rankavia' );
		return is_int( $deleted ) ? $deleted : 0;
	}
}
