<?php
/**
 * Namespaced options wrapper.
 *
 * @package Bixisoft\Rankavia\Vendor\Core
 */

declare( strict_types=1 );

namespace Bixisoft\Rankavia\Vendor\Core;

/**
 * Stores all of a plugin's settings in one wp_options row, merged over defaults.
 */
final class Options {

	/**
	 * The wp_options key.
	 *
	 * @var string
	 */
	private string $option_name;

	/**
	 * Default values.
	 *
	 * @var array<string, mixed>
	 */
	private array $defaults;

	/**
	 * Per-request cache of merged values, or null when not loaded yet.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $cache = null;

	/**
	 * Constructor.
	 *
	 * @param string               $option_name The wp_options key.
	 * @param array<string, mixed> $defaults    Default values.
	 */
	public function __construct( string $option_name, array $defaults = array() ) {
		$this->option_name = $option_name;
		$this->defaults    = $defaults;
	}

	/**
	 * All values, stored merged over defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		if ( null === $this->cache ) {
			$stored      = get_option( $this->option_name, array() );
			$this->cache = array_merge( $this->defaults, is_array( $stored ) ? $stored : array() );
		}
		return $this->cache;
	}

	/**
	 * Get one value.
	 *
	 * @param string $key      Setting key.
	 * @param mixed  $fallback Returned when the key is neither stored nor defaulted.
	 * @return mixed
	 */
	public function get( string $key, $fallback = null ) {
		$all = $this->all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $fallback;
	}

	/**
	 * Set one value and persist.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value New value.
	 * @return void
	 */
	public function set( string $key, $value ): void {
		$all         = $this->all();
		$all[ $key ] = $value;
		$this->save( $all );
	}

	/**
	 * Merge several values and persist.
	 *
	 * @param array<string, mixed> $values Values to merge over the current ones.
	 * @return void
	 */
	public function update( array $values ): void {
		$this->save( array_merge( $this->all(), $values ) );
	}

	/**
	 * Replace all values and persist.
	 *
	 * @param array<string, mixed> $values Complete value set (defaults are merged underneath).
	 * @return void
	 */
	public function save( array $values ): void {
		$this->cache = array_merge( $this->defaults, $values );
		update_option( $this->option_name, $this->cache );
	}

	/**
	 * The defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return $this->defaults;
	}

	/**
	 * The wp_options key.
	 *
	 * @return string
	 */
	public function option_name(): string {
		return $this->option_name;
	}

	/**
	 * Drop the per-request cache so the next read hits the database.
	 *
	 * @return void
	 */
	public function flush(): void {
		$this->cache = null;
	}
}
