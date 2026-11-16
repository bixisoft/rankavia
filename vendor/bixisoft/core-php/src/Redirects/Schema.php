<?php
/**
 * Tables, legacy migration and removal for redirects and the 404 log.
 *
 * @package Bixisoft\Rankavia\Vendor\Core
 */

declare( strict_types=1 );

namespace Bixisoft\Rankavia\Vendor\Core\Redirects;

/**
 * Shared by every package that manages redirects, so whichever one runs first creates the same
 * tables and migrates the old option exactly once.
 */
final class Schema {
	/** Current table version. */
	public const VERSION = '1';

	/** Option holding the installed table version. */
	public const VERSION_OPTION = 'rankavia_redirects_db';

	/** Option the first preview stored up to 100 rules in. */
	public const LEGACY_OPTION = 'rankavia_redirect_rules';

	/** Copy of the legacy option kept after a verified migration, for rollback. */
	public const BACKUP_OPTION = 'rankavia_redirect_rules_backup';

	/** Option recording the migration outcome. */
	public const MIGRATION_OPTION = 'rankavia_redirects_migration';

	/**
	 * Create or upgrade the tables and migrate legacy rules when needed. Cheap when current.
	 *
	 * @param string $home Site home URL, for validating legacy rules.
	 * @return void
	 */
	public static function ensure( string $home ): void {
		if ( self::VERSION === get_option( self::VERSION_OPTION ) ) {
			return;
		}
		self::install();
		update_option( self::VERSION_OPTION, self::VERSION, true );
		self::migrate( $home, new Store() );
	}

	/**
	 * Run dbDelta for both tables.
	 *
	 * @return void
	 */
	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		dbDelta( Store::schema( $charset ) );
		dbDelta( Not_Found_Log::schema( $charset ) );
	}

	/**
	 * Copy rules from the legacy option into the table, verify each one, then keep the option as a backup.
	 * Running it again is harmless.
	 *
	 * @param string $home  Site home URL.
	 * @param Store  $store Rule storage.
	 * @return string 'none', 'migrated', 'invalid' or 'incomplete'.
	 */
	public static function migrate( string $home, Store $store ): string {
		$stored = get_option( self::LEGACY_OPTION, null );
		if ( null === $stored ) {
			return 'none';
		}
		$rules = Rules::legacy( $stored, $home );
		if ( null === $rules ) {
			// Leave the option untouched so nothing is lost; the admin page reports it.
			update_option( self::MIGRATION_OPTION, 'invalid', false );
			return 'invalid';
		}
		foreach ( $rules as $rule ) {
			if ( null === $store->find( $rule['source'] ) ) {
				$store->add( $rule, 'migrated' );
			}
		}
		foreach ( $rules as $rule ) {
			if ( $store->find( $rule['source'] ) !== $rule ) {
				update_option( self::MIGRATION_OPTION, 'incomplete', false );
				return 'incomplete';
			}
		}
		add_option( self::BACKUP_OPTION, $stored, '', false );
		delete_option( self::LEGACY_OPTION );
		update_option( self::MIGRATION_OPTION, 'migrated:' . count( $rules ), false );
		return 'migrated';
	}

	/**
	 * Remove both tables and every redirect option. Only for a full uninstall when no other package
	 * still manages redirects.
	 *
	 * @return void
	 */
	public static function drop(): void {
		global $wpdb;
		foreach ( array( Store::table(), Not_Found_Log::table() ) as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Uninstall of this package's own tables.
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}
		foreach ( array( self::VERSION_OPTION, self::LEGACY_OPTION, self::BACKUP_OPTION, self::MIGRATION_OPTION, Not_Found_Log::DROPPED_OPTION ) as $option ) {
			delete_option( $option );
		}
		wp_clear_scheduled_hook( Service::PRUNE_HOOK );
	}
}
