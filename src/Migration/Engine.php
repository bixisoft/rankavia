<?php
/** Transactional, insert-only migration with signed previews and durable undo.
 *
 * @package Rankavia
 */

declare( strict_types=1 );
namespace Bixisoft\Rankavia\Migration;

/** The source plugin is never changed or deactivated. */
final class Engine {
	/** Native wpdb retry policy restored after every transactional operation.
	 *
	 * @var int|null
	 */
	private ?int $retry_limit = null;

	public const JOURNAL_PREFIX = 'rankavia_migration_';
	/** Return the allowlisted metadata prefix for a source provider.
	 *
	 * @param string $provider Source plugin identifier.
	 * @return string Metadata prefix.
	 * @throws \RuntimeException When the provider is unsupported.
	 */
	public static function prefix( string $provider ): string {
		$prefixes = array(
			'yoast'    => '_yoast_wpseo_',
			'rankmath' => 'rank_math_',
		);
		if ( ! isset( $prefixes[ $provider ] ) ) {
			throw new \RuntimeException( 'Choose Yoast or Rank Math.' );
		}
		return $prefixes[ $provider ];
	}

	/** Discover editable posts and pages containing source metadata.
	 *
	 * @param string $provider Source plugin identifier.
	 * @param int    $after    Return IDs greater than this value.
	 * @return array<int,int> Post IDs.
	 * @throws \RuntimeException When authorization or discovery fails.
	 */
	public function discover( string $provider, int $after = 0 ): array {
		$this->authorize();
		global $wpdb;
		$like = $wpdb->esc_like( self::prefix( $provider ) ) . '%';
		$ids  = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT m.post_id FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID=m.post_id WHERE m.meta_key LIKE %s AND m.post_id>%d AND p.post_type IN ('post','page') AND p.post_status NOT IN ('trash','auto-draft') ORDER BY m.post_id LIMIT 20", $like, max( 0, $after ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- WordPress-owned table names; values prepared.
		if ( ! is_array( $ids ) || '' !== $wpdb->last_error ) {
			throw new \RuntimeException( 'Source discovery failed.' );
		}
		return array_values(
			array_filter(
				array_map( 'intval', $ids ),
				static function ( int $id ): bool {
					return current_user_can( 'edit_post', $id );
				}
			)
		);
	}

