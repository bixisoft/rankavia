<?php
/**
 * Plugin kernel: owns the container and boots modules.
 *
 * @package Bixisoft\Rankavia\Vendor\Core
 */

declare( strict_types=1 );

namespace Bixisoft\Rankavia\Vendor\Core;

use Bixisoft\Rankavia\Vendor\Core\Contracts\Module;

/**
 * Registers every module's services first, then boots the enabled ones.
 */
final class Kernel {

	/**
	 * Shared container.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Modules keyed by id, in registration order.
	 *
	 * @var array<string, Module>
	 */
	private array $modules = array();

	/**
	 * Whether boot() already ran.
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * Constructor.
	 *
	 * @param Container|null $container Optional pre-built container.
	 */
	public function __construct( ?Container $container = null ) {
		$this->container = $container ?? new Container();
	}

	/**
	 * The shared container.
	 *
	 * @return Container
	 */
	public function container(): Container {
		return $this->container;
	}

	/**
	 * Add a module. Adding a module with an existing id replaces it.
	 *
	 * @param Module $module Module instance.
	 * @return self
	 */
	public function add( Module $module ): self {
		$this->modules[ $module->id() ] = $module;
		return $this;
	}

	/**
	 * All modules keyed by id.
	 *
	 * @return array<string, Module>
	 */
	public function modules(): array {
		return $this->modules;
	}

	/**
	 * Get one module by id.
	 *
	 * @param string $id Module id.
	 * @return Module|null
	 */
	public function module( string $id ): ?Module {
		return $this->modules[ $id ] ?? null;
	}

	/**
	 * Whether boot() already ran.
	 *
	 * @return bool
	 */
	public function is_booted(): bool {
		return $this->booted;
	}

	/**
	 * Register all modules, then boot the enabled ones. Safe to call more than once.
	 *
	 * @return void
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		foreach ( $this->modules as $module ) {
			$module->register( $this->container );
		}

		foreach ( $this->modules as $id => $module ) {
			/**
			 * Filters whether a module is enabled.
			 *
			 * @param bool   $enabled Whether the module boots. Default true.
			 * @param string $id      Module id.
			 * @param Module $module  Module instance.
			 */
			$enabled = (bool) apply_filters( 'bixisoft_core_module_enabled', true, $id, $module );

			if ( $enabled ) {
				$module->boot( $this->container );
			}
		}
	}
}
