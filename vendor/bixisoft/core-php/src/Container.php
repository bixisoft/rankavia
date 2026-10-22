<?php
/**
 * Minimal service container.
 *
 * @package Bixisoft\Rankavia\Vendor\Core
 */

declare( strict_types=1 );

namespace Bixisoft\Rankavia\Vendor\Core;

use Closure;
use InvalidArgumentException;

/**
 * A tiny dependency container: factories, shared singletons and raw instances.
 */
final class Container {

	/**
	 * Registered factories keyed by service id.
	 *
	 * @var array<string, Closure>
	 */
	private array $factories = array();

	/**
	 * Ids of services that are resolved once and then shared.
	 *
	 * @var array<string, bool>
	 */
	private array $shared = array();

	/**
	 * Resolved shared values and raw instances keyed by service id.
	 *
	 * @var array<string, mixed>
	 */
	private array $instances = array();

	/**
	 * Register a factory that produces a new value on every get().
	 *
	 * @param string  $id      Service id.
	 * @param Closure $factory Factory receiving the container.
	 * @return void
	 */
	public function bind( string $id, Closure $factory ): void {
		$this->factories[ $id ] = $factory;
		unset( $this->shared[ $id ], $this->instances[ $id ] );
	}

	/**
	 * Register a factory whose result is cached after the first get().
	 *
	 * @param string  $id      Service id.
	 * @param Closure $factory Factory receiving the container.
	 * @return void
	 */
	public function singleton( string $id, Closure $factory ): void {
		$this->factories[ $id ] = $factory;
		$this->shared[ $id ]    = true;
		unset( $this->instances[ $id ] );
	}

	/**
	 * Store a ready-made value.
	 *
	 * @param string $id    Service id.
	 * @param mixed  $value Any value.
	 * @return void
	 */
	public function instance( string $id, $value ): void {
		$this->instances[ $id ] = $value;
		unset( $this->factories[ $id ], $this->shared[ $id ] );
	}

	/**
	 * Whether a service id is known.
	 *
	 * @param string $id Service id.
	 * @return bool
	 */
	public function has( string $id ): bool {
		return isset( $this->factories[ $id ] ) || array_key_exists( $id, $this->instances );
	}

	/**
	 * Resolve a service.
	 *
	 * @param string $id Service id.
	 * @return mixed
	 * @throws InvalidArgumentException When the id is unknown.
	 */
	public function get( string $id ) {
		if ( array_key_exists( $id, $this->instances ) ) {
			return $this->instances[ $id ];
		}

		if ( ! isset( $this->factories[ $id ] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing exception, never rendered to site visitors.
			throw new InvalidArgumentException( sprintf( 'Service "%s" is not registered.', $id ) );
		}

		$value = ( $this->factories[ $id ] )( $this );

		if ( isset( $this->shared[ $id ] ) ) {
			$this->instances[ $id ] = $value;
		}

		return $value;
	}
}