	/** Build a read-only preview bound to the selected posts and current user.
	 *
	 * @param string         $provider Source plugin identifier.
	 * @param array<int,int> $ids      Post IDs to preview.
	 * @return array<string,mixed> Preview details and signed token.
	 * @throws \RuntimeException When authorization or validation fails.
	 */
	public function preview( string $provider, array $ids ): array {
		$this->authorize();
		$ids           = $this->ids( $ids );
		$plan          = $this->plan( $provider, $ids, false );
		$payload       = base64_encode(
			(string) wp_json_encode(
				array(
					'provider' => $provider,
					'ids'      => $ids,
					'digest'   => $plan['digest'],
					'expires'  => time() + 900,
					'user'     => get_current_user_id(),
					'blog'     => get_current_blog_id(),
				)
			)
		);
		$plan['token'] = $payload . '.' . hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );
		unset( $plan['digest'] );
		return $plan;
	}

	/** Apply the exact preview and save its undo receipt with the metadata.
	 *
	 * @param string $token Signed preview token.
	 * @return string Receipt identifier.
	 * @throws \RuntimeException When authorization or applying the preview fails.
	 * @throws \Throwable When a transactional write fails.
	 */
	public function apply( string $token ): string {
		$this->authorize();
		$data    = $this->verify( $token );
		$lock    = $this->begin();
		$changed = array();
		try {
			global $wpdb;
			$plan = $this->plan( $data['provider'], $data['ids'], true );
			if ( ! hash_equals( $data['digest'], $plan['digest'] ) ) {
				throw new \RuntimeException( 'Source or destination changed. Preview again.' );
			}
			if ( array() === $plan['changes'] ) {
				throw new \RuntimeException( 'No supported empty destination fields to import.' );
			}
			$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( self::JOURNAL_PREFIX ) . '%' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal table name, prepared prefix.
			if ( $count >= 50 || '' !== $wpdb->last_error ) {
				throw new \RuntimeException( 'Migration receipt limit reached or unavailable. Preserve/export existing receipts before continuing.' );
			}
			foreach ( $plan['changes'] as $change ) {
				$ok = $wpdb->insert(
					$wpdb->postmeta,
					array(
						'post_id'    => $change['post_id'],
						'meta_key'   => $change['key'],
						'meta_value' => $change['value'],
					)
				);
				if ( 1 !== $ok ) {
					throw new \RuntimeException( 'Metadata write failed; nothing was committed.' );
				}
				$change['meta_id'] = (int) $wpdb->insert_id;
				$changed[]         = $change;
			}
			$id      = bin2hex( random_bytes( 16 ) );
			$journal = array(
				'status'   => 'applied',
				'provider' => $data['provider'],
				'user'     => get_current_user_id(),
				'created'  => time(),
				'changes'  => $changed,
			);
			if ( 1 !== $wpdb->insert(
				$wpdb->options,
				array(
					'option_name'  => self::JOURNAL_PREFIX . $id,
					'option_value' => maybe_serialize( $journal ),
					'autoload'     => 'no',
				)
			) ) {
				throw new \RuntimeException( 'Undo receipt could not be saved; nothing was committed.' );
			}
			$this->commit();
			$this->clear( $changed );
			return $id;
		} catch ( \Throwable $error ) {
			$wpdb->query( 'ROLLBACK' );
			throw $error;
		} finally {
			$this->unlock( $lock );
		}
	}

	/** Undo the job only when every inserted value remains untouched.
	 *
	 * @param string $id Receipt identifier.
	 * @return void
	 * @throws \RuntimeException When authorization or rollback fails.
	 * @throws \Throwable When a transactional write fails.
	 */
	public function rollback( string $id ): void {
		$this->authorize();
		if ( 1 !== preg_match( '/\A[a-f0-9]{32}\z/D', $id ) ) {
			throw new \RuntimeException( 'Invalid migration receipt.' );
		}
		$lock = $this->begin();
		try {
			global $wpdb;
			$row     = $wpdb->get_row( $wpdb->prepare( "SELECT option_id,option_value FROM {$wpdb->options} WHERE option_name=%s FOR UPDATE", self::JOURNAL_PREFIX . $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- WordPress table name, prepared receipt key.
			$journal = is_array( $row ) ? unserialize( $row['option_value'], array( 'allowed_classes' => false ) ) : null; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- Private internally generated receipt, classes disabled.
			if ( ! is_array( $journal ) || ! isset( $journal['changes'], $journal['status'] ) || ! is_array( $journal['changes'] ) || count( $journal['changes'] ) > 180 ) {
				throw new \RuntimeException( 'Undo receipt unavailable or invalid.' );
			}
			if ( 'rolled_back' === $journal['status'] ) {
				$this->commit();
				return;
			}
			if ( 'applied' !== $journal['status'] ) {
				throw new \RuntimeException( 'Receipt is not an applied migration.' );
			}
			foreach ( $journal['changes'] as $change ) {
				$this->post( (int) $change['post_id'], true );
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT meta_id,meta_value FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key=%s FOR UPDATE", $change['post_id'], $change['key'] ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- WordPress table name; values prepared.
				if ( ! is_array( $rows ) || count( $rows ) !== 1 || (int) $rows[0]['meta_id'] !== (int) $change['meta_id'] || $rows[0]['meta_value'] !== $change['value'] ) {
					throw new \RuntimeException( 'An imported field was edited, removed or duplicated. No fields were rolled back.' );
				}
			}
			foreach ( $journal['changes'] as $change ) {
				if ( 1 !== $wpdb->delete( $wpdb->postmeta, array( 'meta_id' => $change['meta_id'] ) ) ) {
					throw new \RuntimeException( 'Undo write failed; no fields were rolled back.' );
				}
			}
			$journal['status'] = 'rolled_back';
			if ( 1 !== $wpdb->update( $wpdb->options, array( 'option_value' => maybe_serialize( $journal ) ), array( 'option_id' => $row['option_id'] ) ) ) {
				throw new \RuntimeException( 'Undo receipt update failed; no fields were rolled back.' );
			}
			$this->commit();
			$this->clear( $journal['changes'] );
		} catch ( \Throwable $error ) {
			$wpdb->query( 'ROLLBACK' );
			throw $error;
		} finally {
			$this->unlock( $lock );
		}
	}

	/** List recent migration receipts without exposing source content.
	 *
	 * @return array<int,array<string,mixed>> Receipt summaries.
	 * @throws \RuntimeException When authorization fails.
	 */
	public function receipts(): array {
		$this->authorize();
		global $wpdb;
		$rows   = $wpdb->get_results( $wpdb->prepare( "SELECT option_name,option_value FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_id DESC LIMIT 50", $wpdb->esc_like( self::JOURNAL_PREFIX ) . '%' ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Prepared internal prefix.
		$result = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$data = unserialize( $row['option_value'], array( 'allowed_classes' => false ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- Private receipt, classes disabled.
			if ( is_array( $data ) && isset( $data['status'], $data['provider'], $data['created'], $data['changes'] ) ) {
				$result[] = array(
					'id'       => substr( $row['option_name'], strlen( self::JOURNAL_PREFIX ) ),
					'status'   => $data['status'],
					'provider' => $data['provider'],
					'created'  => $data['created'],
					'count'    => count( $data['changes'] ),
				);
			}
		}
		return $result;
	}

	/** Build a migration plan from current source and destination metadata.
	 *
	 * @param string         $provider Source plugin identifier.
	 * @param array<int,int> $ids      Post IDs to inspect.
	 * @param bool           $lock     Lock rows for a mutating operation.
	 * @return array<string,mixed> Changes, warnings, and digest.
	 * @throws \RuntimeException When metadata cannot be read safely.
	 */
	private function plan( string $provider, array $ids, bool $lock ): array {
		global $wpdb;
		$source_prefix = self::prefix( $provider );
		$snapshot      = array();
		$changes       = array();
		$warnings      = array();
		foreach ( $ids as $id ) {
			$this->post( $id, $lock );
			$sql  = $wpdb->prepare( "SELECT meta_id,meta_key,meta_value FROM {$wpdb->postmeta} WHERE post_id=%d AND (meta_key LIKE %s OR meta_key LIKE %s) ORDER BY meta_id", $id, $wpdb->esc_like( $source_prefix ) . '%', $wpdb->esc_like( '_rankavia_' ) . '%' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Prepared values, WP table.
			$rows = $wpdb->get_results( $sql . ( $lock ? ' FOR UPDATE' : '' ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query prepared immediately above; fixed lock suffix only.
			if ( ! is_array( $rows ) || '' !== $wpdb->last_error || strlen( serialize( $rows ) ) > 131072 ) {
				throw new \RuntimeException( 'Metadata unavailable or too large for a bounded batch.' );
			}
			$snapshot[ $id ] = $rows;
			$source          = array();
			$existing        = array();
			$duplicate       = false;
			foreach ( $rows as $row ) {
				$key = $row['meta_key'];
				if ( 0 === strpos( $key, $source_prefix ) ) {
					$duplicate      = $duplicate || array_key_exists( $key, $source );
					$source[ $key ] = $row['meta_value'];
				} else {
					$existing[ $key ] = true;
				}
			}
			if ( $duplicate ) {
				$warnings[] = $id . ': duplicate source metadata; post skipped.';
				continue;
			}
			$mapped = Mapper::map( $provider, $source );
			foreach ( $mapped['warnings'] as $warning ) {
				$warnings[] = $id . ': ' . $warning;
			}
			foreach ( $mapped['values'] as $key => $value ) {
				if ( isset( $existing[ $key ] ) ) {
					$warnings[] = $id . ': ' . $key . ' already exists; preserved.';
					continue;
				}
				if ( '_rankavia_og_image' === $key && ! wp_attachment_is_image( (int) $value ) ) {
					$warnings[] = $id . ': social image is not an existing local image attachment; skipped.';
					continue;
				}
				$changes[] = array(
					'post_id' => $id,
					'key'     => $key,
					'value'   => $value,
				);
			}
		}
		return array(
			'changes'  => $changes,
			'warnings' => $warnings,
			'digest'   => hash( 'sha256', serialize( array( $provider, $snapshot, $changes, $warnings ) ) ),
		);
	}

	/** Validate scope and current per-post authority.
	 *
	 * @param int  $id   Post ID.
	 * @param bool $lock Whether to lock the post row.
	 * @return void
	 * @throws \RuntimeException When the post is unavailable or not editable.
	 */
	private function post( int $id, bool $lock ): void {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT post_type,post_status FROM {$wpdb->posts} WHERE ID=%d", $id ) . ( $lock ? ' FOR UPDATE' : '' ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- WP table name is trusted; fixed FOR UPDATE suffix follows the prepared query.
		if ( ! is_array( $row ) || ! in_array( $row['post_type'], array( 'post', 'page' ), true ) || in_array( $row['post_status'], array( 'trash', 'auto-draft' ), true ) || ! current_user_can( 'edit_post', $id ) ) {
			throw new \RuntimeException( 'Post unavailable or not editable by this user.' );
		}
	}

	/** Validate, deduplicate, and sort a bounded set of post IDs.
	 *
	 * @param array<int,int> $ids Candidate post IDs.
	 * @return array<int,int> Validated post IDs.
	 * @throws \RuntimeException When the list is empty, too large, or invalid.
	 */
	private function ids( array $ids ): array {
		if ( array() === $ids || count( $ids ) > 20 ) {
			throw new \RuntimeException( 'Choose between one and twenty post/page IDs.' );
		}
		foreach ( $ids as $id ) {
			if ( ! is_int( $id ) || $id <= 0 ) {
				throw new \RuntimeException( 'Post IDs must be positive integers.' );
			}
		}
		$ids = array_values( array_unique( $ids ) );
		sort( $ids );
		return $ids;
	}

	/** Verify a signed preview proof and return its validated claims.
	 *
	 * @param string $token Signed preview token.
	 * @return array<string,mixed> Validated preview claims.
	 * @throws \RuntimeException When the proof is invalid, expired, or out of scope.
	 */
	private function verify( string $token ): array {
		$parts = explode( '.', $token );
		if ( 8192 < strlen( $token ) || 2 !== count( $parts ) || ! hash_equals( hash_hmac( 'sha256', $parts[0], wp_salt( 'auth' ) ), $parts[1] ) ) {
			throw new \RuntimeException( 'Invalid preview proof.' );
		}
		$raw  = base64_decode( $parts[0], true );
		$data = false !== $raw ? json_decode( $raw, true ) : null;
		if ( ! is_array( $data ) || ! isset( $data['provider'], $data['ids'], $data['digest'], $data['expires'], $data['user'], $data['blog'] ) || ! is_string( $data['provider'] ) || ! is_array( $data['ids'] ) || ! is_string( $data['digest'] ) || ! is_int( $data['expires'] ) || time() > $data['expires'] || time() + 900 < $data['expires'] || get_current_user_id() !== $data['user'] || get_current_blog_id() !== $data['blog'] ) {
			throw new \RuntimeException( 'Preview expired or belongs to another user/site.' );
		}
		$data['ids'] = $this->ids( $data['ids'] );
		self::prefix( $data['provider'] );
		return $data;
	}

	/** Start a serialized transaction after checking table engines and wpdb.
	 *
	 * @return string Acquired database lock name.
	 * @throws \RuntimeException When transactional prerequisites are unavailable.
	 */
	private function begin(): string {
		global $wpdb;
		if ( 'wpdb' !== get_class( $wpdb ) || null !== $this->retry_limit ) {
			throw new \RuntimeException( 'Migration requires native wpdb and a non-nested operation; custom database drop-ins are not supported.' );
		}
		$engines = $wpdb->get_col( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (%s,%s,%s)', $wpdb->posts, $wpdb->postmeta, $wpdb->options ) );
		if ( ! is_array( $engines ) || count( $engines ) !== 3 || array_filter(
			$engines,
			static function ( string $engine ): bool {
				return 'innodb' !== strtolower( $engine ); }
		) ) {
			throw new \RuntimeException( 'Migration requires InnoDB posts, postmeta and options tables.' );
		}
		$lock = 'rankavia_migration_' . substr( hash( 'sha256', $wpdb->prefix ), 0, 32 );
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s,2)', $lock ) ) ) {
			throw new \RuntimeException( 'Another migration is running or the database lock is unavailable.' );
		}
		// Native wpdb otherwise reconnects and retries a failed statement in
		// autocommit after losing the original transaction. Its compatibility
		// accessors expose this policy; zero forbids every reconnect attempt.
		$this->retry_limit = (int) $wpdb->__get( 'reconnect_retries' );
		$wpdb->__set( 'reconnect_retries', 0 );
		if ( 0 !== $wpdb->__get( 'reconnect_retries' ) ) {
			$this->unlock( $lock );
			throw new \RuntimeException( 'Could not disable database query replay.' );
		}
		if ( false === $wpdb->query( 'SET TRANSACTION ISOLATION LEVEL SERIALIZABLE' ) || false === $wpdb->query( 'START TRANSACTION' ) ) {
			$this->unlock( $lock );
			throw new \RuntimeException( 'Transaction unavailable.' );
		}
		return $lock;
	}

	/** Commit a complete content-and-receipt transaction.
	 *
	 * @return void
	 * @throws \RuntimeException When commit fails.
	 */
	private function commit(): void {
		global $wpdb;
		if ( false === $wpdb->query( 'COMMIT' ) ) {
			throw new \RuntimeException( 'Commit failed; inspect the database before retrying.' );
		}
	}

	/** Release the named lock and restore wpdb retry policy.
	 *
	 * @param string $lock Acquired database lock name.
	 * @return void
	 */
	private function unlock( string $lock ): void {
		global $wpdb;
		try {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		} finally {
			if ( null !== $this->retry_limit ) {
				$wpdb->__set( 'reconnect_retries', $this->retry_limit );
				$this->retry_limit = null;
			}
		}
	}

	/** Clear affected post caches after a successful write.
	 *
	 * @param array<int,array<string,mixed>> $changes Applied changes.
	 * @return void
	 * @throws \RuntimeException When cache invalidation fails.
	 */
	private function clear( array $changes ): void {
		foreach ( array_unique( array_column( $changes, 'post_id' ) ) as $id ) {
			clean_post_cache( (int) $id );
		}
	}

	/** Require the native site-administrator capability.
	 *
	 * @return void
	 * @throws \RuntimeException When the current user is not an administrator.
	 */
	private function authorize(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \RuntimeException( 'Administrator permission required.' );
		}
	}
}
