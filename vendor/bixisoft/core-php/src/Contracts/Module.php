<?php
/**
 * Module contract.
 *
 * @package Bixisoft\Rankavia\Vendor\Core
 */

declare( strict_types=1 );

namespace Bixisoft\Rankavia\Vendor\Core\Contracts;

use Bixisoft\Rankavia\Vendor\Core\Container;

/**
 * A feature module: registers services, then hooks into WordPress when booted.
 */
interface Module {

	/**
	 * Unique, stable module id (used for enable/disable filters and settings keys).
	 *
	 * @return string
	 */
	public function id(): string;

	/**
	 * Bind services into the container. Must not add hooks.
	 *
	 * @param Container $container Shared container.
	 * @return void
	 */
	public function register( Container $container ): void;

	/**
	 * Add WordPress hooks. Called only when the module is enabled.
	 *
	 * @param Container $container Shared container.
	 * @return void
	 */
	public function boot( Container $container ): void;
}
