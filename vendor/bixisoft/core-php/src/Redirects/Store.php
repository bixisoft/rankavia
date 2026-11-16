<?php
/**
 * Indexed redirect rule storage.
 *
 * @package Bixisoft\Rankavia\Vendor\Core
 */

declare( strict_types=1 );

namespace Bixisoft\Rankavia\Vendor\Core\Redirects;

/**
 * One row per rule, keyed by a SHA-256 of the exact source path. Rule lookups are a single indexed
 * read, so the number of rules has no product limit; admin reads are paginated.
 */
class Store {
	/** Unprefixed table name, shared by every package that manages redirects. */
	public const TABLE = 'rankavia_redirects';

	/** Where a rule came from. */
	public const ORIGINS = array( 'manual', 'slug', 'not_found', 'migrated' );

	/** Seconds between last-used updates for one rule. */
	public const TOUCH_INTERVAL = 3600;

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
  source_hash char(64) NOT NULL,
  source varchar(200) NOT NULL,
  target varchar(2048) NOT NULL DEFAULT '',
  status smallint(5) unsigned NOT NULL,
  origin varchar(16) NOT NULL DEFAULT 'manual',
  created_at datetime NOT NULL,
  last_used datetime NULL DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY source_hash (source_hash)
) {$charset_collate};";
	}

	/**
	 * Lookup key for an exact source path.
	 *
	 * @param string $source Source path.
	 * @return string
	 */
	public static function hash( string $source ): string {
		return hash( 'sha256', $source );
	}

	/**
	 * The rule for an exact source path.
	 *
	 * @param string $source Source path.
	 * @return array{source:string,target:string,status:int}|null
	 */
	public function find( string $source ): ?array {
		$row = $this->row( $source );
		if ( null === $row ) {
			return null;
		}
		return array(
			'source' => $row['source'],
			'target' => $row['target'],
			'status' => $row['status'],
		);
	}

	/**
	 * The full stored row for an exact source path.
	 *
	 * @param string $source Source path.
	 * @return array{id:int,source:string,target:string,status:int,origin:string,created_at:string,last_used:string|null}|null
	 */
	public function row( string $source ): ?array {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom indexed table; a stale cache could serve a deleted redirect.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, source, target, status, origin, created_at, last_used FROM {$table} WHERE source_hash = %s LIMIT 1", self::hash( $source ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
		if ( ! is_array( $row ) || (string) $row['source'] !== $source ) {
			return null;
		}
		return self::normalize( $row );
	}

	/**
	 * Insert a validated rule. Fails when the source already has a rule.
	 *
	 * @param array{source:string,target:string,status:int} $rule   Rule from Rules::create().
	 * @param string                                        $origin One of ORIGINS.
	 * @return bool
	 */
	public function add( array $rule, string $origin = 'manual' ): bool {
		global $wpdb;
		if ( ! in_array( $origin, self::ORIGINS, true ) ) {
			return false;
		}
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Atomic insert on the unique key.
		$inserted = $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$table} (source_hash, source, target, status, origin, created_at) VALUES (%s, %s, %s, %d, %s, %s)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
				self::hash( $rule['source'] ),
				$rule['source'],
				$rule['target'],
				$rule['status'],
				$origin,
				gmdate( 'Y-m-d H:i:s' )
			)
		);
		return 1 === $inserted;
	}

	/**
	 * Delete the rule for a source and return what was deleted, for undo.
	 *
	 * @param string $source Source path.
	 * @return array{id:int,source:string,target:string,status:int,origin:string,created_at:string,last_used:string|null}|null
	 */
	public function delete( string $source ): ?array {
		global $wpdb;
		$row = $this->row( $source );
		if ( null === $row ) {
			return null;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		$deleted = $wpdb->delete( self::table(), array( 'id' => $row['id'] ), array( '%d' ) );
		return 1 === $deleted ? $row : null;
	}

	/**
	 * One page of rules, newest first.
	 *
	 * @param int    $page     1-based page.
	 * @param int    $per_page Rows per page (1–200).
	 * @param string $search   Optional substring of source or target.
	 * @return array<int,array{id:int,source:string,target:string,status:int,origin:string,created_at:string,last_used:string|null}>
	 */
	public function page( int $page, int $per_page, string $search = '' ): array {
		global $wpdb;
		$per_page = max( 1, min( 200, $per_page ) );
		$offset   = ( max( 1, $page ) - 1 ) * $per_page;
		$table    = self::table();
		if ( '' === $search ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, paginated.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, source, target, status, origin, created_at, last_used FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, $offset ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
		} else {
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, paginated.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, source, target, status, origin, created_at, last_used FROM {$table} WHERE source LIKE %s OR target LIKE %s ORDER BY id DESC LIMIT %d OFFSET %d", $like, $like, $per_page, $offset ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
		}
		return array_map( array( self::class, 'normalize' ), is_array( $rows ) ? $rows : array() );
	}

	/**
	 * Number of rules, optionally matching a search.
	 *
	 * @param string $search Optional substring of source or target.
	 * @return int
	 */
	public function count( string $search = '' ): int {
		global $wpdb;
		$table = self::table();
		if ( '' === $search ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table; no input.
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		}
		$like = '%' . $wpdb->esc_like( $search ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE source LIKE %s OR target LIKE %s", $like, $like ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
	}

	/**
	 * Point automatic (address-change) redirects at a page's new address, so moving a page twice
	 * never leaves a chain. Manual rules are left alone.
	 *
	 * @param string $old_target Previous destination URL.
	 * @param string $new_target New destination URL.
	 * @return int Rules updated.
	 */
	public function retarget( string $old_target, string $new_target ): int {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		$updated = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET target = %s WHERE target = %s AND origin = %s AND status IN (301, 302)", $new_target, $old_target, 'slug' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
		return is_int( $updated ) ? $updated : 0;
	}

	/**
	 * Record that a rule was used, at most once per TOUCH_INTERVAL.
	 *
	 * @param string $source Source path.
	 * @return void
	 */
	public function touch( string $source ): void {
		global $wpdb;
		$table = self::table();
		$now   = time();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Throttled single-row update.
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET last_used = %s WHERE source_hash = %s AND (last_used IS NULL OR last_used < %s)", gmdate( 'Y-m-d H:i:s', $now ), self::hash( $source ), gmdate( 'Y-m-d H:i:s', $now - self::TOUCH_INTERVAL ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
	}

	/**
	 * Cast a database row to typed values.
	 *
	 * @param array<string,mixed> $row Row from $wpdb.
	 * @return array{id:int,source:string,target:string,status:int,origin:string,created_at:string,last_used:string|null}
	 */
	public static function normalize( array $row ): array {
		return array(
			'id'         => (int) ( $row['id'] ?? 0 ),
			'source'     => (string) ( $row['source'] ?? '' ),
			'target'     => (string) ( $row['target'] ?? '' ),
			'status'     => (int) ( $row['status'] ?? 0 ),
			'origin'     => (string) ( $row['origin'] ?? 'manual' ),
			'created_at' => (string) ( $row['created_at'] ?? '' ),
			'last_used'  => isset( $row['last_used'] ) ? (string) $row['last_used'] : null,
		);
	}
}
